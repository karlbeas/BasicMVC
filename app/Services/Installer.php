<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Migrator;

/**
 * Web installer for shared hosting without SSH: checks requirements, tests the
 * database, writes .env and runs the migrations. Protected by the one-time code
 * stored in storage/install.key (generated when the release zip is built) and
 * disabled for good once .env exists.
 */
final class Installer
{
    public const MIN_PHP = '8.3.0';
    public const EXTENSIONS = ['pdo_mysql', 'mbstring', 'json', 'fileinfo', 'openssl', 'curl', 'zip', 'xmlreader'];

    public function __construct(private string $root, private string $storagePath) {}

    public function isInstalled(): bool
    {
        return is_file($this->root . '/.env');
    }

    public function keyFile(): string
    {
        return $this->storagePath . '/install.key';
    }

    public function checkKey(string $given): bool
    {
        $file = $this->keyFile();
        if (!is_file($file)) {
            return false;
        }
        $expected = trim((string) file_get_contents($file));

        return strlen($expected) >= 16 && hash_equals($expected, trim($given));
    }

    /** @return list<array{key: string, ok: bool, detail: string}> */
    public function requirements(): array
    {
        $checks = [[
            'key' => 'php',
            'ok' => version_compare(PHP_VERSION, self::MIN_PHP, '>='),
            'detail' => PHP_VERSION,
        ]];
        foreach (self::EXTENSIONS as $ext) {
            $checks[] = ['key' => 'ext', 'ok' => extension_loaded($ext), 'detail' => $ext];
        }
        foreach (['', '/sessions', '/logs', '/cache', '/uploads'] as $sub) {
            $dir = $this->storagePath . $sub;
            $checks[] = ['key' => 'writable', 'ok' => is_dir($dir) && is_writable($dir), 'detail' => 'storage' . $sub];
        }
        $checks[] = ['key' => 'writable', 'ok' => is_writable($this->root), 'detail' => '.env (' . basename($this->root) . '/)'];
        $checks[] = ['key' => 'key', 'ok' => is_file($this->keyFile()), 'detail' => 'storage/install.key'];

        return $checks;
    }

    public function requirementsMet(): bool
    {
        foreach ($this->requirements() as $check) {
            if (!$check['ok']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{db_host: string, db_port: int, db_name: string, db_user: string, db_password: string, app_url: string, https: bool, locale: string} $input
     *
     * @return array{migrations: int, cron_token: string}
     *
     * @throws ValidationException|\PDOException
     */
    public function install(array $input): array
    {
        $errors = [];
        if ($input['db_host'] === '' || preg_match('/[\s"\'\\\\]/', $input['db_host'])) {
            $errors['db_host'] = 'install.error.db_host';
        }
        if (!preg_match('/^[A-Za-z0-9_$-]{1,64}$/', $input['db_name'])) {
            $errors['db_name'] = 'install.error.db_name';
        }
        if ($input['db_user'] === '' || strlen($input['db_user']) > 80) {
            $errors['db_user'] = 'install.error.db_user';
        }
        if ($input['db_port'] < 1 || $input['db_port'] > 65535) {
            $errors['db_port'] = 'install.error.db_port';
        }
        if (filter_var($input['app_url'], FILTER_VALIDATE_URL) === false || !preg_match('#^https?://#', $input['app_url'])) {
            $errors['app_url'] = 'install.error.app_url';
        }
        foreach (['db_host', 'db_name', 'db_user', 'db_password', 'app_url'] as $field) {
            if (preg_match('/[\r\n\0]/', (string) $input[$field])) {
                $errors[$field] = 'install.error.line_break';
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $db = new Database([
            'host' => $input['db_host'],
            'port' => $input['db_port'],
            'database' => $input['db_name'],
            'username' => $input['db_user'],
            'password' => $input['db_password'],
        ]);
        $db->pdo(); // throws PDOException when credentials are wrong

        $migrations = (new Migrator($db, $this->root . '/database/migrations'))->migrate(static function (): void {});

        $cronToken = bin2hex(random_bytes(24));
        EnvWriter::write($this->root . '/.env', [
            'APP_ENV' => 'production',
            'APP_DEBUG' => false,
            'APP_URL' => rtrim($input['app_url'], '/'),
            'APP_LOCALE' => in_array($input['locale'], ['fr', 'en'], true) ? $input['locale'] : 'fr',
            'APP_FALLBACK_LOCALE' => 'en',
            'SESSION_SECURE_COOKIE' => $input['https'],
            'DB_HOST' => $input['db_host'],
            'DB_PORT' => $input['db_port'],
            'DB_DATABASE' => $input['db_name'],
            'DB_USERNAME' => $input['db_user'],
            'DB_PASSWORD' => $input['db_password'],
            'MAX_UPLOAD_MB' => 10,
            'JOBS_TIME_BUDGET' => 50,
            'JOBS_STALE_AFTER' => 600,
            'CRON_HTTP_TOKEN' => $cronToken,
            'MAMMOUTH_BASE_URL' => 'https://api.mammouth.ai/v1',
            'MAMMOUTH_API_KEY' => '',
            'MAIL_FROM' => 'no-reply@' . (parse_url($input['app_url'], PHP_URL_HOST) ?: 'example.com'),
        ], 'F2S Pro — generated by the web installer on ' . gmdate('c') . "\nNever share this file. Edit it with the hosting file manager.");

        (new SchemaUpgrader($db, $this->root . '/database/migrations', $this->storagePath . '/cache'))->writeMarker();
        @unlink($this->keyFile());

        return ['migrations' => $migrations, 'cron_token' => $cronToken];
    }
}
