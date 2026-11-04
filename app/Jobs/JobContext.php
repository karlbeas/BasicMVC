<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Core\App;

final class JobContext
{
    /**
     * @param array<string, mixed> $job
     * @param array<string, mixed> $payload
     * @param array<string, mixed>|null $progress state saved by a previous yield
     */
    public function __construct(
        public readonly App $app,
        public readonly array $job,
        public readonly array $payload,
        public readonly ?array $progress,
        private readonly float $deadline,
        private readonly ?bool $longRun = null, // tests: force web (false) or CRON (true) behaviour
    ) {}

    public function organizationId(): ?int
    {
        return $this->job['organization_id'] === null ? null : (int) $this->job['organization_id'];
    }

    public function secondsLeft(): float
    {
        return $this->deadline - microtime(true);
    }

    public function deadline(): float
    {
        return $this->deadline;
    }

    /**
     * True when PHP imposes no time limit (CLI: CRON dispatcher). A step may then
     * outlast the dispatcher budget, which only stops it from starting new jobs.
     * Web requests (max_execution_time ~30 s on shared hosting) return false.
     */
    public function canRunLong(): bool
    {
        // CLI = CRON dispatcher (no time limit by default; never rely on max_execution_time
        // here, the script itself may have called set_time_limit()).
        return $this->longRun ?? (PHP_SAPI === 'cli');
    }

    /**
     * Persists progress now, so a retry after a failure resumes from here.
     *
     * @param array<string, mixed> $progress
     */
    public function checkpoint(array $progress): void
    {
        (new JobQueue($this->app->db()))->saveProgress((int) $this->job['id'], $progress);
    }

    public function shouldYield(float $marginSeconds = 5.0): bool
    {
        return $this->secondsLeft() <= $marginSeconds;
    }
}
