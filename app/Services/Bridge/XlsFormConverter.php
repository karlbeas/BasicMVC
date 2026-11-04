<?php

declare(strict_types=1);

namespace App\Services\Bridge;

/**
 * XLSForm (Kobo, ODK, SurveyCTO) → F2S Schema v1. Every element that is not
 * carried over unchanged is listed in the ConversionReport: no silent conversion.
 */
final class XlsFormConverter
{
    private const METADATA = ['start', 'end', 'today', 'deviceid', 'phonenumber', 'username', 'email', 'audit', 'simserial', 'subscriberid',
        'start-geopoint', 'background-audio', 'background-geopoint', 'uri:email', 'uri:deviceid', 'uri:username', 'uri:phonenumber', 'uri:subscriberid', 'uri:simserial'];
    private const DIRECT = ['integer' => 'integer', 'decimal' => 'decimal', 'date' => 'date', 'time' => 'time', 'datetime' => 'datetime',
        'note' => 'note', 'calculate' => 'calculate', 'geopoint' => 'geopoint', 'image' => 'image', 'file' => 'file'];
    private const KNOWN_COLUMNS = ['type', 'name', 'label', 'hint', 'required', 'required_message', 'relevant', 'constraint', 'constraint_message',
        'calculation', 'appearance', 'default', 'read_only', 'choice_filter', 'parameters', 'repeat_count'];

    private ConversionReport $report;
    private Naming $names;
    /** @var array<string, string> original XLSForm name => F2S name (first occurrence only) */
    private array $renamed = [];
    /** @var array<string, true> original names already met */
    private array $seenRaw = [];
    /** @var list<string> language codes in column order */
    private array $languages = [];
    /** @var array<string, string> raw column language suffix => code */
    private array $langMap = [];
    private string $defaultLanguage = 'fr';
    /** @var array<string, string> raw list name => F2S list name */
    private array $listKeys = [];
    private Naming $listNames;

    /**
     * @param list<list<string>> $survey
     * @param list<list<string>> $choices
     * @param list<list<string>> $settings
     *
     * @return array{0: array<string, mixed>, 1: ConversionReport}
     */
    public function convert(array $survey, array $choices, array $settings, string $filename): array
    {
        $this->report = new ConversionReport('xlsform', $filename);
        $this->names = new Naming();
        $this->listNames = new Naming();
        $this->listKeys = [];
        $this->renamed = [];
        $this->seenRaw = [];
        if (count($survey) < 2) {
            throw new ImportException('empty', 'The survey sheet has no rows');
        }
        $settingsRow = $this->settings($settings);
        $surveyRows = self::assoc($survey);
        $choiceRows = self::assoc($choices);
        $this->detectLanguages(array_merge(array_keys($surveyRows[0] ?? []), array_keys($choiceRows[0] ?? [])), (string) ($settingsRow['default_language'] ?? ''));

        $lists = $this->choiceLists($choiceRows);
        $items = $this->items($surveyRows, $lists);
        if ($items === []) {
            throw new ImportException('empty', 'No convertible question');
        }
        $items = $this->rewriteReferences($items);

        $title = $this->label($settingsRow, 'form_title');
        if ($title === []) {
            $title = [$this->defaultLanguage => pathinfo($filename, PATHINFO_FILENAME)];
            $this->report->transformed('settings.form_title', 'titre absent : nom du fichier utilisé');
        }
        $formIdRaw = (string) ($settingsRow['form_id'] ?? '');
        $formId = Naming::isValidName($formIdRaw) ? $formIdRaw : ((new Naming())->unique($formIdRaw !== '' ? $formIdRaw : pathinfo($filename, PATHINFO_FILENAME), 'form'));
        if ($formIdRaw !== '' && $formId !== $formIdRaw) {
            $this->report->transformed('settings.form_id', "« {$formIdRaw} » renommé « {$formId} »");
        }
        if (($settingsRow['version'] ?? '') !== '') {
            $this->report->transformed('settings.version', 'la version est recalculée par F2S à chaque publication');
        }
        foreach ($settingsRow as $key => $value) {
            if (!in_array($key, ['form_title', 'form_id', 'version', 'default_language'], true) && !str_starts_with($key, 'form_title::') && $value !== '') {
                $this->report->lost('settings.' . $key, 'paramètre XLSForm non repris');
            }
        }
        $usedLists = [];
        array_walk_recursive($items, static function ($v, $k) use (&$usedLists): void {
            if ($k === 'choices' && is_string($v)) {
                $usedLists[$v] = true;
            }
        });
        foreach (array_keys($lists) as $listName) {
            if (!isset($usedLists[$listName])) {
                unset($lists[$listName]);
                $this->report->lost('choices.' . $listName, 'liste non utilisée par le formulaire');
            }
        }

        $form = [
            'schema_version' => '1.0',
            'settings' => ['form_id' => $formId, 'title' => $title, 'sampling' => 'unknown'],
            'languages' => $this->languages,
            'default_language' => $this->defaultLanguage,
            'choice_lists' => $lists === [] ? new \stdClass() : $lists,
            'items' => $items,
        ];

        return [$form, $this->report];
    }

