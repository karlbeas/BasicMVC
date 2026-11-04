<?php

declare(strict_types=1);

namespace App\Services\Bridge;

/**
 * A plain list of questions (Excel/CSV, one question per row) → F2S Schema v1.
 * Columns are recognised by name (FR/EN); missing types are inferred and reported.
 */
final class QuestionListConverter
{
    private const COLUMNS = [
        'label' => ['question', 'questions', 'libelle', 'label', 'intitule', 'enonce', 'texte', 'text', 'question text'],
        'type' => ['type', 'type de question', 'format', 'question type', 'type de reponse'],
        'choices' => ['choix', 'choices', 'options', 'modalites', 'reponses', 'reponses possibles', 'answers', 'choix de reponse', 'valeurs'],
        'required' => ['obligatoire', 'required', 'requis', 'mandatory'],
        'name' => ['nom', 'name', 'variable', 'code', 'identifiant', 'id'],
        'hint' => ['aide', 'hint', 'consigne', 'instruction', 'help'],
        'section' => ['section', 'groupe', 'group', 'partie', 'module', 'theme', 'rubrique'],
    ];
    private const TYPES = [
        'long_text' => ['texte long', 'long', 'paragraphe', 'commentaire', 'long text', 'long_text', 'textarea', 'texte libre long'],
        'text' => ['texte', 'text', 'court', 'texte court', 'short text', 'libre', 'ouverte', 'open'],
        'integer' => ['entier', 'integer', 'nombre', 'number', 'numerique', 'numeric', 'nombre entier', 'int', 'age'],
        'decimal' => ['decimal', 'montant', 'reel', 'nombre decimal', 'float', 'prix', 'amount'],
        'date' => ['date'],
        'time' => ['heure', 'time'],
        'yes_no' => ['oui/non', 'oui-non', 'yes/no', 'yes-no', 'booleen', 'boolean', 'oui non'],
        'select_multiple' => ['choix multiple', 'choix multiples', 'select_multiple', 'cases a cocher', 'checkbox', 'checkboxes', 'multiple', 'qcm multiple', 'multi'],
        'select_one' => ['choix unique', 'select_one', 'liste', 'radio', 'liste deroulante', 'dropdown', 'qcm', 'single choice', 'choix'],
        'nps' => ['nps', 'recommandation'],
        'scale' => ['echelle', 'scale', 'likert', 'note', 'notation', 'rating', 'satisfaction', 'etoiles', 'stars'],
        'email' => ['email', 'e-mail', 'courriel', 'mail'],
        'phone' => ['telephone', 'phone', 'tel', 'mobile', 'portable'],
        'note' => ['information', 'note informative', 'texte informatif', 'info', 'intro'],
    ];

