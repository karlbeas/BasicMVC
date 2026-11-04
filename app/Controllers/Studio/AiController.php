<?php

declare(strict_types=1);

namespace App\Controllers\Studio;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Jobs\JobQueue;
use App\Models\FormRepository;
use App\Models\ProjectRepository;
use App\Services\Ai\AiGateway;
use App\Services\Ai\CreditLedger;
use App\Services\AuditLogger;

/** AI Studio pages: describe a survey, follow the generation, revise a draft. */
final class AiController extends Controller
{
    public function create(Request $request): Response
    {
        $ctx = $this->authorize('form.create');
        $project = $this->project($ctx->organizationId(), $request);

        return $this->view('ai/new', [
            'project' => $project,
            'credits' => (new CreditLedger($this->app->db()))->summary($ctx->organizationId()),
            'cost' => $this->app->config()->int('ai.credits.form_generation', 5),
            'configured' => (new AiGateway($this->app->db(), $this->app->config()))->isConfigured(),
            'old' => ['languages' => [$this->app->lang()->locale()], 'minutes' => '5', 'sampling' => 'non_probabilistic'],
            'errors' => [],
        ]);
    }

    public function store(Request $request): Response
    {
        $ctx = $this->authorize('form.create');
        $orgId = $ctx->organizationId();
        $project = $this->project($orgId, $request);
        $languages = array_values(array_intersect(['fr', 'en'], (array) $request->input('languages', [])));
        $old = [
            'objective' => mb_substr($request->string('objective'), 0, 1500),
            'audience' => mb_substr($request->string('audience'), 0, 1000),
            'analyses' => mb_substr($request->string('analyses'), 0, 1000),
            'context' => mb_substr($request->string('context'), 0, 1500),
            'minutes' => (string) max(1, min(60, (int) $request->string('minutes', '5'))),
            'sampling' => in_array($request->string('sampling'), ['probabilistic', 'non_probabilistic', 'census', 'unknown'], true) ? $request->string('sampling') : 'unknown',
            'languages' => $languages === [] ? ['fr'] : $languages,
        ];
        $errors = [];
        if (mb_strlen($old['objective']) < 15) {
            $errors['objective'] = 'ai.error.objective';
        }
        $cost = $this->app->config()->int('ai.credits.form_generation', 5);
        $credits = new CreditLedger($this->app->db());
        if ($errors === [] && $credits->available($orgId) < $cost) {
            $errors['credits'] = 'ai.error.insufficient_credits';
        }
        if ($errors !== []) {
            return $this->view('ai/new', [
                'project' => $project, 'credits' => $credits->summary($orgId), 'cost' => $cost,
                'configured' => (new AiGateway($this->app->db(), $this->app->config()))->isConfigured(),
                'old' => $old, 'errors' => $errors,
            ], 422);
        }
        $jobId = (new JobQueue($this->app->db()))->push('ai.form_generate', [
            'mode' => 'generate',
            'user_id' => $ctx->userId(),
            'project_id' => (int) $project['id'],
            'brief' => $old,
        ], $orgId, 0, 3);
        (new AuditLogger($this->app->db()))->log($orgId, $ctx->userId(), 'ai.form_generate.requested', 'job', $jobId, [], $request->ip());

        return $this->redirect('/ai/jobs/' . $jobId);
    }

    /** POST /forms/{form}/ai-revise */
    public function revise(Request $request): Response
    {
        $ctx = $this->authorize('form.edit');
        $orgId = $ctx->organizationId();
        $form = (new FormRepository($this->app->db()))->findByPublicId($orgId, $request->params['form'] ?? '') ?? throw new HttpException(404);
        $instruction = mb_substr($request->string('instruction'), 0, 2000);
        $cost = $this->app->config()->int('ai.credits.form_revision', 1);
        if (mb_strlen($instruction) < 5) {
            $this->flash('danger', t('ai.error.instruction'));

            return $this->redirect('/forms/' . $form['public_id'] . '/edit');
        }
        if ((new CreditLedger($this->app->db()))->available($orgId) < $cost) {
            $this->flash('danger', t('ai.error.insufficient_credits'));

            return $this->redirect('/forms/' . $form['public_id'] . '/edit');
        }
        $jobId = (new JobQueue($this->app->db()))->push('ai.form_revise', [
            'mode' => 'revise', 'user_id' => $ctx->userId(), 'form_id' => (int) $form['id'], 'instruction' => $instruction,
        ], $orgId, 0, 3);

        return $this->redirect('/ai/jobs/' . $jobId);
    }

    public function job(Request $request): Response
    {
        $ctx = $this->authorize('project.view');
        $job = $this->ownJob($ctx->organizationId(), $request);

        return $this->view('ai/job', [
            'job' => $job,
            'mode' => str_ends_with((string) $job['type'], 'revise') ? 'revise' : 'generate',
            // Without a working CRON the job only progresses while this page is open.
            'cronAlive' => ($age = \App\Jobs\Dispatcher::heartbeatAge($this->app)) !== null && $age < 180,
            'scripts' => ['js/f2s-jobs.js'],
        ]);
    }

    /** @return array<string, mixed> */
    private function ownJob(int $orgId, Request $request): array
    {
        $id = $request->params['job'] ?? '';
        $job = ctype_digit($id) ? (new JobQueue($this->app->db()))->find((int) $id) : null;
        if ($job === null || (int) $job['organization_id'] !== $orgId || !str_starts_with((string) $job['type'], 'ai.')) {
            throw new HttpException(404);
        }

        return $job;
    }

    /** @return array<string, mixed> */
    private function project(int $orgId, Request $request): array
    {
        return (new ProjectRepository($this->app->db()))->findByPublicId($orgId, $request->params['project'] ?? '') ?? throw new HttpException(404);
    }
}
