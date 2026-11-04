<?php

declare(strict_types=1);

namespace App\Services\Bridge;

/**
 * Honest import report (MANIFEST rule 5): what was kept as is, transformed,
 * lost, or is not supported yet. Shown to the user before any confirmation.
 */
final class ConversionReport
{
    public const KEPT = 'kept';
    public const TRANSFORMED = 'transformed';
    public const LOST = 'lost';
    public const UNSUPPORTED = 'unsupported';

    /** @var array<string, list<array{item: string, detail: string}>> */
    private array $entries = [self::KEPT => [], self::TRANSFORMED => [], self::LOST => [], self::UNSUPPORTED => []];
    private int $keptCount = 0;
    /** @var list<array{path: string, message: string}> */
    private array $validationErrors = [];

    public function __construct(public readonly string $format, public readonly string $filename) {}

    /** Counts a cleanly converted element (listed only up to 200 entries). */
    public function kept(string $item, string $detail = ''): void
    {
        ++$this->keptCount;
        if (count($this->entries[self::KEPT]) < 200) {
            $this->entries[self::KEPT][] = ['item' => $item, 'detail' => $detail];
        }
    }

    public function transformed(string $item, string $detail): void
    {
        $this->entries[self::TRANSFORMED][] = ['item' => $item, 'detail' => $detail];
    }

    public function lost(string $item, string $detail): void
    {
        $this->entries[self::LOST][] = ['item' => $item, 'detail' => $detail];
    }

    public function unsupported(string $item, string $detail): void
    {
        $this->entries[self::UNSUPPORTED][] = ['item' => $item, 'detail' => $detail];
    }

    /** @param list<array{path: string, code: string, message: string}> $errors */
    public function setValidationErrors(array $errors): void
    {
        $this->validationErrors = array_map(static fn(array $e): array => ['path' => $e['path'], 'message' => $e['message']], $errors);
    }

    public function hasValidationErrors(): bool
    {
        return $this->validationErrors !== [];
    }

    /** @return list<array{item: string, detail: string}> */
    public function entries(string $category): array
    {
        return $this->entries[$category] ?? [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'format' => $this->format,
            'filename' => $this->filename,
            'counts' => [
                self::KEPT => $this->keptCount,
                self::TRANSFORMED => count($this->entries[self::TRANSFORMED]),
                self::LOST => count($this->entries[self::LOST]),
                self::UNSUPPORTED => count($this->entries[self::UNSUPPORTED]),
            ],
            'entries' => $this->entries,
            'validation_errors' => $this->validationErrors,
        ];
    }
}
