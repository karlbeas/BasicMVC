<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\OrganizationRepository;
use App\Models\ProjectRepository;
use App\Services\AuditLogger;
use App\Services\AuthContext;
use App\Services\Bridge\ImportException;
use App\Services\Bridge\ImportService;

final class ImportServiceTest extends DatabaseTestCase
{
    private const DIR = __DIR__ . '/../fixtures/imports/';

    /** @return array{0: AuthContext, 1: int} */
    private function setup2(): array
    {
        $ctx = $this->newAccount('imp');
        $ws = (new OrganizationRepository($this->db()))->workspaces($ctx->organizationId());

        return [$ctx, (new ProjectRepository($this->db()))->create($ctx->organizationId(), (int) $ws[0]['id'], $ctx->userId(), 'Import', '')];
    }

    private function service(): ImportService
    {
        return new ImportService($this->db(), new AuditLogger($this->db()));
    }

    public function testNothingIsCreatedBeforeConfirmation(): void
    {
        [$ctx, $projectId] = $this->setup2();
        $result = $this->service()->analyze($ctx, $projectId, self::DIR . 'kobo-xlsform.xlsx', 'kobo-xlsform.xlsx', 'fr');

        self::assertSame('pending', $result['status']);
        self::assertSame(0, (int) $this->db()->fetchValue('SELECT COUNT(*) FROM forms WHERE organization_id = ?', [$ctx->organizationId()]));
        $import = $this->service()->find($ctx->organizationId(), $result['public_id']);
        self::assertSame('xlsform', $import['source_format'] ?? null);
        self::assertSame(hash_file('sha256', self::DIR . 'kobo-xlsform.xlsx'), $import['file_sha256'] ?? null);
        $report = json_decode((string) ($import['report_json'] ?? '{}'), true);
        self::assertGreaterThan(0, $report['counts']['lost']);

        $formId = $this->service()->confirm($ctx, $result['public_id']);
        $version = $this->db()->fetchOne('SELECT v.source, v.status FROM forms f JOIN form_versions v ON v.id = f.current_version_id WHERE f.id = ?', [$formId]);
        self::assertSame(['xlsform', 'draft'], [$version['source'] ?? null, $version['status'] ?? null]);
        self::assertSame('confirmed', $this->service()->find($ctx->organizationId(), $result['public_id'])['status'] ?? null);

        $this->expectException(\DomainException::class);
        $this->service()->confirm($ctx, $result['public_id']);
    }

    public function testCancelAndIsolation(): void
    {
        [$ctx, $projectId] = $this->setup2();
        $result = $this->service()->analyze($ctx, $projectId, self::DIR . 'questions-fr.xlsx', 'questions-fr.xlsx', 'fr');
        $other = $this->newAccount('other');

        self::assertNull($this->service()->find($other->organizationId(), $result['public_id']));
        try {
            $this->service()->confirm($other, $result['public_id']);
            self::fail('Another organization cannot confirm');
        } catch (\DomainException) {
        }
        $this->service()->cancel($ctx, $result['public_id']);
        self::assertSame('cancelled', $this->service()->find($ctx->organizationId(), $result['public_id'])['status'] ?? null);
    }

    public function testInvalidResultCannotBeConfirmed(): void
    {
        [$ctx, $projectId] = $this->setup2();
        $file = tempnam(sys_get_temp_dir(), 'imp') . '.json';
        file_put_contents($file, '{"schema_version": "1.0", "items": []}');
        $result = $this->service()->analyze($ctx, $projectId, $file, 'cassé.json', 'fr');
        unlink($file);

        self::assertSame('failed', $result['status']);
        $report = json_decode((string) $this->service()->find($ctx->organizationId(), $result['public_id'])['report_json'], true);
        self::assertNotEmpty($report['validation_errors']);
        $this->expectException(\DomainException::class);
        $this->service()->confirm($ctx, $result['public_id']);
    }

    public function testUnsupportedFilesAreRefusedWithAClearReason(): void
    {
        [$ctx, $projectId] = $this->setup2();
        foreach (['ancien.xls' => 'xls_legacy', 'odk.xml' => 'xform_later', 'image.png' => 'bad_type'] as $name => $reason) {
            try {
                $this->service()->analyze($ctx, $projectId, self::DIR . 'questions-utf8.csv', $name, 'fr');
                self::fail('Expected refusal for ' . $name);
            } catch (ImportException $e) {
                self::assertSame($reason, $e->reason);
            }
        }
    }
}
