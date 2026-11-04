<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Uuid;

final class FormRepository
{
    public function __construct(private Database $db) {}

    /** @return list<array<string, mixed>> */
    public function forProject(int $organizationId, int $projectId): array
    {
        return $this->db->fetchAll(
            'SELECT f.id, f.public_id, f.title, f.form_key, f.created_at,
                    v.version_number, v.status, v.question_count, v.public_id AS version_public_id
             FROM forms f LEFT JOIN form_versions v ON v.id = f.current_version_id AND v.organization_id = f.organization_id
             WHERE f.organization_id = ? AND f.project_id = ? AND f.archived_at IS NULL
             ORDER BY f.created_at DESC, f.id DESC',
            [$organizationId, $projectId],
        );
    }

    /** @return array<string, mixed>|null */
    public function findByPublicId(int $organizationId, string $publicId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM forms WHERE organization_id = ? AND public_id = ? AND archived_at IS NULL',
            [$organizationId, $publicId],
        );
    }

    public function create(int $organizationId, int $projectId, int $userId, string $formKey, string $title): int
    {
        return $this->db->insert('forms', [
            'public_id' => Uuid::v4(),
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'form_key' => $formKey,
            'title' => mb_substr($title, 0, 255),
            'created_by' => $userId,
        ]);
    }

    public function setCurrentVersion(int $organizationId, int $formId, int $versionId): void
    {
        $this->db->update('forms', ['current_version_id' => $versionId, 'updated_at' => gmdate('Y-m-d H:i:s')], [
            'id' => $formId,
            'organization_id' => $organizationId,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function versions(int $organizationId, int $formId): array
    {
        return $this->db->fetchAll(
            'SELECT id, public_id, version_number, status, question_count, schema_hash, source, created_at, published_at
             FROM form_versions WHERE organization_id = ? AND form_id = ? ORDER BY version_number DESC',
            [$organizationId, $formId],
        );
    }

    /** @return array<string, mixed>|null */
    public function findVersion(int $organizationId, int $versionId): ?array
    {
        return $this->db->fetchOne('SELECT * FROM form_versions WHERE organization_id = ? AND id = ?', [$organizationId, $versionId]);
    }

    /** @return array<string, mixed>|null */
    public function findVersionByPublicId(int $organizationId, string $publicId): ?array
    {
        return $this->db->fetchOne('SELECT * FROM form_versions WHERE organization_id = ? AND public_id = ?', [$organizationId, $publicId]);
    }

    public function nextVersionNumber(int $organizationId, int $formId): int
    {
        return 1 + (int) $this->db->fetchValue(
            'SELECT COALESCE(MAX(version_number), 0) FROM form_versions WHERE organization_id = ? AND form_id = ?',
            [$organizationId, $formId],
        );
    }

    public function createVersion(int $organizationId, int $formId, int $userId, int $number, string $schemaJson, int $questionCount, string $source): int
    {
        return $this->db->insert('form_versions', [
            'public_id' => Uuid::v4(),
            'organization_id' => $organizationId,
            'form_id' => $formId,
            'version_number' => $number,
            'status' => 'draft',
            'schema_version' => '1.0',
            'schema_json' => $schemaJson,
            'schema_hash' => hash('sha256', $schemaJson),
            'question_count' => $questionCount,
            'source' => $source,
            'created_by' => $userId,
        ]);
    }

    /**
     * Only drafts can change. The status guard is part of the WHERE clause so a
     * published version cannot be modified even by a concurrent request.
     */
    public function updateDraft(int $organizationId, int $versionId, string $schemaJson, int $questionCount): bool
    {
        return $this->db->update('form_versions', [
            'schema_json' => $schemaJson,
            'schema_hash' => hash('sha256', $schemaJson),
            'question_count' => $questionCount,
        ], [
            'id' => $versionId,
            'organization_id' => $organizationId,
            'status' => 'draft',
        ]) === 1 || $this->isUnchangedDraft($organizationId, $versionId, $schemaJson);
    }

    public function markPublished(int $organizationId, int $versionId, string $schemaJson): bool
    {
        return $this->db->update('form_versions', [
            'status' => 'published',
            'schema_json' => $schemaJson,
            'schema_hash' => hash('sha256', $schemaJson),
            'published_at' => gmdate('Y-m-d H:i:s'),
        ], [
            'id' => $versionId,
            'organization_id' => $organizationId,
            'status' => 'draft',
        ]) === 1;
    }

    /**
     * @param list<array{name: string, type: string, path: string, label: string, choice_list: ?string, analysis_role: ?string, sensitive: bool, personal: bool}> $questions
     */
    public function replaceQuestions(int $organizationId, int $versionId, array $questions): void
    {
        $this->db->execute('DELETE FROM questions WHERE organization_id = ? AND form_version_id = ?', [$organizationId, $versionId]);
        foreach ($questions as $position => $q) {
            $this->db->insert('questions', [
                'organization_id' => $organizationId,
                'form_version_id' => $versionId,
                'name' => $q['name'],
                'type' => $q['type'],
                'path' => mb_substr($q['path'], 0, 512),
                'label' => $q['label'],
                'choice_list' => $q['choice_list'],
                'analysis_role' => $q['analysis_role'],
                'is_sensitive' => $q['sensitive'] ? 1 : 0,
                'is_personal' => $q['personal'] ? 1 : 0,
                'position' => $position,
            ]);
        }
    }

    /** @return list<array<string, mixed>> */
    public function questions(int $organizationId, int $versionId): array
    {
        return $this->db->fetchAll(
            'SELECT name, type, path, label, choice_list, analysis_role, is_sensitive, is_personal
             FROM questions WHERE organization_id = ? AND form_version_id = ? ORDER BY position',
            [$organizationId, $versionId],
        );
    }

    /** MySQL reports 0 affected rows when values are identical: distinguish that from "not a draft". */
    private function isUnchangedDraft(int $organizationId, int $versionId, string $schemaJson): bool
    {
        $row = $this->db->fetchOne(
            'SELECT status, schema_hash FROM form_versions WHERE organization_id = ? AND id = ?',
            [$organizationId, $versionId],
        );

        return $row !== null && $row['status'] === 'draft' && $row['schema_hash'] === hash('sha256', $schemaJson);
    }
}
