<?php

declare(strict_types=1);

namespace App\Services\Bridge;

/** Unreadable or unsupported file; $reason is a translation key suffix (bridge.error.*). */
final class ImportException extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message !== '' ? $message : $reason);
    }
}
