<?php

declare(strict_types=1);

namespace App\Jobs\Handlers;

use App\Jobs\JobContext;
use App\Jobs\JobHandler;
use App\Jobs\JobResult;

/**
 * Health-check job, also the reference example of a resumable handler:
 * payload {"steps": n} processes n steps, yielding when time runs out.
 */
final class PingHandler implements JobHandler
{
    public function handle(JobContext $ctx): JobResult
    {
        $steps = max(1, (int) ($ctx->payload['steps'] ?? 1));
        $done = (int) ($ctx->progress['done'] ?? 0);
        while ($done < $steps) {
            if ($ctx->shouldYield(1.0)) {
                return JobResult::yield(['done' => $done]);
            }
            ++$done;
        }

        return JobResult::done(['pong' => true, 'steps' => $done, 'at' => gmdate('c')]);
    }
}
