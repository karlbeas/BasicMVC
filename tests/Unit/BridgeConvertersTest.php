<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Bridge\CsvReader;
use App\Services\Bridge\ImportException;
use App\Services\Bridge\QuestionListConverter;
use App\Services\Bridge\XlsFormConverter;
use App\Services\Bridge\XlsxReader;
use App\Services\FormSchema;
use App\Services\FormValidator;
use PHPUnit\Framework\TestCase;

final class BridgeConvertersTest extends TestCase
{
    private const DIR = __DIR__ . '/../fixtures/imports/';

    /** @param array<string, mixed> $form */
    private static function assertValidForm(array $form): void
    {
        $result = FormValidator::default()->validateJson((string) json_encode($form, JSON_UNESCAPED_UNICODE));
        self::assertTrue($result->isValid(), (string) json_encode($result->errors(), JSON_UNESCAPED_UNICODE));
    }

    /** @param array<string, mixed> $report */
    private static function details(array $report, string $category): string
    {
        return implode("\n", array_map(static fn(array $e): string => $e['item'] . ' — ' . $e['detail'], $report['entries'][$category]));
    }

    public function testXlsxReaderReadsSheetsWrittenByOpenpyxl(): void
    {
        $reader = new XlsxReader(self::DIR . 'kobo-xlsform.xlsx');

        self::assertSame(['survey', 'choices', 'settings'], array_keys($reader->sheets()));
        $settings = $reader->rows('SETTINGS'); // sheet names are matched case-insensitively
        self::assertSame(['form_title', 'form_id', 'version', 'default_language', 'style'], $settings[0]);
        self::assertSame('Enquête santé Douala', $settings[1][0]);
        self::assertSame('2026110401', $settings[1][2], 'Numbers come back as clean text');
        self::assertSame('C', chr(65 + XlsxReader::columnIndex('C12')));
        self::assertSame(27, XlsxReader::columnIndex('AB3'));
    }

    public function testXlsxReaderRejectsNonExcelFiles(): void
    {
        $this->expectException(ImportException::class);
        new XlsxReader(self::DIR . 'questions-utf8.csv');
    }

    public function testKoboXlsFormConversionAndHonestReport(): void
    {
        $reader = new XlsxReader(self::DIR . 'kobo-xlsform.xlsx');
        [$form, $report] = (new XlsFormConverter())->convert($reader->rows('survey'), $reader->rows('choices'), $reader->rows('settings'), 'kobo-xlsform.xlsx');
        $r = $report->toArray();
        self::assertValidForm($form);

        self::assertSame(['fr', 'en'], $form['languages']);
        self::assertSame('fr', $form['default_language']);
        self::assertSame(['fr' => 'Enquête santé Douala'], $form['settings']['title']);
        self::assertSame('sante_douala', $form['settings']['form_id']);

        $q = array_column(FormSchema::questions($form), null, 'name');
        self::assertSame('Respondent profile', $form['items'][0]['label']['en']);
        self::assertTrue($form['items'][0]['page'], 'field-list → page');
        self::assertSame('integer', $q['age']['type']);
        self::assertArrayHasKey('nom_complet', $q, 'invalid name sanitized');
        self::assertSame('long_text', $q['commentaire']['type']);
        self::assertSame('scale', $q['note_accueil']['type']);
        self::assertArrayNotHasKey('priorites', $q, 'rank is not supported');
        self::assertArrayNotHasKey('start', $q, 'metadata dropped');

        $services = $form['items'][1];
        self::assertSame('${age} >= 18', $services['relevant'], 'reference to the FIRST "age", not the duplicate');
        $comment = array_values(array_filter($services['items'], static fn(array $i): bool => $i['name'] === 'commentaire'))[0];
        self::assertSame("\${nom_complet} != ''", $comment['relevant'], 'references follow renamed questions');
        $satisfaction = array_values(array_filter($services['items'], static fn(array $i): bool => $i['name'] === 'satisfaction'))[0];
        self::assertTrue($satisfaction['allow_other']);
        self::assertSame(['tres_satisfait', 'pas_satisfait'], array_column($form['choice_lists']['satisf'], 'value'));
        self::assertArrayNotHasKey('inutile', $form['choice_lists']);
        self::assertSame(['media::image' => 'age.png'], array_values(array_filter($form['items'][0]['items'], static fn(array $i): bool => $i['name'] === 'age'))[0]['x_source'], 'unknown columns kept, not lost');

        self::assertStringContainsString('start (start)', self::details($r, 'lost'));
        self::assertStringContainsString('settings.style', self::details($r, 'lost'));
        self::assertStringContainsString('valeur en double', self::details($r, 'lost'));
        self::assertStringContainsString('priorites (rank)', self::details($r, 'unsupported'));
        self::assertStringContainsString('groupe répété enfants', self::details($r, 'unsupported'));
        self::assertStringContainsString('« nom-complet » — renommé « nom_complet »', self::details($r, 'transformed'));
        self::assertStringContainsString('en double', self::details($r, 'transformed'));
        self::assertStringContainsString('settings.version', self::details($r, 'transformed'));
        self::assertSame(1, substr_count(self::details($r, 'transformed') . self::details($r, 'lost'), 'satisf/pas satisfait — valeur en double'), 'each problem is reported once');
    }

