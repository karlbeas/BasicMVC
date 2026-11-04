<?php

declare(strict_types=1);

namespace App\Services\Collect;

use App\Services\FormSchema;

/**
 * Server-side validation of a response against a form version. The browser
 * hides non-relevant questions, but the server decides: relevance, required,
 * types, choices and constraints are all re-checked here.
 */
final class SubmissionValidator
{
    public const MAX_TEXT = 2000;
    public const MAX_LONG_TEXT = 10000;

    /** @var array<string, list<string>> list name => allowed values */
    private array $lists = [];
    /** @var array<string, string> values used to evaluate expressions */
    private array $values = [];
    /** @var array<string, string|list<string>> */
    private array $data = [];
    /** @var array<string, array{code: string, message: ?string}> */
    private array $errors = [];
    /** @var array<string, mixed> */
    private array $input = [];
    private ExpressionEvaluator $expr;

    /** @param array<string, mixed> $form decoded F2S Schema v1 */
    public function __construct(private array $form, private string $language = '')
    {
        $this->expr = new ExpressionEvaluator();
        foreach (is_array($form['choice_lists'] ?? null) ? $form['choice_lists'] : [] as $name => $choices) {
            $this->lists[(string) $name] = array_map(static fn(array $c): string => (string) $c['value'], is_array($choices) ? $choices : []);
        }
        if ($this->language === '') {
            $this->language = (string) ($form['default_language'] ?? 'fr');
        }
    }

    /**
     * @param array<string, mixed> $input raw POST fields
     *
     * @return array{data: array<string, string|list<string>>, errors: array<string, array{code: string, message: ?string}>}
     */
    public function validate(array $input): array
    {
        $this->input = $input;
        $this->data = [];
        $this->errors = [];
        $this->values = [];
        // First pass: everything submitted, as strings, so relevance can look ahead.
        foreach ($input as $key => $value) {
            $this->values[(string) $key] = is_array($value) ? implode(' ', array_map('strval', array_filter($value, 'is_scalar'))) : (is_scalar($value) ? trim((string) $value) : '');
        }
        $this->walk(is_array($this->form['items'] ?? null) ? $this->form['items'] : [], true);

        return ['data' => $this->data, 'errors' => $this->errors];
    }

    /** @param array<mixed> $items */
    private function walk(array $items, bool $parentRelevant): void
    {
        foreach ($items as $item) {
            if (!is_array($item) || !is_string($item['name'] ?? null)) {
                continue;
            }
            $relevant = $parentRelevant && $this->relevant($item);
            if (($item['kind'] ?? '') === 'group' || ($item['kind'] ?? '') === 'repeat') {
                $this->walk(is_array($item['items'] ?? null) ? $item['items'] : [], $relevant);
                continue;
            }
            $name = $item['name'];
            if (!$relevant) {
                $this->forget($item);
                continue;
            }
            $this->question($item, $name);
        }
    }

    /** @param array<string, mixed> $item */
    private function relevant(array $item): bool
    {
        $expression = is_string($item['relevant'] ?? null) ? trim($item['relevant']) : '';
        if ($expression === '') {
            return true;
        }
        try {
            return $this->expr->isTrue($expression, $this->values);
        } catch (UnsupportedExpressionException) {
            return true; // campaigns refuse unsupported expressions; be permissive if one slips through
        }
    }

    /** @param array<string, mixed> $item */
    private function forget(array $item): void
    {
        $this->values[(string) $item['name']] = '';
        foreach (is_array($item['rows'] ?? null) ? $item['rows'] : [] as $row) {
            if (is_array($row) && is_string($row['name'] ?? null)) {
                $this->values[$row['name']] = '';
            }
        }
    }

    /** @param array<string, mixed> $q */
    private function question(array $q, string $name): void
    {
        $type = (string) ($q['type'] ?? 'text');
        $required = ($q['required'] ?? false) === true;

        if ($type === 'note' || in_array($type, FormSchema::NOT_YET_SUPPORTED_TYPES, true)) {
            return;
        }
        if ($type === 'calculate') {
            $this->calculate($q, $name);

            return;
        }
        if ($type === 'matrix') {
            $this->matrix($q, $required);

            return;
        }
        if ($type === 'select_multiple') {
            $this->selectMultiple($q, $name, $required);

            return;
        }

        $raw = $this->input[$name] ?? '';
        $value = is_scalar($raw) ? trim((string) $raw) : '';
        if (($q['read_only'] ?? false) === true) {
            $value = '';
        }
        if ($value === '') {
            $this->values[$name] = '';
            if ($required) {
                $this->error($name, 'required', $q['required_message'] ?? null);
            }

            return;
        }

        $normalized = $this->normalize($q, $type, $name, $value);
        if ($normalized === null) {
            return; // error already recorded
        }
        $this->values[$name] = $normalized;
        if (!$this->constraintHolds($q, $normalized)) {
            $this->error($name, 'constraint', $q['constraint_message'] ?? null);

            return;
        }
        $this->data[$name] = $normalized;
    }

    /** @param array<string, mixed> $q */
    private function normalize(array $q, string $type, string $name, string $value): ?string
    {
        switch ($type) {
            case 'text':
            case 'long_text':
                if (mb_strlen($value) > ($type === 'text' ? self::MAX_TEXT : self::MAX_LONG_TEXT)) {
                    return $this->error($name, 'too_long');
                }

                return $value;
            case 'integer':
                $v = str_replace([' ', "\u{00A0}"], '', $value);

                return preg_match('/^-?\d{1,15}$/', $v) ? (string) (int) $v : $this->error($name, 'integer');
            case 'decimal':
                $v = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], $value);

