<?php
/** @var array<string, mixed> $project */
/** @var list<array<string, mixed>> $forms */
/** @var App\Services\AuthContext $auth */
$title = (string) $project['name'];
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= e(url('projects')) ?>"><?= e(t('nav.projects')) ?></a></li>
    <li class="breadcrumb-item active" aria-current="page"><?= e($project['name']) ?></li>
</ol></nav>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1"><?= e($project['name']) ?></h1>
        <?php if (!empty($project['description'])): ?><p class="text-muted mb-0"><?= e($project['description']) ?></p><?php endif; ?>
    </div>
    <?php if ($auth->can('form.create')): ?>
        <a class="btn btn-f2s" href="<?= e(url('projects/' . $project['public_id'] . '/forms/new')) ?>"><?= e(t('forms.new')) ?></a>
    <?php endif; ?>
</div>
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead><tr>
                <th><?= e(t('forms.title')) ?></th><th><?= e(t('forms.version')) ?></th>
                <th><?= e(t('forms.status')) ?></th><th class="text-end"><?= e(t('forms.questions')) ?></th>
            </tr></thead>
            <tbody>
            <?php if ($forms === []): ?>
                <tr><td colspan="4" class="text-muted"><?= e(t('forms.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($forms as $f): ?>
                <tr>
                    <td><a href="<?= e(url('forms/' . $f['public_id'])) ?>"><?= e($f['title']) ?></a><div class="small text-muted font-monospace"><?= e($f['form_key']) ?></div></td>
                    <td>v<?= e($f['version_number']) ?></td>
                    <td><span class="badge text-bg-<?= $f['status'] === 'published' ? 'success' : 'secondary' ?>"><?= e(t('status.' . (string) $f['status'])) ?></span></td>
                    <td class="text-end"><?= e($f['question_count']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
