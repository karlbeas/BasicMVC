<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\App;
use App\Core\Database;
use App\Core\Env;
use App\Core\Migrator;
use App\Core\Session;
use App\Services\AuditLogger;
use App\Services\AuthContext;
use App\Services\AuthService;
use PHPUnit\Framework\TestCase;

/**
 * Runs against TEST_DB_* (phpunit.xml.dist). The schema is dropped and rebuilt
 * from the migrations once per PHPUnit run. Skipped when no database is reachable.
 */
abstract class DatabaseTestCase extends TestCase
{
    private static ?Database $db = null;
    private static ?string $unavailable = null;
    private static int $sequence = 0;

    protected App $app;

    protected function setUp(): void
    {
        $db = self::database();
        if ($db === null) {
            self::markTestSkipped('Test database unavailable: ' . self::$unavailable);
        }
        $this->app = App::boot(dirname(__DIR__, 2), false);
        $this->app->setDatabase($db);
    }

    protected function db(): Database
    {
        return $this->app->db();
    }

    /** Registers a fresh user + organization (free plan) and returns its context. */
    protected function newAccount(string $prefix = 'user'): AuthContext
    {
        $n = ++self::$sequence . '-' . bin2hex(random_bytes(3));
        $auth = new AuthService($this->db(), new Session('/nonexistent', false, false), new AuditLogger($this->db()));

        return $auth->register('Test ' . $n, "{$prefix}{$n}@example.test", 'correct-horse-battery', 'Org ' . $n, 'fr');
    }

    protected function newAuthService(): AuthService
    {
        return new AuthService($this->db(), new Session('/nonexistent', false, false), new AuditLogger($this->db()));
    }

    private static function database(): ?Database
    {
        if (self::$db !== null || self::$unavailable !== null) {
            return self::$db;
        }
        $name = (string) Env::get('TEST_DB_DATABASE', '');
        if ($name === '' || !preg_match('/test/i', $name)) {
            self::$unavailable = 'TEST_DB_DATABASE must name a dedicated test database (its name must contain "test")';

            return null;
        }
        $db = new Database([
            'host' => (string) Env::get('TEST_DB_HOST', '127.0.0.1'),
            'port' => Env::int('TEST_DB_PORT', 3306),
            'database' => $name,
            'username' => (string) Env::get('TEST_DB_USERNAME', 'root'),
            'password' => (string) Env::get('TEST_DB_PASSWORD', ''),
        ]);
        try {
            $db->pdo();
        } catch (\PDOException $e) {
            self::$unavailable = $e->getMessage();

            return null;
        }
        self::resetSchema($db);

        return self::$db = $db;
    }

    private static function resetSchema(Database $db): void
    {
        $db->execute('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($db->fetchAll('SHOW TABLES') as $row) {
            $table = (string) array_values($row)[0];
            if (preg_match('/^[a-z_]+$/', $table)) {
                $db->execute('DROP TABLE `' . $table . '`');
            }
        }
        $db->execute('SET FOREIGN_KEY_CHECKS = 1');
        (new Migrator($db, dirname(__DIR__, 2) . '/database/migrations'))->migrate(static function (): void {});
    }
}
