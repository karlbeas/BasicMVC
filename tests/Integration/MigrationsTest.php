<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Migrator;

final class MigrationsTest extends DatabaseTestCase
{
    public function testAllMigrationsAppliedAndUnmodified(): void
    {
        $migrator = new Migrator($this->db(), $this->app->root . '/database/migrations');

        self::assertSame(array_keys($migrator->files()), array_keys($migrator->applied()));
        self::assertSame([], $migrator->modifiedSinceApplied());
        self::assertSame(0, $migrator->migrate(static function (): void {}));
    }

    public function testPlansAreSeeded(): void
    {
        $codes = array_column($this->db()->fetchAll('SELECT code FROM plans ORDER BY id'), 'code');

        self::assertSame(['free', 'project_pass', 'pro', 'pro_yearly'], $codes);
    }

    public function testEveryBusinessTableCarriesOrganizationId(): void
    {
        $tenantTables = ['workspaces', 'memberships', 'projects', 'forms', 'form_versions', 'questions', 'jobs', 'audit_logs'];
        foreach ($tenantTables as $table) {
            $column = $this->db()->fetchOne("SHOW COLUMNS FROM `{$table}` LIKE 'organization_id'");
            self::assertNotNull($column, "{$table}.organization_id is missing");
        }
    }
}
