<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'name' => 'F2S Pro',
    'tagline' => 'Field, Form & Survey Pro',
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => Env::bool('APP_DEBUG', false),
    // Full public URL, e.g. https://f2s.example.com or https://example.com/f2s (no trailing slash).
    // Leave empty to auto-detect (works for most shared-hosting layouts).
    'url' => rtrim((string) Env::get('APP_URL', ''), '/'),
    'locale' => Env::get('APP_LOCALE', 'fr'),
    'fallback_locale' => Env::get('APP_FALLBACK_LOCALE', 'en'),
    // Must be outside the web root in production (see docs/deployment/shared-hosting.md).
    'storage_path' => Env::get('STORAGE_PATH', dirname(__DIR__) . '/storage'),
    'secure_cookies' => Env::bool('SESSION_SECURE_COOKIE', true),
    'max_upload_mb' => Env::int('MAX_UPLOAD_MB', 10),
];
