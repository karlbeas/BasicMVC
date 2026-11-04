<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Uuid;

final class CampaignRepository
{
    private const TOKEN_ALPHABET = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(private Database $db) {}

    /** @param array{title: string, closes_at: ?string, opens_at: ?string, response_limit: ?int, access_code_hash: ?string} $fields */
    public function create(int $organizationId, int $projectId, int $formId, int $versionId, int $userId, array $fields): int
    {
        return $this->db->insert('campaigns', [
            'public_id' => Uuid::v4(),
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'form_id' => $formId,
            'form_version_id' => $versionId,
            'token' => $this->uniqueToken(),
            'title' => mb_substr($fields['title'], 0, 255),
            'status' => 'open',
            'opens_at' => $fields['opens_at'],
            'closes_at' => $fields['closes_at'],
            'response_limit' => $fields['response_limit'],
            'access_code_hash' => $fields['access_code_hash'],
            'created_by' => $userId,
        ]);
    }

    /** @return array<string, mixed>|null */
    public function find(int $organizationId, int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM campaigns WHERE organization_id = ? AND id = ?', [$organizationId, $id]);
    }

    /** @return array<string, mixed>|null */
    public function findByPublicId(int $organizationId, string $publicId): ?array
    {
        return $this->db->fetchOne('SELECT * FROM campaigns WHERE organization_id = ? AND public_id = ?', [$organizationId, $publicId]);
    }

    /**
     * Public entry point: the token is the only lookup without organization_id,
     * the caller then works with the campaign's own organization_id.
     *
     * @return array<string, mixed>|null
     */
    public function findByToken(string $token): ?array
    {
        return $this->db->fetchOne(
            'SELECT c.*, o.plan_id, p.code AS plan_code FROM campaigns c
             JOIN organizations o ON o.id = c.organization_id AND o.deleted_at IS NULL
             JOIN plans p ON p.id = o.plan_id
             WHERE c.token = ?',
            [$token],
        );
    }

    /** @return list<array<string, mixed>> */
    public function forForm(int $organizationId, int $formId): array
    {
        return $this->db->fetchAll(
            'SELECT c.*, v.version_number, (SELECT COUNT(*) FROM submissions s WHERE s.campaign_id = c.id) AS response_count
             FROM campaigns c JOIN form_versions v ON v.id = c.form_version_id
             WHERE c.organization_id = ? AND c.form_id = ? ORDER BY c.created_at DESC, c.id DESC',
            [$organizationId, $formId],
        );
    }

    public function countOpen(int $organizationId): int
    {
        return (int) $this->db->fetchValue("SELECT COUNT(*) FROM campaigns WHERE organization_id = ? AND status = 'open'", [$organizationId]);
    }

    public function countCreatedSince(int $organizationId, string $since): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM campaigns WHERE organization_id = ? AND created_at >= ?', [$organizationId, $since]);
    }

    public function setStatus(int $organizationId, int $id, string $status): void
    {
        $this->db->update('campaigns', [
            'status' => $status,
            'closed_at' => $status === 'closed' ? gmdate('Y-m-d H:i:s') : null,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], ['id' => $id, 'organization_id' => $organizationId]);
    }

    private function uniqueToken(): string
    {
        for ($attempt = 0; $attempt < 10; ++$attempt) {
            $token = '';
            $max = strlen(self::TOKEN_ALPHABET) - 1;
            for ($i = 0; $i < 10; ++$i) {
                $token .= self::TOKEN_ALPHABET[random_int(0, $max)];
            }
            if ($this->db->fetchValue('SELECT 1 FROM campaigns WHERE token = ?', [$token]) === null) {
                return $token;
            }
        }
        throw new \RuntimeException('Could not generate a unique campaign token');
    }
}
