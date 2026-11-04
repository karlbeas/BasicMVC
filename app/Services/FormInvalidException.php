<?php

declare(strict_types=1);

namespace App\Services;

final class FormInvalidException extends \RuntimeException
{
    public function __construct(public readonly FormValidationResult $result)
    {
        parent::__construct('Form is invalid');
    }
}
