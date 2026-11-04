<?php
/** @var array<string, mixed> $project */
/** @var string|null $error */
$title = t('bridge.title');
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= e(url('projects')) ?>"><?= e(t('nav.projects')) ?></a></li>
    <li class="breadcrumb-item"><a href="<?= e(url('projects/' . $project['public_id'])) ?>"><?= e($project['name']) ?></a></li>
    <li class="breadcrumb-item active" aria-current="page"><?= e(t('bridge.title')) ?></li>
</ol></nav>
<div class="row g-4">
    <div class="col-lg-7">
        <h1 class="h3"><?= e(t('bridge.title')) ?></h1>
        <p class="text-muted"><?= e(t('bridge.intro')) ?></p>
        <?php if ($error !== null): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post" action="<?= e(url('projects/' . $project['public_id'] . '/import')) ?>" enctype="multipart/form-data" class="card shadow-sm">
            <div class="card-body">
                <?= csrf_field() ?>
                <label class="form-label fw-semibold" for="file"><?= e(t('bridge.file')) ?></label>
                <input class="form-control mb-2" type="file" id="file" name="file" accept=".xlsx,.csv,.json" required>
                <div class="form-text mb-3"><?= e(t('bridge.file_help')) ?></div>
                <button class="btn btn-f2s"><?= e(t('bridge.analyze')) ?></button>
            </div>
        </form>
    </div>
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header"><?= e(t('bridge.formats')) ?></div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item"><strong>XLSForm (.xlsx)</strong> — <?= e(t('bridge.format.xlsform')) ?></li>
                <li class="list-group-item"><strong>Excel / CSV</strong> — <?= e(t('bridge.format.list')) ?></li>
                <li class="list-group-item"><strong>JSON</strong> — <?= e(t('bridge.format.json')) ?></li>
                <li class="list-group-item text-muted"><?= e(t('bridge.format.later')) ?></li>
            </ul>
        </div>
    </div>
</div>
