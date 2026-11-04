<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Ai\FormAssembler;
use App\Services\FormValidator;
use PHPUnit\Framework\TestCase;

final class FormAssemblerTest extends TestCase
{
    /**
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed> $lists
     *
     * @return array{group: array<string, mixed>, choice_lists: array<string, mixed>}
     */
    private static function section(string $name, array $items, array $lists = []): array
    {
        $built = FormAssembler::section(['choice_lists' => $lists, 'group' => ['name' => $name, 'label' => ['fr' => ucfirst($name)], 'items' => $items]], $name, $name, 'fr');
        self::assertNotNull($built);

        return $built;
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private static function q(string $name, string $type = 'text', array $extra = []): array
    {
        return ['kind' => 'question', 'type' => $type, 'name' => $name, 'label' => ['fr' => ucfirst($name)]] + $extra;
    }

    public function testCollisionsAreResolvedAndTheResultIsValid(): void
    {
        $yesNo = [['value' => 'oui', 'label' => ['fr' => 'Oui']], ['value' => 'non', 'label' => ['fr' => 'Non']]];
        $scale = [['value' => '1', 'label' => ['fr' => 'Bas']], ['value' => '2', 'label' => ['fr' => 'Haut']]];
        $form = FormAssembler::assemble([
            self::section('profil', [self::q('age', 'integer'), self::q('client', 'select_one', ['choices' => 'reponse'])], ['reponse' => $yesNo]),
            self::section('avis', [
                self::q('age', 'integer', ['constraint' => '. >= 0']),
                self::q('reco', 'select_one', ['choices' => 'reponse', 'relevant' => '${age} >= 18']),
                self::q('fidele', 'select_one', ['choices' => 'reponse']),
            ], ['reponse' => $scale]),
            self::section('fin', [self::q('encore', 'select_one', ['choices' => 'reponse'])], ['reponse' => $yesNo]),
        ], ['form_id' => 'test', 'title' => ['fr' => 'Test']], ['fr']);

        $result = FormValidator::default()->validateArray($form);
        self::assertTrue($result->isValid(), (string) json_encode($result->errors()));
        $avis = $form['items'][1]['items'];
        self::assertSame('age_2', $avis[0]['name'], 'duplicate question renamed');
        self::assertSame('${age_2} >= 18', $avis[1]['relevant'], 'reference inside the section follows the rename');
        self::assertSame('reponse_2', $avis[1]['choices'], 'same list name, different content: renamed');
        self::assertSame('reponse', $form['items'][2]['items'][0]['choices'], 'identical list: shared');
        self::assertSame(['reponse', 'reponse_2'], array_keys($form['choice_lists']));
    }

    public function testSectionAcceptsLooseAnswers(): void
    {
        self::assertNull(FormAssembler::section(['group' => ['items' => []]], 'a', 'A', 'fr'));
        $bare = FormAssembler::section(['kind' => 'group', 'name' => '1 bad name', 'items' => [self::q('x')]], 'profil', 'Profil', 'fr');
        self::assertSame('profil', $bare['group']['name'] ?? null);
        self::assertSame(['fr' => 'Profil'], $bare['group']['label'] ?? null);
    }

    public function testAutofixRepairsCommonSlips(): void
    {
        $form = [
            'schema_version' => '1.0', 'settings' => ['form_id' => 'f', 'title' => ['fr' => 'F']], 'languages' => ['fr', 'en'], 'default_language' => 'fr',
            'choice_lists' => ['l' => [['value' => 'a', 'label' => ['en' => 'A']], ['value' => 'a', 'label' => ['fr' => 'Bis']]]],
            'items' => [['kind' => 'group', 'name' => 'g', 'label' => ['fr' => 'G'], 'items' => [
                ['kind' => 'question', 'type' => 'select_one', 'name' => 'q1', 'label' => ['en' => 'Q1'], 'choices' => 'l'],
                ['kind' => 'question', 'type' => 'select_one', 'name' => 'q2', 'label' => ['fr' => 'Q2'], 'choices' => 'ghost', 'relevant' => '${unknown} = 1'],
            ]]],
        ];
        self::assertFalse(FormValidator::default()->validateArray($form)->isValid());

        $fixed = FormAssembler::autofix($form);

        self::assertTrue(FormValidator::default()->validateArray($fixed)->isValid(), (string) json_encode(FormValidator::default()->validateArray($fixed)->errors()));
        self::assertCount(1, $fixed['choice_lists']['l'], 'duplicate code removed');
        self::assertSame('text', $fixed['items'][0]['items'][1]['type'], 'missing list → free text');
        self::assertArrayNotHasKey('relevant', $fixed['items'][0]['items'][1], 'dangling reference removed');
        self::assertSame('Q1', $fixed['items'][0]['items'][0]['label']['fr'], 'label copied into the default language');
    }
}
