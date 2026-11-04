<?php

declare(strict_types=1);

namespace App\Controllers\Control;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\FormRepository;
use App\Models\OrganizationRepository;
use App\Models\ProjectRepository;
use App\Services\AuditLogger;

final class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $ctx = $this->authorize('project.view');

        return $this->view('projects/index', [
            'projects' => (new ProjectRepository($this->app->db()))->all($ctx->organizationId()),
            'errors' => [],
            'old' => [],
        ]);
    }

    public function store(Request $request): Response
    {
        $ctx = $this->authorize('project.create');
        $name = $request->string('name');
        $description = $request->string('description');
        $errors = [];
        if ($name === '' || mb_strlen($name) > 150) {
            $errors['name'] = 'validation.project_name';
        }
        if (mb_strlen($description) > 2000) {
            $errors['description'] = 'validation.too_long';
        }
        $repo = new ProjectRepository($this->app->db());
        if ($errors !== []) {
            return $this->view('projects/index', [
                'projects' => $repo->all($ctx->organizationId()),
                'errors' => $errors,
                'old' => ['name' => $name, 'description' => $description],
            ], 422);
        }
        $workspaces = (new OrganizationRepository($this->app->db()))->workspaces($ctx->organizationId());
        if ($workspaces === []) {
            throw new HttpException(409);
        }
        $id = $repo->create($ctx->organizationId(), (int) $workspaces[0]['id'], $ctx->userId(), $name, $description);
        (new AuditLogger($this->app->db()))->log($ctx->organizationId(), $ctx->userId(), 'project.created', 'project', $id, [], $request->ip());
        $project = $repo->find($ctx->organizationId(), $id);
        $this->flash('success', t('projects.created'));

        return $this->redirect('/projects/' . ($project['public_id'] ?? ''));
    }

    public function show(Request $request): Response
    {
        $ctx = $this->authorize('project.view');
        $project = (new ProjectRepository($this->app->db()))->findByPublicId($ctx->organizationId(), $request->params['project'] ?? '')
            ?? throw new HttpException(404);

        return $this->view('projects/show', [
            'project' => $project,
            'forms' => (new FormRepository($this->app->db()))->forProject($ctx->organizationId(), (int) $project['id']),
        ]);
    }
}
