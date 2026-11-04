<?php
/** @var array<string, mixed> $import */
/** @var array<string, mixed>|null $project */
/** @var array<string, mixed> $report */
/** @var list<array<string, mixed>> $questions */
/** @var int $questionCount */
/** @var list<string> $languages */
$title = t('bridge.report.title');
$counts = $report['counts'] ?? [];
$cats = ['transformed' => 'warning', 'lost' => 'danger', 'unsupported' => 'secondary', 'kept' => 'success'];
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= e(url('projects')) ?>"><?= e(t('nav.projects')) ?></a></li>
    <?php if ($project !== null): ?><li class="breadcrumb-item"><a href="<?= e(url('projects/' . $project['public_id'])) ?>"><?= e($project['name']) ?></a></li><?php endif; ?>
    <li class="breadcrumb-item active" aria-current="page"><?= e(t('bridge.report.title')) ?></li>
</ol></nav>
<h1 class="h3 mb-1"><?= e(t('bridge.report.title')) ?></h1>
<p class="text-muted"><?= e($import['filename']) ?> · <?= e(t('bridge.format_name.' . (string) $import['source_format'])) ?><?= $languages !== [] ? ' · ' . e(strtoupper(implode(', ', $languages))) : '' ?></p>

<div class="row g-3 mb-4">
    <?php foreach ($cats as $cat => $color): ?>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-<?= e($color) ?> h-100"><div class="card-body text-center py-3">
                <div class="fs-3 fw-bold text-<?= e($color) ?>"><?= e((int) ($counts[$cat] ?? 0)) ?></div>
                <div class="small"><?= e(t('bridge.report.' . $cat)) ?></div>
            </div></div>
        </div>
    <?php endforeach; ?>
</div>

<?php if (!empty($report['validation_errors'])): ?>
    <div class="alert alert-danger">
        <strong><?= e(t('bridge.report.invalid')) ?></strong>
        <ul class="small mb-0"><?php foreach ($report['validation_errors'] as $err): ?><li><code><?= e($err['path']) ?></code> — <?= e($err['message']) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-6">
        <?php foreach (['transformed', 'lost', 'unsupported'] as $cat): $entries = $report['entries'][$cat] ?? []; if ($entries === []) { continue; } ?>
            <div class="card shadow-sm mb-3">
                <div class="card-header text-<?= e($cats[$cat]) ?> fw-semibold"><?= e(t('bridge.report.' . $cat)) ?> (<?= e(count($entries)) ?>)</div>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($entries as $entry): ?>
                        <li class="list-group-item"><code><?= e($entry['item']) ?></code> — <?= e($entry['detail']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
        <?php if (($counts['transformed'] ?? 0) + ($counts['lost'] ?? 0) + ($counts['unsupported'] ?? 0) === 0): ?>
            <div class="alert alert-success"><?= e(t('bridge.report.clean')) ?></div>
        <?php endif; ?>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm mb-3">
            <div class="card-header"><?= e(t('bridge.report.preview', ['count' => $questionCount])) ?></div>
            <div class="table-responsive f2s-scroll">
                <table class="table table-sm mb-0 small align-middle">
                    <thead><tr><th><?= e(t('forms.q_name')) ?></th><th><?= e(t('forms.q_type')) ?></th><th><?= e(t('forms.q_label')) ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($questions as $q): ?>
                        <tr><td class="font-monospace"><?= e($q['path']) ?></td><td><span class="badge text-bg-light border"><?= e($q['type']) ?></span></td><td><?= e($q['label']) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($import['status'] === 'pending' || $import['status'] === 'failed'): ?>
    <div class="d-flex gap-2 justify-content-end mt-3">
        <form method="post" action="<?= e(url('imports/' . $import['public_id'] . '/cancel')) ?>"><?= csrf_field() ?><button class="btn btn-outline-secondary"><?= e(t('bridge.cancel')) ?></button></form>
        <?php if ($import['status'] === 'pending'): ?>
            <form method="post" action="<?= e(url('imports/' . $import['public_id'] . '/confirm')) ?>"><?= csrf_field() ?><button class="btn btn-f2s"><?= e(t('bridge.confirm')) ?></button></form>
        <?php endif; ?>
    </div>
    <p class="small text-muted text-end mt-2"><?= e(t('bridge.confirm_help')) ?></p>
<?php else: ?>
    <div class="alert alert-info mt-3"><?= e(t('bridge.status.' . (string) $import['status'])) ?></div>
<?php endif; ?>
