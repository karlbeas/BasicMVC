<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Env;
use App\Services\Installer;
use App\Services\ValidationException;

final class InstallerTest extends DatabaseTestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . '/f2s-install-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/storage/sessions', 0o777, true);
        foreach (['logs', 'cache', 'uploads'] as $d) {
            mkdir($this->root . '/storage/' . $d);
        }
        symlink($this->app->root . '/database', $this->root . '/database');
        file_put_contents($this->root . '/storage/install.key', "ABCDEF0123456789AB\n");
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    /** @return array{db_host: string, db_port: int, db_name: string, db_user: string, db_password: string, app_url: string, https: bool, locale: string} */
    private function input(): array
    {
        return [
            'db_host' => (string) Env::get('TEST_DB_HOST', '127.0.0.1'),
            'db_port' => Env::int('TEST_DB_PORT', 3306),
            'db_name' => (string) Env::get('TEST_DB_DATABASE'),
            'db_user' => (string) Env::get('TEST_DB_USERNAME'),
            'db_password' => (string) Env::get('TEST_DB_PASSWORD'),
            'app_url' => 'https://enquetes.example.org/',
            'https' => true,
            'locale' => 'fr',
        ];
    }

    public function testInstallWritesEnvRunsMigrationsAndBurnsTheKey(): void
    {
        $installer = new Installer($this->root, $this->root . '/storage');
        self::assertFalse($installer->isInstalled());
        self::assertTrue($installer->requirementsMet());
        self::assertFalse($installer->checkKey('wrong'));
        self::assertTrue($installer->checkKey(' ABCDEF0123456789AB '));

        $result = $installer->install($this->input());

        self::assertSame(0, $result['migrations'], 'Test DB is already migrated: nothing to apply');
        self::assertSame(48, strlen($result['cron_token']));
        self::assertTrue($installer->isInstalled());
        self::assertFileDoesNotExist($this->root . '/storage/install.key');
        $env = (string) file_get_contents($this->root . '/.env');
        self::assertStringContainsString('APP_URL="https://enquetes.example.org"', $env);
        self::assertStringContainsString('APP_DEBUG="false"', $env);
        self::assertStringContainsString('CRON_HTTP_TOKEN="' . $result['cron_token'] . '"', $env);
        self::assertSame('600', substr(sprintf('%o', fileperms($this->root . '/.env')), -3));
    }

    public function testWrongCredentialsWriteNothing(): void
    {
        $installer = new Installer($this->root, $this->root . '/storage');
        $input = $this->input();
        $input['db_password'] = 'definitely-wrong-password';

        try {
            $installer->install($input);
            self::fail('Expected PDOException');
        } catch (\PDOException) {
        }
        self::assertFalse($installer->isInstalled());
        self::assertFileExists($this->root . '/storage/install.key');
    }

    public function testInputValidation(): void
    {
        $installer = new Installer($this->root, $this->root . '/storage');
        $input = $this->input();
        $input['db_name'] = 'bad name;';
        $input['app_url'] = 'ftp://x';
        $input['db_host'] = "local\nhost";

        try {
            $installer->install($input);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('db_name', $e->errors);
            self::assertArrayHasKey('app_url', $e->errors);
            self::assertArrayHasKey('db_host', $e->errors);
        }
    }
}
