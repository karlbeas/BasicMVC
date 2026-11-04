<?php

declare(strict_types=1);

namespace App\Services;

final class FormValidationResult
{
    /** @var list<array{path: string, code: string, message: string}> */
    private array $errors = [];
    /** @var list<array{path: string, code: string, message: string}> */
    private array $warnings = [];

    /** @param array<string, mixed>|null $form decoded form (associative), null if JSON was unreadable */
    public function __construct(public ?array $form = null) {}

    public function addError(string $path, string $code, string $message): void
    {
        $this->errors[] = ['path' => $path, 'code' => $code, 'message' => $message];
    }

    public function addWarning(string $path, string $code, string $message): void
    {
        $this->warnings[] = ['path' => $path, 'code' => $code, 'message' => $message];
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /** @return list<array{path: string, code: string, message: string}> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return list<array{path: string, code: string, message: string}> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /** @return list<string> */
    public function errorCodes(): array
    {
        return array_values(array_unique(array_column($this->errors, 'code')));
    }

    /** @return array{valid: bool, errors: list<array{path: string, code: string, message: string}>, warnings: list<array{path: string, code: string, message: string}>} */
    public function toArray(): array
    {
        return ['valid' => $this->isValid(), 'errors' => $this->errors, 'warnings' => $this->warnings];
    }
}
