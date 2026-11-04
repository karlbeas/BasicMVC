<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\FormValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Fixture convention: tests/fixtures/forms/invalid/<expected_error_code>[--variant].json
 */
final class FormValidatorTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    private static ?FormValidator $validator = null;

    private static function validator(): FormValidator
    {
        return self::$validator ??= FormValidator::default();
    }

    /** @return iterable<string, array{string}> */
    public static function validForms(): iterable
    {
        foreach ([...glob(self::ROOT . '/docs/schema/examples/*.json') ?: [], ...glob(self::ROOT . '/tests/fixtures/forms/valid/*.json') ?: []] as $file) {
            yield basename($file) => [$file];
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidForms(): iterable
    {
        foreach (glob(self::ROOT . '/tests/fixtures/forms/invalid/*.json') ?: [] as $file) {
            $code = explode('--', basename($file, '.json'))[0];
            yield basename($file) => [$file, $code];
        }
    }

    #[DataProvider('validForms')]
    public function testValidFormsPass(string $file): void
    {
        $result = self::validator()->validateJson((string) file_get_contents($file));

        self::assertTrue($result->isValid(), json_encode($result->errors(), JSON_PRETTY_PRINT) ?: '');
        self::assertIsArray($result->form);
    }

    #[DataProvider('invalidForms')]
    public function testInvalidFormsReportTheExpectedError(string $file, string $expectedCode): void
    {
        $result = self::validator()->validateJson((string) file_get_contents($file));

        self::assertFalse($result->isValid());
        self::assertContains($expectedCode, $result->errorCodes(), json_encode($result->errors(), JSON_PRETTY_PRINT) ?: '');
        foreach ($result->errors() as $error) {
            self::assertStringStartsWith('/', $error['path']);
        }
    }

    public function testWarningsDoNotBlock(): void
    {
        $form = json_decode((string) file_get_contents(self::ROOT . '/tests/fixtures/forms/valid/minimal.json'), true);
        $form['choice_lists']['unused'] = [['value' => 'a', 'label' => ['fr' => 'A']]];
        $form['items'][] = ['kind' => 'question', 'type' => 'geopoint', 'name' => 'gps', 'label' => ['fr' => 'Position', 'en' => 'Location']];

        $result = self::validator()->validateArray($form);
        $codes = array_column($result->warnings(), 'code');

        self::assertTrue($result->isValid(), json_encode($result->errors()) ?: '');
        self::assertContains('unused_choice_list', $codes);
        self::assertContains('not_yet_supported', $codes);
        self::assertContains('label_missing_translation', $codes);
    }

    public function testMatrixRowNamesMustBeUniqueAcrossTheForm(): void
    {
        $form = json_decode((string) file_get_contents(self::ROOT . '/docs/schema/examples/satisfaction-pharmacie.json'), true);
        $form['items'][1]['items'][1]['rows'][0]['name'] = 'nps';

        $result = self::validator()->validateArray($form);

        self::assertContains('duplicate_name', $result->errorCodes());
    }

    public function testRejectsOversizedDocuments(): void
    {
        $result = self::validator()->validateJson(str_repeat(' ', FormValidator::MAX_JSON_BYTES + 1));

        self::assertSame(['too_large'], $result->errorCodes());
    }
}
