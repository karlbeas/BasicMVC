<?php
/** @var string $title */
/** @var string $description */
/** @var int $estimated */
/** @var array<string, mixed> $campaign */
/** @var list<string> $languages */
/** @var string $lang */
/** @var string $fieldsHtml */
/** @var int $errorCount */
/** @var string $submission */
/** @var string $started */
$action = url('f/' . $campaign['token']);
?>
<div class="mx-auto f2s-form-col">
    <header class="mb-4">
        <?php if (count($languages) > 1): ?>
            <nav class="text-end small mb-2" aria-label="<?= e(t('collect.language')) ?>">
                <?php foreach ($languages as $l): ?>
                    <a class="ms-2<?= $l === $lang ? ' fw-bold text-decoration-none' : '' ?>" href="<?= e($action . '?lang=' . rawurlencode($l)) ?>" hreflang="<?= e($l) ?>"><?= e(strtoupper($l)) ?></a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
        <h1 class="h3"><?= e($title) ?></h1>
        <?php if ($description !== ''): ?><p class="text-muted"><?= nl2br(e($description)) ?></p><?php endif; ?>
        <?php if ($estimated > 0): ?><p class="small text-muted mb-0"><?= e(t('collect.estimated', ['minutes' => $estimated])) ?></p><?php endif; ?>
    </header>

    <?php if ($errorCount > 0): ?>
        <div class="alert alert-danger" role="alert" tabindex="-1" id="f2s-errors"><?= e(t('collect.has_errors', ['count' => $errorCount])) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e($action) ?>" class="f2s-collect" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="_submission" value="<?= e($submission) ?>">
        <input type="hidden" name="_started" value="<?= e($started) ?>">
        <input type="hidden" name="lang" value="<?= e($lang) ?>">
        <div class="f2s-hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <?= $fieldsHtml ?>
        <button class="btn btn-f2s btn-lg w-100" data-sending="<?= e(t('collect.sending')) ?>"><?= e(t('collect.submit')) ?></button>
        <p class="small text-muted mt-3 mb-0"><?= e(t('collect.privacy')) ?></p>
    </form>
</div>
