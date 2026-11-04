<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Core\Database;
use App\Services\PlanPolicy;

/**
 * AI credits (MANIFEST §2). Allowance comes from the plan: "ai_credits" (one-off,
 * free plan and Pass) or "ai_credits_per_month" (Pro, resets on the 1st, UTC).
 * Spending is recorded once per reference (idempotent: a retried job never pays twice).
 */
final class CreditLedger
{
    public function __construct(private Database $db) {}

    /** @return array{allowance: int, spent: int, granted: int, available: int, monthly: bool} */
    public function summary(int $organizationId): array
    {
        $policy = PlanPolicy::forOrganization($this->db, $organizationId);
        $monthly = $policy->limit('ai_credits_per_month') !== null;
        $allowance = $monthly ? (int) $policy->limit('ai_credits_per_month') : (int) ($policy->limit('ai_credits') ?? 0);
        $since = $monthly ? gmdate('Y-m-01 00:00:00') : '1970-01-01 00:00:00';
        $row = $this->db->fetchOne(
            'SELECT COALESCE(SUM(CASE WHEN delta < 0 THEN -delta ELSE 0 END), 0) AS spent,
                    COALESCE(SUM(CASE WHEN delta > 0 THEN delta ELSE 0 END), 0) AS granted
             FROM credit_ledger WHERE organization_id = ? AND created_at >= ?',
            [$organizationId, $since],
        );
        $spent = (int) ($row['spent'] ?? 0);
        $granted = (int) ($row['granted'] ?? 0);

        return ['allowance' => $allowance, 'spent' => $spent, 'granted' => $granted, 'available' => max(0, $allowance + $granted - $spent), 'monthly' => $monthly];
    }

    public function available(int $organizationId): int
    {
        return $this->summary($organizationId)['available'];
    }

    /** @return bool false if this reference was already charged */
    public function charge(int $organizationId, ?int $userId, int $amount, string $reason, string $reference): bool
    {
        try {
            $this->db->insert('credit_ledger', [
                'organization_id' => $organizationId,
                'user_id' => $userId,
                'delta' => -abs($amount),
                'reason' => mb_substr($reason, 0, 48),
                'reference' => mb_substr($reference, 0, 64),
            ]);

            return true;
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                return false;
            }
            throw $e;
        }
    }

    public function grant(int $organizationId, ?int $userId, int $amount, string $reference): void
    {
        $this->db->insert('credit_ledger', [
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'delta' => abs($amount),
            'reason' => 'grant',
            'reference' => mb_substr($reference, 0, 64),
        ]);
    }
}
