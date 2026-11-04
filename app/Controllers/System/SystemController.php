<?php

declare(strict_types=1);

namespace App\Controllers\System;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Jobs\Dispatcher;

final class SystemController extends Controller
{
    /** Minimal health check for uptime monitors; reveals nothing about the setup. */
    public function health(Request $request): Response
    {
        try {
            $this->app->db()->fetchValue('SELECT 1');
            $db = 'ok';
        } catch (\Throwable) {
            $db = 'unavailable';
        }

        return $this->json(['status' => $db === 'ok' ? 'ok' : 'degraded', 'db' => $db], $db === 'ok' ? 200 : 503);
    }

    /**
     * HTTP trigger of the job dispatcher, for hosts without CLI cron or an
     * external pinger (cron-job.org…). Disabled unless CRON_HTTP_TOKEN is set.
     */
    public function cron(Request $request): Response
    {
        $expected = $this->app->config()->string('jobs.http_token');
        $given = $request->params['token'] ?? '';
        if (strlen($expected) < 32 || !hash_equals($expected, $given)) {
            throw new HttpException(404);
        }
        ignore_user_abort(true);
        $maxExec = (int) ini_get('max_execution_time');
        $budget = $this->app->config()->int('jobs.time_budget_seconds', 50);
        if ($maxExec > 0) {
            $budget = min($budget, max(5, $maxExec - 5));
        }
        $migrated = $this->app->schemaUpgrader()->upgrade();
        $stats = (new Dispatcher($this->app))->run($budget);

        return $this->json(['ok' => true, 'migrations_applied' => $migrated] + $stats);
    }
}
