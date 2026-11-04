<?php

declare(strict_types=1);

namespace App\Services\Collect;

use App\Core\Database;
use App\Models\CampaignRepository;
use App\Models\FormRepository;
use App\Services\AuditLogger;
use App\Services\AuthContext;
use App\Services\PlanPolicy;
use App\Services\QuotaExceededException;
use App\Services\ValidationException;

/** Opens a published form version for responses, under plan quotas (MANIFEST §2). */
final class CampaignService
{
    private CampaignRepository $campaigns;
    private FormRepository $forms;

    public function __construct(private Database $db, private AuditLogger $audit)
    {
        $this->campaigns = new CampaignRepository($db);
        $this->forms = new FormRepository($db);
    }

    /**
     * @param array{title: string, closes_at: string, response_limit: string, access_code: string} $input
     *
     * @throws ValidationException|QuotaExceededException|FormNotCollectableException
     */
    public function open(AuthContext $ctx, int $formId, int $versionId, array $input): int
    {
        $orgId = $ctx->organizationId();
        $version = $this->forms->findVersion($orgId, $versionId);
        if ($version === null || (int) $version['form_id'] !== $formId) {
            throw new \DomainException('Version not found');
        }
        $form = $this->db->fetchOne('SELECT * FROM forms WHERE organization_id = ? AND id = ?', [$orgId, $formId]) ?? throw new \DomainException('Form not found');

        $errors = [];
        if ($version['status'] !== 'published') {
            $errors['version'] = 'campaigns.error.not_published';
        }
        $title = trim($input['title']) !== '' ? trim($input['title']) : (string) $form['title'];
        $closesAt = null;
        if (trim($input['closes_at']) !== '') {
            $d = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($input['closes_at']), new \DateTimeZone('UTC'));
            if ($d === false || $d->format('Y-m-d') !== trim($input['closes_at']) || $d->getTimestamp() < time()) {
                $errors['closes_at'] = 'campaigns.error.closes_at';
            } else {
                $closesAt = $d->setTime(23, 59, 59)->format('Y-m-d H:i:s');
            }
        }
        $policy = PlanPolicy::forOrganization($this->db, $orgId);
        $planLimit = $policy->limit('responses_per_campaign');
        $limit = null;
        if (trim($input['response_limit']) !== '') {
            if (!ctype_digit(trim($input['response_limit'])) || (int) $input['response_limit'] < 1) {
                $errors['response_limit'] = 'campaigns.error.response_limit';
            } else {
                $limit = (int) $input['response_limit'];
            }
        }
        if ($planLimit !== null && ($limit === null || $limit > $planLimit)) {
            $limit = $planLimit; // never above the plan
        }
        $code = trim($input['access_code']);
        if ($code !== '' && (mb_strlen($code) < 4 || mb_strlen($code) > 64)) {
            $errors['access_code'] = 'campaigns.error.access_code';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $schema = json_decode((string) $version['schema_json'], true, 64, JSON_THROW_ON_ERROR);
        $blockers = CollectGuard::blockers($schema);
        if ($blockers !== []) {
            throw new FormNotCollectableException($blockers);
        }

        return $this->db->transaction(function () use ($ctx, $orgId, $form, $formId, $versionId, $policy, $title, $closesAt, $limit, $code): int {
            // Serialize quota checks per organization.
            $this->db->fetchValue('SELECT id FROM organizations WHERE id = ? FOR UPDATE', [$orgId]);
            $policy->assertWithin('active_campaigns', $this->campaigns->countOpen($orgId) + 1);
            $policy->assertWithin(
                'published_campaigns_rolling_90_days',
                $this->campaigns->countCreatedSince($orgId, gmdate('Y-m-d H:i:s', time() - 90 * 86400)) + 1,
            );
            $id = $this->campaigns->create($orgId, (int) $form['project_id'], $formId, $versionId, $ctx->userId(), [
                'title' => $title,
                'opens_at' => null,
                'closes_at' => $closesAt,
                'response_limit' => $limit,
                'access_code_hash' => $code === '' ? null : password_hash($code, PASSWORD_DEFAULT),
            ]);
            $this->audit->log($orgId, $ctx->userId(), 'campaign.opened', 'campaign', $id, ['form_version_id' => $versionId]);

            return $id;
        });
    }

    /** @throws QuotaExceededException */
    public function setOpen(AuthContext $ctx, int $campaignId, bool $open): void
    {
        $orgId = $ctx->organizationId();
        $campaign = $this->campaigns->find($orgId, $campaignId) ?? throw new \DomainException('Campaign not found');
        if ($open && $campaign['status'] !== 'open') {
            PlanPolicy::forOrganization($this->db, $orgId)->assertWithin('active_campaigns', $this->campaigns->countOpen($orgId) + 1);
        }
        $this->campaigns->setStatus($orgId, $campaignId, $open ? 'open' : 'closed');
        $this->audit->log($orgId, $ctx->userId(), $open ? 'campaign.reopened' : 'campaign.closed', 'campaign', $campaignId);
    }

    /**
     * Why a campaign currently refuses responses, or null if it accepts them.
     *
     * @param array<string, mixed> $campaign
     */
    public static function closedReason(array $campaign, int $responseCount, ?int $monthlyCount = null, ?int $monthlyLimit = null): ?string
    {
        $now = gmdate('Y-m-d H:i:s');

        return match (true) {
            $campaign['status'] !== 'open' => 'closed',
            $campaign['opens_at'] !== null && $campaign['opens_at'] > $now => 'not_yet_open',
            $campaign['closes_at'] !== null && $campaign['closes_at'] < $now => 'expired',
            $campaign['response_limit'] !== null && $responseCount >= (int) $campaign['response_limit'] => 'full',
            $monthlyLimit !== null && $monthlyCount !== null && $monthlyCount >= $monthlyLimit => 'full',
            default => null,
        };
    }
}
