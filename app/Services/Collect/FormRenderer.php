<?php

declare(strict_types=1);

namespace App\Services\Collect;

use App\Services\FormSchema;

/**
 * Renders a form version as accessible, mobile-first HTML (Bootstrap 5).
 * Works without JavaScript; f2s-collect.js only hides non-relevant questions.
 * Every dynamic string goes through e().
 */
final class FormRenderer
{
    private const RADIO_MAX = 8;

    /** @var array<string, list<array<string, mixed>>> */
    private array $lists;

    /**
     * @param array<string, mixed> $form
     * @param array<string, mixed> $values previously submitted input (redisplay after errors)
     * @param array<string, array{code: string, message: ?string}> $errors
     * @param callable(string): string $translate UI translation (t())
     */
    public function __construct(
        private array $form,
        private string $lang,
        private array $values,
        private array $errors,
        private $translate,
    ) {
        $lists = is_array($form['choice_lists'] ?? null) ? $form['choice_lists'] : [];
        $this->lists = array_map(static fn($l): array => is_array($l) ? array_values($l) : [], $lists);
    }

    public function render(): string
    {
        return $this->items(is_array($this->form['items'] ?? null) ? $this->form['items'] : []);
    }

    /** @param array<mixed> $items */
    private function items(array $items): string
    {
        $html = '';
        foreach ($items as $item) {
            if (!is_array($item) || !is_string($item['name'] ?? null)) {
                continue;
            }
            $html .= ($item['kind'] ?? '') === 'group' ? $this->group($item) : $this->question($item);
        }

        return $html;
    }

    /** @param array<string, mixed> $g */
    private function group(array $g): string
    {
        $label = $this->label($g['label'] ?? null);
        $html = '<fieldset class="f2s-group card shadow-sm mb-4"' . $this->relevantAttr($g) . '>';
        if ($label !== '') {
            $html .= '<legend class="card-header h6 mb-0 w-100 float-none">' . e($label) . '</legend>';
        }

        return $html . '<div class="card-body">' . $this->items(is_array($g['items'] ?? null) ? $g['items'] : []) . '</div></fieldset>';
    }

    /** @param array<string, mixed> $q */
    private function question(array $q): string
    {
        $type = (string) ($q['type'] ?? 'text');
        if ($type === 'calculate' || in_array($type, FormSchema::NOT_YET_SUPPORTED_TYPES, true)) {
            return '';
        }
        $name = (string) $q['name'];
        $id = 'q-' . $name;
        $required = ($q['required'] ?? false) === true;
        $label = $this->label($q['label'] ?? null);
        $hint = $this->label($q['hint'] ?? null);

        $html = '<div class="f2s-q mb-4" id="' . e($id) . '-wrap" data-name="' . e($name) . '"' . $this->relevantAttr($q) . '>';
        if ($type === 'note') {
            return $html . '<p class="mb-0">' . nl2br(e($label)) . '</p>' . ($hint !== '' ? '<p class="form-text">' . e($hint) . '</p>' : '') . '</div>';
        }
        $isGroupInput = in_array($type, ['select_one', 'select_multiple', 'scale', 'matrix'], true);
        $html .= $isGroupInput
            ? '<div class="form-label fw-semibold" id="' . e($id) . '-label">' . e($label) . $this->star($required) . '</div>'
            : '<label class="form-label fw-semibold" for="' . e($id) . '">' . e($label) . $this->star($required) . '</label>';
        if ($hint !== '') {
            $html .= '<div class="form-text mt-n1 mb-2">' . e($hint) . '</div>';
        }
        $html .= match ($type) {
            'select_one' => $this->selectOne($q, $name, $id, $required),
            'select_multiple' => $this->selectMultiple($q, $name, $id),
            'scale' => $this->scale($q, $name, $id, $required),
            'matrix' => $this->matrix($q, $required),
            default => $this->input($q, $type, $name, $id, $required),
        };
        if ($type !== 'matrix') {
            $html .= $this->errorFor($name);
        }

        return $html . '</div>';
    }

