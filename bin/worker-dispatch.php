<?php

declare(strict_types=1);

/**
 * Processes the job queue within a time budget. Schedule it with the hosting
 * control panel (cPanel → Cron Jobs), every minute if allowed:
 *   * * * * * /usr/local/bin/php /home/USER/f2s/bin/worker-dispatch.php >/dev/null 2>&1
 * Overlapping runs are harmless: a MySQL named lock lets only one work.
 */

use App\Jobs\Dispatcher;

/** @var App\Core\App $app */
$app = require __DIR__ . '/bootstrap.php';
$budget = $app->config()->int('jobs.time_budget_seconds', 50);
if (isset($argv[1]) && ctype_digit($argv[1])) {
    $budget = (int) $argv[1];
}
@set_time_limit($budget + 15);

$stats = (new Dispatcher($app))->run($budget);
echo json_encode($stats) . PHP_EOL;
