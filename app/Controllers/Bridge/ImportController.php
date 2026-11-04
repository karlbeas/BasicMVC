<?php

declare(strict_types=1);

namespace App\Controllers\Bridge;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\ProjectRepository;
use App\Services\AuditLogger;
use App\Services\Bridge\ImportException;
use App\Services\Bridge\ImportService;
use App\Services\FormSchema;
use App\Services\QuotaExceededException;

final class ImportController extends Controller
{
    public function create(Request $request): Response
    {
        $ctx = $this->authorize('form.create');

        return $this->view('bridge/new', ['project' => $this->project($ctx->organizationId(), $request), 'error' => null]);
    }

    public function store(Request $request): Response
    {
        $ctx = $this->authorize('form.create');
        $project = $this->project($ctx->organizationId(), $request);
        $file = $request->file('file');
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
            return $this->view('bridge/new', ['project' => $project, 'error' => t('bridge.error.upload')], 422);
        }
        try {
            $result = $this->service()->analyze($ctx, (int) $project['id'], $file['tmp_name'], (string) ($file['name'] ?? 'import'), $this->app->lang()->locale());
        } catch (ImportException $e) {
            return $this->view('bridge/new', ['project' => $project, 'error' => t('bridge.error.' . $e->reason)], 422);
        }

        return $this->redirect('/imports/' . $result['public_id']);
    }

    public function show(Request $request): Response
    {
        $ctx = $this->authorize('form.create');
        $import = $this->service()->find($ctx->organizationId(), $request->params['import'] ?? '') ?? throw new HttpException(404);
        $schema = json_decode((string) ($import['schema_json'] ?? 'null'), true);
        $project = (new ProjectRepository($this->app->db()))->find($ctx->organizationId(), (int) $import['project_id']);

        return $this->view('bridge/show', [
            'import' => $import,
            'project' => $project,
            'report' => json_decode((string) $import['report_json'], true),
            'questions' => is_array($schema) ? FormSchema::questions($schema) : [],
            'questionCount' => is_array($schema) ? FormSchema::questionCount($schema) : 0,
            'languages' => is_array($schema) ? (array) ($schema['languages'] ?? []) : [],
        ]);
    }

    public function confirm(Request $request): Response
    {
        $ctx = $this->authorize('form.create');
        $publicId = $request->params['import'] ?? '';
        try {
            $formId = $this->service()->confirm($ctx, $publicId);
        } catch (QuotaExceededException $e) {
            $this->flash('danger', t('quota.questions_per_form', ['limit' => $e->limit, 'actual' => $e->actual]));

            return $this->redirect('/imports/' . $publicId);
        } catch (\DomainException) {
            throw new HttpException(404);
        }
        $form = $this->app->db()->fetchOne('SELECT public_id FROM forms WHERE id = ? AND organization_id = ?', [$formId, $ctx->organizationId()]);
        $this->flash('success', t('bridge.confirmed'));

        return $this->redirect('/forms/' . ($form['public_id'] ?? '') . '/edit');
    }

    public function cancel(Request $request): Response
    {
        $ctx = $this->authorize('form.create');
        $import = $this->service()->find($ctx->organizationId(), $request->params['import'] ?? '') ?? throw new HttpException(404);
        $this->service()->cancel($ctx, (string) $import['public_id']);
        $project = (new ProjectRepository($this->app->db()))->find($ctx->organizationId(), (int) $import['project_id']);
        $this->flash('info', t('bridge.cancelled'));

        return $this->redirect('/projects/' . ($project['public_id'] ?? ''));
    }

    private function service(): ImportService
    {
        return new ImportService($this->app->db(), new AuditLogger($this->app->db()));
    }

    /** @return array<string, mixed> */
    private function project(int $orgId, Request $request): array
    {
        return (new ProjectRepository($this->app->db()))->findByPublicId($orgId, $request->params['project'] ?? '') ?? throw new HttpException(404);
    }
}
