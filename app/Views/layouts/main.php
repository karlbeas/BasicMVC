<?php
/** @var string $content */
/** @var App\Services\AuthContext|null $auth */
/** @var list<array{type: string, message: string}> $flashes */
/** @var string $locale */
$pageTitle = isset($title) && is_string($title) ? $title . ' — F2S Pro' : 'F2S Pro — Field, Form & Survey Pro';
?>
<!doctype html>
<html lang="<?= e($locale) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-md navbar-dark bg-f2s">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= e(url('')) ?>">F2S <span class="fw-light">Pro</span></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="<?= e(t('nav.menu')) ?>">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav me-auto">
                <?php if ($auth !== null): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('')) ?>"><?= e(t('nav.dashboard')) ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('projects')) ?>"><?= e(t('nav.projects')) ?></a></li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav align-items-md-center">
                <li class="nav-item">
                    <a class="nav-link<?= $locale === 'fr' ? ' active' : '' ?>" href="<?= e(url('lang/fr')) ?>">FR</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $locale === 'en' ? ' active' : '' ?>" href="<?= e(url('lang/en')) ?>">EN</a>
                </li>
                <?php if ($auth !== null): ?>
                    <li class="nav-item ms-md-3 text-white-50 small"><?= e($auth->organization['name']) ?> · <?= e(t('role.' . $auth->role)) ?></li>
                    <li class="nav-item ms-md-2">
                        <form method="post" action="<?= e(url('logout')) ?>" class="d-inline">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-light"><?= e(t('nav.logout')) ?></button>
                        </form>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('login')) ?>"><?= e(t('nav.login')) ?></a></li>
                    <li class="nav-item"><a class="btn btn-sm btn-light ms-md-2" href="<?= e(url('register')) ?>"><?= e(t('nav.register')) ?></a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main class="container py-4 flex-grow-1">
    <?php foreach ($flashes as $flash): ?>
        <div class="alert alert-<?= e(in_array($flash['type'], ['success', 'warning', 'danger', 'info'], true) ? $flash['type'] : 'info') ?> alert-dismissible fade show" role="alert">
            <?= e($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= e(t('common.close')) ?>"></button>
        </div>
    <?php endforeach; ?>
    <?= $content ?>
</main>

<footer class="border-top py-3 small text-muted">
    <div class="container d-flex justify-content-between flex-wrap gap-2">
        <span>F2S Pro — <?= e(t('app.slogan')) ?></span>
        <span>&copy; <?= e(gmdate('Y')) ?></span>
    </div>
</footer>
<script src="<?= e(asset('vendor/jquery.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
