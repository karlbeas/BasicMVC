<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class AuditLogger
{
    public function __construct(private Database $db) {}

    /** @param array<string, mixed> $metadata Never put passwords, tokens or respondent data here. */
    public function log(
        ?int $organizationId,
        ?int $userId,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        array $metadata = [],
        ?string $ip = null,
    ): void {
        $this->db->insert('audit_logs', [
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'ip' => $ip,
        ]);
    }
}