    /**
     * @param list<list<string>> $rows
     *
     * @return list<array<string, string>> rows keyed by lower-cased header
     */
    public static function assoc(array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $headers = array_map(static fn(string $h): string => strtolower(trim($h)), array_shift($rows));
        $out = [];
        foreach ($rows as $row) {
            $assoc = [];
            foreach ($headers as $i => $h) {
                if ($h !== '') {
                    $assoc[$h] = trim((string) ($row[$i] ?? ''));
                }
            }
            if (implode('', $assoc) !== '') {
                $out[] = $assoc;
            }
        }

        return $out;
    }

    /**
     * @param list<list<string>> $settings
     *
     * @return array<string, string>
     */
    private function settings(array $settings): array
    {
        $rows = self::assoc($settings);

        return $rows[0] ?? [];
    }

    /** @param list<string> $columns */
    private function detectLanguages(array $columns, string $defaultSetting): void
    {
        $this->languages = [];
        $this->langMap = [];
        foreach ($columns as $col) {
            if (preg_match('/^(?:label|hint|constraint_message|required_message|form_title)::(.+)$/', $col, $m)) {
                $raw = trim($m[1]);
                if (!isset($this->langMap[$raw])) {
                    $code = $this->languageCode($raw);
                    $this->langMap[$raw] = $code;
                    if (!in_array($code, $this->languages, true)) {
                        $this->languages[] = $code;
                    }
                }
            }
        }
        $default = $defaultSetting !== '' ? $this->languageCode($defaultSetting) : '';
        if ($default === '') {
            $default = $this->languages[0] ?? 'fr';
        }
        if (!in_array($default, $this->languages, true)) {
            array_unshift($this->languages, $default);
        }
        $this->defaultLanguage = $default;
    }

    private function languageCode(string $raw): string
    {
        if (preg_match('/\(([a-z]{2,3})(?:-[A-Za-z]{2})?\)\s*$/i', $raw, $m)) {
            return strtolower($m[1]);
        }
        $n = Naming::normalizeHeader($raw);
        $map = ['french' => 'fr', 'francais' => 'fr', 'fr' => 'fr', 'english' => 'en', 'anglais' => 'en', 'en' => 'en', 'spanish' => 'es', 'espanol' => 'es',
            'portuguese' => 'pt', 'portugues' => 'pt', 'german' => 'de', 'deutsch' => 'de', 'arabic' => 'ar', 'swahili' => 'sw', 'wolof' => 'wo', 'fulfulde' => 'ff', 'hausa' => 'ha', 'lingala' => 'ln'];
        $code = $map[$n] ?? (preg_match('/^[a-z]{2,3}$/', $n) ? $n : substr(Naming::slug($n), 0, 2));
        $code = preg_match('/^[a-z]{2,3}$/', $code) ? $code : 'xx';
        if (!preg_match('/^[a-z]{2,3}$/', $raw)) {
            $this->report->transformed('langue « ' . $raw . ' »', "interprétée comme le code « {$code} »");
        }

        return $code;
    }

    /**
     * Multilingual text of a column ("label" or "label::English (en)" …).
     *
     * @param array<string, string> $row
     *
     * @return array<string, string>
     */
    private function label(array $row, string $column): array
    {
        $out = [];
        if (($row[$column] ?? '') !== '') {
            $out[$this->defaultLanguage] = $row[$column];
        }
        foreach ($row as $key => $value) {
            if ($value !== '' && str_starts_with($key, $column . '::')) {
                $raw = trim(substr($key, strlen($column) + 2));
                $out[$this->langMap[$raw] ?? $this->languageCode($raw)] = $value;
            }
        }

        return $out;
    }

