<?php

declare(strict_types=1);

namespace App\Services;

/** User input errors: field => translation key. */
final class ValidationException extends \RuntimeException
{
    /** @param array<string, string> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Validation failed');
    }
}
