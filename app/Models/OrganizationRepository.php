<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Uuid;

final class OrganizationRepository
{
    public function __construct(private Database $db) {}

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT o.id, o.public_id, o.name, o.locale, o.plan_id, p.code AS plan_code, p.name AS plan_name
             FROM organizations o JOIN plans p ON p.id = o.plan_id
             WHERE o.id = ? AND o.deleted_at IS NULL',
            [$id],
        );
    }

    public function create(string $name, string $planCode, string $locale): int
    {
        $planId = $this->db->fetchValue('SELECT id FROM plans WHERE code = ? AND is_active = 1', [$planCode]);
        if ($planId === null) {
            throw new \RuntimeException('Unknown plan: ' . $planCode . ' (did you run the migrations?)');
        }

        return $this->db->insert('organizations', [
            'public_id' => Uuid::v4(),
            'name' => $name,
            'plan_id' => (int) $planId,
            'locale' => $locale,
        ]);
    }

    public function createWorkspace(int $organizationId, string $name): int
    {
        return $this->db->insert('workspaces', [
            'public_id' => Uuid::v4(),
            'organization_id' => $organizationId,
            'name' => $name,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function workspaces(int $organizationId): array
    {
        return $this->db->fetchAll(
            'SELECT id, public_id, name FROM workspaces WHERE organization_id = ? ORDER BY id',
            [$organizationId],
        );
    }

    public function addMember(int $organizationId, int $userId, string $role): int
    {
        return $this->db->insert('memberships', [
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'role' => $role,
        ]);
    }

    /** @return array<string, mixed>|null */
    public function membership(int $organizationId, int $userId): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, organization_id, user_id, role FROM memberships WHERE organization_id = ? AND user_id = ?',
            [$organizationId, $userId],
        );
    }

    /** @return list<array<string, mixed>> */
    public function membershipsForUser(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT m.organization_id, m.role, o.name FROM memberships m
             JOIN organizations o ON o.id = m.organization_id AND o.deleted_at IS NULL
             WHERE m.user_id = ? ORDER BY m.id',
            [$userId],
        );
    }
}
