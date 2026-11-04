<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Server-side quota checks (MANIFEST §2). Limits live in the `plans` table
 * (seeded by migration) so they can be tuned without a deploy.
 */
final class PlanPolicy
{
    /** @param array<string, mixed> $limits */
    public function __construct(public readonly string $planCode, private array $limits) {}

    public static function forOrganization(Database $db, int $organizationId): self
    {
        $row = $db->fetchOne(
            'SELECT p.code, p.limits FROM organizations o JOIN plans p ON p.id = o.plan_id WHERE o.id = ?',
            [$organizationId],
        );
        if ($row === null) {
            throw new \RuntimeException('Organization has no plan');
        }
        $limits = json_decode((string) $row['limits'], true, 16, JSON_THROW_ON_ERROR);

        return new self((string) $row['code'], is_array($limits) ? $limits : []);
    }

    /** Numeric limit, or null when unlimited / not defined. */
    public function limit(string $key): ?int
    {
        $value = $this->limits[$key] ?? null;

        return is_int($value) ? $value : null;
    }

    public function flag(string $key): bool
    {
        return ($this->limits[$key] ?? false) === true;
    }

    /** @return list<string> */
    public function list(string $key): array
    {
        $value = $this->limits[$key] ?? [];

        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }

    /** @throws QuotaExceededException */
    public function assertWithin(string $key, int $actual): void
    {
        $limit = $this->limit($key);
        if ($limit !== null && $actual > $limit) {
            throw new QuotaExceededException($key, $limit, $actual);
        }
    }
}
