<?php
/** @var App\Services\FormValidationResult|null $result */
if ($result === null) {
    return;
}
?>
<?php if ($result->errors() !== []): ?>
    <div class="alert alert-danger">
        <strong><?= e(t('forms.errors', ['count' => count($result->errors())])) ?></strong>
        <ul class="mb-0 small">
            <?php foreach ($result->errors() as $err): ?>
                <li><code><?= e($err['path']) ?></code> — <?= e(t('validation.form.' . $err['code'])) ?> <span class="text-muted">(<?= e($err['message']) ?>)</span></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<?php if ($result->warnings() !== []): ?>
    <div class="alert alert-warning">
        <strong><?= e(t('forms.warnings', ['count' => count($result->warnings())])) ?></strong>
        <ul class="mb-0 small">
            <?php foreach ($result->warnings() as $w): ?>
                <li><code><?= e($w['path']) ?></code> — <?= e(t('validation.form.' . $w['code'])) ?> <span class="text-muted">(<?= e($w['message']) ?>)</span></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
