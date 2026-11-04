<?php

declare(strict_types=1);

namespace App\Controllers\Studio;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\FormRepository;
use App\Models\ProjectRepository;
use App\Services\AuditLogger;
use App\Services\FormInvalidException;
use App\Services\FormService;
use App\Services\FormValidator;
use App\Services\ImmutableVersionException;
use App\Services\QuotaExceededException;
use App\Services\UploadedJson;

final class FormController extends Controller
{
    public function create(Request $request): Response
    {
        $ctx = $this->authorize('form.create');
        $project = $this->project($ctx->organizationId(), $request);

        return $this->view('forms/create', ['project' => $project, 'json' => '', 'result' => null, 'error' => null]);
    }

    public function store(Request $request): Response
    {
        $ctx = $this->authorize('form.create');
        $project = $this->project($ctx->organizationId(), $request);
        try {
            $json = UploadedJson::fromRequest($request, 'file', 'json', FormValidator::MAX_JSON_BYTES);
        } catch (\InvalidArgumentException $e) {
            return $this->view('forms/create', ['project' => $project, 'json' => '', 'result' => null, 'error' => t($e->getMessage())], 422);
        }
        try {
            $ids = $this->service()->createFromJson($ctx, (int) $project['id'], $json);
        } catch (FormInvalidException $e) {
            return $this->view('forms/create', ['project' => $project, 'json' => $json, 'result' => $e->result, 'error' => t('forms.invalid')], 422);
        } catch (QuotaExceededException $e) {
            return $this->view('forms/create', [
                'project' => $project, 'json' => $json, 'result' => null,
                'error' => t('quota.questions_per_form', ['limit' => $e->limit, 'actual' => $e->actual]),
            ], 422);
        }
        $form = $this->app->db()->fetchOne('SELECT public_id FROM forms WHERE id = ? AND organization_id = ?', [$ids['form_id'], $ctx->organizationId()]);
        $this->flash('success', t('forms.created'));
        if ($ids['result']->warnings() !== []) {
            $this->flash('warning', t('forms.created_with_warnings', ['count' => count($ids['result']->warnings())]));
        }

        return $this->redirect('/forms/' . ($form['public_id'] ?? ''));
    }

    public function show(Request $request): Response
    {
        $ctx = $this->authorize('project.view');
        $repo = new FormRepository($this->app->db());
        $form = $this->form($ctx->organizationId(), $request);
        $versions = $repo->versions($ctx->organizationId(), (int) $form['id']);
        $current = $form['current_version_id'] !== null ? $repo->findVersion($ctx->organizationId(), (int) $form['current_version_id']) : null;
        $project = (new ProjectRepository($this->app->db()))->find($ctx->organizationId(), (int) $form['project_id']);

        return $this->view('forms/show', [
            'form' => $form,
            'project' => $project,
            'versions' => $versions,
            'current' => $current,
            'questions' => $current !== null ? $repo->questions($ctx->organizationId(), (int) $current['id']) : [],
            'schemaPretty' => $current !== null ? json_encode(json_decode((string) $current['schema_json']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '',
            'canEdit' => $ctx->can('form.edit'),
            'canPublish' => $ctx->can('form.publish'),
            'result' => null,
        ]);
    }

    public function updateDraft(Request $request): Response
    {
        $ctx = $this->authorize('form.edit');
        $form = $this->form($ctx->organizationId(), $request);
        $version = $this->version($ctx->organizationId(), (int) $form['id'], $request);
        $json = (string) $request->input('json', '');
        try {
            $this->service()->updateDraft($ctx, (int) $version['id'], $json);
            $this->flash('success', t('forms.draft_saved'));
        } catch (FormInvalidException $e) {
            $this->flash('danger', t('forms.invalid') . ' ' . implode(' · ', array_map(
                static fn(array $err): string => $err['path'] . ' : ' . $err['message'],
                array_slice($e->result->errors(), 0, 5),
            )));
        } catch (QuotaExceededException $e) {
            $this->flash('danger', t('quota.questions_per_form', ['limit' => $e->limit, 'actual' => $e->actual]));
        } catch (ImmutableVersionException) {
            $this->flash('danger', t('forms.immutable'));
        }

        return $this->redirect('/forms/' . $form['public_id']);
    }

    public function publish(Request $request): Response
    {
        $ctx = $this->authorize('form.publish');
        $form = $this->form($ctx->organizationId(), $request);
        $version = $this->version($ctx->organizationId(), (int) $form['id'], $request);
        try {
            $this->service()->publish($ctx, (int) $version['id']);
            $this->flash('success', t('forms.published'));
        } catch (ImmutableVersionException) {
            $this->flash('warning', t('forms.already_published'));
        }

        return $this->redirect('/forms/' . $form['public_id']);
    }

    public function newDraft(Request $request): Response
    {
        $ctx = $this->authorize('form.edit');
        $form = $this->form($ctx->organizationId(), $request);
        $this->service()->newDraft($ctx, (int) $form['id']);
        $this->flash('success', t('forms.draft_created'));

        return $this->redirect('/forms/' . $form['public_id']);
    }

    /** JSON export is always available, on every plan (MANIFEST §2). */
    public function exportJson(Request $request): Response
    {
        $ctx = $this->authorize('project.view');
        $form = $this->form($ctx->organizationId(), $request);
        $version = $this->version($ctx->organizationId(), (int) $form['id'], $request);
        $filename = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $form['form_key']) . '-v' . (int) $version['version_number'] . '.json';
        $pretty = (string) json_encode(json_decode((string) $version['schema_json']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return new Response($pretty, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function service(): FormService
    {
        return new FormService($this->app->db(), FormValidator::default(), new AuditLogger($this->app->db()));
    }

    /** @return array<string, mixed> */
    private function project(int $orgId, Request $request): array
    {
        return (new ProjectRepository($this->app->db()))->findByPublicId($orgId, $request->params['project'] ?? '')
            ?? throw new HttpException(404);
    }

    /** @return array<string, mixed> */
    private function form(int $orgId, Request $request): array
    {
        return (new FormRepository($this->app->db()))->findByPublicId($orgId, $request->params['form'] ?? '')
            ?? throw new HttpException(404);
    }

    /** @return array<string, mixed> */
    private function version(int $orgId, int $formId, Request $request): array
    {
        $version = (new FormRepository($this->app->db()))->findVersionByPublicId($orgId, $request->params['version'] ?? '');
        if ($version === null || (int) $version['form_id'] !== $formId) {
            throw new HttpException(404);
        }

        return $version;
    }
}
