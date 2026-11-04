<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Ai\FormAssembler;
use App\Services\FormValidator;
use PHPUnit\Framework\TestCase;

/**
 * "Sloppy" sections like fast models produce: every deviation must be normalized
 * into a valid F2S form without any AI call.
 */
final class FormNormalizerTest extends TestCase
{
    public function testSloppySectionBecomesAValidForm(): void
    {
        $answer = [
            'section' => 'Profil', // unknown top-level key ignored
            'group' => [
                'name' => 'Profil du répondant',
                'title' => 'Profil du répondant',
                'questions' => [
                    ['type' => 'single_choice', 'name' => 'sexe', 'question' => 'Votre sexe ?', 'options' => ['Homme', 'Femme', 'Préfère ne pas dire'], 'required' => 'oui', 'analysis' => ['role' => 'demographic']],
                    ['type' => 'number', 'name' => 'âge', 'label' => 'Votre âge', 'required' => 'yes', 'constraint' => '. >= 15', 'constraint_message' => 'Au moins 15 ans'],
                    ['type' => 'rating', 'name' => 'satisfaction', 'label' => 'Satisfaction globale', 'min' => 1, 'max' => 5, 'relevant' => "\${sexe} = 'Préfère ne pas dire'"],
                    ['type' => 'yes_no', 'name' => 'revenir', 'label' => 'Reviendrez-vous ?'],
                    ['type' => 'checkbox', 'name' => 'services', 'label' => 'Services utilisés', 'choices' => 'Conseil; Vente; Livraison à domicile'],
                    ['type' => 'textarea', 'name' => 'commentaire', 'label' => 'Commentaires', 'placeholder' => 'Votre avis…', 'analysis' => ['role' => 'feedback']],
                    ['type' => 'grid', 'name' => 'qualite', 'label' => 'Qualité', 'options' => ['Mauvais', 'Bon'], 'rows' => ['Accueil', 'Conseils']],
                    ['type' => 'telepathy', 'name' => 'weird', 'label' => 'Type inconnu'],
                    'Une question donnée comme simple texte',
                ],
            ],
            'choice_lists' => [],
        ];

        $section = FormAssembler::section($answer, 'profil', 'Profil', 'fr');
        self::assertNotNull($section);
        $form = FormAssembler::assemble([$section], ['form_id' => 'f', 'title' => ['fr' => 'F']], ['fr']);
        $result = FormValidator::default()->validateArray($form);

        self::assertTrue($result->isValid(), (string) json_encode($result->errors(), JSON_UNESCAPED_UNICODE));
        $q = array_column($form['items'][0]['items'], null, 'name');
        self::assertSame('profil', $form['items'][0]['name'], 'invalid section name: the plan name is used');
        self::assertSame(['fr' => 'Profil du répondant'], $form['items'][0]['label']);
        self::assertSame('select_one', $q['sexe']['type']);
        self::assertTrue($q['sexe']['required']);
        self::assertSame('segment', $q['sexe']['analysis']['role']);
        self::assertSame(['homme', 'femme', 'prefere_ne_pas_dire'], array_column($form['choice_lists'][$q['sexe']['choices']], 'value'));
        self::assertSame("\${sexe} = 'prefere_ne_pas_dire'", $q['satisfaction']['relevant'], 'expression follows the recoded choice');
        self::assertSame('integer', $q['age']['type'], 'accented name made ASCII');
        self::assertSame(['fr' => 'Au moins 15 ans'], $q['age']['constraint_message']);
        self::assertSame(['min' => 1, 'max' => 5], $q['satisfaction']['scale']);
        self::assertSame(['oui', 'non'], array_column($form['choice_lists'][$q['revenir']['choices']], 'value'));
        self::assertSame('select_multiple', $q['services']['type']);
        self::assertSame('long_text', $q['commentaire']['type']);
        self::assertSame(['placeholder' => 'Votre avis…'], $q['commentaire']['x_source'], 'unknown property kept, not lost');
        self::assertSame('open_feedback', $q['commentaire']['analysis']['role']);
        self::assertSame('matrix', $q['qualite']['type']);
        self::assertCount(2, $q['qualite']['rows']);
        self::assertSame('text', $q['weird']['type']);
    }

    public function testRevisedPartKeepsOriginalListsAndAcceptsBareQuestions(): void
    {
        $original = ['group' => ['kind' => 'question', 'type' => 'select_one', 'name' => 'q', 'label' => ['fr' => 'Q'], 'choices' => 'yn'],
            'choice_lists' => ['yn' => [['value' => 'oui', 'label' => ['fr' => 'Oui']], ['value' => 'non', 'label' => ['fr' => 'Non']]]]];

        $part = FormAssembler::item(['type' => 'select_one', 'name' => 'q', 'label' => 'Q modifiée', 'choices' => 'yn'], $original, 'fr');

        self::assertSame(['fr' => 'Q modifiée'], $part['group']['label'] ?? null);
        self::assertArrayHasKey('yn', $part['choice_lists']);
        self::assertSame($original, FormAssembler::item(['unchanged' => true], $original, 'fr'));
    }
}
