<?php
/** @var string $content */
/** @var string|null $title */
/** @var bool|null $watermark */
$lang = isset($lang) && is_string($lang) ? $lang : (isset($locale) && is_string($locale) ? $locale : 'fr');
?>
<!doctype html>
<html lang="<?= e($lang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e(isset($title) && $title !== '' ? $title : 'F2S Pro') ?></title>
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="bg-light">
<main class="container py-3 py-md-4 f2s-public">
    <?= $content ?>
    <?php if (!empty($watermark)): ?>
        <p class="text-center small text-muted mt-4"><?= e(t('collect.watermark')) ?> <strong>F2S Pro</strong></p>
    <?php endif; ?>
</main>
<script src="<?= e(asset('vendor/jquery.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('js/f2s-expr.js')) ?>"></script>
<script src="<?= e(asset('js/f2s-collect.js')) ?>"></script>
</body>
</html>