    /**
     * @param list<list<string>> $rows header row first
     *
     * @return array{0: array<string, mixed>, 1: ConversionReport}
     */
    public function convert(array $rows, string $filename, string $format, string $language = 'fr'): array
    {
        $report = new ConversionReport($format, $filename);
        if (count($rows) < 2) {
            throw new ImportException('empty', 'Header and at least one question are required');
        }
        $headers = array_map([Naming::class, 'normalizeHeader'], array_shift($rows));
        $map = [];
        foreach (self::COLUMNS as $field => $aliases) {
            foreach ($headers as $i => $h) {
                if (in_array($h, $aliases, true) && !in_array($i, $map, true)) {
                    $map[$field] = $i;
                    break;
                }
            }
        }
        if (!isset($map['label'])) {
            $map['label'] = 0;
            $report->transformed('colonne « ' . ($headers[0] ?? '?') . ' »', 'aucune colonne « question » reconnue : première colonne utilisée comme libellé');
        }
        foreach ($headers as $i => $h) {
            if ($h !== '' && !in_array($i, $map, true)) {
                $report->lost('colonne « ' . $h . ' »', 'colonne non reconnue, non reprise');
            }
        }

        $names = new Naming();
        $listNames = new Naming();
        $lists = [];
        $items = [];
        $currentSection = null;
        $sectionIndex = -1;
        $width = count($headers);
        foreach ($rows as $n => $row) {
            $line = $n + 2;
            $overflow = array_filter(array_slice($row, $width), static fn($v): bool => trim((string) $v) !== '');
            if ($overflow !== []) {
                $report->lost("ligne {$line}", 'cellules au-delà des colonnes d\'en-tête non reprises : « ' . implode(' », « ', $overflow) . ' » (séparateur ou guillemets à vérifier)');
            }
            $cell = static fn(string $field): string => isset($map[$field]) ? trim((string) ($row[$map[$field]] ?? '')) : '';
            $label = $cell('label');
            if ($label === '') {
                if (implode('', $row) !== '') {
                    $report->lost("ligne {$line}", 'libellé de question vide');
                }
                continue;
            }
            $q = ['kind' => 'question', 'type' => 'text', 'name' => '', 'label' => [$language => mb_substr($label, 0, 2000)]];
            $rawName = $cell('name');
            $q['name'] = $names->unique($rawName !== '' ? $rawName : (Naming::slug($label, 30) ?: 'q' . $line), 'q');
            if ($rawName !== '' && $q['name'] !== $rawName) {
                $report->transformed("identifiant « {$rawName} »", 'renommé « ' . $q['name'] . ' »');
            }
            $choices = $this->splitChoices($cell('choices'));
            $typeRaw = Naming::normalizeHeader($cell('type'));
            $type = $this->type($typeRaw);
            if ($typeRaw !== '' && $type === null) {
                $report->transformed($q['name'], "type « {$cell('type')} » non reconnu : " . ($choices !== [] ? 'choix unique' : 'texte court') . ' utilisé');
            }
            if ($type === null) {
                $type = $choices !== [] ? 'select_one' : 'text';
                if ($typeRaw === '') {
                    $report->transformed($q['name'], $choices !== [] ? 'type déduit : choix unique (des choix sont listés)' : 'type déduit : texte court');
                }
            }
            if ($type === 'yes_no') {
                $type = 'select_one';
                $choices = $choices !== [] ? $choices : ($language === 'en' ? ['Yes', 'No'] : ['Oui', 'Non']);
            }
            if ($type === 'nps') {
                $q['scale'] = ['min' => 0, 'max' => 10, 'preset' => 'nps'];
                $type = 'scale';
            } elseif ($type === 'scale') {
                $q['scale'] = ['min' => 1, 'max' => 5, 'preset' => 'likert5'];
                if ($choices !== []) {
                    $report->transformed($q['name'], 'échelle 1 à 5 : les choix listés ne sont pas repris (utilisez « choix unique » pour garder les libellés)');
                }
            }
            if (in_array($type, ['select_one', 'select_multiple'], true)) {
                if ($choices === []) {
                    $report->transformed($q['name'], 'question à choix sans liste de choix : convertie en texte court');
                    $type = 'text';
                } else {
                    $listName = $listNames->unique($q['name'] . '_choices', 'list');
                    // Choice codes may start with a digit (unlike question names): own uniqueness rule.
                    $used = [];
                    $lists[$listName] = array_map(static function (string $c, int $i) use (&$used, $language): array {
                        $base = Naming::slug($c, 40) !== '' ? Naming::slug($c, 40) : (string) ($i + 1);
                        $value = $base;
                        for ($k = 2; isset($used[$value]); ++$k) {
                            $value = $base . '_' . $k;
                        }
                        $used[$value] = true;

                        return ['value' => $value, 'label' => [$language => mb_substr($c, 0, 2000)]];
                    }, $choices, array_keys($choices));
                    $q['choices'] = $listName;
                }
            }
            $q['type'] = $type;
            if (in_array(Naming::normalizeHeader($cell('required')), ['oui', 'yes', 'true', '1', 'x', 'obligatoire', 'required', 'o', 'y'], true) && $type !== 'note') {
                $q['required'] = true;
            }
            if ($cell('hint') !== '') {
                $q['hint'] = [$language => mb_substr($cell('hint'), 0, 2000)];
            }
            $report->kept($q['name'], $type);

            $section = $cell('section');
            if ($section !== '' && $section !== $currentSection) {
                $currentSection = $section;
                $items[] = ['kind' => 'group', 'name' => $names->unique(Naming::slug($section, 30) ?: 'section', 'section'), 'label' => [$language => mb_substr($section, 0, 2000)], 'page' => true, 'items' => []];
                $sectionIndex = count($items) - 1;
            }
            if ($section !== '' && $sectionIndex >= 0) {
                $items[$sectionIndex]['items'][] = $q;
            } else {
                $currentSection = null;
                $items[] = $q;
            }
        }
        if ($items === []) {
            throw new ImportException('empty', 'No question found');
        }
        $title = pathinfo($filename, PATHINFO_FILENAME);

        return [[
            'schema_version' => '1.0',
            'settings' => ['form_id' => (new Naming())->unique($title, 'form'), 'title' => [$language => $title], 'sampling' => 'unknown'],
            'languages' => [$language],
            'default_language' => $language,
            'choice_lists' => $lists === [] ? new \stdClass() : $lists,
            'items' => $items,
        ], $report];
    }

    /** @return list<string> */
    private function splitChoices(string $raw): array
    {
        if ($raw === '') {
            return [];
        }
        $parts = preg_split('/\s*(?:\R|;|\|)\s*/', $raw) ?: [];
        if (count($parts) === 1 && str_contains($raw, ',')) {
            $parts = preg_split('/\s*,\s*/', $raw) ?: [];
        }

        return array_values(array_unique(array_filter(array_map('trim', $parts), static fn(string $p): bool => $p !== '')));
    }

    private function type(string $normalized): ?string
    {
        if ($normalized === '') {
            return null;
        }
        foreach (self::TYPES as $type => $words) {
            if (in_array($normalized, $words, true)) {
                return $type;
            }
        }
        foreach (self::TYPES as $type => $words) {
            foreach ($words as $w) {
                if (strlen($w) > 3 && str_contains($normalized, $w)) {
                    return $type;
                }
            }
        }

        return null;
    }
}
