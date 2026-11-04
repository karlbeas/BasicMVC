<?php
/** @var string $appUrl */
/** @var string $root */
/** @var string $phpBinary */
/** @var string $cronToken */
/** @var int $migrations */
$title = t('install.done.title');
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="alert alert-success">
            <h1 class="h4"><?= e(t('install.done.title')) ?></h1>
            <p class="mb-0"><?= e(t('install.done.text', ['count' => $migrations])) ?></p>
        </div>
        <div class="card shadow-sm mb-4">
            <div class="card-header"><?= e(t('install.done.cron_title')) ?></div>
            <div class="card-body">
                <p class="small"><?= e(t('install.done.cron_cli')) ?></p>
                <pre class="bg-light border p-2 small mb-3">* * * * * <?= e($phpBinary) ?> <?= e($root) ?>/bin/worker-dispatch.php &gt;/dev/null 2&gt;&amp;1</pre>
                <p class="small"><?= e(t('install.done.cron_url')) ?></p>
                <pre class="bg-light border p-2 small mb-2"><?= e($appUrl) ?>/cron/<?= e($cronToken) ?></pre>
                <p class="small text-danger mb-0"><?= e(t('install.done.secret')) ?></p>
            </div>
        </div>
        <a class="btn btn-f2s btn-lg" href="<?= e($appUrl) ?>/register"><?= e(t('install.done.next')) ?></a>
    </div>
</div>
