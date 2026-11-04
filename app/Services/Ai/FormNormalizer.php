<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Services\Bridge\Naming;

/**
 * Deterministic normalization of AI output into F2S Schema v1 (no AI call).
 * Fast models take liberties with strict JSON formats; this converts the common
 * deviations seen in practice, without losing information:
 *  - texts given as plain strings → {"<lang>": "…"}
 *  - type synonyms (single_choice, radio, number, rating…) → schema types
 *  - inline choices (strings or objects) → a named choice list
 *  - choice codes with spaces/accents → ASCII codes (references updated)
 *  - "required": "oui"/"yes"/1 → true ; unknown analysis roles → none
 *  - unknown question properties → kept in x_source (never silently lost)
 */
final class FormNormalizer
{
    private const QUESTION_KEYS = ['kind', 'type', 'name', 'label', 'hint', 'required', 'required_message', 'relevant', 'constraint',
        'constraint_message', 'calculation', 'default', 'read_only', 'choices', 'choice_filter', 'allow_other', 'scale', 'rows', 'appearance', 'analysis', 'x_source'];
    private const GROUP_KEYS = ['kind', 'name', 'label', 'relevant', 'appearance', 'page', 'repeat_count', 'items'];
    private const TYPES = ['text', 'long_text', 'integer', 'decimal', 'date', 'time', 'datetime', 'select_one', 'select_multiple', 'scale', 'matrix',
        'phone', 'email', 'note', 'calculate', 'file', 'image', 'geopoint'];
    private const TYPE_SYNONYMS = [
        'single_choice' => 'select_one', 'single' => 'select_one', 'radio' => 'select_one', 'select' => 'select_one', 'dropdown' => 'select_one',
        'choice' => 'select_one', 'choix_unique' => 'select_one', 'yes_no' => 'select_one', 'boolean' => 'select_one', 'oui_non' => 'select_one',
        'multiple_choice' => 'select_multiple', 'multiple' => 'select_multiple', 'checkbox' => 'select_multiple', 'checkboxes' => 'select_multiple',
        'multi_select' => 'select_multiple', 'choix_multiple' => 'select_multiple',
        'short_text' => 'text', 'string' => 'text', 'texte' => 'text', 'open' => 'text', 'open_ended' => 'text',
        'textarea' => 'long_text', 'paragraph' => 'long_text', 'longtext' => 'long_text', 'comment' => 'long_text', 'texte_long' => 'long_text',
        'number' => 'integer', 'numeric' => 'integer', 'int' => 'integer', 'nombre' => 'integer', 'entier' => 'integer',
        'float' => 'decimal', 'currency' => 'decimal', 'amount' => 'decimal', 'money' => 'decimal',
        'rating' => 'scale', 'likert' => 'scale', 'nps' => 'scale', 'stars' => 'scale', 'echelle' => 'scale', 'slider' => 'scale', 'range' => 'scale',
        'grid' => 'matrix', 'table' => 'matrix', 'matrice' => 'matrix',
        'tel' => 'phone', 'telephone' => 'phone', 'mail' => 'email', 'e_mail' => 'email',
        'info' => 'note', 'information' => 'note', 'notice' => 'note', 'statement' => 'note',
        'datetime_local' => 'datetime', 'photo' => 'image', 'picture' => 'image', 'upload' => 'file', 'document' => 'file',
        'gps' => 'geopoint', 'location' => 'geopoint',
    ];
    private const ROLES = ['outcome', 'segment', 'identifier', 'metadata', 'open_feedback', 'screening', 'none'];
    private const ROLE_SYNONYMS = ['demographic' => 'segment', 'demographics' => 'segment', 'segmentation' => 'segment', 'profile' => 'segment',
        'kpi' => 'outcome', 'indicator' => 'outcome', 'measure' => 'outcome', 'satisfaction' => 'outcome', 'feedback' => 'open_feedback',
        'open' => 'open_feedback', 'comment' => 'open_feedback', 'filter' => 'screening', 'eligibility' => 'screening', 'id' => 'identifier'];

    /** @var array<string, list<array<string, mixed>>> choice lists created while normalizing */
    private array $lists;
    /** @var array<string, array<string, string>> list name => old code => new code */
    private array $recoded = [];

    /** @param array<string, mixed> $lists existing choice lists of the part */
    public function __construct(private string $lang, array $lists = [])
    {
        $this->lists = [];
        foreach ($lists as $name => $choices) {
            $this->addList((string) $name, is_array($choices) ? $choices : []);
        }
    }

