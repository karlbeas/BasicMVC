<?php

declare(strict_types=1);

namespace App\Services;

final class QuotaExceededException extends \RuntimeException
{
    public function __construct(public readonly string $limitKey, public readonly int $limit, public readonly int $actual)
    {
        parent::__construct(sprintf('Quota "%s" exceeded: %d > %d', $limitKey, $actual, $limit));
    }
}