    /** @param array<string, mixed> $q */
    private function input(array $q, string $type, string $name, string $id, bool $required): string
    {
        $value = $this->scalar($name);
        $invalid = isset($this->errors[$name]) ? ' is-invalid' : '';
        $req = $required ? ' required' : '';
        $ro = ($q['read_only'] ?? false) === true ? ' readonly' : '';
        if ($type === 'long_text') {
            return '<textarea class="form-control' . $invalid . '" id="' . e($id) . '" name="' . e($name) . '" rows="4" maxlength="' . SubmissionValidator::MAX_LONG_TEXT . '"' . $req . $ro . '>' . e($value) . '</textarea>';
        }
        [$htmlType, $extra] = match ($type) {
            'integer' => ['number', ' step="1" inputmode="numeric"'],
            'decimal' => ['text', ' inputmode="decimal"'],
            'date' => ['date', ''],
            'time' => ['time', ''],
            'datetime' => ['datetime-local', ''],
            'email' => ['email', ' autocomplete="email"'],
            'phone' => ['tel', ' autocomplete="tel"'],
            default => ['text', ' maxlength="' . SubmissionValidator::MAX_TEXT . '"'],
        };

        return '<input class="form-control' . $invalid . '" type="' . $htmlType . '" id="' . e($id) . '" name="' . e($name) . '" value="' . e($value) . '"' . $extra . $req . $ro . '>';
    }

    /** @param array<string, mixed> $q */
    private function selectOne(array $q, string $name, string $id, bool $required): string
    {
        $choices = $this->lists[(string) ($q['choices'] ?? '')] ?? [];
        $current = $this->scalar($name);
        $other = ($q['allow_other'] ?? false) === true;
        $appearance = (string) ($q['appearance'] ?? '');
        if (count($choices) > self::RADIO_MAX || str_contains($appearance, 'minimal')) {
            $html = '<select class="form-select' . (isset($this->errors[$name]) ? ' is-invalid' : '') . '" id="' . e($id) . '" name="' . e($name) . '"' . ($required ? ' required' : '') . '>';
            $html .= '<option value="">' . e(($this->translate)('collect.choose')) . '</option>';
            foreach ($choices as $c) {
                $v = (string) $c['value'];
                $html .= '<option value="' . e($v) . '"' . ($current === $v ? ' selected' : '') . '>' . e($this->label($c['label'] ?? null)) . '</option>';
            }
            if ($other) {
                $html .= '<option value="other"' . ($current === 'other' ? ' selected' : '') . '>' . e(($this->translate)('collect.other')) . '</option>';
            }

            return $html . '</select>' . ($other ? $this->otherInput($name) : '');
        }
        $html = '<div role="radiogroup" aria-labelledby="' . e($id) . '-label">';
        foreach ($choices as $i => $c) {
            $v = (string) $c['value'];
            $html .= $this->check('radio', $name, $id . '-' . $i, $v, $this->label($c['label'] ?? null), $current === $v, $required && $i === 0);
        }
        if ($other) {
            $html .= $this->check('radio', $name, $id . '-other', 'other', ($this->translate)('collect.other'), $current === 'other', false);
        }

        return $html . '</div>' . ($other ? $this->otherInput($name) : '');
    }

    /** @param array<string, mixed> $q */
    private function selectMultiple(array $q, string $name, string $id): string
    {
        $choices = $this->lists[(string) ($q['choices'] ?? '')] ?? [];
        $selected = $this->list($name);
        $html = '<div role="group" aria-labelledby="' . e($id) . '-label">';
        foreach ($choices as $i => $c) {
            $v = (string) $c['value'];
            $html .= $this->check('checkbox', $name . '[]', $id . '-' . $i, $v, $this->label($c['label'] ?? null), in_array($v, $selected, true), false);
        }
        if (($q['allow_other'] ?? false) === true) {
            $html .= $this->check('checkbox', $name . '[]', $id . '-other', 'other', ($this->translate)('collect.other'), in_array('other', $selected, true), false);
            $html .= '</div>' . $this->otherInput($name);

            return $html;
        }

        return $html . '</div>';
    }

    /** @param array<string, mixed> $q */
    private function scale(array $q, string $name, string $id, bool $required): string
    {
        $min = (int) ($q['scale']['min'] ?? 1);
        $max = (int) ($q['scale']['max'] ?? 5);
        $current = $this->scalar($name);
        $html = '<div class="f2s-scale d-flex flex-wrap gap-1" role="radiogroup" aria-labelledby="' . e($id) . '-label">';
        for ($v = $min; $v <= $max; ++$v) {
            $rid = $id . '-' . $v;
            $html .= '<input type="radio" class="btn-check" name="' . e($name) . '" id="' . e($rid) . '" value="' . $v . '" autocomplete="off"'
                . ((string) $v === $current ? ' checked' : '') . ($required && $v === $min ? ' required' : '') . '>'
                . '<label class="btn btn-outline-secondary f2s-scale-btn" for="' . e($rid) . '">' . $v . '</label>';
        }
        $html .= '</div>';
        $minLabel = $this->label($q['scale']['min_label'] ?? null);
        $maxLabel = $this->label($q['scale']['max_label'] ?? null);
        if ($minLabel !== '' || $maxLabel !== '') {
            $html .= '<div class="d-flex justify-content-between small text-muted mt-1"><span>' . e($minLabel) . '</span><span>' . e($maxLabel) . '</span></div>';
        }

        return $html;
    }

