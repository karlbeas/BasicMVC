<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Services\Bridge\Naming;

/**
 * Builds a full F2S Schema v1 form from sections generated one by one (short AI
 * calls that fit shared-hosting time limits). Resolves collisions deterministically:
 * duplicate question names are renamed (references updated inside the section),
 * identical choice lists are shared, different lists with the same name are renamed.
 */
final class FormAssembler
{
    /**
     * Normalizes one section answer: {"choice_lists": {...}, "group": {...}} (tolerates a bare group).
     *
     * @param array<string, mixed> $answer
     *
     * @return array{group: array<string, mixed>, choice_lists: array<string, mixed>}|null
     */
    public static function section(array $answer, string $fallbackName, string $fallbackLabel, string $lang): ?array
    {
        $group = is_array($answer['group'] ?? null) ? $answer['group'] : (($answer['kind'] ?? null) === 'group' ? $answer : null);
        if ($group === null && is_array($answer['items'] ?? null)) {
            $group = ['items' => $answer['items']];
        }
        if ($group === null || !is_array($group['items'] ?? null) || $group['items'] === []) {
            return null;
        }
        $group['kind'] = 'group';
        $group['name'] = is_string($group['name'] ?? null) && Naming::isValidName($group['name']) ? $group['name'] : (Naming::slug($fallbackName) ?: 'section');
        if (!is_array($group['label'] ?? null) || $group['label'] === []) {
            $group['label'] = [$lang => $fallbackLabel];
        }
        $group['page'] = true;
        $lists = is_array($answer['choice_lists'] ?? null) ? $answer['choice_lists'] : [];

        return ['group' => $group, 'choice_lists' => $lists];
    }

