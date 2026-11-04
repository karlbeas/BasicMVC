<?php
/** @var string $title */
/** @var array<string, mixed> $campaign */
/** @var string|null $error */
?>
<div class="mx-auto f2s-form-col py-4">
    <h1 class="h3 mb-3"><?= e($title) ?></h1>
    <form method="post" action="<?= e(url('f/' . $campaign['token'] . '/code')) ?>" class="card shadow-sm">
        <div class="card-body">
            <?= csrf_field() ?>
            <label for="access_code" class="form-label"><?= e(t('collect.code_label')) ?></label>
            <input class="form-control<?= $error !== null ? ' is-invalid' : '' ?>" id="access_code" name="access_code" autocomplete="off" required autofocus>
            <?php if ($error !== null): ?><div class="invalid-feedback"><?= e($error) ?></div><?php endif; ?>
            <button class="btn btn-f2s w-100 mt-3"><?= e(t('collect.code_submit')) ?></button>
        </div>
    </form>
</div>
