<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Core\App;

/**
 * Runs queued jobs within a time budget. Called by CRON (bin/worker-dispatch.php),
 * by the protected HTTP endpoint (hosts without CLI cron), or for a single job
 * from a web request (runOne) so the user does not wait for the next CRON tick.
 */
final class Dispatcher
{
    private const LOCK = 'f2s_job_dispatcher';

    private JobQueue $queue;

    public function __construct(private App $app)
    {
        $this->queue = new JobQueue($app->db());
    }

    /** @return array{processed: int, done: int, yielded: int, failed: int, skipped: bool} */
    public function run(int $budgetSeconds): array
    {
        $stats = ['processed' => 0, 'done' => 0, 'yielded' => 0, 'failed' => 0, 'skipped' => false];
        $db = $this->app->db();
        // One dispatcher at a time: overlapping CRON runs exit immediately.
        if ((int) $db->fetchValue('SELECT GET_LOCK(?, 0)', [self::LOCK]) !== 1) {
            $stats['skipped'] = true;

            return $stats;
        }
        try {
            self::touchHeartbeat($this->app);
            $this->queue->releaseStale($this->staleAfter());
            $deadline = microtime(true) + $budgetSeconds;
            $workerId = gethostname() . ':' . getmypid();
            $yielded = [];
            while (microtime(true) < $deadline - 2) {
                // A job that yielded is not taken again in the same run (no busy loop).
                $job = $this->queue->reserve($workerId, 'php', null, $yielded);
                if ($job === null) {
                    break;
                }
                $outcome = $this->execute($job, $deadline);
                if ($outcome === 'yielded') {
                    $yielded[] = (int) $job['id'];
                }
                ++$stats['processed'];
                ++$stats[$outcome];
            }
        } finally {
            $db->fetchValue('SELECT RELEASE_LOCK(?)', [self::LOCK]);
        }

        return $stats;
    }

    /**
     * A step never legitimately lasts longer than one AI call plus a margin, so a job still
     * "running" after that was killed by the host. Capped even if JOBS_STALE_AFTER is higher
     * (installs before v0.4.2 wrote 600 s in .env).
     */
    private function staleAfter(): int
    {
        $configured = $this->app->config()->int('jobs.stale_after_seconds', 300);
        $longestStep = $this->app->config()->int('ai.timeout_seconds', 120) + 60;

        return max(60, min($configured, $longestStep));
    }

    /** Records that a dispatcher (CRON or HTTP cron) ran: lets the UI tell whether the CRON works. */
    public static function touchHeartbeat(\App\Core\App $app): void
    {
        $dir = $app->storagePath('cache');
        if (!is_dir($dir)) {
            @mkdir($dir, 0o750, true);
        }
        @file_put_contents($dir . '/cron.last', (string) time(), LOCK_EX);
    }

    /** Seconds since the last dispatcher run, or null if it never ran. */
    public static function heartbeatAge(\App\Core\App $app): ?int
    {
        $file = $app->storagePath('cache') . '/cron.last';
        if (!is_file($file)) {
            return null;
        }

        return max(0, time() - (int) file_get_contents($file));
    }

    /** Runs one specific pending job now (bounded by the request time limit). */
    public function runOne(int $jobId, int $budgetSeconds): ?string
    {
        // Jobs killed by the host (time limit) must not wait for the CRON to be released.
        $this->queue->releaseStale($this->staleAfter());
        $job = $this->queue->reserve('web:' . getmypid(), 'php', $jobId);

        return $job === null ? null : $this->execute($job, microtime(true) + $budgetSeconds);
    }

    /**
     * @param array<string, mixed> $job
     *
     * @return 'done'|'yielded'|'failed'
     */
    private function execute(array $job, float $deadline): string
    {
        $id = (int) $job['id'];
        try {
            $handler = $this->handlerFor((string) $job['type']);
            $payload = json_decode((string) $job['payload'], true, 64, JSON_THROW_ON_ERROR);
            $progress = $job['progress'] !== null ? json_decode((string) $job['progress'], true, 64, JSON_THROW_ON_ERROR) : null;
            $result = $handler->handle(new JobContext(
                $this->app,
                $job,
                is_array($payload) ? $payload : [],
                is_array($progress) ? $progress : null,
                $deadline,
            ));
            if ($result->status === 'yield') {
                $this->queue->yield($id, $result->data);

                return 'yielded';
            }
            $this->queue->complete($id, $result->data);

            return 'done';
        } catch (\Throwable $e) {
            $this->app->logger()->error('Job failed', ['job_id' => $id, 'type' => $job['type'], 'error' => $e->getMessage()]);
            $this->queue->fail($id, $e::class . ': ' . $e->getMessage());

            return 'failed';
        }
    }

    private function handlerFor(string $type): JobHandler
    {
        $handlers = $this->app->config()->get('jobs.handlers', []);
        $class = is_array($handlers) ? ($handlers[$type] ?? null) : null;
        if (!is_string($class) || !class_exists($class)) {
            throw new \RuntimeException('No handler registered for job type ' . $type);
        }
        $handler = new $class();
        if (!$handler instanceof JobHandler) {
            throw new \RuntimeException($class . ' must implement JobHandler');
        }

        return $handler;
    }
}
