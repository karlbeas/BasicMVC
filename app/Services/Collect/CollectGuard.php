<?php

declare(strict_types=1);

namespace App\Services\Collect;

use App\Services\FormSchema;

/** What a form must satisfy before it can be opened for responses. */
final class CollectGuard
{
    /**
     * @param array<string, mixed> $form
     *
     * @return list<array{path: string, problem: string}>
     */
    public static function blockers(array $form): array
    {
        $problems = [];
        $evaluator = new ExpressionEvaluator();
        self::walk(is_array($form['items'] ?? null) ? $form['items'] : [], '', $evaluator, $problems);

        return $problems;
    }

    /**
     * @param array<mixed> $items
     * @param list<array{path: string, problem: string}> $problems
     */
    private static function walk(array $items, string $prefix, ExpressionEvaluator $evaluator, array &$problems): void
    {
        foreach ($items as $item) {
            if (!is_array($item) || !is_string($item['name'] ?? null)) {
                continue;
            }
            $path = $prefix === '' ? $item['name'] : $prefix . '/' . $item['name'];
            if (($item['kind'] ?? '') === 'repeat') {
                $problems[] = ['path' => $path, 'problem' => 'repeat'];
            }
            if (in_array($item['type'] ?? '', FormSchema::NOT_YET_SUPPORTED_TYPES, true)) {
                $problems[] = ['path' => $path, 'problem' => 'type:' . $item['type']];
            }
            foreach (['relevant', 'constraint', 'calculation'] as $field) {
                $expression = is_string($item[$field] ?? null) ? trim($item[$field]) : '';
                if ($expression === '') {
                    continue;
                }
                try {
                    $evaluator->evaluate($expression, [], '');
                } catch (UnsupportedExpressionException $e) {
                    $problems[] = ['path' => $path . ' (' . $field . ')', 'problem' => 'expression:' . $e->getMessage()];
                }
            }
            if (is_array($item['items'] ?? null)) {
                self::walk($item['items'], $path, $evaluator, $problems);
            }
        }
    }
}