    /**
     * @param array<string, mixed> $part a group or a question
     *
     * @return array{group: array<string, mixed>, choice_lists: array<string, list<array<string, mixed>>>}
     */
    public function part(array $part): array
    {
        $node = $this->node($part);
        if ($this->recoded !== []) {
            $node = $this->recodeExpressions($node);
        }

        return ['group' => $node, 'choice_lists' => $this->lists];
    }

    /**
     * @param array<string, mixed> $n
     *
     * @return array<string, mixed>
     */
    private function node(array $n): array
    {
        if (!isset($n['items']) && is_array($n['questions'] ?? null)) {
            $n['items'] = $n['questions'];
        }
        $isGroup = ($n['kind'] ?? null) === 'group' || ($n['kind'] ?? null) === 'repeat' || (!isset($n['type']) && is_array($n['items'] ?? null));

        return $isGroup ? $this->group($n) : $this->question($n);
    }

    /**
     * @param array<string, mixed> $g
     *
     * @return array<string, mixed>
     */
    private function group(array $g): array
    {
        $g['kind'] = ($g['kind'] ?? null) === 'repeat' ? 'repeat' : 'group';
        $g['name'] = $this->name($g['name'] ?? ($g['title'] ?? 'section'), 'section');
        $label = $this->text($g['label'] ?? ($g['title'] ?? null));
        if ($label !== null) {
            $g['label'] = $label;
        } else {
            unset($g['label']);
        }
        $g['page'] = true;
        $items = [];
        foreach (is_array($g['items'] ?? null) ? $g['items'] : [] as $child) {
            if (is_array($child)) {
                $items[] = $this->node($child);
            } elseif (is_string($child) && trim($child) !== '') {
                $items[] = $this->question(['label' => $child]);
            }
        }
        $g['items'] = $items;

        return array_intersect_key($g, array_flip(self::GROUP_KEYS));
    }

