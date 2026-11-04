<?php

declare(strict_types=1);

/**
 * Usage:
 *   php bin/migrate.php           apply pending migrations
 *   php bin/migrate.php --status  list applied / pending migrations
 *   php bin/migrate.php --print   print one SQL script for phpMyAdmin (no SSH hosting)
 */

use App\Core\Migrator;

/** @var App\Core\App $app */
$app = require __DIR__ . '/bootstrap.php';
$migrator = new Migrator($app->db(), $app->root . '/database/migrations');
$mode = $argv[1] ?? '';

if ($mode === '--print') {
    echo $migrator->toSql();
    exit(0);
}

try {
    $modified = $migrator->modifiedSinceApplied();
    if ($modified !== []) {
        fwrite(STDERR, "ERROR: these applied migrations were edited (create a new migration instead):\n  - " . implode("\n  - ", $modified) . "\n");
        exit(1);
    }
    if ($mode === '--status') {
        $applied = $migrator->applied();
        foreach (array_keys($migrator->files()) as $name) {
            echo (isset($applied[$name]) ? '[x] ' : '[ ] ') . $name . PHP_EOL;
        }
        exit(0);
    }
    $count = $migrator->migrate(static function (string $line): void {
        echo $line . PHP_EOL;
    });
    echo $count === 0 ? "Nothing to migrate.\n" : "{$count} migration(s) applied.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
