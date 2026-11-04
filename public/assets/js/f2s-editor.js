/* F2S Pro — visual form editor. Edits the F2S Schema v1 JSON held in #json;
 * the server validates it on save (FormValidator), the editor never bypasses that. */
jQuery(function ($) {
    'use strict';
    var $root = $('#f2s-editor'), $json = $('#json');
    if (!$root.length) { return; }
    var S = $root.data('strings') || {};
    var form;
    try { form = JSON.parse($json.val()); } catch (e) { return; }
    form.choice_lists = form.choice_lists || {};
    form.items = form.items || [];
    var open = null; // item object currently expanded

    var TYPES = ['text', 'long_text', 'integer', 'decimal', 'date', 'time', 'datetime', 'select_one', 'select_multiple', 'scale', 'matrix', 'phone', 'email', 'note', 'calculate'];
    var ROLES = ['', 'outcome', 'segment', 'identifier', 'metadata', 'open_feedback', 'screening', 'none'];
    var WITH_CHOICES = ['select_one', 'select_multiple', 'matrix'];

    function langs() { return form.languages && form.languages.length ? form.languages : ['fr']; }
    function dl() { return form.default_language || langs()[0]; }
    function txt(label) { if (!label) { return ''; } return label[dl()] || label[Object.keys(label)[0]] || ''; }
    function slug(s) {
        var x = String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').substring(0, 40);
        return /^[a-z_]/.test(x) ? x : (x ? 'q_' + x : '');
    }
    function allNames(items, out) {
        out = out || [];
        (items || []).forEach(function (it) {
            out.push(it.name);
            (it.rows || []).forEach(function (r) { out.push(r.name); });
            if (it.items) { allNames(it.items, out); }
        });
        return out;
    }
    function uniqueName(base) {
        var names = allNames(form.items).map(function (n) { return String(n).toLowerCase(); });
        base = base || 'question';
        if (names.indexOf(base) < 0) { return base; }
        for (var i = 2; ; i++) { if (names.indexOf(base + '_' + i) < 0) { return base + '_' + i; } }
    }
    function listUsers(list) {
        var n = 0;
        (function walk(items) { (items || []).forEach(function (it) { if (it.choices === list) { n++; } if (it.items) { walk(it.items); } }); })(form.items);
        return n;
    }
    function sync() { $json.val(JSON.stringify(form, null, 2)); markDuplicates(); }
    function el(tag, attrs, text) { var $e = $('<' + tag + '>'); if (attrs) { $e.attr(attrs); } if (text !== undefined) { $e.text(text); } return $e; }
    function field(labelText, $input, help) {
        var id = 'f' + Math.random().toString(36).slice(2, 9);
        $input.attr('id', id);
        var $g = el('div', { 'class': 'mb-2' }).append(el('label', { 'class': 'form-label small mb-1', 'for': id }, labelText), $input);
        if (help) { $g.append(el('div', { 'class': 'form-text' }, help)); }
        return $g;
    }
    function textInput(value, onChange, attrs) {
        return el('input', $.extend({ type: 'text', 'class': 'form-control form-control-sm' }, attrs || {})).val(value || '').on('input', function () { onChange($(this).val()); sync(); });
    }
    function langInputs(obj, key, labelText, onChange) {
        var $wrap = el('div');
        langs().forEach(function (l) {
            var cur = (obj[key] || {})[l] || '';
            $wrap.append(field(labelText + (langs().length > 1 ? ' (' + l.toUpperCase() + ')' : ''), textInput(cur, function (v) {
                obj[key] = obj[key] || {};
                if (v === '') { delete obj[key][l]; } else { obj[key][l] = v; }
                if ($.isEmptyObject(obj[key])) { delete obj[key]; }
                if (onChange) { onChange(l, v); }
            })));
        });
        return $wrap;
    }
    function select(options, value, onChange, labels) {
        var $s = el('select', { 'class': 'form-select form-select-sm' });
        options.forEach(function (o, i) { $s.append(el('option', { value: o }, labels ? labels[i] : o)); });
        return $s.val(value || '').on('change', function () { onChange($(this).val()); sync(); });
    }
    function check(labelText, value, onChange) {
        var id = 'c' + Math.random().toString(36).slice(2, 9);
        return el('div', { 'class': 'form-check mb-2' }).append(
            el('input', { type: 'checkbox', 'class': 'form-check-input', id: id }).prop('checked', !!value).on('change', function () { onChange(this.checked); sync(); }),
            el('label', { 'class': 'form-check-label small', 'for': id }, labelText)
        );
    }

    /* ---------- settings ---------- */
    function renderSettings() {
        var st = form.settings = form.settings || {};
        var $c = el('div', { 'class': 'card shadow-sm mb-3' });
        var $b = el('div', { 'class': 'card-body' });
        $c.append(el('div', { 'class': 'card-header' }, S.settings), $b);
        $b.append(langInputs(st, 'title', S.form_title));
        var $row = el('div', { 'class': 'row' });
        $row.append(el('div', { 'class': 'col-md-4' }).append(field(S.form_id, textInput(st.form_id, function (v) { st.form_id = v; }, { pattern: '[A-Za-z_][A-Za-z0-9_]*' }))));
        $row.append(el('div', { 'class': 'col-md-4' }).append(field(S.default_language, select(langs(), dl(), function (v) { form.default_language = v; render(); }))));
        $row.append(el('div', { 'class': 'col-md-4' }).append(field(S.estimated_minutes, el('input', { type: 'number', min: 1, max: 240, 'class': 'form-control form-control-sm' }).val(st.estimated_minutes || '').on('input', function () {
            var v = parseInt($(this).val(), 10); if (v > 0) { st.estimated_minutes = v; } else { delete st.estimated_minutes; } sync();
        }))));
        $b.append($row);
        var $langs = el('div', { 'class': 'mb-2' }).append(el('div', { 'class': 'form-label small mb-1' }, S.languages));
        ['fr', 'en'].concat(langs().filter(function (l) { return l !== 'fr' && l !== 'en'; })).forEach(function (l) {
            $langs.append(el('span', { 'class': 'me-3' }).append(check(l.toUpperCase(), langs().indexOf(l) >= 0, function (on) {
                var ls = langs().slice();
                if (on && ls.indexOf(l) < 0) { ls.push(l); }
                if (!on) { ls = ls.filter(function (x) { return x !== l; }); }
                if (!ls.length) { ls = [l]; }
                form.languages = ls;
                if (ls.indexOf(form.default_language) < 0) { form.default_language = ls[0]; }
                render();
            }).addClass('d-inline-block')));
        });
        $b.append($langs);
        $b.append(field(S.objective, el('textarea', { 'class': 'form-control form-control-sm', rows: 2, maxlength: 2000 }).val(st.objective || '').on('input', function () {
            var v = $(this).val(); if (v) { st.objective = v; } else { delete st.objective; } sync();
        })));
        $b.append(field(S.sampling, select(['unknown', 'probabilistic', 'non_probabilistic', 'census'], st.sampling || 'unknown', function (v) { st.sampling = v; },
            ['unknown', 'probabilistic', 'non_probabilistic', 'census'].map(function (s) { return S['sampling_' + s]; }))));
        return $c;
    }

    /* ---------- items ---------- */
    function renderItems(items, $container, depth) {
        if (!items.length) { $container.append(el('p', { 'class': 'text-muted small' }, S.empty)); }
        items.forEach(function (item, index) { $container.append(renderItem(items, index, depth)); });
        $container.append(addBar(items, depth));
    }

    function addBar(items, depth) {
        var $bar = el('div', { 'class': 'd-flex flex-wrap gap-2 align-items-center my-2' });
        var $type = select(TYPES.filter(function (t) { return t !== 'calculate'; }), 'text', function () {}, TYPES.filter(function (t) { return t !== 'calculate'; }).map(function (t) { return S['type_' + t]; })).css('max-width', '16rem');
        $bar.append($type, el('button', { type: 'button', 'class': 'btn btn-sm btn-outline-primary' }, depth ? S.add_in_section : S.add_question).on('click', function () {
            var q = { kind: 'question', type: 'text', name: uniqueName('question'), label: {} };
            q.label[dl()] = S.new_question;
            applyType(q, $type.val());
            items.push(q); open = q; render();
        }));
        if (!depth) {
            $bar.append(el('button', { type: 'button', 'class': 'btn btn-sm btn-outline-secondary' }, S.add_section).on('click', function () {
                var g = { kind: 'group', name: uniqueName('section'), label: {}, page: true, items: [] };
                g.label[dl()] = S.new_section;
                var q = { kind: 'question', type: 'text', name: uniqueName('question'), label: {} };
                q.label[dl()] = S.new_question;
                g.items.push(q);
                items.push(g); open = g; render();
            }));
        }
        return $bar;
    }

    function renderItem(items, index, depth) {
        var item = items[index], isGroup = item.kind === 'group' || item.kind === 'repeat';
        var $card = el('div', { 'class': 'card shadow-sm mb-2 f2s-item' + (isGroup ? ' f2s-group-item' : ''), 'data-name': item.name });
        var $head = el('div', { 'class': 'card-body py-2 d-flex align-items-center gap-2 flex-wrap' });
        $head.append(el('span', { 'class': 'badge text-bg-light border' }, isGroup ? S.section : (S['type_' + item.type] || item.type)));
        $head.append(el('span', { 'class': 'flex-grow-1 f2s-preview' }, txt(item.label) || '—').append(item.required ? el('span', { 'class': 'text-danger' }, ' *') : ''));
        $head.append(el('code', { 'class': 'small text-muted f2s-name' }, item.name));
        var btn = function (label, cls, fn, disabled) { return el('button', { type: 'button', 'class': 'btn btn-sm ' + cls, title: label, 'aria-label': label }).text(label).prop('disabled', !!disabled).on('click', fn); };
        $head.append(btn('↑', 'btn-outline-secondary', function () { items.splice(index - 1, 0, items.splice(index, 1)[0]); render(); }, index === 0).attr('aria-label', S.move_up));
        $head.append(btn('↓', 'btn-outline-secondary', function () { items.splice(index + 1, 0, items.splice(index, 1)[0]); render(); }, index === items.length - 1).attr('aria-label', S.move_down));
        $head.append(btn(open === item ? S.done : S.edit, open === item ? 'btn-secondary' : 'btn-outline-primary', function () { open = open === item ? null : item; render(); }));
        $head.append(btn('✕', 'btn-outline-danger', function () {
            if (window.confirm(S.remove_confirm)) { items.splice(index, 1); if (open === item) { open = null; } cleanupLists(); render(); }
        }, depth === 0 && form.items.length === 1).attr('aria-label', S.remove));
        $card.append($head);
        if (open === item) { $card.append(isGroup ? groupBody(item) : questionBody(item)); }
        if (isGroup) {
            var $children = el('div', { 'class': 'card-body pt-0 f2s-children' });
            renderItems(item.items || (item.items = []), $children, depth + 1);
            $card.append($children);
        }
        return $card;
    }

    function updatePreview(item) {
        $root.find('.f2s-item').filter(function () { return $(this).data('name') === item.name; }).first().find('> .card-body .f2s-preview').first().text(txt(item.label) || '—');
    }

    function groupBody(g) {
        var $b = el('div', { 'class': 'card-body border-top bg-light' });
        $b.append(langInputs(g, 'label', S.label, function () { updatePreview(g); }));
        $b.append(field(S.name, textInput(g.name, function (v) { g.name = v; }, { 'class': 'form-control form-control-sm font-monospace f2s-name-input' })));
        $b.append(check(S.page, g.page, function (v) { if (v) { g.page = true; } else { delete g.page; } }));
        $b.append(field(S.relevant, textInput(g.relevant, function (v) { if (v) { g.relevant = v; } else { delete g.relevant; } }, { 'class': 'form-control form-control-sm font-monospace' }), S.relevant_help));
        return $b;
    }

    function applyType(q, type) {
        q.type = type;
        if (WITH_CHOICES.indexOf(type) >= 0) {
            if (!q.choices || !form.choice_lists[q.choices]) {
                var list = uniqueListName(q.name + '_choices');
                form.choice_lists[list] = [newChoice('1', 1), newChoice('2', 2)];
                q.choices = list;
            }
        } else if (q.choices) { delete q.choices; delete q.allow_other; delete q.choice_filter; }
        if (type === 'scale') { q.scale = q.scale || { min: 1, max: 5, preset: 'likert5' }; } else { delete q.scale; }
        if (type === 'matrix') {
            if (!q.rows || !q.rows.length) { var r = { name: uniqueName(q.name + '_1'), label: {} }; r.label[dl()] = S.new_row; q.rows = [r]; }
        } else { delete q.rows; }
        if (type === 'calculate') { q.calculation = q.calculation || '0'; } else { delete q.calculation; }
        if (type === 'note') { delete q.required; }
        cleanupLists();
    }
    function newChoice(value, n) { var c = { value: value, label: {} }; c.label[dl()] = S.new_choice + ' ' + n; return c; }
    function uniqueListName(base) {
        base = (slug(base) || 'list').substring(0, 60);
        if (!form.choice_lists[base]) { return base; }
        for (var i = 2; ; i++) { if (!form.choice_lists[base + '_' + i]) { return base + '_' + i; } }
    }
    function cleanupLists() { Object.keys(form.choice_lists).forEach(function (l) { if (listUsers(l) === 0) { delete form.choice_lists[l]; } }); }

    function questionBody(q) {
        var $b = el('div', { 'class': 'card-body border-top bg-light' });
        $b.append(langInputs(q, 'label', S.label, function (l, v) {
            updatePreview(q);
            if (l === dl() && /^question(_\d+)?$/.test(q.name) && slug(v)) {
                var n = uniqueName(slug(v)); q.name = n;
                $b.find('.f2s-name-input').first().val(n);
                $b.closest('.f2s-item').attr('data-name', n).data('name', n).find('> .card-body .f2s-name').first().text(n);
            }
        }));
        var $row = el('div', { 'class': 'row' });
        $row.append(el('div', { 'class': 'col-md-6' }).append(field(S.name, textInput(q.name, function (v) {
            q.name = v; $b.closest('.f2s-item').attr('data-name', v).data('name', v).find('> .card-body .f2s-name').first().text(v);
        }, { pattern: '[A-Za-z_][A-Za-z0-9_]*', 'class': 'form-control form-control-sm font-monospace f2s-name-input' }))));
        $row.append(el('div', { 'class': 'col-md-6' }).append(field(S.type, select(TYPES, q.type, function (v) { applyType(q, v); render(); }, TYPES.map(function (t) { return S['type_' + t]; })))));
        $b.append($row);
        if (q.type !== 'note' && q.type !== 'calculate') {
            $b.append(check(S.required, q.required, function (v) { if (v) { q.required = true; } else { delete q.required; } updatePreviewRequired(q, $b); }));
        }
        $b.append(langInputs(q, 'hint', S.hint));
        if (WITH_CHOICES.indexOf(q.type) >= 0) { $b.append(choicesEditor(q)); }
        if (q.type === 'select_one' || q.type === 'select_multiple') {
            $b.append(check(S.allow_other, q.allow_other, function (v) { if (v) { q.allow_other = true; } else { delete q.allow_other; } }));
        }
        if (q.type === 'scale') { $b.append(scaleEditor(q)); }
        if (q.type === 'matrix') { $b.append(rowsEditor(q)); }
        if (q.type === 'calculate') {
            $b.append(field('calculation', textInput(q.calculation, function (v) { q.calculation = v; }, { 'class': 'form-control form-control-sm font-monospace' })));
        }
        var $adv = el('details', { 'class': 'mt-2' }).append(el('summary', { 'class': 'small' }, S.advanced));
        $adv.append(field(S.relevant, textInput(q.relevant, function (v) { if (v) { q.relevant = v; } else { delete q.relevant; } }, { 'class': 'form-control form-control-sm font-monospace', placeholder: "${age} >= 18" }), S.relevant_help));
        if (q.type !== 'note' && q.type !== 'matrix' && q.type !== 'calculate') {
            $adv.append(field(S.constraint, textInput(q.constraint, function (v) { if (v) { q.constraint = v; } else { delete q.constraint; } }, { 'class': 'form-control form-control-sm font-monospace', placeholder: '. >= 0 and . <= 120' }), S.constraint_help));
            $adv.append(langInputs(q, 'constraint_message', S.constraint_message));
        }
        q.analysis = q.analysis || {};
        $adv.append(field(S.role, select(ROLES, q.analysis.role || '', function (v) {
            if (v) { q.analysis.role = v; } else { delete q.analysis.role; }
            if ($.isEmptyObject(q.analysis)) { delete q.analysis; }
        }, ROLES.map(function (r) { return S['role_' + r]; }))));
        if ($.isEmptyObject(q.analysis)) { delete q.analysis; }
        $adv.append(field(S.appearance, textInput(q.appearance, function (v) { if (v) { q.appearance = v; } else { delete q.appearance; } })));
        $b.append($adv);
        return $b;
    }
    function updatePreviewRequired(q, $b) {
        var $p = $b.closest('.f2s-item').find('> .card-body .f2s-preview').first();
        $p.text(txt(q.label) || '—'); if (q.required) { $p.append(el('span', { 'class': 'text-danger' }, ' *')); }
    }

    function choicesEditor(q) {
        var list = form.choice_lists[q.choices] || (form.choice_lists[q.choices] = []);
        var $w = el('div', { 'class': 'mb-2' }).append(el('div', { 'class': 'form-label small mb-1 fw-semibold' }, S.choices + ' '));
        if (listUsers(q.choices) > 1) { $w.append(el('div', { 'class': 'alert alert-warning py-1 px-2 small' }, S.shared_list + ' (' + q.choices + ')')); }
        var $t = el('table', { 'class': 'table table-sm align-middle mb-1' });
        var $h = el('tr').append(el('th', { 'class': 'small' }, S.choice_value));
        langs().forEach(function (l) { $h.append(el('th', { 'class': 'small' }, S.choice_label + (langs().length > 1 ? ' (' + l.toUpperCase() + ')' : ''))); });
        $t.append(el('thead').append($h.append(el('th'))));
        var $body = el('tbody');
        list.forEach(function (c, i) {
            var $tr = el('tr');
            var $val = textInput(c.value, function (v) { c.value = v; }, { 'class': 'form-control form-control-sm font-monospace', pattern: '[A-Za-z0-9_.-]+' });
            $tr.append(el('td', { 'class': 'w-25' }).append($val));
            langs().forEach(function (l) {
                $tr.append(el('td').append(textInput((c.label || {})[l], function (v) {
                    c.label = c.label || {}; if (v) { c.label[l] = v; } else { delete c.label[l]; }
                    if (l === dl() && /^\d+$/.test(c.value) && slug(v) && list.every(function (o) { return o === c || o.value !== slug(v); })) { c.value = slug(v); $val.val(c.value); }
                })));
            });
            $tr.append(el('td', { 'class': 'text-end' }).append(el('button', { type: 'button', 'class': 'btn btn-sm btn-outline-danger', 'aria-label': S.remove }).text('✕').prop('disabled', list.length <= 1).on('click', function () { list.splice(i, 1); render(); })));
            $body.append($tr);
        });
        $t.append($body);
        $w.append(el('div', { 'class': 'table-responsive' }).append($t));
        $w.append(el('button', { type: 'button', 'class': 'btn btn-sm btn-outline-primary' }, S.add_choice).on('click', function () {
            var n = list.length + 1, v = String(n);
            while (list.some(function (c) { return c.value === v; })) { n++; v = String(n); }
            list.push(newChoice(v, n)); render();
        }));
        return $w;
    }

    function scaleEditor(q) {
        var sc = q.scale = q.scale || { min: 1, max: 5 };
        var num = function (key) {
            return el('input', { type: 'number', 'class': 'form-control form-control-sm' }).val(sc[key]).on('input', function () { var v = parseInt($(this).val(), 10); if (!isNaN(v)) { sc[key] = v; sync(); } });
        };
        var $r = el('div', { 'class': 'row' });
        $r.append(el('div', { 'class': 'col-4' }).append(field(S.scale_min, num('min'))), el('div', { 'class': 'col-4' }).append(field(S.scale_max, num('max'))));
        $r.append(el('div', { 'class': 'col-4' }).append(field(S.preset, select(['custom', 'likert5', 'likert7', 'nps', 'stars5'], sc.preset || 'custom', function (v) {
            sc.preset = v;
            if (v === 'likert5' || v === 'stars5') { sc.min = 1; sc.max = 5; } else if (v === 'likert7') { sc.min = 1; sc.max = 7; } else if (v === 'nps') { sc.min = 0; sc.max = 10; }
            render();
        }))));
        var $w = el('div').append($r);
        $w.append(langInputs(sc, 'min_label', S.scale_min_label), langInputs(sc, 'max_label', S.scale_max_label));
        return $w;
    }

    function rowsEditor(q) {
        var $w = el('div', { 'class': 'mb-2' }).append(el('div', { 'class': 'form-label small mb-1 fw-semibold' }, S.rows));
        q.rows.forEach(function (r, i) {
            var $line = el('div', { 'class': 'row g-1 mb-1 align-items-end' });
            $line.append(el('div', { 'class': 'col-md-3' }).append(textInput(r.name, function (v) { r.name = v; }, { 'class': 'form-control form-control-sm font-monospace f2s-name-input', 'aria-label': S.row_name })));
            langs().forEach(function (l) {
                $line.append(el('div', { 'class': 'col' }).append(textInput((r.label || {})[l], function (v) { r.label = r.label || {}; if (v) { r.label[l] = v; } else { delete r.label[l]; } }, { 'aria-label': S.row_label + ' ' + l, placeholder: S.row_label + (langs().length > 1 ? ' (' + l.toUpperCase() + ')' : '') })));
            });
            $line.append(el('div', { 'class': 'col-auto' }).append(el('button', { type: 'button', 'class': 'btn btn-sm btn-outline-danger', 'aria-label': S.remove }).text('✕').prop('disabled', q.rows.length <= 1).on('click', function () { q.rows.splice(i, 1); render(); })));
            $w.append($line);
        });
        $w.append(el('button', { type: 'button', 'class': 'btn btn-sm btn-outline-primary' }, S.add_row).on('click', function () {
            var r = { name: uniqueName(q.name + '_' + (q.rows.length + 1)), label: {} }; r.label[dl()] = S.new_row; q.rows.push(r); render();
        }));
        return $w;
    }

    function markDuplicates() {
        var counts = {};
        allNames(form.items).forEach(function (n) { n = String(n).toLowerCase(); counts[n] = (counts[n] || 0) + 1; });
        $root.find('.f2s-name-input').each(function () {
            var dup = counts[String($(this).val()).toLowerCase()] > 1 || !/^[A-Za-z_][A-Za-z0-9_]{0,63}$/.test($(this).val());
            $(this).toggleClass('is-invalid', dup).attr('title', dup ? S.duplicate_name : '');
        });
    }

    function render() {
        var scroll = window.scrollY;
        $root.empty().append(renderSettings());
        var $items = el('div');
        renderItems(form.items, $items, 0);
        $root.append($items);
        sync();
        window.scrollTo(0, scroll);
    }

    $('#editor-form').on('submit', function () { sync(); });
    render();
});
