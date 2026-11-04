<?php

declare(strict_types=1);

use App\Core\Env;

return [
    // Seconds a single dispatcher run may work. Keep it below the host's
    // max_execution_time and below the CRON interval (1 min → 50 s).
    'time_budget_seconds' => Env::int('JOBS_TIME_BUDGET', 50),
    // A job still "running" after this delay was killed by the host: re-queue it.
    // Longest legitimate step: an AI call of AI_TIMEOUT_SECONDS (120 s) in the CRON.
    'stale_after_seconds' => Env::int('JOBS_STALE_AFTER', 300),
    // Secret for the HTTP trigger /cron/{token} (hosts without CLI cron, or an
    // external pinger). Empty = endpoint disabled. Minimum 32 characters.
    'http_token' => Env::get('CRON_HTTP_TOKEN', ''),
    'handlers' => [
        'system.ping' => App\Jobs\Handlers\PingHandler::class,
        'ai.form_generate' => App\Jobs\Handlers\FormGenerationHandler::class,
        'ai.form_revise' => App\Jobs\Handlers\FormGenerationHandler::class,
    ],
];
