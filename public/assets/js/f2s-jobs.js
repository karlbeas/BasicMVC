/* F2S Pro — follows a queued AI job; the status call may run the job itself (ADR 0002 §4). */
jQuery(function ($) {
    'use strict';
    var $box = $('#f2s-job');
    if (!$box.length) { return; }
    var steps = $box.data('steps') || {}, tries = 0;
    function poll() {
        tries++;
        $.ajax({ url: $box.data('endpoint'), dataType: 'json', headers: { Accept: 'application/json' } }).done(function (res) {
            if (res.redirect) { $box.find('[data-step]').text(steps.done || ''); window.location.href = res.redirect; return; }
            if (res.error) {
                $box.find('[data-spinner]').addClass('d-none');
                $box.find('[data-error]').removeClass('d-none').text(res.message || res.error);
                $box.find('[data-back]').removeClass('d-none');
                return;
            }
            $box.find('[data-step]').text(steps[res.step] || steps.pending || '');
            setTimeout(poll, tries < 20 ? 2000 : 5000);
        }).fail(function () { setTimeout(poll, 5000); });
    }
    poll();
});
