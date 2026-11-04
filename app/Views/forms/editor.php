<?php
/** @var array<string, mixed> $form */
/** @var array<string, mixed> $version */
/** @var string $schemaJson */
$title = t('editor.title') . ' — ' . (string) $form['title'];
$keys = ['add_question', 'add_section', 'add_in_section', 'section', 'question', 'move_up', 'move_down', 'remove', 'remove_confirm', 'edit', 'done',
    'label', 'name', 'type', 'hint', 'required', 'choices', 'choice_value', 'choice_label', 'add_choice', 'allow_other', 'shared_list',
    'scale_min', 'scale_max', 'scale_min_label', 'scale_max_label', 'preset', 'rows', 'row_name', 'row_label', 'add_row', 'advanced',
    'relevant', 'relevant_help', 'constraint', 'constraint_help', 'constraint_message', 'role', 'appearance', 'page', 'settings',
    'form_title', 'form_id', 'languages', 'default_language', 'estimated_minutes', 'objective', 'sampling', 'add_language',
    'duplicate_name', 'empty', 'new_choice', 'new_row', 'new_question', 'new_section', 'starter_question'];
$strings = [];
foreach ($keys as $k) {
    $strings[$k] = t('editor.' . $k);
}
foreach (['text', 'long_text', 'integer', 'decimal', 'date', 'time', 'datetime', 'select_one', 'select_multiple', 'scale', 'matrix', 'phone', 'email', 'note', 'calculate'] as $type) {
    $strings['type_' . $type] = t('editor.type.' . $type);
}
foreach (['', 'outcome', 'segment', 'identifier', 'metadata', 'open_feedback', 'screening', 'none'] as $role) {
    $strings['role_' . $role] = t('editor.role.' . ($role === '' ? 'unset' : $role));
}
foreach (['unknown', 'probabilistic', 'non_probabilistic', 'census'] as $s) {
    $strings['sampling_' . $s] = t('editor.sampling.' . $s);
}
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= e(url('projects')) ?>"><?= e(t('nav.projects')) ?></a></li>
    <li class="breadcrumb-item"><a href="<?= e(url('forms/' . $form['public_id'])) ?>"><?= e($form['title']) ?></a></li>
    <li class="breadcrumb-item active" aria-current="page"><?= e(t('editor.title')) ?></li>
</ol></nav>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h1 class="h3 mb-0"><?= e(t('editor.title')) ?> <span class="badge text-bg-secondary fs-6 align-middle">v<?= e($version['version_number']) ?> · <?= e(t('status.draft')) ?></span></h1>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary" data-validate-form="#json" data-endpoint="<?= e(url('api/forms/validate')) ?>"><?= e(t('forms.validate')) ?></button>
        <button type="submit" class="btn btn-f2s" form="editor-form"><?= e(t('editor.save')) ?></button>
    </div>
</div>
<p class="text-muted small"><?= e(t('editor.help')) ?></p>
<div id="validation-result" aria-live="polite"></div>

<details class="card shadow-sm mb-3">
    <summary class="card-header"><?= e(t('ai.revise.title')) ?></summary>
    <form method="post" action="<?= e(url('forms/' . $form['public_id'] . '/ai-revise')) ?>" class="card-body">
        <?= csrf_field() ?>
        <label class="form-label small" for="instruction"><?= e(t('ai.revise.label')) ?></label>
        <textarea class="form-control mb-2" id="instruction" name="instruction" rows="2" maxlength="2000" placeholder="<?= e(t('ai.revise.placeholder')) ?>" required></textarea>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="small text-muted"><?= e(t('ai.revise.help')) ?></span>
            <button class="btn btn-outline-primary btn-sm"><?= e(t('ai.revise.submit')) ?></button>
        </div>
    </form>
</details>

<div id="f2s-editor" data-strings="<?= e((string) json_encode($strings, JSON_UNESCAPED_UNICODE)) ?>">
    <noscript><div class="alert alert-warning"><?= e(t('editor.noscript')) ?></div></noscript>
</div>

<form method="post" action="<?= e(url('forms/' . $form['public_id'] . '/versions/' . $version['public_id'])) ?>" id="editor-form" class="mt-4">
    <?= csrf_field() ?>
    <input type="hidden" name="return" value="edit">
    <details>
        <summary class="small text-muted"><?= e(t('editor.json_view')) ?></summary>
        <textarea class="form-control font-monospace small mt-2" id="json" name="json" rows="12" spellcheck="false"><?= e($schemaJson) ?></textarea>
    </details>
    <div class="d-flex justify-content-end gap-2 mt-3">
        <a class="btn btn-outline-secondary" href="<?= e(url('forms/' . $form['public_id'])) ?>"><?= e(t('editor.back')) ?></a>
        <button class="btn btn-f2s"><?= e(t('editor.save')) ?></button>
    </div>
</form>