    /** @param array<string, mixed> $q */
    private function matrix(array $q, bool $required): string
    {
        $choices = $this->lists[(string) ($q['choices'] ?? '')] ?? [];
        $html = '<div class="f2s-matrix">';
        foreach (is_array($q['rows'] ?? null) ? $q['rows'] : [] as $row) {
            if (!is_array($row) || !is_string($row['name'] ?? null)) {
                continue;
            }
            $rowName = $row['name'];
            $rid = 'q-' . $rowName;
            $current = $this->scalar($rowName);
            $html .= '<div class="f2s-matrix-row border rounded p-2 mb-2"><div class="small fw-semibold mb-1" id="' . e($rid) . '-label">' . e($this->label($row['label'] ?? null)) . $this->star($required) . '</div>';
            $html .= '<div class="d-flex flex-wrap gap-1" role="radiogroup" aria-labelledby="' . e($rid) . '-label">';
            foreach ($choices as $i => $c) {
                $v = (string) $c['value'];
                $cid = $rid . '-' . $i;
                $html .= '<input type="radio" class="btn-check" name="' . e($rowName) . '" id="' . e($cid) . '" value="' . e($v) . '" autocomplete="off"'
                    . ($current === $v ? ' checked' : '') . ($required && $i === 0 ? ' required' : '') . '>'
                    . '<label class="btn btn-sm btn-outline-secondary" for="' . e($cid) . '">' . e($this->label($c['label'] ?? null)) . '</label>';
            }
            $html .= '</div>' . $this->errorFor($rowName) . '</div>';
        }

        return $html . '</div>';
    }

    private function check(string $type, string $name, string $id, string $value, string $label, bool $checked, bool $required): string
    {
        return '<div class="form-check py-1"><input class="form-check-input" type="' . $type . '" name="' . e($name) . '" id="' . e($id) . '" value="' . e($value) . '"'
            . ($checked ? ' checked' : '') . ($required ? ' required' : '') . '>'
            . '<label class="form-check-label" for="' . e($id) . '">' . e($label) . '</label></div>';
    }

    private function otherInput(string $name): string
    {
        return '<div class="f2s-other mt-2" data-other-for="' . e($name) . '"><input class="form-control' . (isset($this->errors[$name . '_other']) ? ' is-invalid' : '') . '" type="text" name="' . e($name . '_other')
            . '" value="' . e($this->scalar($name . '_other')) . '" maxlength="' . SubmissionValidator::MAX_TEXT . '" placeholder="' . e(($this->translate)('collect.other_specify')) . '" aria-label="' . e(($this->translate)('collect.other_specify')) . '"></div>';
    }

    private function errorFor(string $name): string
    {
        $error = $this->errors[$name] ?? null;
        if ($error === null) {
            return '';
        }
        $text = $error['message'] ?? ($this->translate)('collect.error.' . $error['code']);

        return '<div class="invalid-feedback d-block" role="alert">' . e($text) . '</div>';
    }

    /** @param array<string, mixed> $item */
    private function relevantAttr(array $item): string
    {
        $r = is_string($item['relevant'] ?? null) ? trim($item['relevant']) : '';

        return $r === '' ? '' : ' data-relevant="' . e($r) . '"';
    }

    private function star(bool $required): string
    {
        return $required ? ' <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden">(' . e(($this->translate)('collect.required')) . ')</span>' : '';
    }

    private function label(mixed $label): string
    {
        return is_array($label) ? FormSchema::label($label, $this->lang) : '';
    }

    private function scalar(string $name): string
    {
        $v = $this->values[$name] ?? '';

        return is_scalar($v) ? (string) $v : '';
    }

    /** @return list<string> */
    private function list(string $name): array
    {
        $v = $this->values[$name] ?? [];

        return is_array($v) ? array_values(array_map('strval', array_filter($v, 'is_scalar'))) : [];
    }
}