    public function testQuestionListFromExcel(): void
    {
        $reader = new XlsxReader(self::DIR . 'questions-fr.xlsx');
        [$form, $report] = (new QuestionListConverter())->convert($reader->rows('Questionnaire'), 'questions-fr.xlsx', 'xlsx_questions');
        self::assertValidForm($form);
        $r = $report->toArray();

        self::assertSame(['profil', 'experience'], array_column($form['items'], 'name'), 'sections from the Section column');
        $q = array_column(FormSchema::questions($form), null, 'name');
        self::assertSame('select_one', $q['votre_tranche_d_age']['type']);
        self::assertSame(['18_24_ans', '25_39_ans', '40_ans_et_plus'], array_column($form['choice_lists'][$q['votre_tranche_d_age']['choice_list']], 'value'));
        self::assertSame('select_one', $q['votre_quartier']['type'], 'type inferred from the choices');
        self::assertSame('integer', $q['nombre_d_enfants']['type']);
        self::assertSame('scale', $q['recommanderiez_vous_la_pharmac']['type']);
        self::assertSame('select_multiple', $q['quels_services_utilisez_vous']['type']);
        self::assertSame('long_text', $q['vos_suggestions']['type']);
        self::assertStringContainsString('remarques internes', self::details($r, 'lost'));
        self::assertStringContainsString('curseur 3D', self::details($r, 'transformed'));
        self::assertStringContainsString('type déduit', self::details($r, 'transformed'));
    }

    public function testCsvEncodingsDelimitersAndOverflow(): void
    {
        $utf8 = CsvReader::read((string) file_get_contents(self::DIR . 'questions-utf8.csv'));
        self::assertSame(';', $utf8['delimiter']);
        self::assertFalse($utf8['converted_encoding']);
        [$form, $report] = (new QuestionListConverter())->convert($utf8['rows'], 'questions-utf8.csv', 'csv');
        self::assertValidForm($form);
        self::assertStringContainsString('« Carte »', self::details($report->toArray(), 'lost'), 'cells beyond the header are reported, never silently dropped');

        $cp = CsvReader::read((string) file_get_contents(self::DIR . 'questions-cp1252.csv'));
        self::assertSame(',', $cp['delimiter']);
        self::assertTrue($cp['converted_encoding']);
        self::assertSame('Région', $cp['rows'][1][0]);
        [$form] = (new QuestionListConverter())->convert($cp['rows'], 'questions-cp1252.csv', 'csv');
        self::assertSame(['littoral', 'centre', 'ouest'], array_column(array_values($form['choice_lists'])[0], 'value'));
    }
}
