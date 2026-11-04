<?php
/** @var array<string, mixed> $job */
/** @var string $mode */
$title = t('ai.job.title');
?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card shadow-sm" id="f2s-job" data-endpoint="<?= e(url('api/jobs/' . $job['id'])) ?>"
             data-steps="<?= e((string) json_encode(['plan' => t('ai.step.plan'), 'form' => t('ai.step.form'), 'validate' => t('ai.step.validate'), 'pending' => t('ai.step.pending'), 'done' => t('ai.step.done')], JSON_UNESCAPED_UNICODE)) ?>">
            <div class="card-body text-center py-5">
                <div class="spinner-border text-success mb-3" role="status" aria-hidden="true" data-spinner></div>
                <h1 class="h4"><?= e(t($mode === 'revise' ? 'ai.job.revising' : 'ai.job.generating')) ?></h1>
                <p class="text-muted mb-1" data-step aria-live="polite"><?= e(t('ai.step.pending')) ?></p>
                <p class="small text-muted"><?= e(t('ai.job.wait')) ?></p>
                <div class="alert alert-warning small d-none text-start" data-notice role="status"></div>
                <div class="alert alert-danger d-none text-start" data-error role="alert"></div>
                <a class="btn btn-outline-secondary d-none" data-back href="<?= e(url('projects')) ?>"><?= e(t('nav.projects')) ?></a>
            </div>
        </div>
    </div>
</div>
