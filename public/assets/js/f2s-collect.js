/* F2S Pro — public form behaviour (progressive enhancement; the server re-checks everything). */
(function ($) {
    'use strict';
    var $form = $('form.f2s-collect');
    if (!$form.length || !window.F2SExpr) { return; }

    function inHidden(el) { return $(el).closest('[data-relevant].d-none').length > 0; }

    function collectValues() {
        var values = {};
        $form.find('input, select, textarea').each(function () {
            var name = this.name;
            if (!name || name.charAt(0) === '_' || inHidden(this)) { return; }
            var key = name.replace(/\[\]$/, '');
            if (this.type === 'radio') {
                if (!(key in values)) { values[key] = ''; }
                if (this.checked) { values[key] = this.value; }
            } else if (this.type === 'checkbox') {
                if (!(key in values)) { values[key] = ''; }
                if (this.checked) { values[key] = values[key] === '' ? this.value : values[key] + ' ' + this.value; }
            } else {
                values[key] = $.trim($(this).val() || '');
            }
        });
        return values;
    }

    function refresh() {
        // Two passes so chains (B depends on A, C on B) settle.
        for (var pass = 0; pass < 2; pass++) {
            var values = collectValues();
            $form.find('[data-relevant]').each(function () {
                var show = true;
                try { show = window.F2SExpr.isTrue($(this).attr('data-relevant'), values); } catch (e) { show = true; }
                $(this).toggleClass('d-none', !show);
                $(this).find('input, select, textarea').prop('disabled', !show);
            });
        }
        $form.find('[data-other-for]').each(function () {
            var name = $(this).attr('data-other-for');
            var $choice = $form.find('[name="' + name + '"], [name="' + name + '[]"]');
            var selected = $choice.filter(':checked').filter(function () { return this.value === 'other'; }).length > 0
                || ($choice.is('select') && $choice.val() === 'other');
            this.hidden = !selected;
        });
    }

    $form.on('change input', 'input, select, textarea', refresh);
    refresh();

    $form.on('submit', function () {
        var $btn = $form.find('button[data-sending]');
        $btn.prop('disabled', true).text($btn.data('sending'));
    });

    var $errors = $('#f2s-errors');
    if ($errors.length) {
        var $first = $form.find('.is-invalid, .invalid-feedback').first();
        ($first.length ? $first : $errors)[0].scrollIntoView({ block: 'center' });
        $errors.trigger('focus');
    }
})(jQuery);
