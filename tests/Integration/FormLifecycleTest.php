<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\FormRepository;
use App\Models\OrganizationRepository;
use App\Models\ProjectRepository;
use App\Services\AuditLogger;
use App\Services\AuthContext;
use App\Services\FormInvalidException;
use App\Services\FormService;
use App\Services\FormValidator;
use App\Services\ImmutableVersionException;
use App\Services\QuotaExceededException;

final class FormLifecycleTest extends DatabaseTestCase
{
    private function service(): FormService
    {
        return new FormService($this->db(), FormValidator::default(), new AuditLogger($this->db()));
    }

    private function projectFor(AuthContext $ctx): int
    {
        $ws = (new OrganizationRepository($this->db()))->workspaces($ctx->organizationId());

        return (new ProjectRepository($this->db()))->create($ctx->organizationId(), (int) $ws[0]['id'], $ctx->userId(), 'Projet test', '');
    }

    private function exampleJson(): string
    {
        return (string) file_get_contents($this->app->root . '/docs/schema/examples/satisfaction-pharmacie.json');
    }

    public function testCreateValidateStore(): void
    {
        $ctx = $this->newAccount();
        $ids = $this->service()->createFromJson($ctx, $this->projectFor($ctx), $this->exampleJson());

        $repo = new FormRepository($this->db());
        $version = $repo->findVersion($ctx->organizationId(), $ids['version_id']);
        self::assertNotNull($version);
        self::assertSame('draft', $version['status']);
        self::assertSame(1, (int) $version['version_number']);
        self::assertSame(10, (int) $version['question_count']);
        self::assertSame(hash('sha256', (string) $version['schema_json']), $version['schema_hash']);
        self::assertCount(11, $repo->questions($ctx->organizationId(), $ids['version_id']));
        self::assertEquals(json_decode($this->exampleJson(), true), json_decode((string) $version['schema_json'], true), 'Stored schema must be the submitted content, unchanged');
    }

    public function testInvalidFormIsNeverStored(): void
    {
        $ctx = $this->newAccount();
        $projectId = $this->projectFor($ctx);
        try {
            $this->service()->createFromJson($ctx, $projectId, '{"schema_version": "1.0"}');
            self::fail('Expected FormInvalidException');
        } catch (FormInvalidException $e) {
            self::assertFalse($e->result->isValid());
        }
        self::assertSame(0, (int) $this->db()->fetchValue('SELECT COUNT(*) FROM forms WHERE organization_id = ?', [$ctx->organizationId()]));
    }

    public function testPublishedVersionIsImmutable(): void
    {
        $ctx = $this->newAccount();
        $ids = $this->service()->createFromJson($ctx, $this->projectFor($ctx), $this->exampleJson());
        $this->service()->publish($ctx, $ids['version_id']);

        $repo = new FormRepository($this->db());
        $published = $repo->findVersion($ctx->organizationId(), $ids['version_id']);
        self::assertNotNull($published);
        self::assertSame('published', $published['status']);
        self::assertNotNull($published['published_at']);
        self::assertSame('1', json_decode((string) $published['schema_json'], true)['settings']['version']);

        // Service level
        try {
            $this->service()->updateDraft($ctx, $ids['version_id'], $this->exampleJson());
            self::fail('Expected ImmutableVersionException');
        } catch (ImmutableVersionException) {
        }
        try {
            $this->service()->publish($ctx, $ids['version_id']);
            self::fail('Expected ImmutableVersionException on double publish');
        } catch (ImmutableVersionException) {
        }
        // Repository level: the WHERE status = 'draft' guard
        self::assertFalse($repo->updateDraft($ctx->organizationId(), $ids['version_id'], '{"tampered": true}', 1));
        self::assertFalse($repo->markPublished($ctx->organizationId(), $ids['version_id'], '{"tampered": true}'));

        $after = $repo->findVersion($ctx->organizationId(), $ids['version_id']);
        self::assertSame($published['schema_hash'], $after['schema_hash'] ?? null);
        self::assertSame($published['schema_json'], $after['schema_json'] ?? null);
    }

    public function testNewDraftFromPublishedVersion(): void
    {
        $ctx = $this->newAccount();
        $ids = $this->service()->createFromJson($ctx, $this->projectFor($ctx), $this->exampleJson());
        $this->service()->publish($ctx, $ids['version_id']);

        $draftId = $this->service()->newDraft($ctx, $ids['form_id']);
        self::assertSame($draftId, $this->service()->newDraft($ctx, $ids['form_id']), 'Only one open draft at a time');

        $repo = new FormRepository($this->db());
        $draft = $repo->findVersion($ctx->organizationId(), $draftId);
        self::assertSame(2, (int) ($draft['version_number'] ?? 0));
        self::assertSame('draft', $draft['status'] ?? null);
        self::assertArrayNotHasKey('version', json_decode((string) ($draft['schema_json'] ?? '{}'), true)['settings']);

        $edited = json_decode($this->exampleJson(), true);
        $edited['settings']['estimated_minutes'] = 5;
        $this->service()->updateDraft($ctx, $draftId, (string) json_encode($edited));
        self::assertSame(5, json_decode((string) $repo->findVersion($ctx->organizationId(), $draftId)['schema_json'], true)['settings']['estimated_minutes']);
    }

    public function testQuestionQuotaOfFreePlan(): void
    {
        $ctx = $this->newAccount();
        $form = json_decode((string) file_get_contents($this->app->root . '/tests/fixtures/forms/valid/minimal.json'), true);
        for ($i = 1; $i <= 68; ++$i) {
            $form['items'][] = ['kind' => 'question', 'type' => 'text', 'name' => 'q' . $i, 'label' => ['fr' => 'Q' . $i, 'en' => 'Q' . $i]];
        }
        $projectId = $this->projectFor($ctx);
        $this->service()->createFromJson($ctx, $projectId, (string) json_encode($form)); // 70 questions: allowed (temporary free limit)

        $form['items'][] = ['kind' => 'question', 'type' => 'text', 'name' => 'q69', 'label' => ['fr' => 'Q69', 'en' => 'Q69']];
        try {
            $this->service()->createFromJson($ctx, $projectId, (string) json_encode($form));
            self::fail('71 questions must exceed the free plan (70)');
        } catch (QuotaExceededException $e) {
            self::assertSame('questions_per_form', $e->limitKey);
            self::assertSame(70, $e->limit);
            self::assertSame(71, $e->actual);
        }
    }

    public function testOrganizationsAreIsolated(): void
    {
        $alice = $this->newAccount('alice');
        $bob = $this->newAccount('bob');
        $aliceProject = $this->projectFor($alice);
        $ids = $this->service()->createFromJson($alice, $aliceProject, $this->exampleJson());

        $projects = new ProjectRepository($this->db());
        $forms = new FormRepository($this->db());
        $publicProject = (string) $projects->find($alice->organizationId(), $aliceProject)['public_id'];
        $publicForm = (string) $this->db()->fetchValue('SELECT public_id FROM forms WHERE id = ?', [$ids['form_id']]);

        self::assertNull($projects->findByPublicId($bob->organizationId(), $publicProject));
        self::assertNull($forms->findByPublicId($bob->organizationId(), $publicForm));
        self::assertNull($forms->findVersion($bob->organizationId(), $ids['version_id']));
        self::assertSame([], $projects->all($bob->organizationId()));

        $this->expectException(\DomainException::class);
        $this->service()->createFromJson($bob, $aliceProject, $this->exampleJson());
    }
}
