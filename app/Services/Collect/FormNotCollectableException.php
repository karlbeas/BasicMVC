<?php

declare(strict_types=1);

namespace App\Services\Collect;

final class FormNotCollectableException extends \RuntimeException
{
    /** @param list<array{path: string, problem: string}> $blockers */
    public function __construct(public readonly array $blockers)
    {
        parent::__construct('Form uses features that cannot be collected yet');
    }
}
