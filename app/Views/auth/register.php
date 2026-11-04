<?php
/** @var array<string, string> $old */
/** @var array<string, string> $errors */
$title = t('auth.register.title');
$field = static function (string $name, string $type, string $autocomplete) use ($old, $errors): string {
    $invalid = isset($errors[$name]);
    $html = '<div class="mb-3"><label for="' . e($name) . '" class="form-label">' . e(t('field.' . $name)) . '</label>';
    $html .= '<input type="' . e($type) . '" class="form-control' . ($invalid ? ' is-invalid' : '') . '" id="' . e($name) . '" name="' . e($name) . '"';
    $html .= $type !== 'password' ? ' value="' . e($old[$name] ?? '') . '"' : '';
    $html .= ' autocomplete="' . e($autocomplete) . '" required>';
    if ($invalid) {
        $html .= '<div class="invalid-feedback">' . e(t($errors[$name])) . '</div>';
    }

    return $html . '</div>';
};
?>
<div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-1"><?= e(t('auth.register.title')) ?></h1>
                <p class="text-muted small mb-3"><?= e(t('auth.register.free_plan')) ?></p>
                <form method="post" action="<?= e(url('register')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <?= $field('name', 'text', 'name') ?>
                    <?= $field('email', 'email', 'email') ?>
                    <?= $field('password', 'password', 'new-password') ?>
                    <div class="form-text mb-3 mt-n2"><?= e(t('auth.password_hint')) ?></div>
                    <?= $field('organization', 'text', 'organization') ?>
                    <div class="form-check mb-3">
                        <input class="form-check-input<?= isset($errors['accept_terms']) ? ' is-invalid' : '' ?>" type="checkbox" value="1" id="accept_terms" name="accept_terms">
                        <label class="form-check-label small" for="accept_terms"><?= e(t('auth.accept_terms')) ?></label>
                        <?php if (isset($errors['accept_terms'])): ?>
                            <div class="invalid-feedback"><?= e(t($errors['accept_terms'])) ?></div>
                        <?php endif; ?>
                    </div>
                    <button class="btn btn-f2s w-100"><?= e(t('auth.register.submit')) ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
