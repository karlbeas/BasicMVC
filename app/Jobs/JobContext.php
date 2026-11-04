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
    ) {}

    public function organizationId(): ?int
    {
        return $this->job['organization_id'] === null ? null : (int) $this->job['organization_id'];
    }

    public function secondsLeft(): float
    {
        return $this->deadline - microtime(true);
    }

    public function shouldYield(float $marginSeconds = 5.0): bool
    {
        return $this->secondsLeft() <= $marginSeconds;
    }
}
