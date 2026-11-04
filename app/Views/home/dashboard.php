<?php
/** @var App\Services\AuthContext $auth */
/** @var list<array<string, mixed>> $projects */
/** @var int $projectCount */
/** @var App\Services\PlanPolicy $plan */
/** @var array{available: int} $aiCredits */
$title = t('nav.dashboard');
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h3 mb-0"><?= e(t('dashboard.hello', ['name' => (string) $auth->user['name']])) ?></h1>
    <span class="badge text-bg-light border"><?= e(t('dashboard.plan')) ?> : <?= e($auth->organization['plan_name']) ?></span>
</div>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><?= e(t('dashboard.recent_projects')) ?> (<?= e($projectCount) ?>)</span>
                <a href="<?= e(url('projects')) ?>" class="btn btn-sm btn-outline-primary"><?= e(t('dashboard.all_projects')) ?></a>
            </div>
            <ul class="list-group list-group-flush">
                <?php if ($projects === []): ?>
                    <li class="list-group-item text-muted"><?= e(t('projects.empty')) ?></li>
                <?php endif; ?>
                <?php foreach ($projects as $p): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <a href="<?= e(url('projects/' . $p['public_id'])) ?>"><?= e($p['name']) ?></a>
                        <span class="text-muted small"><?= e(t('projects.form_count', ['count' => (int) $p['form_count']])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header"><?= e(t('dashboard.limits')) ?></div>
            <ul class="list-group list-group-flush small">
                <?php foreach (['questions_per_form', 'responses_per_campaign', 'active_campaigns', 'ai_credits'] as $key): ?>
                    <?php if ($plan->limit($key) !== null): ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <span><?= e(t('limit.' . $key)) ?></span><strong><?= e($plan->limit($key)) ?></strong>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
                <li class="list-group-item d-flex justify-content-between">
                    <span><?= e(t('limit.ai_credits_left')) ?></span><strong><?= e($aiCredits['available']) ?></strong>
                </li>
            </ul>
        </div>
    </div>
</div>
