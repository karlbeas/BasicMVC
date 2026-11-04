<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Uuid;

/** Every method takes the organization id first: tenant isolation is not optional. */
final class ProjectRepository
{
    public function __construct(private Database $db) {}

    /** @return list<array<string, mixed>> */
    public function all(int $organizationId): array
    {
        return $this->db->fetchAll(
            'SELECT p.id, p.public_id, p.name, p.description, p.created_at, w.name AS workspace_name,
                    (SELECT COUNT(*) FROM forms f WHERE f.project_id = p.id AND f.organization_id = p.organization_id) AS form_count
             FROM projects p JOIN workspaces w ON w.id = p.workspace_id AND w.organization_id = p.organization_id
             WHERE p.organization_id = ? AND p.archived_at IS NULL
             ORDER BY p.created_at DESC, p.id DESC',
            [$organizationId],
        );
    }

    /** @return array<string, mixed>|null */
    public function findByPublicId(int $organizationId, string $publicId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM projects WHERE organization_id = ? AND public_id = ? AND archived_at IS NULL',
            [$organizationId, $publicId],
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $organizationId, int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM projects WHERE organization_id = ? AND id = ? AND archived_at IS NULL',
            [$organizationId, $id],
        );
    }

    public function create(int $organizationId, int $workspaceId, int $userId, string $name, string $description): int
    {
        $owned = $this->db->fetchValue('SELECT id FROM workspaces WHERE id = ? AND organization_id = ?', [$workspaceId, $organizationId]);
        if ($owned === null) {
            throw new \DomainException('Workspace does not belong to organization');
        }

        return $this->db->insert('projects', [
            'public_id' => Uuid::v4(),
            'organization_id' => $organizationId,
            'workspace_id' => $workspaceId,
            'name' => $name,
            'description' => $description === '' ? null : $description,
            'created_by' => $userId,
        ]);
    }
}