    /**
     * @param array<string, mixed> $q
     *
     * @return array<string, mixed>
     */
    private function question(array $q): array
    {
        $out = ['kind' => 'question'];
        $rawType = is_string($q['type'] ?? null) ? strtolower(trim($q['type'])) : 'text';
        $rawType = str_replace(['-', ' '], '_', $rawType);
        $type = in_array($rawType, self::TYPES, true) ? $rawType : (self::TYPE_SYNONYMS[$rawType] ?? 'text');
        $label = $this->text($q['label'] ?? ($q['question'] ?? ($q['text'] ?? ($q['title'] ?? null))));
        $out['type'] = $type;
        $out['name'] = $this->name($q['name'] ?? ($q['id'] ?? (is_array($label) ? (string) reset($label) : 'question')), 'q');
        $out['label'] = $label ?? [$this->lang => $out['name']];
        foreach (['hint', 'required_message', 'constraint_message'] as $key) {
            $text = $this->text($q[$key] ?? ($key === 'hint' ? ($q['description'] ?? ($q['help'] ?? null)) : null));
            if ($text !== null) {
                $out[$key] = $text;
            }
        }
        if (array_key_exists('required', $q)) {
            $out['required'] = $this->bool($q['required']);
        }
        foreach (['relevant', 'constraint', 'calculation', 'choice_filter', 'appearance'] as $key) {
            if (is_string($q[$key] ?? null) && trim($q[$key]) !== '') {
                $out[$key] = trim($q[$key]);
            }
        }
        if (isset($q['read_only'])) {
            $out['read_only'] = $this->bool($q['read_only']);
        }
        if (isset($q['default']) && (is_scalar($q['default']))) {
            $out['default'] = $q['default'];
        }

        // Choices: list name, inline list, or "options".
        $choices = $q['choices'] ?? ($q['options'] ?? ($q['answers'] ?? null));
        if (in_array($type, ['select_one', 'select_multiple', 'matrix'], true) || is_array($choices)) {
            if ($rawType === 'yes_no' || $rawType === 'boolean' || $rawType === 'oui_non') {
                $choices ??= $this->lang === 'en' ? ['Yes', 'No'] : ['Oui', 'Non'];
            }
            $list = $this->choiceList($choices, $out['name']);
            if ($list !== null) {
                $out['choices'] = $list;
                if (!in_array($type, ['select_one', 'select_multiple', 'matrix'], true)) {
                    $out['type'] = $type = 'select_one';
                }
            } elseif (in_array($type, ['select_one', 'select_multiple', 'matrix'], true)) {
                $out['type'] = $type = 'text'; // a choice question without any choice cannot be collected
            }
        }
        if (($q['allow_other'] ?? false) && in_array($type, ['select_one', 'select_multiple'], true)) {
            $out['allow_other'] = $this->bool($q['allow_other']);
        }
        if ($type === 'scale') {
            $out['scale'] = $this->scale(is_array($q['scale'] ?? null) ? $q['scale'] : $q, $rawType);
        }
        if ($type === 'matrix') {
            $rows = [];
            foreach (is_array($q['rows'] ?? null) ? $q['rows'] : [] as $row) {
                $rowLabel = $this->text(is_array($row) ? ($row['label'] ?? ($row['text'] ?? null)) : $row);
                if ($rowLabel === null) {
                    continue;
                }
                $rows[] = ['name' => $this->name(is_array($row) ? ($row['name'] ?? (string) reset($rowLabel)) : (string) reset($rowLabel), 'row'), 'label' => $rowLabel];
            }
            if ($rows === []) {
                $out['type'] = $type = 'select_one';
            } else {
                $out['rows'] = $rows;
            }
        }
        if ($type === 'calculate' && !isset($out['calculation'])) {
            $out['type'] = 'note';
        }
        if (is_array($q['analysis'] ?? null)) {
            $analysis = [];
            $role = is_string($q['analysis']['role'] ?? null) ? strtolower($q['analysis']['role']) : '';
            $role = in_array($role, self::ROLES, true) ? $role : (self::ROLE_SYNONYMS[$role] ?? '');
            if ($role !== '') {
                $analysis['role'] = $role;
            }
            if (is_string($q['analysis']['indicator'] ?? null) && $q['analysis']['indicator'] !== '') {
                $analysis['indicator'] = mb_substr($q['analysis']['indicator'], 0, 200);
            }
            foreach (['sensitive', 'personal_data'] as $flag) {
                if (isset($q['analysis'][$flag])) {
                    $analysis[$flag] = $this->bool($q['analysis'][$flag]);
                }
            }
            if ($analysis !== []) {
                $out['analysis'] = $analysis;
            }
        }
        // Anything else is kept, not lost.
        $extra = array_diff_key($q, array_flip([...self::QUESTION_KEYS, 'question', 'text', 'title', 'description', 'help', 'options', 'answers', 'id', 'min', 'max', 'min_label', 'max_label', 'preset']));
        if ($extra !== []) {
            $out['x_source'] = $extra + (is_array($q['x_source'] ?? null) ? $q['x_source'] : []);
        } elseif (is_array($q['x_source'] ?? null) && $q['x_source'] !== []) {
            $out['x_source'] = $q['x_source'];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $s
     *
     * @return array<string, mixed>
     */
    private function scale(array $s, string $rawType): array
    {
        $nps = $rawType === 'nps' || ($s['preset'] ?? null) === 'nps';
        $min = is_numeric($s['min'] ?? null) ? (int) $s['min'] : ($nps ? 0 : 1);
        $max = is_numeric($s['max'] ?? null) ? (int) $s['max'] : ($nps ? 10 : 5);
        if ($max <= $min || $max - $min > 100) {
            [$min, $max] = $nps ? [0, 10] : [1, 5];
        }
        $out = ['min' => $min, 'max' => $max];
        $preset = is_string($s['preset'] ?? null) ? $s['preset'] : ($nps ? 'nps' : null);
        if (in_array($preset, ['likert5', 'likert7', 'nps', 'stars5', 'custom'], true)) {
            $out['preset'] = $preset;
        }
        foreach (['min_label', 'max_label'] as $key) {
            $text = $this->text($s[$key] ?? null);
            if ($text !== null) {
                $out[$key] = $text;
            }
        }

        return $out;
    }

    /** Returns a list name, creating the list from inline choices when needed. */
    private function choiceList(mixed $choices, string $questionName): ?string
    {
        if (is_string($choices) && $choices !== '') {
            if (isset($this->lists[$choices])) {
                return $choices;
            }
            $parts = preg_split('/\s*[;|,]\s*/', $choices) ?: [];
            if (count($parts) < 2) {
                return Naming::isValidName($choices) ? $choices : null; // a list defined elsewhere in the form
            }
            $choices = $parts;
        }
        if (!is_array($choices) || $choices === []) {
            return null;
        }
        $isAssocMap = !array_is_list($choices) && !isset($choices['value']);
        $items = [];
        foreach ($choices as $key => $c) {
            if (is_array($c)) {
                $items[] = $c;
            } elseif (is_scalar($c)) {
                $items[] = $isAssocMap ? ['value' => (string) $key, 'label' => (string) $c] : ['label' => (string) $c];
            }
        }
        $name = $questionName . '_choices';
        for ($i = 2; isset($this->lists[$name]); ++$i) {
            $name = $questionName . '_choices_' . $i;
        }

        return $this->addList($name, $items) ? $name : null;
    }

    /** @param list<mixed>|array<mixed> $choices */
    private function addList(string $name, array $choices): bool
    {
        $name = Naming::isValidName($name) ? $name : (Naming::slug($name) ?: 'list');
        $clean = [];
        $seen = [];
        foreach ($choices as $i => $c) {
            if (!is_array($c)) {
                $c = ['label' => (string) $c];
            }
            $label = $this->text($c['label'] ?? ($c['text'] ?? ($c['name'] ?? ($c['value'] ?? null))));
            $explicit = $c['value'] ?? ($c['code'] ?? null);
            if (is_scalar($explicit) && (string) $explicit !== '') {
                $raw = (string) $explicit;
                $value = preg_match('/^[A-Za-z0-9_\-.]{1,64}$/', $raw) ? $raw : (Naming::slug($raw, 60) ?: (string) ($i + 1));
            } else {
                // Only labels were given: derive a clean, stable code from the label.
                $raw = $label !== null ? (string) reset($label) : (string) ($i + 1);
                $value = Naming::slug($raw, 60) ?: (string) ($i + 1);
            }
            for ($k = 2; isset($seen[$value]); ++$k) {
                $value = (Naming::slug($raw, 55) ?: 'v') . '_' . $k;
            }
            $seen[$value] = true;
            if ($value !== $raw) {
                $this->recoded[$name][$raw] = $value;
            }
            $item = ['value' => $value, 'label' => $label ?? [$this->lang => $raw]];
            if (is_string($c['filter'] ?? null)) {
                $item['filter'] = $c['filter'];
            }
            $clean[] = $item;
        }
        if ($clean === []) {
            return false;
        }
        $this->lists[$name] = $clean;

        return true;
    }

    /**
     * Updates 'old code' literals in expressions after codes were made ASCII.
     *
     * @param array<string, mixed> $node
     *
     * @return array<string, mixed>
     */
    private function recodeExpressions(array $node): array
    {
        $map = [];
        foreach ($this->recoded as $codes) {
            $map += $codes;
        }
        foreach (['relevant', 'constraint', 'calculation', 'choice_filter'] as $field) {
            if (is_string($node[$field] ?? null)) {
                $node[$field] = (string) preg_replace_callback("/'([^']*)'/", static fn(array $m): string => "'" . ($map[$m[1]] ?? $m[1]) . "'", $node[$field]);
            }
        }
        if (is_array($node['items'] ?? null)) {
            $node['items'] = array_map(fn($c) => is_array($c) ? $this->recodeExpressions($c) : $c, $node['items']);
        }

        return $node;
    }

    /** @return array<string, string>|null */
    private function text(mixed $value): ?array
    {
        if (is_string($value) || is_int($value) || is_float($value)) {
            $value = trim((string) $value);

            return $value === '' ? null : [$this->lang => mb_substr($value, 0, 2000)];
        }
        if (!is_array($value)) {
            return null;
        }
        $out = [];
        foreach ($value as $k => $v) {
            if (is_string($v) && trim($v) !== '') {
                $code = is_string($k) && preg_match('/^[a-z]{2,3}(-[A-Z]{2})?$/', $k) ? $k : $this->lang;
                $out[$code] = mb_substr(trim($v), 0, 2000);
            }
        }

        return $out === [] ? null : $out;
    }

    private function bool(mixed $v): bool
    {
        if (is_bool($v)) {
            return $v;
        }

        return in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes', 'oui', 'y', 'o', 'required', 'obligatoire', 'true()'], true);
    }

    private function name(mixed $raw, string $prefix): string
    {
        $raw = is_scalar($raw) ? (string) $raw : '';
        if (Naming::isValidName($raw)) {
            return $raw;
        }
        $slug = Naming::slug($raw, 40);

        return $slug === '' ? $prefix : (preg_match('/^[a-z_]/', $slug) ? $slug : $prefix . '_' . $slug);
    }
}
