/* F2S Pro — progressive enhancement only (the app works without JS). */
(function ($) {
    'use strict';

    var csrf = $('meta[name="csrf-token"]').attr('content');

    function escapeHtml(text) {
        return $('<div>').text(text == null ? '' : String(text)).html();
    }

    function renderList(items, cls, title) {
        if (!items || !items.length) { return ''; }
        var html = '<div class="alert alert-' + cls + ' small"><strong>' + escapeHtml(title) + '</strong><ul class="mb-0">';
        items.forEach(function (it) {
            html += '<li><code>' + escapeHtml(it.path) + '</code> — ' + escapeHtml(it.message) + '</li>';
        });
        return html + '</ul></div>';
    }

    // Load a .json file into the target textarea.
    $(document).on('change', 'input[type=file][data-json-target]', function () {
        var file = this.files && this.files[0];
        var $target = $($(this).data('json-target'));
        if (!file || !window.FileReader) { return; }
        var reader = new FileReader();
        reader.onload = function (e) { $target.val(e.target.result); };
        reader.readAsText(file, 'UTF-8');
    });

    // Validate the form JSON without saving (POST /api/forms/validate).
    $(document).on('click', '[data-validate-form]', function () {
        var $btn = $(this);
        var $out = $('#validation-result');
        var lang = document.documentElement.lang === 'en' ? 'en' : 'fr';
        var labels = lang === 'en'
            ? { ok: 'Valid form', errors: 'Errors', warnings: 'Warnings', questions: 'questions', limit: 'plan limit', fail: 'Validation failed' }
            : { ok: 'Formulaire valide', errors: 'Erreurs', warnings: 'Avertissements', questions: 'questions', limit: 'limite de l’offre', fail: 'Échec de la validation' };
        $btn.prop('disabled', true);
        $.ajax({
            url: $btn.data('endpoint'),
            method: 'POST',
            contentType: 'application/json',
            data: $($btn.data('validate-form')).val(),
            headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
            dataType: 'json'
        }).done(function (res) {
            var html = '';
            if (res.valid) {
                var info = res.question_count + ' ' + labels.questions;
                if (res.question_limit !== null && res.question_limit !== undefined) {
                    info += ' / ' + res.question_limit + ' (' + labels.limit + ')';
                }
                html += '<div class="alert alert-' + (res.within_plan === false ? 'warning' : 'success') + ' small">' + escapeHtml(labels.ok) + ' — ' + escapeHtml(info) + '</div>';
            }
            html += renderList(res.errors, 'danger', labels.errors);
            html += renderList(res.warnings, 'warning', labels.warnings);
            $out.html(html);
        }).fail(function () {
            $out.html('<div class="alert alert-danger small">' + escapeHtml(labels.fail) + '</div>');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    // Copy a value to the clipboard (campaign link).
    $(document).on('click', '[data-copy]', function () {
        var $btn = $(this), $input = $($btn.data('copy'));
        var done = function () { var old = $btn.text(); $btn.text($btn.data('copied')); setTimeout(function () { $btn.text(old); }, 1500); };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText($input.val()).then(done);
        } else {
            $input.trigger('select'); document.execCommand('copy'); done();
        }
    });

    // Confirmation for irreversible actions (publish).
    $(document).on('submit', 'form[data-confirm]', function (e) {
        if (!window.confirm($(this).data('confirm'))) { e.preventDefault(); }
    });
})(jQuery);
