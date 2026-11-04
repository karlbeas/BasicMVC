<?php

declare(strict_types=1);

namespace App\Services\Ai;

/** $reason is a stable code used by the UI (not_configured, budget, network, http, invalid_output, …). */
final class AiException extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message !== '' ? $message : $reason);
    }
}
