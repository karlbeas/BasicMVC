<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Migrator;

/**
 * Applies pending migrations automatically after an update (zip extracted over
 * an existing install, no SSH): run by the CRON dispatcher and the HTTP cron.
 * A marker file in storage/cache records the last applied migration, so web
 * requests can detect a pending upgrade without querying the database.
 */
final class SchemaUpgrader
{
    private const LOCK = 'f2s_schema_upgrade';

    private Migrator $migrator;

    public function __construct(private Database $db, string $migrationsDir, private string $cacheDir)
    {
        $this->migrator = new Migrator($db, $migrationsDir);
    }

    public function latestFile(): string
    {
        $files = array_keys($this->migrator->files());

        return $files === [] ? '' : (string) end($files);
    }

    /**
     * Cheap check for web requests: compares the marker with the newest migration file.
     * Impure: the marker file changes when an upgrade runs.
     *
     * @phpstan-impure
     */
    public function upToDateMarker(): bool
    {
        $marker = $this->markerFile();

        return is_file($marker) && trim((string) file_get_contents($marker)) === $this->latestFile();
    }

    /**
     * Authoritative check against schema_migrations; refreshes the marker when up to date.
     *
     * @phpstan-impure
     */
    public function isUpToDate(): bool
    {
        $applied = $this->migrator->applied();
        foreach (array_keys($this->migrator->files()) as $name) {
            if (!isset($applied[$name])) {
                return false;
            }
        }
        $this->writeMarker();

        return true;
    }

    /** @return int migrations applied (0 when another process holds the lock) */
    public function upgrade(): int
    {
        if ((int) $this->db->fetchValue('SELECT GET_LOCK(?, 0)', [self::LOCK]) !== 1) {
            return 0;
        }
        try {
            if ($this->migrator->modifiedSinceApplied() !== []) {
                throw new \RuntimeException('An applied migration was modified; refusing to upgrade automatically');
            }
            $count = $this->migrator->migrate(static function (): void {});
            $this->writeMarker();

            return $count;
        } finally {
            $this->db->fetchValue('SELECT RELEASE_LOCK(?)', [self::LOCK]);
        }
    }

    public function writeMarker(): void
    {
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0o750, true);
        }
        @file_put_contents($this->markerFile(), $this->latestFile() . "\n", LOCK_EX);
    }

    private function markerFile(): string
    {
        return rtrim($this->cacheDir, '/') . '/schema.version';
    }
}
