<?php

declare(strict_types=1);

namespace App\Core;

/** Flat-key translations from lang/{locale}.php, with fallback locale. */
final class Lang
{
    public const SUPPORTED = ['fr', 'en'];

    /** @var array<string, string> */
    private array $messages;
    /** @var array<string, string> */
    private array $fallback;

    public function __construct(private string $path, private string $locale, string $fallbackLocale = 'en')
    {
        if (!in_array($locale, self::SUPPORTED, true)) {
            $this->locale = $fallbackLocale;
        }
        $this->messages = $this->load($this->locale);
        $this->fallback = $fallbackLocale === $this->locale ? [] : $this->load($fallbackLocale);
    }

    public function locale(): string
    {
        return $this->locale;
    }

    /** @param array<string, string|int|float> $params */
    public function get(string $key, array $params = []): string
    {
        $message = $this->messages[$key] ?? $this->fallback[$key] ?? $key;
        foreach ($params as $name => $value) {
            $message = str_replace(':' . $name, (string) $value, $message);
        }

        return $message;
    }

    /** @return array<string, string> */
    public function load(string $locale): array
    {
        $file = $this->path . '/' . $locale . '.php';
        if (!is_file($file)) {
            return [];
        }
        $messages = require $file;

        return is_array($messages) ? $messages : [];
    }
}
