<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Uuid;
use App\Models\CampaignRepository;
use App\Models\OrganizationRepository;
use App\Models\ProjectRepository;
use App\Models\SubmissionRepository;
use App\Services\AuditLogger;
use App\Services\AuthContext;
use App\Services\Collect\CampaignService;
use App\Services\Collect\FormNotCollectableException;
use App\Services\Collect\ResponseExporter;
use App\Services\Collect\SubmissionService;
use App\Services\FormService;
use App\Services\FormValidator;
use App\Services\QuotaExceededException;
use App\Services\ValidationException;

final class CollectTest extends DatabaseTestCase
{
    private const NO_OPTIONS = ['title' => '', 'closes_at' => '', 'response_limit' => '', 'access_code' => ''];

    /** @return array{ctx: AuthContext, form_id: int, version_id: int, schema: array<string, mixed>} */
    private function publishedForm(?AuthContext $ctx = null, string $fixture = 'collect-all-types.json'): array
    {
        $ctx ??= $this->newAccount();
        $ws = (new OrganizationRepository($this->db()))->workspaces($ctx->organizationId());
        $projectId = (new ProjectRepository($this->db()))->create($ctx->organizationId(), (int) $ws[0]['id'], $ctx->userId(), 'Collecte', '');
        $json = (string) file_get_contents($this->app->root . '/tests/fixtures/forms/valid/' . $fixture);
        $forms = new FormService($this->db(), FormValidator::default(), new AuditLogger($this->db()));
        $ids = $forms->createFromJson($ctx, $projectId, $json);
        $forms->publish($ctx, $ids['version_id']);

        return ['ctx' => $ctx, 'form_id' => $ids['form_id'], 'version_id' => $ids['version_id'], 'schema' => json_decode($json, true)];
    }

    private function campaigns(): CampaignService
    {
        return new CampaignService($this->db(), new AuditLogger($this->db()));
    }

