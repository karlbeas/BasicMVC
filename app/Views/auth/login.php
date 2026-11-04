<?php
/** @var string $email */
/** @var string|null $error */
$title = t('auth.login.title');
?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-3"><?= e(t('auth.login.title')) ?></h1>
                <?php if ($error !== null): ?>
                    <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
                <?php endif; ?>
                <form method="post" action="<?= e(url('login')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="email" class="form-label"><?= e(t('field.email')) ?></label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= e($email) ?>" required autocomplete="email" autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label"><?= e(t('field.password')) ?></label>
                        <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
                    </div>
                    <button class="btn btn-f2s w-100"><?= e(t('auth.login.submit')) ?></button>
                </form>
                <p class="mt-3 mb-0 small text-center"><?= e(t('auth.login.no_account')) ?> <a href="<?= e(url('register')) ?>"><?= e(t('nav.register')) ?></a></p>
            </div>
        </div>
    </div>
</div>
