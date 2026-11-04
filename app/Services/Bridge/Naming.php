<?php

declare(strict_types=1);

namespace App\Services\Bridge;

/** Identifier helpers shared by the converters (F2S names: ^[A-Za-z_][A-Za-z0-9_]{0,63}$). */
final class Naming
{
    /** @var array<string, true> lower-cased names already used */
    private array $used = [];

    public static function slug(string $text, int $max = 40): string
    {
        $ascii = (string) (function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) : $text);
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($ascii)), '_');

        return substr($slug, 0, $max);
    }

    public static function isValidName(string $name): bool
    {
        return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $name);
    }

    public static function isValidValue(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_\-.]{1,64}$/', $value);
    }

    /** Turns any text into a valid, unique name (prefix used when it starts with a digit). */
    public function unique(string $wanted, string $prefix = 'q'): string
    {
        $base = self::isValidName($wanted) ? $wanted : self::slug($wanted);
        if ($base === '' || !preg_match('/^[A-Za-z_]/', $base)) {
            $base = $prefix . ($base === '' ? '' : '_' . $base);
        }
        $base = substr($base, 0, 60);
        $name = $base;
        for ($i = 2; isset($this->used[strtolower($name)]); ++$i) {
            $name = $base . '_' . $i;
        }
        $this->used[strtolower($name)] = true;

        return $name;
    }

    public static function normalizeHeader(string $header): string
    {
        return trim((string) preg_replace('/\s+/', ' ', strtolower((string) (function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $header) : $header))));
    }
}
