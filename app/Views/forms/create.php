<?php
/** @var array<string, mixed> $project */
/** @var string $json */
/** @var App\Services\FormValidationResult|null $result */
/** @var string|null $error */
$title = t('forms.new');
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= e(url('projects')) ?>"><?= e(t('nav.projects')) ?></a></li>
    <li class="breadcrumb-item"><a href="<?= e(url('projects/' . $project['public_id'])) ?>"><?= e($project['name']) ?></a></li>
    <li class="breadcrumb-item active" aria-current="page"><?= e(t('forms.new')) ?></li>
</ol></nav>
<h1 class="h3"><?= e(t('forms.new')) ?></h1>
<p class="text-muted"><?= e(t('forms.new_help')) ?></p>
<?php if ($error !== null): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php require __DIR__ . '/_result.php'; ?>
<div id="validation-result" aria-live="polite"></div>
<form method="post" action="<?= e(url('projects/' . $project['public_id'] . '/forms')) ?>" enctype="multipart/form-data" class="card shadow-sm">
    <div class="card-body">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label for="file" class="form-label"><?= e(t('forms.upload_json')) ?></label>
            <input class="form-control" type="file" id="file" name="file" accept=".json,application/json" data-json-target="#json">
        </div>
        <div class="mb-3">
            <label for="json" class="form-label"><?= e(t('forms.paste_json')) ?></label>
            <textarea class="form-control font-monospace small" id="json" name="json" rows="18" spellcheck="false"><?= e($json) ?></textarea>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-outline-secondary" data-validate-form="#json" data-endpoint="<?= e(url('api/forms/validate')) ?>"><?= e(t('forms.validate')) ?></button>
            <button class="btn btn-f2s"><?= e(t('forms.save')) ?></button>
        </div>
    </div>
</form>
