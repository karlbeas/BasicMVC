<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Core\Database;

/**
 * SQL job queue (table `jobs`). The `runner` column keeps the door open for a
 * Python worker on a VPS later (ADR 0002): PHP only reserves runner = 'php'.
 */
final class JobQueue
{
    public function __construct(private Database $db) {}

    /** @param array<string, mixed> $payload must not contain respondent personal data */
    public function push(string $type, array $payload = [], ?int $organizationId = null, int $delaySeconds = 0, int $maxAttempts = 3, string $runner = 'php'): int
    {
        return $this->db->insert('jobs', [
            'organization_id' => $organizationId,
            'type' => $type,
            'runner' => $runner,
            'status' => 'pending',
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'max_attempts' => $maxAttempts,
            'available_at' => gmdate('Y-m-d H:i:s', time() + $delaySeconds),
        ]);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM jobs WHERE id = ?', [$id]);
    }

    /**
     * Atomically takes the next available job (SKIP LOCKED: MySQL 8+, MariaDB 10.6+).
     *
     * @return array<string, mixed>|null
     */
    public function reserve(string $workerId, string $runner = 'php', ?int $jobId = null): ?array
    {
        return $this->db->transaction(function (Database $db) use ($workerId, $runner, $jobId): ?array {
            $sql = "SELECT * FROM jobs WHERE status = 'pending' AND runner = ? AND available_at <= ?"
                . ($jobId !== null ? ' AND id = ?' : '')
                . ' ORDER BY priority DESC, id ASC LIMIT 1 FOR UPDATE SKIP LOCKED';
            $params = [$runner, gmdate('Y-m-d H:i:s')];
            if ($jobId !== null) {
                $params[] = $jobId;
            }
            $job = $db->fetchOne($sql, $params);
            if ($job === null) {
                return null;
            }
            $db->execute(
                "UPDATE jobs SET status = 'running', attempts = attempts + 1, reserved_at = ?, reserved_by = ?, updated_at = ? WHERE id = ?",
                [gmdate('Y-m-d H:i:s'), mb_substr($workerId, 0, 64), gmdate('Y-m-d H:i:s'), $job['id']],
            );
            $job['attempts'] = (int) $job['attempts'] + 1;
            $job['status'] = 'running';

            return $job;
        });
    }

    /** @param array<string, mixed> $result */
    public function complete(int $id, array $result): void
    {
        $this->db->execute(
            "UPDATE jobs SET status = 'done', result = ?, error = NULL, finished_at = ?, updated_at = ? WHERE id = ?",
            [json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s'), $id],
        );
    }

    /**
     * Saves progress and puts the job back in the queue for the next run.
     * A yield does not consume an attempt.
     *
     * @param array<string, mixed> $progress
     */
    public function yield(int $id, array $progress): void
    {
        $this->db->execute(
            "UPDATE jobs SET status = 'pending', progress = ?, attempts = GREATEST(attempts - 1, 0), reserved_at = NULL, reserved_by = NULL, updated_at = ? WHERE id = ?",
            [json_encode($progress, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), gmdate('Y-m-d H:i:s'), $id],
        );
    }

    /** Retries with exponential backoff until max_attempts, then marks the job failed. */
    public function fail(int $id, string $error): void
    {
        $job = $this->find($id);
        if ($job === null) {
            return;
        }
        $attempts = (int) $job['attempts'];
        $final = $attempts >= (int) $job['max_attempts'];
        $this->db->execute(
            'UPDATE jobs SET status = ?, error = ?, available_at = ?, reserved_at = NULL, reserved_by = NULL, finished_at = ?, updated_at = ? WHERE id = ?',
            [
                $final ? 'failed' : 'pending',
                mb_substr($error, 0, 2000),
                gmdate('Y-m-d H:i:s', time() + (int) (30 * 2 ** max(0, $attempts - 1))),
                $final ? gmdate('Y-m-d H:i:s') : null,
                gmdate('Y-m-d H:i:s'),
                $id,
            ],
        );
    }

    /** Jobs left "running" by a process the host killed (max_execution_time) go back to the queue. */
    public function releaseStale(int $olderThanSeconds): int
    {
        return $this->db->execute(
            "UPDATE jobs SET status = 'pending', reserved_at = NULL, reserved_by = NULL, updated_at = ? WHERE status = 'running' AND reserved_at < ?",
            [gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s', time() - $olderThanSeconds)],
        );
    }
}
