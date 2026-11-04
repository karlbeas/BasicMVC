<?php
/** @var list<array<string, mixed>> $projects */
/** @var array<string, string> $errors */
/** @var array<string, string> $old */
/** @var App\Services\AuthContext $auth */
$title = t('nav.projects');
?>
<h1 class="h3 mb-4"><?= e(t('nav.projects')) ?></h1>
<div class="row g-4">
    <div class="col-lg-8">
        <?php if ($projects === []): ?>
            <div class="alert alert-light border"><?= e(t('projects.empty')) ?></div>
        <?php else: ?>
            <div class="list-group shadow-sm">
                <?php foreach ($projects as $p): ?>
                    <a class="list-group-item list-group-item-action" href="<?= e(url('projects/' . $p['public_id'])) ?>">
                        <div class="d-flex justify-content-between">
                            <strong><?= e($p['name']) ?></strong>
                            <span class="small text-muted"><?= e(t('projects.form_count', ['count' => (int) $p['form_count']])) ?></span>
                        </div>
                        <?php if (!empty($p['description'])): ?>
                            <div class="small text-muted text-truncate"><?= e($p['description']) ?></div>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($auth->can('project.create')): ?>
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header"><?= e(t('projects.new')) ?></div>
                <div class="card-body">
                    <form method="post" action="<?= e(url('projects')) ?>">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label for="name" class="form-label"><?= e(t('field.project_name')) ?></label>
                            <input class="form-control<?= isset($errors['name']) ? ' is-invalid' : '' ?>" id="name" name="name" maxlength="150" value="<?= e($old['name'] ?? '') ?>" required>
                            <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= e(t($errors['name'])) ?></div><?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label"><?= e(t('field.description')) ?></label>
                            <textarea class="form-control<?= isset($errors['description']) ? ' is-invalid' : '' ?>" id="description" name="description" rows="3" maxlength="2000"><?= e($old['description'] ?? '') ?></textarea>
                        </div>
                        <button class="btn btn-f2s w-100"><?= e(t('projects.create')) ?></button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
