<?php

declare(strict_types=1);

namespace App\Services\Collect;

use App\Models\SubmissionRepository;
use App\Services\FormSchema;

/** CSV / JSON exports of a campaign's responses (codes, not labels, like Kobo). */
final class ResponseExporter
{
    /** @param array<string, mixed> $form */
    public function __construct(private SubmissionRepository $submissions, private array $form) {}

    /**
     * Answer columns in form order: notes and matrix containers excluded,
     * "<name>_other" added after questions that allow "Other".
     *
     * @param array<string, mixed> $form
     *
     * @return list<array{name: string, label: string}>
     */
    public static function columns(array $form): array
    {
        $allowOther = [];
        self::collectOther(is_array($form['items'] ?? null) ? $form['items'] : [], $allowOther);
        $columns = [];
        foreach (FormSchema::questions($form) as $q) {
            if (in_array($q['type'], ['note', 'matrix'], true) || in_array($q['type'], FormSchema::NOT_YET_SUPPORTED_TYPES, true)) {
                continue;
            }
            $columns[] = ['name' => $q['name'], 'label' => $q['label']];
            if (isset($allowOther[$q['name']])) {
                $columns[] = ['name' => $q['name'] . '_other', 'label' => $q['label'] . ' (autre)'];
            }
        }

        return $columns;
    }

    public function csv(int $organizationId, int $campaignId, string $separator = ';'): string
    {
        $columns = self::columns($this->form);
        $out = fopen('php://temp', 'w+');
        if ($out === false) {
            throw new \RuntimeException('Cannot open temp stream');
        }
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens accents correctly
        fputcsv($out, ['submission_uuid', 'submitted_at_utc', 'form_version', 'language', 'duration_seconds', ...array_column($columns, 'name')], $separator, '"', '');
        foreach ($this->submissions->each($organizationId, $campaignId) as $row) {
            $data = json_decode((string) $row['data_json'], true, 16, JSON_THROW_ON_ERROR);
            $line = [$row['submission_uuid'], $row['submitted_at'], $row['version_number'], $row['language'], $row['duration_seconds']];
            foreach ($columns as $c) {
                $v = $data[$c['name']] ?? '';
                $line[] = self::cell(is_array($v) ? implode(' ', $v) : (string) $v);
            }
            fputcsv($out, array_map(static fn($v): string => (string) $v, $line), $separator, '"', '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    public function json(int $organizationId, int $campaignId): string
    {
        $items = [];
        foreach ($this->submissions->each($organizationId, $campaignId) as $row) {
            $items[] = [
                'submission_uuid' => $row['submission_uuid'],
                'submitted_at' => $row['submitted_at'] . 'Z',
                'form_version' => (int) $row['version_number'],
                'language' => $row['language'],
                'duration_seconds' => $row['duration_seconds'] === null ? null : (int) $row['duration_seconds'],
                'answers' => json_decode((string) $row['data_json'], false, 16, JSON_THROW_ON_ERROR),
            ];
        }

        return json_encode(['form_id' => $this->form['settings']['form_id'] ?? null, 'count' => count($items), 'submissions' => $items], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /** Neutralizes spreadsheet formula injection (=, +, -, @ at the start of a cell). */
    public static function cell(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && !is_numeric($value) ? "'" . $value : $value;
    }

    /**
     * @param array<mixed> $items
     * @param array<string, true> $out
     */
    private static function collectOther(array $items, array &$out): void
    {
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (($item['allow_other'] ?? false) === true && is_string($item['name'] ?? null)) {
                $out[$item['name']] = true;
            }
            if (is_array($item['items'] ?? null)) {
                self::collectOther($item['items'], $out);
            }
        }
    }
}
