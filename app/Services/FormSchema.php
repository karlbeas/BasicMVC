<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Read helpers over a decoded F2S Schema v1 form (associative arrays).
 */
final class FormSchema
{
    /** Types announced for phases 5–6: accepted by the schema, flagged as warnings. */
    public const NOT_YET_SUPPORTED_TYPES = ['file', 'image', 'geopoint'];

    /**
     * Flat projection used to fill the `questions` table. Matrix rows become
     * individual entries (type "matrix_row"), as they become select_one in XLSForm.
     *
     * @param array<string, mixed> $form
     *
     * @return list<array{name: string, type: string, path: string, label: string, choice_list: ?string, analysis_role: ?string, sensitive: bool, personal: bool}>
     */
    public static function questions(array $form): array
    {
        $lang = is_string($form['default_language'] ?? null) ? $form['default_language'] : 'fr';
        $out = [];
        self::walk(is_array($form['items'] ?? null) ? $form['items'] : [], '', $lang, $out);

        return $out;
    }

    /**
     * Number of answerable questions, used for plan quotas: notes are not counted,
     * each matrix row counts as one question.
     *
     * @param array<string, mixed> $form
     */
    public static function questionCount(array $form): int
    {
        $count = 0;
        foreach (self::questions($form) as $q) {
            if ($q['type'] !== 'note' && $q['type'] !== 'matrix') {
                ++$count;
            }
        }

        return $count;
    }

    /** @param array<string, mixed>|null $label */
    public static function label(?array $label, string $lang): string
    {
        if ($label === null || $label === []) {
            return '';
        }
        $value = $label[$lang] ?? reset($label);

        return is_string($value) ? $value : '';
    }

    /**
     * @param array<mixed> $items
     * @param list<array{name: string, type: string, path: string, label: string, choice_list: ?string, analysis_role: ?string, sensitive: bool, personal: bool}> $out
     */
    private static function walk(array $items, string $prefix, string $lang, array &$out): void
    {
        foreach ($items as $item) {
            if (!is_array($item) || !is_string($item['name'] ?? null)) {
                continue;
            }
            $path = $prefix === '' ? $item['name'] : $prefix . '/' . $item['name'];
            $kind = $item['kind'] ?? null;
            if ($kind === 'group' || $kind === 'repeat') {
                self::walk(is_array($item['items'] ?? null) ? $item['items'] : [], $path, $lang, $out);
                continue;
            }
            $analysis = is_array($item['analysis'] ?? null) ? $item['analysis'] : [];
            $entry = [
                'name' => $item['name'],
                'type' => is_string($item['type'] ?? null) ? $item['type'] : 'text',
                'path' => $path,
                'label' => self::label(is_array($item['label'] ?? null) ? $item['label'] : null, $lang),
                'choice_list' => is_string($item['choices'] ?? null) ? $item['choices'] : null,
                'analysis_role' => is_string($analysis['role'] ?? null) ? $analysis['role'] : null,
                'sensitive' => ($analysis['sensitive'] ?? false) === true,
                'personal' => ($analysis['personal_data'] ?? false) === true,
            ];
            $out[] = $entry;
            if ($entry['type'] === 'matrix') {
                foreach (is_array($item['rows'] ?? null) ? $item['rows'] : [] as $row) {
                    if (!is_array($row) || !is_string($row['name'] ?? null)) {
                        continue;
                    }
                    $out[] = [
                        'name' => $row['name'],
                        'type' => 'matrix_row',
                        'path' => $path . '/' . $row['name'],
                        'label' => self::label(is_array($row['label'] ?? null) ? $row['label'] : null, $lang),
                        'choice_list' => $entry['choice_list'],
                        'analysis_role' => $entry['analysis_role'],
                        'sensitive' => $entry['sensitive'],
                        'personal' => $entry['personal'],
                    ];
                }
            }
        }
    }
}
