<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Collect\SubmissionValidator;
use PHPUnit\Framework\TestCase;

final class SubmissionValidatorTest extends TestCase
{
    /** @return array<string, mixed> */
    private function form(): array
    {
        return json_decode((string) file_get_contents(__DIR__ . '/../fixtures/forms/valid/collect-all-types.json'), true, 64, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    private function validInput(): array
    {
        return [
            'consent' => 'yes', 'city' => '  Douala ', 'age' => '35', 'spend' => '12,50', 'visit' => '2026-10-30',
            'used' => ['lab', 'pharmacy'], 'drug' => 'Paracétamol', 'nps' => '9', 'q_staff' => '3', 'q_wait' => '2',
            'email' => 'awa@example.org', 'phone' => '+237 6 99 00 11 22', '_csrf' => 'x', 'intro' => 'ignored',
        ];
    }

    public function testValidSubmissionIsNormalized(): void
    {
        $result = (new SubmissionValidator($this->form()))->validate($this->validInput());

        self::assertSame([], $result['errors']);
        $data = $result['data'];
        self::assertSame('Douala', $data['city']);
        self::assertSame('12.50', $data['spend'], 'French decimal comma is normalized');
        self::assertSame(['lab', 'pharmacy'], $data['used']);
        self::assertSame('36', $data['age_next'], 'calculate is evaluated server-side');
        self::assertArrayNotHasKey('intro', $data, 'notes carry no answer');
        self::assertArrayNotHasKey('_csrf', $data);
        self::assertArrayNotHasKey('quality', $data, 'matrix answers are stored per row');
        self::assertSame('3', $data['q_staff']);
    }

    public function testNonRelevantQuestionsAreDroppedAndNotRequired(): void
    {
        $result = (new SubmissionValidator($this->form()))->validate(['consent' => 'no', 'city' => 'should be dropped', 'age' => '5']);

        self::assertSame([], $result['errors'], 'Required questions inside a hidden group are not required');
        self::assertSame(['consent' => 'no'], $result['data']);
    }

    public function testRelevanceInsideGroupDependsOnEarlierAnswer(): void
    {
        $input = $this->validInput();
        $input['used'] = ['lab'];
        $input['drug'] = 'should be dropped';
        $result = (new SubmissionValidator($this->form()))->validate($input);

        self::assertSame([], $result['errors']);
        self::assertArrayNotHasKey('drug', $result['data']);

        $input['used'] = ['pharmacy'];
        $input['drug'] = '';
        self::assertSame('required', (new SubmissionValidator($this->form()))->validate($input)['errors']['drug']['code']);
    }

    public function testTypeChoiceAndConstraintErrors(): void
    {
        $input = $this->validInput();
        $input['age'] = '15';
        $input['spend'] = 'beaucoup';
        $input['visit'] = '2026-02-30';
        $input['used'] = ['lab', 'hacked'];
        $input['nps'] = '11';
        $input['q_wait'] = '';
        $input['email'] = 'not-an-email';
        $input['phone'] = 'abc';
        $errors = (new SubmissionValidator($this->form(), 'en'))->validate($input)['errors'];

        self::assertSame(['code' => 'constraint', 'message' => 'Between 18 and 120'], $errors['age']);
        self::assertSame('decimal', $errors['spend']['code']);
        self::assertSame('date', $errors['visit']['code']);
        self::assertSame('choice', $errors['used']['code']);
        self::assertSame('choice', $errors['nps']['code']);
        self::assertSame('required', $errors['q_wait']['code']);
        self::assertSame('email', $errors['email']['code']);
        self::assertSame('phone', $errors['phone']['code']);
    }

    public function testOtherRequiresSpecification(): void
    {
        $input = $this->validInput();
        $input['used'] = ['other'];
        self::assertSame('other_required', (new SubmissionValidator($this->form()))->validate($input)['errors']['used']['code']);

        $input['used_other'] = 'Radiologie';
        $data = (new SubmissionValidator($this->form()))->validate($input)['data'];
        self::assertSame(['other'], $data['used']);
        self::assertSame('Radiologie', $data['used_other']);
    }

    public function testArraysForScalarQuestionsAreRejectedSafely(): void
    {
        $input = $this->validInput();
        $input['city'] = ['injected'];
        self::assertSame('required', (new SubmissionValidator($this->form()))->validate($input)['errors']['city']['code']);
    }
}
