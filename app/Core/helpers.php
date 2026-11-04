<?php

declare(strict_types=1);

use App\Core\App;

/**
 * Global view helpers. Kept deliberately small: escaping, translation, URLs, CSRF.
 */

if (!function_exists('e')) {
    /** Escape a value for HTML output (text and attribute contexts). */
    function e(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        return htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('t')) {
    /** @param array<string, string|int|float> $params */
    function t(string $key, array $params = []): string
    {
        return App::instance()->lang()->get($key, $params);
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        return App::instance()->url($path);
    }
}

if (!function_exists('asset')) {
    /** Asset URL with a version stamp, so browsers reload CSS/JS after every update (they are cached 7 days). */
    function asset(string $path): string
    {
        $path = ltrim($path, '/');
        $file = App::instance()->root . '/public/assets/' . $path;
        $version = is_file($file) ? (string) filemtime($file) : '';

        return App::instance()->url('assets/' . $path) . ($version !== '' ? '?v=' . $version : '');
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return App::instance()->csrf()->token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
    }
}
