<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Rules beyond JSON Schema. Error codes are stable (used by the UI and tests);
 * messages are technical English, translated in the UI through lang keys.
 */
final class FormSemanticChecker
{
    /** @var array<string, string> name => JSON pointer of its first declaration */
    private array $names = [];
    /** @var array<string, true> */
    private array $questionNames = [];
    /** @var array<string, true> */
    private array $usedLists = [];
    /** @var list<array{path: string, field: string, expression: string}> */
    private array $expressions = [];
    private string $defaultLanguage;
    /** @var list<string> */
    private array $languages;

    /** @param array<string, mixed> $form */
    public function __construct(private array $form, private FormValidationResult $result)
    {
        $this->defaultLanguage = (string) ($form['default_language'] ?? '');
        $this->languages = array_values(array_filter((array) ($form['languages'] ?? []), 'is_string'));
    }

    public function run(): void
    {
        if (!in_array($this->defaultLanguage, $this->languages, true)) {
            $this->result->addError('/default_language', 'default_language_not_declared', 'default_language must be listed in languages');
        }
        $settings = is_array($this->form['settings'] ?? null) ? $this->form['settings'] : [];
        $this->checkLabel('/settings/title', is_array($settings['title'] ?? null) ? $settings['title'] : []);

        $this->walk(is_array($this->form['items'] ?? null) ? $this->form['items'] : [], '/items');
        $this->checkChoiceLists();
        $this->checkExpressions();
    }

