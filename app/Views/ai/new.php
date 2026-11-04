<?php
/** @var array<string, mixed> $project */
/** @var array{allowance: int, spent: int, granted: int, available: int, monthly: bool} $credits */
/** @var int $cost */
/** @var bool $configured */
/** @var array<string, mixed> $old */
/** @var array<string, string> $errors */
$title = t('ai.title');
$langs = (array) ($old['languages'] ?? ['fr']);
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= e(url('projects')) ?>"><?= e(t('nav.projects')) ?></a></li>
    <li class="breadcrumb-item"><a href="<?= e(url('projects/' . $project['public_id'])) ?>"><?= e($project['name']) ?></a></li>
    <li class="breadcrumb-item active" aria-current="page"><?= e(t('ai.title')) ?></li>
</ol></nav>
<div class="row g-4">
    <div class="col-lg-8">
        <h1 class="h3"><?= e(t('ai.title')) ?></h1>
        <p class="text-muted"><?= e(t('ai.intro')) ?></p>
        <?php if (!$configured): ?><div class="alert alert-warning"><?= e(t('ai.error.not_configured')) ?></div><?php endif; ?>
        <?php if (isset($errors['credits'])): ?><div class="alert alert-danger"><?= e(t($errors['credits'])) ?></div><?php endif; ?>
        <form method="post" action="<?= e(url('projects/' . $project['public_id'] . '/ai')) ?>" class="card shadow-sm">
            <div class="card-body">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="objective"><?= e(t('ai.field.objective')) ?></label>
                    <textarea class="form-control<?= isset($errors['objective']) ? ' is-invalid' : '' ?>" id="objective" name="objective" rows="3" maxlength="1500" required placeholder="<?= e(t('ai.placeholder.objective')) ?>"><?= e($old['objective'] ?? '') ?></textarea>
                    <?php if (isset($errors['objective'])): ?><div class="invalid-feedback"><?= e(t($errors['objective'])) ?></div><?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="audience"><?= e(t('ai.field.audience')) ?></label>
                    <input class="form-control" id="audience" name="audience" maxlength="1000" value="<?= e($old['audience'] ?? '') ?>" placeholder="<?= e(t('ai.placeholder.audience')) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="analyses"><?= e(t('ai.field.analyses')) ?></label>
                    <input class="form-control" id="analyses" name="analyses" maxlength="1000" value="<?= e($old['analyses'] ?? '') ?>" placeholder="<?= e(t('ai.placeholder.analyses')) ?>">
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label" for="minutes"><?= e(t('ai.field.minutes')) ?></label>
                        <input class="form-control" type="number" min="1" max="60" id="minutes" name="minutes" value="<?= e($old['minutes'] ?? '5') ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label" for="sampling"><?= e(t('editor.sampling')) ?></label>
                        <select class="form-select" id="sampling" name="sampling">
                            <?php foreach (['non_probabilistic', 'probabilistic', 'census', 'unknown'] as $s): ?>
                                <option value="<?= e($s) ?>"<?= ($old['sampling'] ?? '') === $s ? ' selected' : '' ?>><?= e(t('editor.sampling.' . $s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <span class="form-label d-block"><?= e(t('editor.languages')) ?></span>
                        <?php foreach (['fr' => 'Français', 'en' => 'English'] as $code => $name): ?>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="languages[]" id="lang-<?= e($code) ?>" value="<?= e($code) ?>"<?= in_array($code, $langs, true) ? ' checked' : '' ?>>
                                <label class="form-check-label" for="lang-<?= e($code) ?>"><?= e($name) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="context"><?= e(t('ai.field.context')) ?></label>
                    <textarea class="form-control" id="context" name="context" rows="2" maxlength="1500"><?= e($old['context'] ?? '') ?></textarea>
                </div>
                <p class="small text-muted"><?= e(t('ai.privacy')) ?></p>
                <button class="btn btn-f2s"<?= $configured ? '' : ' disabled' ?>><?= e(t('ai.submit', ['cost' => $cost])) ?></button>
            </div>
        </form>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header"><?= e(t('ai.credits')) ?></div>
            <div class="card-body">
                <div class="display-6"><?= e($credits['available']) ?></div>
                <div class="small text-muted"><?= e(t($credits['monthly'] ? 'ai.credits_monthly' : 'ai.credits_total', ['allowance' => $credits['allowance'] + $credits['granted'], 'spent' => $credits['spent']])) ?></div>
                <hr>
                <p class="small mb-0"><?= e(t('ai.how')) ?></p>
            </div>
        </div>
    </div>
</div>
