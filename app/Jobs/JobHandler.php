<?php

declare(strict_types=1);

namespace App\Jobs;

/**
 * A job handler must be resumable: on shared hosting a CRON run lasts at most
 * ~50 s, so long work is split in chunks. Check $ctx->shouldYield() and return
 * JobResult::yield($progress) to continue in the next run.
 */
interface JobHandler
{
    public function handle(JobContext $ctx): JobResult;
}
