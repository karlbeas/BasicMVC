<?php

declare(strict_types=1);

namespace App\Services\Collect;

use App\Core\Database;
use App\Core\Uuid;
use App\Models\SubmissionRepository;
use App\Services\PlanPolicy;

/** Records a public response: limits, server-side validation, idempotent insert. */
final class SubmissionService
{
    private SubmissionRepository $submissions;

    public function __construct(private Database $db)
    {
        $this->submissions = new SubmissionRepository($db);
    }

    /**
     * @param array<string, mixed> $campaign row from CampaignRepository::findByToken
     * @param array<string, mixed> $form decoded schema of the campaign's version
     * @param array<string, mixed> $input POST fields
     *
     * @return array{status: 'saved'|'duplicate'|'invalid'|'closed', errors: array<string, array{code: string, message: ?string}>, reason: ?string}
     */
    public function submit(array $campaign, array $form, array $input, string $language): array
    {
        $uuid = is_string($input['_submission'] ?? null) ? strtolower($input['_submission']) : '';
        if (!Uuid::isValid($uuid)) {
            $uuid = Uuid::v4();
        } elseif ($this->submissions->exists($uuid)) {
            return ['status' => 'duplicate', 'errors' => [], 'reason' => null];
        }

        $result = (new SubmissionValidator($form, $language))->validate($input);
        if ($result['errors'] !== []) {
            return ['status' => 'invalid', 'errors' => $result['errors'], 'reason' => null];
        }

        $orgId = (int) $campaign['organization_id'];
        $campaignId = (int) $campaign['id'];
        $startedAt = is_string($input['_started'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $input['_started']) ? $input['_started'] : null;
        $raw = self::rawAnswers($input);

        return $this->db->transaction(function () use ($campaign, $orgId, $campaignId, $uuid, $language, $raw, $result, $startedAt): array {
            // Lock the campaign row so concurrent submissions cannot overshoot the limit.
            $locked = $this->db->fetchOne('SELECT * FROM campaigns WHERE id = ? FOR UPDATE', [$campaignId]) ?? $campaign;
            $policy = PlanPolicy::forOrganization($this->db, $orgId);
            $monthlyLimit = $policy->limit('responses_per_month');
            $monthly = $monthlyLimit !== null ? $this->submissions->countForOrganizationSince($orgId, gmdate('Y-m-01 00:00:00')) : null;
            $reason = CampaignService::closedReason($locked, $this->submissions->countForCampaign($orgId, $campaignId), $monthly, $monthlyLimit);
            if ($reason !== null) {
                return ['status' => 'closed', 'errors' => [], 'reason' => $reason];
            }
            try {
                $this->submissions->insert($orgId, $campaignId, (int) $locked['form_version_id'], $uuid, $language, $raw, $result['data'], $startedAt);
            } catch (\PDOException $e) {
                if ($e->getCode() === '23000' && $this->submissions->exists($uuid)) {
                    return ['status' => 'duplicate', 'errors' => [], 'reason' => null];
                }
                throw $e;
            }

            return ['status' => 'saved', 'errors' => [], 'reason' => null];
        });
    }

    /**
     * Submitted answers exactly as received (strings / lists), technical fields excluded.
     *
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public static function rawAnswers(array $input): array
    {
        $raw = [];
        foreach ($input as $key => $value) {
            $key = (string) $key;
            if ($key === '' || $key[0] === '_' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,80}$/', $key)) {
                continue;
            }
            if (is_array($value)) {
                $raw[$key] = array_values(array_map(static fn($v): string => mb_substr((string) $v, 0, 10000), array_filter($value, 'is_scalar')));
            } elseif (is_scalar($value)) {
                $raw[$key] = mb_substr((string) $value, 0, 10000);
            }
        }

        return $raw;
    }
}
