<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Migrator;
use PHPUnit\Framework\TestCase;

final class MigratorTest extends TestCase
{
    public function testSplitsStatementsAndIgnoresComments(): void
    {
        $sql = "-- header\nCREATE TABLE a (id INT);\n\n-- note; with semicolon\nINSERT INTO a VALUES (1);\nINSERT INTO a VALUES (2)";

        self::assertSame(['CREATE TABLE a (id INT)', 'INSERT INTO a VALUES (1)', 'INSERT INTO a VALUES (2)'], Migrator::splitStatements($sql));
    }

    public function testMigrationFilesAreOrderedAndNamedConsistently(): void
    {
        $files = glob(__DIR__ . '/../../database/migrations/*.sql') ?: [];
        self::assertNotEmpty($files);
        foreach ($files as $file) {
            self::assertMatchesRegularExpression('/^\d{8}_\d{4}_[a-z0-9_]+\.sql$/', basename($file));
            self::assertStringNotContainsString('utf8mb4_0900', (string) file_get_contents($file), 'Use utf8mb4_unicode_ci: MariaDB (shared hosting) lacks the MySQL 8 collations');
        }
    }
}
