<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\SchemaUpgrader;

final class SchemaUpgraderTest extends DatabaseTestCase
{
    public function testMarkerTracksTheLatestMigration(): void
    {
        $cache = sys_get_temp_dir() . '/f2s-cache-' . bin2hex(random_bytes(4));
        $upgrader = new SchemaUpgrader($this->db(), $this->app->root . '/database/migrations', $cache);

        self::assertFalse($upgrader->upToDateMarker(), 'No marker yet');
        self::assertSame(0, $upgrader->upgrade(), 'Test DB is already migrated');
        self::assertTrue($upgrader->upToDateMarker());
        self::assertTrue($upgrader->isUpToDate());

        file_put_contents($cache . '/schema.version', "20200101_0000_old\n");
        self::assertFalse($upgrader->upToDateMarker(), 'A newer migration file makes the marker stale');
        self::assertTrue($upgrader->isUpToDate(), 'The database itself is up to date');
        self::assertTrue($upgrader->upToDateMarker(), 'isUpToDate() refreshes the marker');

        array_map('unlink', glob($cache . '/*') ?: []);
        rmdir($cache);
    }
}
