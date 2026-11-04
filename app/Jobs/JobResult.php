<?php

declare(strict_types=1);

namespace App\Jobs;

final class JobResult
{
    /**
     * @param 'done'|'yield' $status
     * @param array<string, mixed> $data result (done) or progress (yield)
     */
    private function __construct(public readonly string $status, public readonly array $data) {}

    /** @param array<string, mixed> $result */
    public static function done(array $result = []): self
    {
        return new self('done', $result);
    }

    /** @param array<string, mixed> $progress */
    public static function yield(array $progress): self
    {
        return new self('yield', $progress);
    }
}
