<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Config;
use App\Jobs\Handlers\FormGenerationHandler;
use App\Jobs\JobContext;
use App\Jobs\JobQueue;
use App\Models\OrganizationRepository;
use App\Models\ProjectRepository;
use App\Services\Ai\AiException;
use App\Services\Ai\AiGateway;
use App\Services\Ai\AiResponse;
use App\Services\Ai\CreditLedger;
use App\Services\AuditLogger;
use App\Services\AuthContext;
use App\Services\FormService;
use App\Services\FormValidator;
use Tests\Support\FakeTransport;

final class AiStudioTest extends DatabaseTestCase
{
    private FakeTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transport = new FakeTransport();
        FormGenerationHandler::$gatewayOverride = $this->gateway();
    }

    protected function tearDown(): void
    {
        FormGenerationHandler::$gatewayOverride = null;
    }

    private function gateway(string $key = 'test-key', float $budget = 0.5): AiGateway
    {
        return new AiGateway($this->db(), new Config(['ai' => [
            'base_url' => 'https://api.example.test/v1',
            'api_key' => $key,
            'timeout_seconds' => 25,
            'daily_budget_usd_per_org' => $budget,
            'models' => ['form' => 'gpt-6-luna', 'form_fallback' => 'deepseek-v4.1-flash'],
            'prices' => ['gpt-6-luna' => [0.10, 0.50], 'deepseek-v4.1-flash' => [0.22, 0.66]],
        ]]), $this->transport);
    }

    private function minimalForm(): string
    {
        return (string) file_get_contents($this->app->root . '/tests/fixtures/forms/valid/minimal.json');
    }

    /** @return array{0: AuthContext, 1: int} */
    private function accountWithProject(): array
    {
        $ctx = $this->newAccount('ai');
        $ws = (new OrganizationRepository($this->db()))->workspaces($ctx->organizationId());

        return [$ctx, (new ProjectRepository($this->db()))->create($ctx->organizationId(), (int) $ws[0]['id'], $ctx->userId(), 'IA', '')];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed>|null $progress
     */
    private function runJob(AuthContext $ctx, string $type, array $payload, ?array $progress = null, int $seconds = 120): \App\Jobs\JobResult
    {
        $queue = new JobQueue($this->db());
        $job = $queue->find($queue->push($type, $payload, $ctx->organizationId())) ?? [];

        return (new FormGenerationHandler())->handle(new JobContext($this->app, $job, $payload, $progress, microtime(true) + $seconds));
    }

    /** @return array<string, mixed> */
    private function brief(AuthContext $ctx, int $projectId): array
    {
        return ['mode' => 'generate', 'user_id' => $ctx->userId(), 'project_id' => $projectId,
            'brief' => ['objective' => 'Mesurer la satisfaction des clients', 'languages' => ['fr', 'en'], 'minutes' => 5]];
    }

    public function testGatewayLogsUsageAndCost(): void
    {
        [$ctx] = $this->accountWithProject();
        $this->transport->responses = [FakeTransport::ok('{"ok": true}', 1000, 2000)];

        $response = $this->gateway()->complete('test', 'form', 'sys', 'user', 100, $ctx->organizationId(), $ctx->userId());

        self::assertSame(['ok' => true], $response->json());
        self::assertSame(1100, $response->costMicroUsd, '1000 × 0.10 + 2000 × 0.50 micro-dollars');
        $row = $this->db()->fetchOne('SELECT * FROM token_usage WHERE organization_id = ?', [$ctx->organizationId()]);
        self::assertSame(['gpt-6-luna', 1000, 2000, 1100, 1], [$row['model'], (int) $row['prompt_tokens'], (int) $row['completion_tokens'], (int) $row['cost_micro_usd'], (int) $row['success']]);
        self::assertSame('https://api.example.test/v1/chat/completions', $this->transport->requests[0]['url']);
        self::assertSame(100, $this->transport->requests[0]['body']['max_tokens']);
    }

    public function testGatewayFallsBackToTheSecondModel(): void
    {
        [$ctx] = $this->accountWithProject();
        $this->transport->responses = [['status' => 503, 'body' => '{"error": {"message": "overloaded"}}'], FakeTransport::ok('{"x": 1}')];

        $response = $this->gateway()->complete('test', 'form', 's', 'u', 10, $ctx->organizationId());

        self::assertSame('deepseek-v4.1-flash', $response->model);
        self::assertSame(['gpt-6-luna' => 0, 'deepseek-v4.1-flash' => 1], array_column($this->db()->fetchAll('SELECT model, success FROM token_usage WHERE organization_id = ? ORDER BY id', [$ctx->organizationId()]), 'success', 'model'));
    }

    public function testGatewayRefusesWithoutKeyOrOverBudget(): void
    {
        [$ctx] = $this->accountWithProject();
        try {
            $this->gateway('')->complete('t', 'form', 's', 'u', 10, $ctx->organizationId());
            self::fail('Expected not_configured');
        } catch (AiException $e) {
            self::assertSame('not_configured', $e->reason);
        }
        $this->db()->insert('token_usage', ['organization_id' => $ctx->organizationId(), 'operation' => 't', 'model' => 'm', 'cost_micro_usd' => 600_000]);
        try {
            $this->gateway()->complete('t', 'form', 's', 'u', 10, $ctx->organizationId());
            self::fail('Expected budget');
        } catch (AiException $e) {
            self::assertSame('budget', $e->reason);
        }
        self::assertSame([], $this->transport->requests, 'No API call is made when refused');
    }

    public function testJsonExtraction(): void
    {
        self::assertSame(['a' => 1], AiResponse::extractJson("Voici :\n```json\n{\"a\": 1}\n```"));
        self::assertSame(['a' => ['b' => 2]], AiResponse::extractJson('Bien sûr ! {"a": {"b": 2}} Bonne enquête.'));
        self::assertNull(AiResponse::extractJson('pas de json'));
        self::assertNull(AiResponse::extractJson('[1, 2]'));
    }

    public function testGenerateCreatesAValidDraftAndChargesCreditsOnce(): void
    {
        [$ctx, $projectId] = $this->accountWithProject();
        $this->transport->responses = [
            FakeTransport::ok('{"title": "Satisfaction", "sections": [{"name": "profil", "title": "Profil", "purpose": "segments", "questions": 2}]}'),
            FakeTransport::ok("```json\n" . $this->minimalForm() . "\n```"),
        ];

        $result = $this->runJob($ctx, 'ai.form_generate', $this->brief($ctx, $projectId));

        self::assertSame('done', $result->status);
        self::assertArrayHasKey('form_public_id', $result->data, (string) json_encode($result->data));
        $form = $this->db()->fetchOne('SELECT f.*, v.source, v.status FROM forms f JOIN form_versions v ON v.id = f.current_version_id WHERE f.public_id = ?', [$result->data['form_public_id']]);
        self::assertSame(['ai', 'draft'], [$form['source'] ?? null, $form['status'] ?? null]);
        self::assertSame(5, (new CreditLedger($this->db()))->summary($ctx->organizationId())['spent']);
        self::assertCount(2, $this->transport->requests);
        self::assertStringContainsString('Plan validé', (string) $this->transport->requests[1]['body']['messages'][1]['content'], 'The plan is passed to the second call');
    }

    public function testInvalidOutputIsRepairedOnce(): void
    {
        [$ctx, $projectId] = $this->accountWithProject();
        $broken = json_decode($this->minimalForm(), true);
        unset($broken['settings']['title']);
        $this->transport->responses = [FakeTransport::ok('{"sections": []}'), FakeTransport::ok((string) json_encode($broken)), FakeTransport::ok($this->minimalForm())];

        $result = $this->runJob($ctx, 'ai.form_generate', $this->brief($ctx, $projectId));

        self::assertArrayHasKey('form_public_id', $result->data);
        self::assertCount(3, $this->transport->requests);
        self::assertStringContainsString('Erreurs', (string) $this->transport->requests[2]['body']['messages'][1]['content']);
    }

    public function testStillInvalidAfterRepairFailsWithoutChargingOrSaving(): void
    {
        [$ctx, $projectId] = $this->accountWithProject();
        $this->transport->responses = [FakeTransport::ok('{"sections": []}'), FakeTransport::ok('{"items": []}'), FakeTransport::ok('toujours pas de JSON')];

        $result = $this->runJob($ctx, 'ai.form_generate', $this->brief($ctx, $projectId));

        self::assertSame('invalid_output', $result->data['error'] ?? null);
        self::assertSame(0, (new CreditLedger($this->db()))->summary($ctx->organizationId())['spent']);
        self::assertSame(0, (int) $this->db()->fetchValue('SELECT COUNT(*) FROM forms WHERE organization_id = ?', [$ctx->organizationId()]));
    }

    public function testInsufficientCreditsStopsBeforeAnyCall(): void
    {
        [$ctx, $projectId] = $this->accountWithProject();
        (new CreditLedger($this->db()))->charge($ctx->organizationId(), $ctx->userId(), 8, 'test', 'x'); // free plan: 10

        $result = $this->runJob($ctx, 'ai.form_generate', $this->brief($ctx, $projectId));

        self::assertSame('insufficient_credits', $result->data['error'] ?? null);
        self::assertSame([], $this->transport->requests);
    }

    public function testYieldsWhenTheRemainingTimeCannotFitACall(): void
    {
        [$ctx, $projectId] = $this->accountWithProject();

        $result = $this->runJob($ctx, 'ai.form_generate', $this->brief($ctx, $projectId), null, 10);

        self::assertSame('yield', $result->status);
        self::assertSame(['step' => 'plan'], $result->data);
        self::assertSame([], $this->transport->requests);
    }

    public function testReviseUpdatesTheDraft(): void
    {
        [$ctx, $projectId] = $this->accountWithProject();
        $ids = (new FormService($this->db(), FormValidator::default(), new AuditLogger($this->db())))->createFromJson($ctx, $projectId, $this->minimalForm());
        $revised = json_decode($this->minimalForm(), true);
        $revised['items'][] = ['kind' => 'question', 'type' => 'text', 'name' => 'wait_time', 'label' => ['fr' => "Temps d'attente", 'en' => 'Waiting time']];
        $this->transport->responses = [FakeTransport::ok((string) json_encode($revised))];

        $result = $this->runJob($ctx, 'ai.form_revise', ['mode' => 'revise', 'user_id' => $ctx->userId(), 'form_id' => $ids['form_id'], 'instruction' => "Ajoute le temps d'attente"]);

        self::assertArrayHasKey('form_public_id', $result->data, (string) json_encode($result->data));
        self::assertSame(1, (new CreditLedger($this->db()))->summary($ctx->organizationId())['spent']);
        self::assertStringContainsString('wait_time', (string) $this->db()->fetchValue('SELECT schema_json FROM form_versions WHERE id = ?', [$ids['version_id']]));
        self::assertStringContainsString("Ajoute le temps d'attente", (string) $this->transport->requests[0]['body']['messages'][1]['content']);
    }

    public function testNetworkErrorsAreLeftToTheQueueRetry(): void
    {
        [$ctx, $projectId] = $this->accountWithProject();
        $this->transport->responses = [new AiException('network', 'timeout'), new AiException('network', 'timeout')];

        $this->expectException(AiException::class);
        $this->runJob($ctx, 'ai.form_generate', $this->brief($ctx, $projectId));
    }
}
