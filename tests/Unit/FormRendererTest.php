<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Collect\CollectGuard;
use App\Services\Collect\FormRenderer;
use App\Services\Collect\ResponseExporter;
use PHPUnit\Framework\TestCase;

final class FormRendererTest extends TestCase
{
    /** @return array<string, mixed> */
    private function form(): array
    {
        return json_decode((string) file_get_contents(__DIR__ . '/../fixtures/forms/valid/collect-all-types.json'), true, 64, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $form
     * @param array<string, mixed> $values
     * @param array<string, array{code: string, message: ?string}> $errors
     */
    private function render(array $form, string $lang = 'fr', array $values = [], array $errors = []): string
    {
        return (new FormRenderer($form, $lang, $values, $errors, static fn(string $k): string => '[' . $k . ']'))->render();
    }

    public function testRendersEveryInputAndEscapesLabels(): void
    {
        $html = $this->render($this->form());

        self::assertStringContainsString('Bienvenue &lt;b&gt;!&lt;/b&gt;', $html, 'Labels are escaped');
        self::assertStringNotContainsString('<b>!</b>', $html);
        self::assertStringContainsString('data-relevant="${consent} = &#039;yes&#039;"', $html);
        self::assertStringContainsString('name="used[]"', $html);
        self::assertStringContainsString('name="used_other"', $html);
        self::assertStringContainsString('type="number" id="q-age" name="age"', $html);
        self::assertStringContainsString('name="q_staff"', $html);
        self::assertStringContainsString('name="nps" id="q-nps-10"', $html);
        self::assertStringContainsString('type="email"', $html);
        self::assertStringContainsString('type="tel"', $html);
        self::assertStringNotContainsString('age_next', $html, 'calculate fields are not rendered');
    }

    public function testRedisplaysValuesAndErrors(): void
    {
        $html = $this->render($this->form(), 'en', ['city' => '"><script>', 'used' => ['lab']], ['age' => ['code' => 'constraint', 'message' => 'Between 18 and 120']]);

        self::assertStringContainsString('value="&quot;&gt;&lt;script&gt;"', $html);
        self::assertMatchesRegularExpression('/name="used\[\]" id="q-used-0" value="lab" checked/', $html);
        self::assertStringContainsString('Between 18 and 120', $html);
        self::assertStringContainsString('City', $html, 'Second language is used');
    }

    public function testCollectGuardFlagsUnsupportedFeatures(): void
    {
        self::assertSame([], CollectGuard::blockers($this->form()));

        $form = $this->form();
        $form['items'][] = ['kind' => 'question', 'type' => 'geopoint', 'name' => 'gps', 'label' => ['fr' => 'GPS']];
        $form['items'][1]['relevant'] = "regex(., '^a')";
        $problems = array_column(CollectGuard::blockers($form), 'problem');

        self::assertContains('type:geopoint', $problems);
        self::assertTrue((bool) preg_grep('/^expression:/', $problems));
    }

    public function testExportColumnsAndFormulaInjection(): void
    {
        $names = array_column(ResponseExporter::columns($this->form()), 'name');

        self::assertSame(['consent', 'city', 'age', 'spend', 'visit', 'used', 'used_other', 'drug', 'nps', 'q_staff', 'q_wait', 'email', 'phone', 'age_next'], $names);
        self::assertSame("'=HYPERLINK(\"x\")", ResponseExporter::cell('=HYPERLINK("x")'));
        self::assertSame('-12', ResponseExporter::cell('-12'));
        self::assertSame('Douala', ResponseExporter::cell('Douala'));
    }
}