    /**
     * @param list<array<string, string>> $rows
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function choiceLists(array $rows): array
    {
        $lists = [];
        $seen = [];
        foreach ($rows as $i => $row) {
            $rawList = $row['list_name'] ?? $row['list name'] ?? '';
            $rawValue = $row['name'] ?? $row['value'] ?? '';
            if ($rawList === '' || $rawValue === '') {
                $this->report->lost('choices ligne ' . ($i + 2), 'liste ou valeur manquante');
                continue;
            }
            $list = $this->listKey($rawList);
            $value = Naming::isValidValue($rawValue) ? $rawValue : (Naming::slug($rawValue, 60) !== '' ? Naming::slug($rawValue, 60) : 'v' . ($i + 1));
            if (isset($seen[$list][$value])) {
                $this->report->lost("choix {$rawList}/{$rawValue}", 'valeur en double dans la liste');
                continue;
            }
            if ($value !== $rawValue) {
                $this->report->transformed("choix {$rawList}/{$rawValue}", "code changé en « {$value} » (vérifiez les conditions qui l'utilisent)");
            }
            $seen[$list][$value] = true;
            $label = $this->label($row, 'label');
            if ($label === []) {
                $label = [$this->defaultLanguage => $rawValue];
                $this->report->transformed("choix {$rawList}/{$rawValue}", 'libellé absent : valeur utilisée comme libellé');
            }
            $choice = ['value' => $value, 'label' => $label];
            $extra = array_diff(array_keys($row), ['list_name', 'list name', 'name', 'value', 'label', 'order']);
            $extra = array_values(array_filter($extra, static fn(string $k): bool => !str_starts_with($k, 'label::') && !str_starts_with($k, 'media::') && !str_starts_with($k, 'image') && ($row[$k] ?? '') !== ''));
            if ($extra !== []) {
                $choice['filter'] = $row[$extra[0]];
                if (count($extra) > 1) {
                    $this->report->lost("choix {$rawList}/{$rawValue}", 'colonnes ' . implode(', ', array_slice($extra, 1)) . ' non reprises (une seule colonne de filtre)');
                }
            }
            $lists[$list][] = $choice;
        }

        return $lists;
    }

    private function listKey(string $raw): string
    {
        if (!isset($this->listKeys[$raw])) {
            $this->listKeys[$raw] = $this->listNames->unique($raw, 'list');
            if ($this->listKeys[$raw] !== $raw) {
                $this->report->transformed('liste « ' . $raw . ' »', 'renommée « ' . $this->listKeys[$raw] . ' »');
            }
        }

        return $this->listKeys[$raw];
    }

    /**
     * @param list<array<string, string>> $rows
     * @param array<string, list<array<string, mixed>>> $lists
     *
     * @return list<array<string, mixed>>
     */
    private function items(array $rows, array &$lists): array
    {
        $root = [];
        $stack = [&$root];
        $groups = [];
        foreach ($rows as $i => $row) {
            $line = $i + 2;
            $rawType = trim(preg_replace('/\s+/', ' ', $row['type'] ?? '') ?? '');
            $type = strtolower($rawType);
            $rawName = $row['name'] ?? '';
            if ($type === '') {
                if ($rawName !== '' || ($row['label'] ?? '') !== '') {
                    $this->report->lost("ligne {$line}", 'type manquant');
                }
                continue;
            }
            // Groups and repeats
            if (preg_match('/^begin[ _](group|repeat)$/', $type, $m)) {
                $group = ['kind' => $m[1] === 'repeat' ? 'repeat' : 'group', 'name' => $this->name($rawName !== '' ? $rawName : 'group_' . $line, 'g'), 'items' => []];
                $label = $this->label($row, 'label');
                if ($label !== []) {
                    $group['label'] = $label;
                }
                foreach (['relevant', 'appearance'] as $col) {
                    if (($row[$col] ?? '') !== '') {
                        $group[$col] = $row[$col];
                    }
                }
                if (str_contains($row['appearance'] ?? '', 'field-list')) {
                    $group['page'] = true;
                }
                if ($m[1] === 'repeat') {
                    if (($row['repeat_count'] ?? '') !== '') {
                        $group['repeat_count'] = $row['repeat_count'];
                    }
                    $this->report->unsupported("groupe répété {$rawName}", 'repris tel quel, mais pas encore collectable par F2S (phase 4)');
                } else {
                    $this->report->kept("section {$rawName}");
                }
                $current = &$stack[count($stack) - 1];
                $current[] = $group;
                $index = count($current) - 1;
                $stack[] = &$current[$index]['items'];
                $groups[] = $line;
                unset($current);
                continue;
            }
            if (preg_match('/^end[ _](group|repeat)$/', $type)) {
                if (count($stack) > 1) {
                    array_pop($stack);
                    array_pop($groups);
                } else {
                    $this->report->lost("ligne {$line}", 'fin de groupe sans début');
                }
                continue;
            }
            if (in_array($type, self::METADATA, true)) {
                $this->report->lost("{$rawName} ({$type})", 'métadonnée de collecte mobile non reprise (F2S enregistre lui-même la date et la durée)');
                continue;
            }
            $question = $this->question($row, $type, $rawType, $rawName, $line, $lists);
            if ($question !== null) {
                $stack[count($stack) - 1][] = $question;
            }
        }
        if (count($stack) > 1) {
            $this->report->transformed('groupes', count($stack) - 1 . ' groupe(s) non fermé(s) : fermés en fin de formulaire');
        }

        return $this->dropEmptyGroups($root);
    }

