<?php
/** @var array<string, mixed> $campaign */
/** @var array<string, mixed>|null $form */
/** @var array<string, mixed>|null $version */
/** @var string $publicUrl */
/** @var int $count */
/** @var string|null $closedReason */
/** @var list<array{name: string, label: string}> $columns */
/** @var list<array<string, mixed>> $rows */
/** @var int $page */
/** @var bool $canManage */
$title = (string) $campaign['title'];
$base = 'campaigns/' . $campaign['public_id'];
$shown = array_slice($columns, 0, 6);
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= e(url('projects')) ?>"><?= e(t('nav.projects')) ?></a></li>
    <?php if ($form !== null): ?><li class="breadcrumb-item"><a href="<?= e(url('forms/' . $form['public_id'])) ?>"><?= e($form['title']) ?></a></li><?php endif; ?>
    <li class="breadcrumb-item active" aria-current="page"><?= e($campaign['title']) ?></li>
</ol></nav>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1"><?= e($campaign['title']) ?></h1>
        <div class="small text-muted">v<?= e($version['version_number'] ?? '?') ?> · <?= e(t('campaigns.created_at')) ?> <?= e($campaign['created_at']) ?> UTC</div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge fs-6 text-bg-<?= $closedReason === null ? 'success' : 'secondary' ?>"><?= e($closedReason === null ? t('campaigns.state.open') : t('collect.closed.' . $closedReason)) ?></span>
        <?php if ($canManage): ?>
            <form method="post" action="<?= e(url($base . '/status')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="status" value="<?= $campaign['status'] === 'open' ? 'closed' : 'open' ?>">
                <button class="btn btn-outline-secondary"><?= e(t($campaign['status'] === 'open' ? 'campaigns.close' : 'campaigns.reopen')) ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card shadow-sm h-100">
            <div class="card-header"><?= e(t('campaigns.share')) ?></div>
            <div class="card-body">
                <label class="form-label small" for="public-url"><?= e(t('campaigns.public_link')) ?></label>
                <div class="input-group mb-3">
                    <input class="form-control font-monospace" id="public-url" value="<?= e($publicUrl) ?>" readonly>
                    <button class="btn btn-outline-secondary" type="button" data-copy="#public-url" data-copied="<?= e(t('campaigns.copied')) ?>"><?= e(t('campaigns.copy')) ?></button>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-success" href="<?= e('https://wa.me/?text=' . rawurlencode($campaign['title'] . ' — ' . $publicUrl)) ?>" target="_blank" rel="noopener"><?= e(t('campaigns.whatsapp')) ?></a>
                    <a class="btn btn-outline-secondary" href="<?= e($publicUrl) ?>" target="_blank" rel="noopener"><?= e(t('campaigns.open_form')) ?></a>
                </div>
                <dl class="row small mt-3 mb-0">
                    <dt class="col-sm-5"><?= e(t('campaigns.responses')) ?></dt>
                    <dd class="col-sm-7"><strong><?= e($count) ?></strong><?= $campaign['response_limit'] !== null ? ' / ' . e($campaign['response_limit']) : '' ?></dd>
                    <dt class="col-sm-5"><?= e(t('campaigns.closes_at')) ?></dt>
                    <dd class="col-sm-7"><?= e($campaign['closes_at'] !== null ? $campaign['closes_at'] . ' UTC' : t('campaigns.no_end')) ?></dd>
                    <dt class="col-sm-5"><?= e(t('campaigns.access')) ?></dt>
                    <dd class="col-sm-7"><?= e(t($campaign['access_code_hash'] !== null ? 'campaigns.access_code' : 'campaigns.access_public')) ?></dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm h-100 text-center">
            <div class="card-header"><?= e(t('campaigns.qr')) ?></div>
            <div class="card-body">
                <img src="<?= e(url($base . '/qr')) ?>" alt="<?= e(t('campaigns.qr_alt')) ?>" class="img-fluid f2s-qr mb-2" width="220" height="220">
                <div><a class="btn btn-sm btn-outline-secondary" href="<?= e(url($base . '/qr?download=1')) ?>"><?= e(t('campaigns.qr_download')) ?></a></div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><?= e(t('campaigns.latest')) ?></span>
        <span class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-primary" href="<?= e(url($base . '/export/csv')) ?>"><?= e(t('campaigns.export_csv')) ?></a>
            <a class="btn btn-sm btn-outline-primary" href="<?= e(url($base . '/export/json')) ?>"><?= e(t('campaigns.export_json')) ?></a>
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle small">
            <thead><tr>
                <th><?= e(t('campaigns.submitted_at')) ?></th>
                <?php foreach ($shown as $c): ?><th class="font-monospace"><?= e($c['name']) ?></th><?php endforeach; ?>
                <?php if (count($columns) > count($shown)): ?><th class="text-muted">+<?= e(count($columns) - count($shown)) ?></th><?php endif; ?>
            </tr></thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="<?= count($shown) + 2 ?>" class="text-muted"><?= e(t('campaigns.no_responses')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): $data = json_decode((string) $row['data_json'], true) ?: []; ?>
                <tr>
                    <td class="text-nowrap"><?= e($row['submitted_at']) ?></td>
                    <?php foreach ($shown as $c): $v = $data[$c['name']] ?? ''; ?>
                        <td class="text-truncate f2s-cell"><?= e(is_array($v) ? implode(' ', $v) : (string) $v) ?></td>
                    <?php endforeach; ?>
                    <?php if (count($columns) > count($shown)): ?><td></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($count > 50): ?>
        <div class="card-footer d-flex justify-content-between small">
            <?php if ($page > 1): ?><a href="<?= e(url($base . '?page=' . ($page - 1))) ?>">&larr; <?= e(t('common.previous')) ?></a><?php else: ?><span></span><?php endif; ?>
            <span class="text-muted"><?= e(t('common.page', ['page' => $page])) ?></span>
            <?php if ($page * 50 < $count): ?><a href="<?= e(url($base . '?page=' . ($page + 1))) ?>"><?= e(t('common.next')) ?> &rarr;</a><?php else: ?><span></span><?php endif; ?>
        </div>
    <?php endif; ?>
</div>