    /** @param array<mixed> $items */
    private function walk(array $items, string $pointer): void
    {
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $path = $pointer . '/' . $index;
            $this->declareName((string) ($item['name'] ?? ''), $path . '/name');
            if (isset($item['label']) && is_array($item['label'])) {
                $this->checkLabel($path . '/label', $item['label']);
            }
            foreach (['relevant', 'constraint', 'calculation', 'choice_filter', 'repeat_count'] as $field) {
                if (is_string($item[$field] ?? null) && $item[$field] !== '') {
                    $this->expressions[] = ['path' => $path . '/' . $field, 'field' => $field, 'expression' => $item[$field]];
                }
            }
            $kind = $item['kind'] ?? null;
            if ($kind === 'group' || $kind === 'repeat') {
                if ($kind === 'repeat') {
                    $this->result->addWarning($path, 'not_yet_supported', 'repeat groups are planned for a later phase');
                }
                $this->walk(is_array($item['items'] ?? null) ? $item['items'] : [], $path . '/items');
                continue;
            }
            $this->questionNames[(string) $item['name']] = true;
            $this->checkQuestion($item, $path);
        }
    }

    /** @param array<string, mixed> $q */
    private function checkQuestion(array $q, string $path): void
    {
        $type = (string) ($q['type'] ?? '');
        if (in_array($type, FormSchema::NOT_YET_SUPPORTED_TYPES, true)) {
            $this->result->addWarning($path . '/type', 'not_yet_supported', "type {$type} is planned for a later phase");
        }
        if (is_string($q['choices'] ?? null)) {
            $this->usedLists[$q['choices']] = true;
            $lists = is_array($this->form['choice_lists'] ?? null) ? $this->form['choice_lists'] : [];
            if (!array_key_exists($q['choices'], $lists)) {
                $this->result->addError($path . '/choices', 'unknown_choice_list', "choice list \"{$q['choices']}\" is not defined in choice_lists");
            }
        }
        if ($type === 'scale' && is_array($q['scale'] ?? null)) {
            $min = (int) ($q['scale']['min'] ?? 0);
            $max = (int) ($q['scale']['max'] ?? 0);
            if ($min >= $max) {
                $this->result->addError($path . '/scale', 'scale_range_invalid', 'scale.min must be lower than scale.max');
            } elseif ($max - $min > 100) {
                $this->result->addError($path . '/scale', 'scale_range_invalid', 'scale range cannot exceed 100 steps');
            }
            if (($q['scale']['preset'] ?? null) === 'nps' && ($min !== 0 || $max !== 10)) {
                $this->result->addWarning($path . '/scale', 'nps_range', 'an NPS scale is normally 0 to 10');
            }
        }
        if ($type === 'matrix') {
            foreach (is_array($q['rows'] ?? null) ? $q['rows'] : [] as $i => $row) {
                if (is_array($row)) {
                    $this->declareName((string) ($row['name'] ?? ''), $path . '/rows/' . $i . '/name');
                    $this->questionNames[(string) ($row['name'] ?? '')] = true;
                    if (is_array($row['label'] ?? null)) {
                        $this->checkLabel($path . '/rows/' . $i . '/label', $row['label']);
                    }
                }
            }
        }
        if ($type !== 'select_one' && $type !== 'select_multiple' && $type !== 'matrix' && isset($q['choices'])) {
            $this->result->addWarning($path . '/choices', 'choices_ignored', "choices are ignored for type {$type}");
        }
    }

    private function declareName(string $name, string $pointer): void
    {
        if ($name === '') {
            return;
        }
        $key = strtolower($name); // XLSForm tools treat names case-insensitively in practice
        if (isset($this->names[$key])) {
            $this->result->addError($pointer, 'duplicate_name', "name \"{$name}\" is already used at {$this->names[$key]}");

            return;
        }
        $this->names[$key] = $pointer;
    }

    /** @param array<mixed> $label */
    private function checkLabel(string $pointer, array $label): void
    {
        if ($this->defaultLanguage !== '' && trim((string) ($label[$this->defaultLanguage] ?? '')) === '') {
            $this->result->addError($pointer, 'label_missing_default_language', "label has no text in the default language \"{$this->defaultLanguage}\"");
        }
        foreach ($this->languages as $lang) {
            if ($lang !== $this->defaultLanguage && !isset($label[$lang])) {
                $this->result->addWarning($pointer, 'label_missing_translation', "label has no \"{$lang}\" translation");
            }
        }
        foreach (array_keys($label) as $lang) {
            if (!in_array($lang, $this->languages, true)) {
                $this->result->addWarning($pointer, 'label_undeclared_language', "label uses undeclared language \"{$lang}\"");
            }
        }
    }

    private function checkChoiceLists(): void
    {
        $lists = is_array($this->form['choice_lists'] ?? null) ? $this->form['choice_lists'] : [];
        foreach ($lists as $listName => $choices) {
            $pointer = '/choice_lists/' . $listName;
            if (!isset($this->usedLists[$listName])) {
                $this->result->addWarning($pointer, 'unused_choice_list', "choice list \"{$listName}\" is not used by any question");
            }
            $seen = [];
            foreach (is_array($choices) ? $choices : [] as $i => $choice) {
                $value = is_array($choice) ? (string) ($choice['value'] ?? '') : '';
                if (isset($seen[$value])) {
                    $this->result->addError($pointer . '/' . $i . '/value', 'duplicate_choice_value', "value \"{$value}\" appears twice in list \"{$listName}\"");
                }
                $seen[$value] = true;
                if (is_array($choice) && is_array($choice['label'] ?? null)) {
                    $this->checkLabel($pointer . '/' . $i . '/label', $choice['label']);
                }
            }
        }
    }

    private function checkExpressions(): void
    {
        foreach ($this->expressions as $expr) {
            if (substr_count($expr['expression'], '(') !== substr_count($expr['expression'], ')')) {
                $this->result->addError($expr['path'], 'expression_unbalanced', 'unbalanced parentheses');
            }
            preg_match_all('/\$\{([^}]*)\}/', $expr['expression'], $matches);
            foreach ($matches[1] as $ref) {
                if (!isset($this->questionNames[$ref])) {
                    $this->result->addError($expr['path'], 'unknown_reference', "\${{$ref}} does not refer to a question of this form");
                }
            }
        }
    }
}
