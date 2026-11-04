<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Jobs\Dispatcher;
use App\Jobs\JobQueue;

/**
 * GET /api/jobs/{job}: status of one of the organization's jobs. If it is still
 * pending, the request runs it right away within the PHP time limit (ADR 0002 §4)
 * so the user does not wait for the next CRON tick; the CRON remains the safety net.
 */
final class JobApiController extends Controller
{
    /** "App\\Services\\Ai\\AiException: claude-haiku-5-5 : HTTP 503 — …" → "claude-haiku-5-5 : HTTP 503 — …" */
    public static function readableError(string $error): string
    {
        $error = trim((string) preg_replace('/^[A-Za-z\\\\]+Exception:\s*/', '', $error));

        return mb_substr($error, 0, 200);
    }

    public function show(Request $request): Response
    {
        $ctx = $this->authorize('project.view');
        $id = $request->params['job'] ?? '';
        $queue = new JobQueue($this->app->db());
        $job = ctype_digit($id) ? $queue->find((int) $id) : null;
        if ($job === null || (int) $job['organization_id'] !== $ctx->organizationId()) {
            throw new HttpException(404);
        }
        if ($job['status'] === 'pending' && $job['runner'] === 'php' && $job['available_at'] <= gmdate('Y-m-d H:i:s')) {
            $this->app->session()->close(); // do not block the user's other requests
            ignore_user_abort(true);
            $maxExec = (int) ini_get('max_execution_time');
            $budget = $maxExec > 0 ? max(5, $maxExec - 5) : $this->app->config()->int('jobs.time_budget_seconds', 50);
            @set_time_limit($budget + 10);
            (new Dispatcher($this->app))->runOne((int) $job['id'], min($budget, $this->app->config()->int('jobs.time_budget_seconds', 50)));
            $job = $queue->find((int) $job['id']) ?? $job;
        }
        $result = $job['result'] !== null ? json_decode((string) $job['result'], true) : null;
        $progress = $job['progress'] !== null ? json_decode((string) $job['progress'], true) : null;
        $lastError = self::readableError((string) ($job['error'] ?? ''));
        $data = [
            'status' => $job['status'],
            'step' => is_array($progress) ? ($progress['step'] ?? null) : null,
            'attempts' => (int) $job['attempts'],
            'max_attempts' => (int) $job['max_attempts'],
            'elapsed' => max(0, time() - (int) strtotime($job['created_at'] . ' UTC')),
            'error' => is_array($result) ? ($result['error'] ?? null) : ($job['status'] === 'failed' ? 'failed' : null),
            'notice' => null,
        ];
        $step = $data['step'];
        if ($step === 'parts' && is_array($progress)) {
            $current = min(count((array) ($progress['done'] ?? [])) + 1, count((array) ($progress['todo'] ?? [])));
            $data['step_text'] = t(($progress['mode'] ?? '') === 'revise' ? 'ai.step.revise_parts' : 'ai.step.sections', ['current' => $current, 'total' => count((array) ($progress['todo'] ?? []))]);
        } elseif (is_string($step) && in_array($step, ['plan', 'validate'], true)) {
            $data['step_text'] = t('ai.step.' . $step);
        } else {
            $data['step_text'] = t('ai.step.pending');
        }
        if (is_array($result) && isset($result['form_public_id'])) {
            $data['redirect'] = $this->app->url('forms/' . $result['form_public_id'] . '/edit');
        }
        if ($data['error'] !== null) {
            $data['message'] = t('ai.error.' . $data['error'], ['limit' => (int) ($result['limit'] ?? 0), 'actual' => (int) ($result['actual'] ?? 0)]);
            $detail = is_array($result) && is_string($result['detail'] ?? null) ? $result['detail'] : ($data['error'] === 'failed' ? $lastError : '');
            if ($detail !== '') {
                $data['message'] .= ' (' . $detail . ')';
            }
        } elseif (in_array($job['status'], ['pending', 'running'], true)) {
            $runningFor = $job['reserved_at'] !== null ? time() - (int) strtotime($job['reserved_at'] . ' UTC') : 0;
            if ($job['status'] === 'running' && $runningFor > 90) {
                $data['notice'] = t('ai.job.long_running', ['seconds' => $runningFor]);
            } elseif ($job['status'] === 'pending' && (int) $job['attempts'] > 0 && $lastError !== '') {
                $data['notice'] = t('ai.job.retrying', ['attempt' => (int) $job['attempts'] + 1, 'max' => (int) $job['max_attempts'], 'error' => $lastError]);
            }
        }

        return $this->json($data);
    }
}
