<?php
/** @var array<string, mixed> $form */
/** @var array<string, mixed>|null $project */
/** @var list<array<string, mixed>> $versions */
/** @var array<string, mixed>|null $current */
/** @var list<array<string, mixed>> $questions */
/** @var string $schemaPretty */
/** @var bool $canEdit */
/** @var bool $canPublish */
/** @var list<array<string, mixed>> $campaigns */
/** @var list<array<string, mixed>> $publishedVersions */
$title = (string) $form['title'];
$isDraft = $current !== null && $current['status'] === 'draft';
$base = 'forms/' . $form['public_id'];
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= e(url('projects')) ?>"><?= e(t('nav.projects')) ?></a></li>
    <?php if ($project !== null): ?>
        <li class="breadcrumb-item"><a href="<?= e(url('projects/' . $project['public_id'])) ?>"><?= e($project['name']) ?></a></li>
    <?php endif; ?>
    <li class="breadcrumb-item active" aria-current="page"><?= e($form['title']) ?></li>
</ol></nav>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1"><?= e($form['title']) ?></h1>
        <div class="small text-muted font-monospace"><?= e($form['form_key']) ?></div>
    </div>
    <?php if ($current !== null): ?>
        <div class="d-flex gap-2 flex-wrap">
            <span class="badge fs-6 text-bg-<?= $isDraft ? 'secondary' : 'success' ?> align-self-center">
                v<?= e($current['version_number']) ?> · <?= e(t('status.' . (string) $current['status'])) ?>
            </span>
            <a class="btn btn-outline-secondary" href="<?= e(url($base . '/versions/' . $current['public_id'] . '/json')) ?>"><?= e(t('forms.export_json')) ?></a>
            <?php if ($isDraft && $canEdit): ?>
                <a class="btn btn-primary" href="<?= e(url($base . '/edit')) ?>"><?= e(t('editor.open')) ?></a>
            <?php endif; ?>
            <?php if ($isDraft && $canPublish): ?>
                <form method="post" action="<?= e(url($base . '/versions/' . $current['public_id'] . '/publish')) ?>" data-confirm="<?= e(t('forms.publish_confirm')) ?>">
                    <?= csrf_field() ?><button class="btn btn-success"><?= e(t('forms.publish')) ?></button>
                </form>
            <?php elseif (!$isDraft && $canEdit): ?>
                <form method="post" action="<?= e(url($base . '/drafts')) ?>">
                    <?= csrf_field() ?><button class="btn btn-outline-primary"><?= e(t('forms.new_draft')) ?></button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($current !== null && !$isDraft): ?>
    <div class="alert alert-info small"><?= e(t('forms.immutable_notice')) ?></div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-header"><?= e(t('campaigns.title')) ?></div>
    <div class="card-body">
        <?php if ($campaigns === []): ?>
            <p class="text-muted small"><?= e(t('campaigns.none')) ?></p>
        <?php else: ?>
            <div class="list-group mb-3">
                <?php foreach ($campaigns as $c): ?>
                    <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="<?= e(url('campaigns/' . $c['public_id'])) ?>">
                        <span><?= e($c['title']) ?> <span class="small text-muted">· v<?= e($c['version_number']) ?></span></span>
                        <span>
                            <span class="badge text-bg-<?= $c['status'] === 'open' ? 'success' : 'secondary' ?>"><?= e(t('campaigns.status.' . (string) $c['status'])) ?></span>
                            <span class="badge text-bg-light border"><?= e(t('campaigns.response_count', ['count' => (int) $c['response_count']])) ?></span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($canPublish && $publishedVersions !== []): ?>
            <details<?= $campaigns === [] ? ' open' : '' ?>>
                <summary class="fw-semibold mb-2"><?= e(t('campaigns.new')) ?></summary>
                <form method="post" action="<?= e(url($base . '/versions/' . $publishedVersions[0]['public_id'] . '/campaigns')) ?>" class="row g-2 align-items-end">
                    <?= csrf_field() ?>
                    <div class="col-md-4">
                        <label class="form-label small" for="c-title"><?= e(t('campaigns.field.title')) ?></label>
                        <input class="form-control" id="c-title" name="title" maxlength="255" placeholder="<?= e($form['title']) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small" for="c-closes"><?= e(t('campaigns.field.closes_at')) ?></label>
                        <input class="form-control" type="date" id="c-closes" name="closes_at">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small" for="c-limit"><?= e(t('campaigns.field.response_limit')) ?></label>
                        <input class="form-control" type="number" min="1" id="c-limit" name="response_limit">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small" for="c-code"><?= e(t('campaigns.field.access_code')) ?></label>
                        <input class="form-control" id="c-code" name="access_code" maxlength="64" autocomplete="off">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-f2s w-100"><?= e(t('campaigns.open')) ?></button>
                    </div>
                    <div class="col-12 form-text"><?= e(t('campaigns.new_help', ['version' => (int) $publishedVersions[0]['version_number']])) ?></div>
                </form>
            </details>
        <?php elseif ($publishedVersions === []): ?>
            <p class="small text-muted mb-0"><?= e(t('campaigns.publish_first')) ?></p>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header"><?= e(t('forms.questions')) ?> (<?= e($current['question_count'] ?? 0) ?>)</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th><?= e(t('forms.q_name')) ?></th><th><?= e(t('forms.q_type')) ?></th><th><?= e(t('forms.q_label')) ?></th><th><?= e(t('forms.q_role')) ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($questions as $q): ?>
                        <tr>
                            <td class="font-monospace small"><?= e($q['path']) ?></td>
                            <td><span class="badge text-bg-light border"><?= e($q['type']) ?></span></td>
                            <td class="small"><?= e($q['label']) ?></td>
                            <td class="small text-muted"><?= e($q['analysis_role'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card shadow-sm mb-4">
            <div class="card-header"><?= e(t('forms.versions')) ?></div>
            <ul class="list-group list-group-flush small">
                <?php foreach ($versions as $v): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>v<?= e($v['version_number']) ?> · <?= e(t('status.' . (string) $v['status'])) ?></span>
                        <span class="text-muted"><?= e($v['published_at'] ?? $v['created_at']) ?> UTC</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php if ($current !== null && $isDraft && $canEdit): ?>
            <form method="post" action="<?= e(url($base . '/versions/' . $current['public_id'])) ?>" class="card shadow-sm">
                <div class="card-header"><?= e(t('forms.edit_draft')) ?></div>
                <div class="card-body">
                    <?= csrf_field() ?>
                    <div id="validation-result" aria-live="polite"></div>
                    <textarea class="form-control font-monospace small mb-2" id="json" name="json" rows="16" spellcheck="false"><?= e($schemaPretty) ?></textarea>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-validate-form="#json" data-endpoint="<?= e(url('api/forms/validate')) ?>"><?= e(t('forms.validate')) ?></button>
                        <button class="btn btn-f2s btn-sm"><?= e(t('forms.save_draft')) ?></button>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <details class="card shadow-sm"><summary class="card-header"><?= e(t('forms.schema')) ?></summary>
                <pre class="card-body small mb-0 schema-pre"><?= e($schemaPretty) ?></pre>
            </details>
        <?php endif; ?>
    </div>
</div>
