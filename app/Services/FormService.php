<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\FormRepository;
use App\Models\ProjectRepository;

/**
 * Form lifecycle: create (version 1 draft) → edit draft → publish (immutable)
 * → new draft from the published version.
 */
final class FormService
{
    private FormRepository $forms;
    private ProjectRepository $projects;

    public function __construct(
        private Database $db,
        private FormValidator $validator,
        private AuditLogger $audit,
    ) {
        $this->forms = new FormRepository($db);
        $this->projects = new ProjectRepository($db);
    }

    /**
     * @return array{form_id: int, version_id: int, result: FormValidationResult}
     *
     * @throws FormInvalidException|QuotaExceededException|\DomainException
     */
    public function createFromJson(AuthContext $ctx, int $projectId, string $json, string $source = 'json'): array
    {
        $orgId = $ctx->organizationId();
        if ($this->projects->find($orgId, $projectId) === null) {
            throw new \DomainException('Project not found in organization');
        }
        [$form, $result, $canonical, $count] = $this->validated($orgId, $json);

        $ids = $this->db->transaction(function () use ($ctx, $orgId, $projectId, $form, $canonical, $count, $source): array {
            $settings = $form['settings'];
            $title = FormSchema::label($settings['title'], (string) $form['default_language']);
            $formId = $this->forms->create($orgId, $projectId, $ctx->userId(), (string) $settings['form_id'], $title);
            $versionId = $this->forms->createVersion($orgId, $formId, $ctx->userId(), 1, $canonical, $count, $source);
            $this->forms->replaceQuestions($orgId, $versionId, FormSchema::questions($form));
            $this->forms->setCurrentVersion($orgId, $formId, $versionId);
            $this->audit->log($orgId, $ctx->userId(), 'form.created', 'form', $formId, ['version_id' => $versionId, 'source' => $source]);

            return ['form_id' => $formId, 'version_id' => $versionId];
        });

        return $ids + ['result' => $result];
    }

    /** @throws FormInvalidException|QuotaExceededException|ImmutableVersionException */
    public function updateDraft(AuthContext $ctx, int $versionId, string $json): FormValidationResult
    {
        $orgId = $ctx->organizationId();
        $version = $this->forms->findVersion($orgId, $versionId) ?? throw new \DomainException('Version not found');
        if ($version['status'] !== 'draft') {
            throw new ImmutableVersionException('Published versions cannot be modified; create a new draft');
        }
        [$form, $result, $canonical, $count] = $this->validated($orgId, $json);

        $this->db->transaction(function () use ($ctx, $orgId, $versionId, $form, $canonical, $count): void {
            if (!$this->forms->updateDraft($orgId, $versionId, $canonical, $count)) {
                throw new ImmutableVersionException('Version was published meanwhile');
            }
            $this->forms->replaceQuestions($orgId, $versionId, FormSchema::questions($form));
            $this->audit->log($orgId, $ctx->userId(), 'form.draft_updated', 'form_version', $versionId);
        });

        return $result;
    }

    /** Freezes a draft. settings.version is stamped with the version number. */
    public function publish(AuthContext $ctx, int $versionId): void
    {
        $orgId = $ctx->organizationId();
        $version = $this->forms->findVersion($orgId, $versionId) ?? throw new \DomainException('Version not found');
        if ($version['status'] !== 'draft') {
            throw new ImmutableVersionException('Version already published');
        }
        // Work on objects (not arrays) so empty JSON objects survive the round-trip.
        $form = json_decode((string) $version['schema_json'], false, 64, JSON_THROW_ON_ERROR);
        $form->settings->version = (string) $version['version_number'];
        $canonical = self::encode($form);

        $this->db->transaction(function () use ($ctx, $orgId, $version, $versionId, $canonical): void {
            if (!$this->forms->markPublished($orgId, $versionId, $canonical)) {
                throw new ImmutableVersionException('Version already published');
            }
            $this->forms->setCurrentVersion($orgId, (int) $version['form_id'], $versionId);
            $this->audit->log($orgId, $ctx->userId(), 'form.published', 'form_version', $versionId);
        });
    }

    /** New draft copied from the latest version, to edit a published form. */
    public function newDraft(AuthContext $ctx, int $formId): int
    {
        $orgId = $ctx->organizationId();
        $versions = $this->forms->versions($orgId, $formId);
        if ($versions === []) {
            throw new \DomainException('Form not found');
        }
        $latest = $versions[0];
        if ($latest['status'] === 'draft') {
            return (int) $latest['id'];
        }
        $full = $this->forms->findVersion($orgId, (int) $latest['id']) ?? throw new \DomainException('Version not found');
        $object = json_decode((string) $full['schema_json'], false, 64, JSON_THROW_ON_ERROR);
        unset($object->settings->version);
        $canonical = self::encode($object);
        $form = json_decode($canonical, true, 64, JSON_THROW_ON_ERROR);

        return $this->db->transaction(function () use ($ctx, $orgId, $formId, $form, $canonical, $full): int {
            $number = $this->forms->nextVersionNumber($orgId, $formId);
            $id = $this->forms->createVersion($orgId, $formId, $ctx->userId(), $number, $canonical, (int) $full['question_count'], 'copy');
            $this->forms->replaceQuestions($orgId, $id, FormSchema::questions($form));
            $this->forms->setCurrentVersion($orgId, $formId, $id);
            $this->audit->log($orgId, $ctx->userId(), 'form.draft_created', 'form_version', $id, ['from_version' => (int) $full['id']]);

            return $id;
        });
    }

    /**
     * @return array{0: array<string, mixed>, 1: FormValidationResult, 2: string, 3: int}
     *
     * @throws FormInvalidException|QuotaExceededException
     */
    private function validated(int $orgId, string $json): array
    {
        $result = $this->validator->validateJson($json);
        if (!$result->isValid() || $result->form === null) {
            throw new FormInvalidException($result);
        }
        $form = $result->form;
        $count = FormSchema::questionCount($form);
        PlanPolicy::forOrganization($this->db, $orgId)->assertWithin('questions_per_form', $count);

        // Store the decoded-then-encoded form: stable formatting for hashing,
        // while keeping the content exactly as submitted (no silent conversion).
        $object = json_decode($json, false, 64, JSON_THROW_ON_ERROR);
        $canonical = self::encode($object instanceof \stdClass ? $object : new \stdClass());

        return [$form, $result, $canonical, $count];
    }

    private static function encode(\stdClass $form): string
    {
        return json_encode($form, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
