<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class SubmissionRepository
{
    public function __construct(private Database $db) {}

    public function countForCampaign(int $organizationId, int $campaignId): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM submissions WHERE organization_id = ? AND campaign_id = ?', [$organizationId, $campaignId]);
    }

    public function countForOrganizationSince(int $organizationId, string $since): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM submissions WHERE organization_id = ? AND submitted_at >= ?', [$organizationId, $since]);
    }

    public function exists(string $uuid): bool
    {
        return $this->db->fetchValue('SELECT 1 FROM submissions WHERE submission_uuid = ?', [$uuid]) !== null;
    }

    /**
     * @param array<string, mixed> $raw
     * @param array<string, string|list<string>> $data
     */
    public function insert(int $organizationId, int $campaignId, int $versionId, string $uuid, string $language, array $raw, array $data, ?string $startedAt): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $duration = null;
        if ($startedAt !== null) {
            $seconds = time() - (int) strtotime($startedAt . ' UTC');
            $duration = $seconds >= 0 && $seconds < 86400 * 7 ? $seconds : null;
        }
        $id = $this->db->insert('submissions', [
            'organization_id' => $organizationId,
            'campaign_id' => $campaignId,
            'form_version_id' => $versionId,
            'submission_uuid' => $uuid,
            'status' => 'complete',
            'language' => $language,
            'raw_json' => json_encode((object) $raw, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'data_json' => json_encode((object) $data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'started_at' => $startedAt,
            'submitted_at' => $now,
            'duration_seconds' => $duration,
        ]);
        foreach ($data as $name => $value) {
            foreach (is_array($value) ? $value : [$value] as $v) {
                $this->db->insert('answers', [
                    'organization_id' => $organizationId,
                    'submission_id' => $id,
                    'campaign_id' => $campaignId,
                    'question_name' => mb_substr((string) $name, 0, 64),
                    'value_text' => $v,
                    'value_number' => is_numeric($v) && strlen($v) <= 20 ? $v : null,
                ]);
            }
        }

        return $id;
    }

    /** @return list<array<string, mixed>> */
    public function latest(int $organizationId, int $campaignId, int $limit = 50, int $offset = 0): array
    {
        return $this->db->fetchAll(
            'SELECT id, submission_uuid, language, data_json, submitted_at, duration_seconds FROM submissions
             WHERE organization_id = ? AND campaign_id = ? ORDER BY submitted_at DESC, id DESC LIMIT ? OFFSET ?',
            [$organizationId, $campaignId, $limit, $offset],
        );
    }

    /**
     * Streams submissions in id order, chunk by chunk (constant memory on shared hosting).
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function each(int $organizationId, int $campaignId, int $chunk = 500): \Generator
    {
        $lastId = 0;
        do {
            $rows = $this->db->fetchAll(
                'SELECT s.id, s.submission_uuid, s.language, s.data_json, s.submitted_at, s.duration_seconds, v.version_number
                 FROM submissions s JOIN form_versions v ON v.id = s.form_version_id
                 WHERE s.organization_id = ? AND s.campaign_id = ? AND s.id > ? ORDER BY s.id LIMIT ?',
                [$organizationId, $campaignId, $lastId, $chunk],
            );
            foreach ($rows as $row) {
                $lastId = (int) $row['id'];
                yield $row;
            }
        } while (count($rows) === $chunk);
    }
}
