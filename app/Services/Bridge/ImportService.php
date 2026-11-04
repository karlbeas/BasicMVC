<?php

declare(strict_types=1);

namespace App\Services\Bridge;

use App\Core\Database;
use App\Core\Uuid;
use App\Services\AuditLogger;
use App\Services\AuthContext;
use App\Services\FormService;
use App\Services\FormValidator;

/**
 * Bridge pipeline (MANIFEST §3.2): detection → conversion → validation →
 * report → user confirmation → form. Nothing is created before confirmation.
 */
final class ImportService
{
    public const MAX_BYTES = 5 * 1024 * 1024;
    public const EXTENSIONS = ['xlsx', 'csv', 'json'];

    public function __construct(private Database $db, private AuditLogger $audit) {}

    /**
     * @return array{import_id: int, public_id: string, status: string}
     *
     * @throws ImportException
     */
    public function analyze(AuthContext $ctx, int $projectId, string $path, string $originalName, string $language): array
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext === 'xls') {
            throw new ImportException('xls_legacy');
        }
        if ($ext === 'xml') {
            throw new ImportException('xform_later');
        }
        if (!in_array($ext, self::EXTENSIONS, true)) {
            throw new ImportException('bad_type');
        }
        $size = (int) @filesize($path);
        if ($size <= 0) {
            throw new ImportException('empty');
        }
        if ($size > self::MAX_BYTES) {
            throw new ImportException('too_large');
        }
        $filename = mb_substr(basename($originalName), 0, 200);
        [$form, $report] = $this->convert($path, $ext, $filename, $language);

        $schemaJson = json_encode($form, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $result = FormValidator::default()->validateJson($schemaJson);
        if (!$result->isValid()) {
            $report->setValidationErrors(array_slice($result->errors(), 0, 30));
        }
        foreach ($result->warnings() as $w) {
            if ($w['code'] === 'label_missing_translation' && preg_match('/"([a-z]{2,3})"/', $w['message'], $m)) {
                $report->transformed($w['path'], 'traduction « ' . $m[1] . ' » manquante (texte à compléter dans l\'éditeur)');
            }
        }
        $publicId = Uuid::v4();
        $status = $report->hasValidationErrors() ? 'failed' : 'pending';
        $id = $this->db->insert('imports', [
            'public_id' => $publicId,
            'organization_id' => $ctx->organizationId(),
            'project_id' => $projectId,
            'user_id' => $ctx->userId(),
            'source_format' => $report->format,
            'filename' => $filename,
            'file_sha256' => (string) hash_file('sha256', $path),
            'status' => $status,
            'schema_json' => $schemaJson,
            'report_json' => json_encode($report->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
        $this->audit->log($ctx->organizationId(), $ctx->userId(), 'import.analyzed', 'import', $id, ['format' => $report->format, 'status' => $status]);

        return ['import_id' => $id, 'public_id' => $publicId, 'status' => $status];
    }

    /**
     * @return array{0: array<string, mixed>, 1: ConversionReport}
     *
     * @throws ImportException
     */
    public function convert(string $path, string $ext, string $filename, string $language): array
    {
        if ($ext === 'json') {
            $content = (string) file_get_contents($path);
            $data = json_decode(str_starts_with($content, "\xEF\xBB\xBF") ? substr($content, 3) : $content, true, 64);
            if (!is_array($data)) {
                throw new ImportException('unreadable', 'Invalid JSON');
            }
            $report = new ConversionReport('f2s_json', $filename);
            $report->kept('formulaire', 'F2S Schema v1, repris sans transformation');

            return [$data, $report];
        }
        if ($ext === 'csv') {
            $csv = CsvReader::read((string) file_get_contents($path));
            [$form, $report] = (new QuestionListConverter())->convert($csv['rows'], $filename, 'csv', $language);
            if ($csv['converted_encoding']) {
                $report->transformed('encodage', 'fichier en Windows-1252 converti en UTF-8');
            }

            return [$form, $report];
        }
        $xlsx = new XlsxReader($path);
        $sheets = array_keys($xlsx->sheets());
        $lower = array_map('strtolower', $sheets);
        if (in_array('survey', $lower, true)) {
            return (new XlsFormConverter())->convert(
                $xlsx->rows('survey'),
                in_array('choices', $lower, true) ? $xlsx->rows('choices') : [],
                in_array('settings', $lower, true) ? $xlsx->rows('settings') : [],
                $filename,
            );
        }
        if ($sheets === []) {
            throw new ImportException('empty');
        }
        [$form, $report] = (new QuestionListConverter())->convert($xlsx->rows($sheets[0]), $filename, 'xlsx_questions', $language);
        if (count($sheets) > 1) {
            $report->lost('feuilles ' . implode(', ', array_slice($sheets, 1)), 'seule la première feuille est lue pour une liste de questions');
        }

        return [$form, $report];
    }

    /** @return array<string, mixed>|null */
    public function find(int $organizationId, string $publicId): ?array
    {
        return $this->db->fetchOne('SELECT * FROM imports WHERE organization_id = ? AND public_id = ?', [$organizationId, $publicId]);
    }

    /** Creates the form (draft v1) from a pending import. */
    public function confirm(AuthContext $ctx, string $publicId): int
    {
        $import = $this->find($ctx->organizationId(), $publicId) ?? throw new \DomainException('Import not found');
        if ($import['status'] !== 'pending') {
            throw new \DomainException('Import is not pending');
        }
        $forms = new FormService($this->db, FormValidator::default(), $this->audit);
        $ids = $forms->createFromJson($ctx, (int) $import['project_id'], (string) $import['schema_json'], mb_substr((string) $import['source_format'], 0, 32));
        $this->db->update('imports', ['status' => 'confirmed', 'form_id' => $ids['form_id'], 'decided_at' => gmdate('Y-m-d H:i:s')], [
            'id' => (int) $import['id'],
            'organization_id' => $ctx->organizationId(),
        ]);
        $this->audit->log($ctx->organizationId(), $ctx->userId(), 'import.confirmed', 'import', (int) $import['id'], ['form_id' => $ids['form_id']]);

        return $ids['form_id'];
    }

    public function cancel(AuthContext $ctx, string $publicId): void
    {
        $import = $this->find($ctx->organizationId(), $publicId) ?? throw new \DomainException('Import not found');
        if (in_array($import['status'], ['pending', 'failed'], true)) {
            $this->db->update('imports', ['status' => 'cancelled', 'decided_at' => gmdate('Y-m-d H:i:s')], ['id' => (int) $import['id'], 'organization_id' => $ctx->organizationId()]);
        }
    }
}