    /** @return array<string, mixed> */
    private function campaignRow(AuthContext $ctx, int $id): array
    {
        $c = (new CampaignRepository($this->db()))->find($ctx->organizationId(), $id);
        self::assertNotNull($c);

        return (new CampaignRepository($this->db()))->findByToken((string) $c['token']) ?? [];
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function answers(array $extra = []): array
    {
        return $extra + [
            '_submission' => Uuid::v4(), '_started' => gmdate('Y-m-d H:i:s', time() - 95),
            'consent' => 'yes', 'city' => 'Douala', 'age' => '35', 'used' => ['lab'], 'q_staff' => '3', 'q_wait' => '1',
        ];
    }

    public function testOnlyPublishedVersionsCanBeCollected(): void
    {
        $ctx = $this->newAccount();
        $ws = (new OrganizationRepository($this->db()))->workspaces($ctx->organizationId());
        $projectId = (new ProjectRepository($this->db()))->create($ctx->organizationId(), (int) $ws[0]['id'], $ctx->userId(), 'P', '');
        $ids = (new FormService($this->db(), FormValidator::default(), new AuditLogger($this->db())))
            ->createFromJson($ctx, $projectId, (string) file_get_contents($this->app->root . '/tests/fixtures/forms/valid/minimal.json'));

        try {
            $this->campaigns()->open($ctx, $ids['form_id'], $ids['version_id'], self::NO_OPTIONS);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            self::assertSame('campaigns.error.not_published', $e->errors['version']);
        }
    }

    public function testSubmitStoresRawDataAndAnswersAndIsIdempotent(): void
    {
        $f = $this->publishedForm();
        $id = $this->campaigns()->open($f['ctx'], $f['form_id'], $f['version_id'], self::NO_OPTIONS);
        $campaign = $this->campaignRow($f['ctx'], $id);
        $service = new SubmissionService($this->db());
        $input = $this->answers(['spend' => '12,5', 'used' => ['lab', 'pharmacy'], 'drug' => 'Quinine']);

        self::assertSame('saved', $service->submit($campaign, $f['schema'], $input, 'fr')['status']);
        self::assertSame('duplicate', $service->submit($campaign, $f['schema'], $input, 'fr')['status'], 'Same submission_uuid twice = one response');

        $rows = $this->db()->fetchAll('SELECT * FROM submissions WHERE campaign_id = ?', [$id]);
        self::assertCount(1, $rows);
        $raw = json_decode((string) $rows[0]['raw_json'], true);
        $data = json_decode((string) $rows[0]['data_json'], true);
        self::assertSame('12,5', $raw['spend'], 'raw_json keeps the answer exactly as received');
        self::assertSame('12.5', $data['spend']);
        self::assertArrayNotHasKey('_submission', $raw);
        self::assertSame($f['version_id'], (int) $rows[0]['form_version_id'], 'Submission points to its form version');
        self::assertGreaterThanOrEqual(90, (int) $rows[0]['duration_seconds']);
        self::assertSame(['lab', 'pharmacy'], array_column($this->db()->fetchAll("SELECT value_text FROM answers WHERE submission_id = ? AND question_name = 'used' ORDER BY id", [$rows[0]['id']]), 'value_text'));
        self::assertSame('35.000000', (string) $this->db()->fetchValue("SELECT value_number FROM answers WHERE submission_id = ? AND question_name = 'age'", [$rows[0]['id']]));
    }

    public function testInvalidSubmissionIsNotStored(): void
    {
        $f = $this->publishedForm();
        $campaign = $this->campaignRow($f['ctx'], $this->campaigns()->open($f['ctx'], $f['form_id'], $f['version_id'], self::NO_OPTIONS));

        $result = (new SubmissionService($this->db()))->submit($campaign, $f['schema'], $this->answers(['age' => '12']), 'fr');

        self::assertSame('invalid', $result['status']);
        self::assertSame('constraint', $result['errors']['age']['code']);
        self::assertSame(0, (int) $this->db()->fetchValue('SELECT COUNT(*) FROM submissions WHERE campaign_id = ?', [$campaign['id']]));
    }

    public function testFreePlanQuotas(): void
    {
        $f = $this->publishedForm();
        $id = $this->campaigns()->open($f['ctx'], $f['form_id'], $f['version_id'], ['response_limit' => '5000'] + self::NO_OPTIONS);
        self::assertSame(100, (int) $this->campaignRow($f['ctx'], $id)['response_limit'], 'Response limit never exceeds the plan (free: 100)');

        // Temporary free limit: 5 published campaigns (open at once and per rolling 90 days).
        for ($i = 2; $i <= 5; ++$i) {
            $this->campaigns()->open($f['ctx'], $f['form_id'], $f['version_id'], self::NO_OPTIONS);
        }
        try {
            $this->campaigns()->open($f['ctx'], $f['form_id'], $f['version_id'], self::NO_OPTIONS);
            self::fail('Free plan: 5 open campaigns at most');
        } catch (QuotaExceededException $e) {
            self::assertSame('active_campaigns', $e->limitKey);
            self::assertSame(5, $e->limit);
        }

        $this->campaigns()->setOpen($f['ctx'], $id, false);
        try {
            $this->campaigns()->open($f['ctx'], $f['form_id'], $f['version_id'], self::NO_OPTIONS);
            self::fail('Free plan: 5 campaigns per rolling 90 days, even after closing one');
        } catch (QuotaExceededException $e) {
            self::assertSame('published_campaigns_rolling_90_days', $e->limitKey);
        }
    }

    public function testResponseLimitAndClosedCampaign(): void
    {
        $f = $this->publishedForm();
        $id = $this->campaigns()->open($f['ctx'], $f['form_id'], $f['version_id'], ['response_limit' => '1'] + self::NO_OPTIONS);
        $service = new SubmissionService($this->db());

        self::assertSame('saved', $service->submit($this->campaignRow($f['ctx'], $id), $f['schema'], $this->answers(), 'fr')['status']);
        $second = $service->submit($this->campaignRow($f['ctx'], $id), $f['schema'], $this->answers(), 'fr');
        self::assertSame(['closed', 'full'], [$second['status'], $second['reason']]);

        $this->campaigns()->setOpen($f['ctx'], $id, false);
        self::assertSame('closed', CampaignService::closedReason($this->campaignRow($f['ctx'], $id), 0));
    }

    public function testFormsWithUnsupportedFeaturesCannotBeCollected(): void
    {
        $ctx = $this->newAccount();
        $ws = (new OrganizationRepository($this->db()))->workspaces($ctx->organizationId());
        $projectId = (new ProjectRepository($this->db()))->create($ctx->organizationId(), (int) $ws[0]['id'], $ctx->userId(), 'P', '');
        $form = json_decode((string) file_get_contents($this->app->root . '/tests/fixtures/forms/valid/minimal.json'), true);
        $form['items'][] = ['kind' => 'question', 'type' => 'geopoint', 'name' => 'gps', 'label' => ['fr' => 'GPS', 'en' => 'GPS']];
        $forms = new FormService($this->db(), FormValidator::default(), new AuditLogger($this->db()));
        $ids = $forms->createFromJson($ctx, $projectId, (string) json_encode($form));
        $forms->publish($ctx, $ids['version_id']);

        $this->expectException(FormNotCollectableException::class);
        $this->campaigns()->open($ctx, $ids['form_id'], $ids['version_id'], self::NO_OPTIONS);
    }

    public function testCsvAndJsonExports(): void
    {
        $f = $this->publishedForm();
        $id = $this->campaigns()->open($f['ctx'], $f['form_id'], $f['version_id'], self::NO_OPTIONS);
        $service = new SubmissionService($this->db());
        $service->submit($this->campaignRow($f['ctx'], $id), $f['schema'], $this->answers(['city' => '=cmd|calc', 'used' => ['lab', 'other'], 'used_other' => 'Radio; X']), 'fr');

        $exporter = new ResponseExporter(new SubmissionRepository($this->db()), $f['schema']);
        $csv = $exporter->csv($f['ctx']->organizationId(), $id);
        $lines = array_values(array_filter(explode("\n", $csv)));

        self::assertStringStartsWith("\xEF\xBB\xBF" . 'submission_uuid;submitted_at_utc;form_version;language;duration_seconds;consent;city', $lines[0]);
        self::assertCount(2, $lines);
        self::assertStringContainsString(";'=cmd|calc;", $lines[1], 'Formula injection neutralized');
        self::assertStringContainsString(';"lab other";"Radio; X";', $lines[1]);

        $json = json_decode($exporter->json($f['ctx']->organizationId(), $id), true);
        self::assertSame(1, $json['count']);
        self::assertSame(['lab', 'other'], $json['submissions'][0]['answers']['used']);
    }

    public function testCampaignsAreIsolatedByOrganization(): void
    {
        $f = $this->publishedForm();
        $id = $this->campaigns()->open($f['ctx'], $f['form_id'], $f['version_id'], self::NO_OPTIONS);
        $other = $this->newAccount('other');
        $public = (string) (new CampaignRepository($this->db()))->find($f['ctx']->organizationId(), $id)['public_id'];

        self::assertNull((new CampaignRepository($this->db()))->findByPublicId($other->organizationId(), $public));
        self::assertNull((new CampaignRepository($this->db()))->find($other->organizationId(), $id));
        $this->expectException(\DomainException::class);
        $this->campaigns()->setOpen($other, $id, false);
    }

    public function testUnpublishClosesCampaignsAndOpensADraftWithoutTouchingThePublishedVersion(): void
    {
        $f = $this->publishedForm();
        $id = $this->campaigns()->open($f['ctx'], $f['form_id'], $f['version_id'], self::NO_OPTIONS);
        (new SubmissionService($this->db()))->submit($this->campaignRow($f['ctx'], $id), $f['schema'], $this->answers(), 'fr');
        $publishedBefore = $this->db()->fetchOne('SELECT schema_hash, status FROM form_versions WHERE id = ?', [$f['version_id']]);
        $service = new FormService($this->db(), FormValidator::default(), new AuditLogger($this->db()));

        $result = $service->unpublish($f['ctx'], $f['form_id']);

        self::assertSame(1, $result['closed_campaigns']);
        self::assertNotNull($result['draft_version_id']);
        self::assertSame('closed', CampaignService::closedReason($this->campaignRow($f['ctx'], $id), 0), 'public link no longer accepts responses');
        self::assertSame($publishedBefore, $this->db()->fetchOne('SELECT schema_hash, status FROM form_versions WHERE id = ?', [$f['version_id']]), 'published version unchanged');
        self::assertSame(1, (int) $this->db()->fetchValue('SELECT COUNT(*) FROM submissions WHERE campaign_id = ?', [$id]), 'responses kept');
        self::assertSame((int) $result['draft_version_id'], (int) $this->db()->fetchValue('SELECT current_version_id FROM forms WHERE id = ?', [$f['form_id']]));
    }

    public function testDeleteHidesTheFormKillsPublicLinksAndKeepsResponses(): void
    {
        $f = $this->publishedForm();
        $id = $this->campaigns()->open($f['ctx'], $f['form_id'], $f['version_id'], self::NO_OPTIONS);
        $token = (string) (new CampaignRepository($this->db()))->find($f['ctx']->organizationId(), $id)['token'];
        (new SubmissionService($this->db()))->submit($this->campaignRow($f['ctx'], $id), $f['schema'], $this->answers(), 'fr');
        $publicId = (string) $this->db()->fetchValue('SELECT public_id FROM forms WHERE id = ?', [$f['form_id']]);
        $service = new FormService($this->db(), FormValidator::default(), new AuditLogger($this->db()));
        $other = $this->newAccount('intruder');

        try {
            $service->delete($other, $f['form_id']);
            self::fail('Another organization cannot delete the form');
        } catch (\DomainException) {
        }
        $service->delete($f['ctx'], $f['form_id']);

        self::assertNull((new \App\Models\FormRepository($this->db()))->findByPublicId($f['ctx']->organizationId(), $publicId));
        self::assertNull((new CampaignRepository($this->db()))->findByToken($token), 'public link is dead');
        self::assertSame(1, (int) $this->db()->fetchValue('SELECT COUNT(*) FROM submissions WHERE campaign_id = ?', [$id]), 'responses are kept (soft delete)');
        $this->expectException(\DomainException::class);
        $service->delete($f['ctx'], $f['form_id']);
    }
}