    /**
     * @param array<string, string> $row
     * @param array<string, list<array<string, mixed>>> $lists
     *
     * @return array<string, mixed>|null
     */
    private function question(array $row, string $type, string $rawType, string $rawName, int $line, array &$lists): ?array
    {
        $q = ['kind' => 'question'];
        $label = $this->label($row, 'label');
        $f2sType = null;
        $notes = [];
        if (preg_match('/^(select_one|select_multiple)\s+(\S+)(\s+or_other)?$/', $type, $m)) {
            $f2sType = $m[1];
            $listRaw = trim(explode(' ', substr($rawType, strlen($m[1]) + 1))[0]);
            $listKey = $this->listKey($listRaw);
            if (!isset($lists[$listKey])) {
                $this->report->lost("{$rawName} ({$type})", "liste de choix « {$listRaw} » introuvable dans la feuille choices");

                return null;
            }
            $q['choices'] = $listKey;
            if (!empty($m[3])) {
                $q['allow_other'] = true;
                $notes[] = '« or_other » → option « Autre (préciser) »';
            }
        } elseif (preg_match('/^(select_one_from_file|select_multiple_from_file)/', $type)) {
            $this->report->unsupported("{$rawName} ({$type})", 'liste de choix dans un fichier externe : non reprise');

            return null;
        } elseif ($type === 'text') {
            $f2sType = str_contains($row['appearance'] ?? '', 'multiline') ? 'long_text' : 'text';
            if ($f2sType === 'long_text') {
                $notes[] = 'text + appearance multiline → texte long';
            }
        } elseif (isset(self::DIRECT[$type]) || $type === 'datetime') {
            $f2sType = $type === 'datetime' ? 'datetime' : self::DIRECT[$type];
            if (in_array($f2sType, ['geopoint', 'image', 'file'], true)) {
                $this->report->unsupported("{$rawName} ({$type})", 'repris, mais pas encore collectable par F2S (phase 4)');
            }
        } elseif ($type === 'range') {
            $f2sType = 'scale';
            $params = [];
            foreach (preg_split('/\s+/', $row['parameters'] ?? '') ?: [] as $pair) {
                if (str_contains($pair, '=')) {
                    [$k, $v] = explode('=', $pair, 2);
                    $params[strtolower($k)] = $v;
                }
            }
            $min = (int) round((float) ($params['start'] ?? 1));
            $max = (int) round((float) ($params['end'] ?? 10));
            if (isset($params['step']) && (float) $params['step'] !== 1.0) {
                $notes[] = 'pas de ' . $params['step'] . ' non repris (échelle par pas de 1)';
            }
            if ($max <= $min || $max - $min > 100) {
                $this->report->lost("{$rawName} (range)", 'bornes invalides ou plus de 100 pas');

                return null;
            }
            $q['scale'] = ['min' => $min, 'max' => $max, 'preset' => $min === 0 && $max === 10 ? 'nps' : 'custom'];
            $notes[] = 'range → échelle';
        } elseif ($type === 'acknowledge') {
            $f2sType = 'select_one';
            if (!isset($lists['acknowledge_ok'])) {
                $lists['acknowledge_ok'] = [['value' => 'OK', 'label' => [$this->defaultLanguage => 'OK']]];
            }
            $q['choices'] = 'acknowledge_ok';
            $notes[] = 'acknowledge → choix unique « OK »';
        } elseif ($type === 'barcode') {
            $f2sType = 'text';
            $notes[] = 'code-barres → saisie texte';
        } else {
            $this->report->unsupported("{$rawName} ({$type})", 'type non pris en charge : question non reprise');

            return null;
        }
        $q['type'] = $f2sType;
        $q['name'] = $this->name($rawName !== '' ? $rawName : 'q_' . $line, 'q');
        if ($label === [] && $f2sType !== 'calculate') {
            $label = [$this->defaultLanguage => $rawName !== '' ? $rawName : 'Question ' . $line];
            $notes[] = 'libellé absent : identifiant utilisé';
        }
        if ($label !== []) {
            $q['label'] = $label;
        } else {
            $q['label'] = [$this->defaultLanguage => $rawName];
        }
        foreach (['hint', 'constraint_message', 'required_message'] as $col) {
            $text = $this->label($row, $col);
            if ($text !== []) {
                $q[$col] = $text;
            }
        }
        $required = strtolower($row['required'] ?? '');
        if (in_array($required, ['yes', 'true', 'true()', '1', 'oui'], true)) {
            $q['required'] = true;
        } elseif (!in_array($required, ['', 'no', 'false', 'false()', '0', 'non'], true)) {
            $this->report->lost("{$q['name']}.required", "obligatoire conditionnel « {$row['required']} » non repris (question facultative)");
        }
        foreach (['relevant', 'constraint', 'calculation', 'choice_filter', 'appearance'] as $col) {
            if (($row[$col] ?? '') !== '') {
                $q[$col] = $row[$col];
            }
        }
        if ($f2sType === 'calculate' && !isset($q['calculation'])) {
            $this->report->lost("{$q['name']} (calculate)", 'calcul sans formule');

            return null;
        }
        if (($row['default'] ?? '') !== '') {
            $q['default'] = $row['default'];
        }
        if (in_array(strtolower($row['read_only'] ?? ''), ['yes', 'true', 'true()', '1'], true)) {
            $q['read_only'] = true;
        }
        $extra = [];
        foreach ($row as $key => $value) {
            $base = explode('::', $key)[0];
            if ($value !== '' && !in_array($base, self::KNOWN_COLUMNS, true)) {
                $extra[$key] = $value;
            }
        }
        if ($extra !== []) {
            $q['x_source'] = $extra;
            $notes[] = 'colonnes conservées dans x_source : ' . implode(', ', array_keys($extra));
        }
        if ($notes === [] && $q['name'] === $rawName) {
            $this->report->kept($q['name'], $f2sType);
        } else {
            foreach ($notes as $note) {
                $this->report->transformed($q['name'], $note);
            }
            if ($notes === []) {
                $this->report->kept($q['name'], $f2sType);
            }
        }

        return $q;
    }

