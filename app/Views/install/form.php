<?php
/** @var list<array{key: string, ok: bool, detail: string}> $requirements */
/** @var bool $ready */
/** @var array<string, string> $old */
/** @var array<string, string> $errors */
/** @var string|null $error */
$title = t('install.title');
$input = static function (string $name, string $type = 'text', string $help = '') use ($old, $errors): string {
    $invalid = isset($errors[$name]);
    $html = '<div class="mb-3"><label class="form-label" for="' . e($name) . '">' . e(t('install.field.' . $name)) . '</label>';
    $html .= '<input class="form-control' . ($invalid ? ' is-invalid' : '') . '" type="' . e($type) . '" id="' . e($name) . '" name="' . e($name) . '"';
    $html .= $type === 'password' ? ' autocomplete="new-password"' : ' value="' . e($old[$name] ?? '') . '"';
    $html .= $name === 'db_password' ? '>' : ' required>';
    if ($invalid) {
        $html .= '<div class="invalid-feedback">' . e(t($errors[$name])) . '</div>';
    }
    if ($help !== '') {
        $html .= '<div class="form-text">' . e($help) . '</div>';
    }

    return $html . '</div>';
};
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <h1 class="h3 mb-1"><?= e(t('install.title')) ?></h1>
        <p class="text-muted"><?= e(t('install.intro')) ?></p>

        <div class="card shadow-sm mb-4">
            <div class="card-header"><?= e(t('install.requirements')) ?></div>
            <ul class="list-group list-group-flush small">
                <?php foreach ($requirements as $r): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span><?= e(t('install.req.' . $r['key'])) ?> <code><?= e($r['detail']) ?></code></span>
                        <span class="badge text-bg-<?= $r['ok'] ? 'success' : 'danger' ?>"><?= e($r['ok'] ? 'OK' : t('install.req.missing')) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <?php if ($error !== null): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

        <?php if ($ready): ?>
            <form method="post" action="<?= e(url('install')) ?>" class="card shadow-sm" autocomplete="off">
                <div class="card-body">
                    <?= csrf_field() ?>
                    <?= $input('install_key', 'text', t('install.help.install_key')) ?>
                    <hr>
                    <h2 class="h6"><?= e(t('install.database')) ?></h2>
                    <p class="small text-muted"><?= e(t('install.help.database')) ?></p>
                    <div class="row">
                        <div class="col-md-8"><?= $input('db_host') ?></div>
                        <div class="col-md-4"><?= $input('db_port', 'number') ?></div>
                    </div>
                    <?= $input('db_name') ?>
                    <?= $input('db_user') ?>
                    <?= $input('db_password', 'password') ?>
                    <hr>
                    <?= $input('app_url', 'url', t('install.help.app_url')) ?>
                    <button class="btn btn-f2s w-100"><?= e(t('install.submit')) ?></button>
                </div>
            </form>
        <?php else: ?>
            <div class="alert alert-warning"><?= e(t('install.requirements_failed')) ?></div>
        <?php endif; ?>
    </div>
</div>
