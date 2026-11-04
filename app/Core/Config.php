<?php

declare(strict_types=1);

namespace App\Core;

/** Read-only configuration with dot-notation access ("app.name"). */
final class Config
{
    /** @param array<string, mixed> $items */
    public function __construct(private array $items = []) {}

    public static function fromDirectory(string $dir): self
    {
        $items = [];
        foreach (glob(rtrim($dir, '/') . '/*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            if ($name === 'routes') {
                continue;
            }
            $value = require $file;
            if (is_array($value)) {
                $items[$name] = $value;
            }
        }

        return new self($items);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);

        return is_bool($value) ? $value : (bool) $value;
    }
}