    /**
     * @param list<array{group: array<string, mixed>, choice_lists: array<string, mixed>}> $sections
     * @param array<string, mixed> $settings
     * @param list<string> $languages
     *
     * @return array<string, mixed>
     */
    public static function assemble(array $sections, array $settings, array $languages): array
    {
        $used = [];
        $lists = [];
        $items = [];
        foreach ($sections as $section) {
            $group = $section['group'];
            // 1. Choice lists: share identical ones, rename conflicting ones.
            $listRename = [];
            foreach ($section['choice_lists'] as $name => $choices) {
                if (!is_array($choices) || $choices === []) {
                    continue;
                }
                $name = (string) $name;
                $target = Naming::isValidName($name) ? $name : (Naming::slug($name) ?: 'list');
                if (isset($lists[$target]) && $lists[$target] != $choices) {
                    $base = $target;
                    for ($i = 2; isset($lists[$target]) && $lists[$target] != $choices; ++$i) {
                        $target = $base . '_' . $i;
                    }
                }
                $lists[$target] = $choices;
                if ($target !== $name) {
                    $listRename[$name] = $target;
                }
            }
            // 2. Names: unique across the whole form.
            $nameRename = [];
            $group = self::renameNames($group, $used, $nameRename, $listRename);
            if ($nameRename !== []) {
                $group = self::rewriteRefs($group, $nameRename);
            }
            $items[] = $group;
        }

        return [
            'schema_version' => '1.0',
            'settings' => $settings,
            'languages' => $languages,
            'default_language' => $languages[0],
            'choice_lists' => $lists === [] ? new \stdClass() : $lists,
            'items' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, true> $used
     * @param array<string, string> $nameRename
     * @param array<string, string> $listRename
     *
     * @return array<string, mixed>
     */
    private static function renameNames(array $node, array &$used, array &$nameRename, array $listRename): array
    {
        $name = is_string($node['name'] ?? null) ? $node['name'] : '';
        $unique = self::unique($name !== '' ? $name : 'q', $used);
        if ($unique !== $name && $name !== '') {
            $nameRename[$name] = $unique;
        }
        $node['name'] = $unique;
        if (is_string($node['choices'] ?? null) && isset($listRename[$node['choices']])) {
            $node['choices'] = $listRename[$node['choices']];
        }
        if (is_array($node['rows'] ?? null)) {
            foreach ($node['rows'] as $i => $row) {
                if (is_array($row)) {
                    $rowName = is_string($row['name'] ?? null) ? $row['name'] : 'row';
                    $node['rows'][$i]['name'] = self::unique($rowName, $used);
                }
            }
        }
        if (is_array($node['items'] ?? null)) {
            foreach ($node['items'] as $i => $child) {
                if (is_array($child)) {
                    $node['items'][$i] = self::renameNames($child, $used, $nameRename, $listRename);
                }
            }
        }

        return $node;
    }

    /** @param array<string, true> $used */
    private static function unique(string $name, array &$used): string
    {
        $base = Naming::isValidName($name) ? $name : (Naming::slug($name) ?: 'q');
        if (!preg_match('/^[A-Za-z_]/', $base)) {
            $base = 'q_' . $base;
        }
        $candidate = $base;
        for ($i = 2; isset($used[strtolower($candidate)]); ++$i) {
            $candidate = $base . '_' . $i;
        }
        $used[strtolower($candidate)] = true;

        return $candidate;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, string> $map
     *
     * @return array<string, mixed>
     */
    private static function rewriteRefs(array $node, array $map): array
    {
        foreach (['relevant', 'constraint', 'calculation', 'choice_filter'] as $field) {
            if (is_string($node[$field] ?? null)) {
                $node[$field] = (string) preg_replace_callback('/\$\{([^}]+)\}/', static fn(array $m): string => '${' . ($map[$m[1]] ?? $m[1]) . '}', $node[$field]);
            }
        }
        if (is_array($node['items'] ?? null)) {
            foreach ($node['items'] as $i => $child) {
                if (is_array($child)) {
                    $node['items'][$i] = self::rewriteRefs($child, $map);
                }
            }
        }

        return $node;
    }

    /**
     * Deterministic fixes of common AI slips, applied before any (long) repair call:
     * missing choice list → free text, dangling ${ref} → condition removed,
     * duplicate choice codes → deduplicated, label missing in the default language → copied.
     *
     * @param array<string, mixed> $form
     *
     * @return array<string, mixed>
     */
    public static function autofix(array $form): array
    {
        $lang = is_string($form['default_language'] ?? null) ? $form['default_language'] : 'fr';
        $lists = is_array($form['choice_lists'] ?? null) ? $form['choice_lists'] : [];
        foreach ($lists as $name => $choices) {
            $seen = [];
            $clean = [];
            foreach (is_array($choices) ? $choices : [] as $c) {
                $value = is_array($c) ? (string) ($c['value'] ?? '') : '';
                if ($value === '' || isset($seen[$value])) {
                    continue;
                }
                $seen[$value] = true;
                $c['label'] = self::labelFix(is_array($c['label'] ?? null) ? $c['label'] : [], $lang, $value);
                $clean[] = $c;
            }
            $lists[$name] = $clean;
        }
        $lists = array_filter($lists, static fn($c): bool => $c !== []);
        $names = [];
        $collect = static function (array $node) use (&$collect, &$names): void {
            if (is_string($node['name'] ?? null)) {
                $names[$node['name']] = true;
            }
            foreach (['items', 'rows'] as $key) {
                foreach (is_array($node[$key] ?? null) ? $node[$key] : [] as $child) {
                    if (is_array($child)) {
                        $collect($child);
                    }
                }
            }
        };
        foreach (is_array($form['items'] ?? null) ? $form['items'] : [] as $item) {
            if (is_array($item)) {
                $collect($item);
            }
        }
        $fix = static function (array $node) use (&$fix, $lists, $names, $lang): array {
            if (($node['kind'] ?? '') === 'question' && is_string($node['choices'] ?? null) && !isset($lists[$node['choices']])) {
                $node['type'] = 'text';
                unset($node['choices'], $node['rows'], $node['allow_other'], $node['choice_filter']);
            }
            foreach (['relevant', 'constraint', 'calculation', 'choice_filter'] as $field) {
                if (is_string($node[$field] ?? null)) {
                    preg_match_all('/\$\{([^}]*)\}/', $node[$field], $m);
                    foreach ($m[1] as $ref) {
                        if (!isset($names[$ref])) {
                            unset($node[$field]);
                            break;
                        }
                    }
                }
            }
            if (($node['type'] ?? '') === 'calculate' && !isset($node['calculation'])) {
                $node['type'] = 'note';
            }
            if (isset($node['label']) || ($node['kind'] ?? '') === 'question') {
                $node['label'] = self::labelFix(is_array($node['label'] ?? null) ? $node['label'] : [], $lang, (string) ($node['name'] ?? ''));
            }
            foreach (['items', 'rows'] as $key) {
                if (is_array($node[$key] ?? null)) {
                    foreach ($node[$key] as $i => $child) {
                        if (is_array($child)) {
                            $node[$key][$i] = $key === 'rows' ? ['name' => $child['name'] ?? 'row', 'label' => self::labelFix(is_array($child['label'] ?? null) ? $child['label'] : [], $lang, (string) ($child['name'] ?? ''))] : $fix($child);
                        }
                    }
                }
            }

            return $node;
        };
        $form['items'] = array_map(static fn($item) => is_array($item) ? $fix($item) : $item, is_array($form['items'] ?? null) ? $form['items'] : []);
        $form['choice_lists'] = $lists === [] ? new \stdClass() : $lists;

        return $form;
    }

    /**
     * @param array<string, mixed> $label
     *
     * @return array<string, mixed>
     */
    private static function labelFix(array $label, string $lang, string $fallback): array
    {
        if (trim((string) ($label[$lang] ?? '')) === '') {
            $other = array_values(array_filter($label, static fn($v): bool => is_string($v) && trim($v) !== ''));
            $label[$lang] = $other[0] ?? ($fallback !== '' ? $fallback : '…');
        }

        return $label;
    }
}
