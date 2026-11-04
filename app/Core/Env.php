<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal .env loader. putenv() is often disabled on shared hosting, so values
 * are only stored in $_ENV. Real environment variables always win.
 */
final class Env
{
    public static function load(string $file): void
    {
        if (!is_file($file) || !is_readable($file)) {
            return;
        }
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_starts_with($line, 'export ')) {
                $line = substr($line, 7);
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            if (!preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key)) {
                continue;
            }
            if (array_key_exists($key, $_ENV) || getenv($key) !== false) {
                continue;
            }
            $_ENV[$key] = self::parseValue(trim(substr($line, $pos + 1)));
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, $_ENV) && is_scalar($_ENV[$key])) {
            return (string) $_ENV[$key];
        }
        $value = getenv($key);

        return $value === false ? $default : $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);

        return ($value === null || !is_numeric($value)) ? $default : (int) $value;
    }

    private static function parseValue(string $value): string
    {
        if ($value === '') {
            return '';
        }
        $quote = $value[0];
        if (($quote === '"' || $quote === "'") && strlen($value) >= 2 && str_ends_with($value, $quote)) {
            $inner = substr($value, 1, -1);

            return $quote === '"' ? str_replace(['\\n', '\\"', '\\\\'], ["\n", '"', '\\'], $inner) : $inner;
        }
        $hash = strpos($value, ' #');
        if ($hash !== false) {
            $value = rtrim(substr($value, 0, $hash));
        }

        return $value;
    }
}