    private function name(string $raw, string $prefix): string
    {
        $duplicate = isset($this->seenRaw[strtolower($raw)]);
        $this->seenRaw[strtolower($raw)] = true;
        $name = $this->names->unique($raw, $prefix);
        if ($duplicate) {
            // References ${raw} keep pointing to the first question with that name.
            $this->report->transformed('identifiant « ' . $raw . ' »', 'en double : cette occurrence devient « ' . $name . ' » (les références ${' . $raw . '} visent la première)');
        } elseif ($name !== $raw) {
            $this->renamed[$raw] = $name;
            $this->report->transformed('identifiant « ' . $raw . ' »', 'renommé « ' . $name . ' » (références ${…} mises à jour)');
        }

        return $name;
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return list<array<string, mixed>>
     */
    private function rewriteReferences(array $items): array
    {
        if ($this->renamed === []) {
            return $items;
        }
        foreach ($items as &$item) {
            foreach (['relevant', 'constraint', 'calculation', 'choice_filter', 'repeat_count'] as $field) {
                if (is_string($item[$field] ?? null)) {
                    $item[$field] = preg_replace_callback('/\$\{([^}]+)\}/', fn(array $m): string => '${' . ($this->renamed[$m[1]] ?? $m[1]) . '}', $item[$field]);
                }
            }
            if (is_array($item['items'] ?? null)) {
                $item['items'] = $this->rewriteReferences($item['items']);
            }
        }

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return list<array<string, mixed>>
     */
    private function dropEmptyGroups(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if (isset($item['items'])) {
                $item['items'] = $this->dropEmptyGroups($item['items']);
                if ($item['items'] === []) {
                    $this->report->lost('section ' . $item['name'], 'section vide (toutes ses questions ont été écartées)');
                    continue;
                }
            }
            $out[] = $item;
        }

        return $out;
    }
}