                return is_numeric($v) && strlen($v) <= 30 ? $v : $this->error($name, 'decimal');
            case 'date':
                $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

                return $d !== false && $d->format('Y-m-d') === $value ? $value : $this->error($name, 'date');
            case 'time':
                return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : $this->error($name, 'time');
            case 'datetime':
                $d = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);

                return $d !== false && $d->format('Y-m-d\TH:i') === $value ? $value : $this->error($name, 'datetime');
            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false && strlen($value) <= 190 ? $value : $this->error($name, 'email');
            case 'phone':
                return preg_match('/^\+?[0-9][0-9 ().-]{5,19}$/', $value) ? $value : $this->error($name, 'phone');
            case 'scale':
                $min = (int) ($q['scale']['min'] ?? 0);
                $max = (int) ($q['scale']['max'] ?? 0);

                return preg_match('/^-?\d+$/', $value) && (int) $value >= $min && (int) $value <= $max ? $value : $this->error($name, 'choice');
            case 'select_one':
                $allowed = $this->lists[(string) ($q['choices'] ?? '')] ?? [];
                if (in_array($value, $allowed, true)) {
                    return $value;
                }
                if ($value === 'other' && ($q['allow_other'] ?? false) === true) {
                    $other = trim((string) (is_scalar($this->input[$name . '_other'] ?? null) ? $this->input[$name . '_other'] : ''));
                    if ($other === '') {
                        return $this->error($name, 'other_required');
                    }
                    $this->data[$name . '_other'] = mb_substr($other, 0, self::MAX_TEXT);

                    return $value;
                }

                return $this->error($name, 'choice');
            default:
                return $value;
        }
    }

    /** @param array<string, mixed> $q */
    private function selectMultiple(array $q, string $name, bool $required): void
    {
        $raw = $this->input[$name] ?? [];
        $values = is_array($raw) ? array_values(array_unique(array_map('strval', array_filter($raw, 'is_scalar')))) : [];
        $values = array_values(array_filter(array_map('trim', $values), static fn(string $v): bool => $v !== ''));
        $allowed = $this->lists[(string) ($q['choices'] ?? '')] ?? [];
        if (($q['allow_other'] ?? false) === true) {
            $allowed[] = 'other';
        }
        $this->values[$name] = implode(' ', $values);
        if ($values === []) {
            if ($required) {
                $this->error($name, 'required', $q['required_message'] ?? null);
            }

            return;
        }
        if (array_diff($values, $allowed) !== []) {
            $this->error($name, 'choice');

            return;
        }
        if (in_array('other', $values, true)) {
            $other = trim((string) (is_scalar($this->input[$name . '_other'] ?? null) ? $this->input[$name . '_other'] : ''));
            if ($other === '') {
                $this->error($name, 'other_required');

                return;
            }
            $this->data[$name . '_other'] = mb_substr($other, 0, self::MAX_TEXT);
        }
        if (!$this->constraintHolds($q, $this->values[$name])) {
            $this->error($name, 'constraint', $q['constraint_message'] ?? null);

            return;
        }
        $this->data[$name] = $values;
    }

    /** @param array<string, mixed> $q */
    private function matrix(array $q, bool $required): void
    {
        $allowed = $this->lists[(string) ($q['choices'] ?? '')] ?? [];
        foreach (is_array($q['rows'] ?? null) ? $q['rows'] : [] as $row) {
            if (!is_array($row) || !is_string($row['name'] ?? null)) {
                continue;
            }
            $rowName = $row['name'];
            $raw = $this->input[$rowName] ?? '';
            $value = is_scalar($raw) ? trim((string) $raw) : '';
            $this->values[$rowName] = $value;
            if ($value === '') {
                if ($required) {
                    $this->error($rowName, 'required', $q['required_message'] ?? null);
                }
                continue;
            }
            if (!in_array($value, $allowed, true)) {
                $this->error($rowName, 'choice');
                continue;
            }
            $this->data[$rowName] = $value;
        }
    }

    /** @param array<string, mixed> $q */
    private function calculate(array $q, string $name): void
    {
        try {
            $result = $this->expr->evaluate((string) ($q['calculation'] ?? ''), $this->values);
        } catch (UnsupportedExpressionException) {
            return;
        }
        $value = is_bool($result) ? ($result ? 'true' : 'false') : (is_float($result) ? (is_nan($result) ? '' : rtrim(rtrim(sprintf('%.6F', $result), '0'), '.')) : (string) $result);
        $this->values[$name] = $value;
        if ($value !== '') {
            $this->data[$name] = $value;
        }
    }

    /** @param array<string, mixed> $q */
    private function constraintHolds(array $q, string $value): bool
    {
        $expression = is_string($q['constraint'] ?? null) ? trim($q['constraint']) : '';
        if ($expression === '') {
            return true;
        }
        try {
            return $this->expr->isTrue($expression, $this->values, $value);
        } catch (UnsupportedExpressionException) {
            return true;
        }
    }

    /** @return null always, so callers can `return $this->error(...)` */
    private function error(string $name, string $code, mixed $customMessage = null): null
    {
        $message = is_array($customMessage) ? FormSchema::label($customMessage, $this->language) : null;
        $this->errors[$name] = ['code' => $code, 'message' => $message !== '' ? $message : null];

        return null;
    }
}
