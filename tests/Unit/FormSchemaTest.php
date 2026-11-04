<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\FormSchema;
use PHPUnit\Framework\TestCase;

final class FormSchemaTest extends TestCase
{
    /** @return array<string, mixed> */
    private function example(): array
    {
        return json_decode((string) file_get_contents(__DIR__ . '/../../docs/schema/examples/satisfaction-pharmacie.json'), true, 64, JSON_THROW_ON_ERROR);
    }

    public function testProjectionFlattensGroupsAndExpandsMatrixRows(): void
    {
        $questions = FormSchema::questions($this->example());
        $byName = array_column($questions, null, 'name');

        self::assertCount(11, $questions);
        self::assertSame('profile/age_group', $byName['age_group']['path']);
        self::assertSame('segment', $byName['age_group']['analysis_role']);
        self::assertSame("Votre tranche d'âge", $byName['age_group']['label']);
        self::assertSame('matrix', $byName['service_quality']['type']);
        self::assertSame('matrix_row', $byName['staff_friendly']['type']);
        self::assertSame('agreement', $byName['staff_friendly']['choice_list']);
        self::assertSame('experience/service_quality/staff_friendly', $byName['staff_friendly']['path']);
    }

    public function testQuestionCountExcludesNotesAndMatrixContainers(): void
    {
        $form = $this->example();
        self::assertSame(10, FormSchema::questionCount($form));

        $form['items'][0]['items'][] = ['kind' => 'question', 'type' => 'note', 'name' => 'intro', 'label' => ['fr' => 'Merci']];
        self::assertSame(10, FormSchema::questionCount($form));
    }

    public function testLabelFallsBackToFirstLanguage(): void
    {
        self::assertSame('Hello', FormSchema::label(['en' => 'Hello'], 'fr'));
        self::assertSame('', FormSchema::label(null, 'fr'));
    }
}
