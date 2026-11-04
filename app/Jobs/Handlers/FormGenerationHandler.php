<?php

declare(strict_types=1);

namespace App\Jobs\Handlers;

use App\Jobs\JobContext;
use App\Jobs\JobHandler;
use App\Jobs\JobResult;
use App\Models\FormRepository;
use App\Services\Ai\AiException;
use App\Services\Ai\AiGateway;
use App\Services\Ai\AiResponse;
use App\Services\Ai\CreditLedger;
use App\Services\Ai\FormAssembler;
use App\Services\AuditLogger;
use App\Services\Bridge\Naming;
use App\Services\ContextFactory;
use App\Services\FormInvalidException;
use App\Services\FormSchema;
use App\Services\FormService;
use App\Services\FormValidationResult;
use App\Services\FormValidator;
use App\Services\ImmutableVersionException;
use App\Services\QuotaExceededException;

/**
 * AI Studio job (types "ai.form_generate" and "ai.form_revise"), built for shared hosting:
 * ONLY SHORT AI CALLS (one section or one question each, about 10–20 s), each checkpointed.
 * The status page runs them one after another within its own time limit, so the job
 * progresses with or without a CRON, and survives a host killing a process.
 *
 *  generate: plan of sections → one call per section → assemble → validate
 *  revise:   current draft split into parts → one call per part (or "unchanged") → assemble → validate
 *  validate: deterministic autofix, then at most one repair call per faulty part.
 *
 * Credits are charged once, only when a valid form has been saved. Never stores an invalid form.
 * Payload: {mode, user_id, project_id?, form_id?, brief?: {...}, instruction?: string}
 */
final class FormGenerationHandler implements JobHandler
{
    public static ?AiGateway $gatewayOverride = null; // tests

    private JobContext $ctx;
    private AiGateway $gateway;
    private int $orgId;
    private int $userId;

    public function handle(JobContext $ctx): JobResult
    {
        $this->ctx = $ctx;
        $db = $ctx->app->db();
        $this->orgId = (int) $ctx->organizationId();
        $p = $ctx->payload;
        $mode = ($p['mode'] ?? 'generate') === 'revise' ? 'revise' : 'generate';
        $operation = $mode === 'revise' ? 'form_revision' : 'form_generation';
        $cost = $ctx->app->config()->int('ai.credits.' . $operation, $mode === 'revise' ? 1 : 5);
        $this->userId = (int) ($p['user_id'] ?? 0);
        $this->gateway = self::$gatewayOverride ?? new AiGateway($db, $ctx->app->config());
        $credits = new CreditLedger($db);
        $progress = $ctx->progress ?? ['step' => $mode === 'revise' ? 'split' : 'plan'];

        try {
            $auth = ContextFactory::forUser($db, $this->orgId, $this->userId);
        } catch (\DomainException) {
            return JobResult::done(['error' => 'forbidden']);
        }
        if (!$auth->can($mode === 'revise' ? 'form.edit' : 'form.create')) {
            return JobResult::done(['error' => 'forbidden']);
        }
        if ($credits->available($this->orgId) < $cost) {
            return JobResult::done(['error' => 'insufficient_credits']);
        }
        if (!$this->gateway->isConfigured()) {
            return JobResult::done(['error' => 'not_configured']);
        }

        try {
            // 1a. generate: plan of sections.
            if ($progress['step'] === 'plan') {
                if (!$this->canStart()) {
                    return JobResult::yield($progress);
                }
                $languages = $this->languages($p);
                $plan = $this->call('form_plan', 'form_plan', $this->briefText($p, $languages), 'form_plan')->json();
                $sections = $this->planSections($plan);
                if ($plan === null || $sections === []) {
                    return JobResult::done(['error' => 'invalid_output']);
                }
                $progress = [
                    'step' => 'parts', 'mode' => 'generate', 'plan' => $plan,
                    'todo' => $sections, 'done' => [], 'misses' => 0,
                    'settings' => $this->settings($p, $plan, $languages), 'languages' => $languages,
                ];
                $ctx->checkpoint($progress);
            }

            // 1b. revise: split the current draft into parts (no AI call).
            if ($progress['step'] === 'split') {
                $current = $this->currentDraft((int) ($p['form_id'] ?? 0));
                if ($current === null) {
                    return JobResult::done(['error' => 'not_draft']);
                }
                $form = json_decode((string) $current['schema_json'], true, 64, JSON_THROW_ON_ERROR);
                $progress = [
                    'step' => 'parts', 'mode' => 'revise',
                    'todo' => FormAssembler::parts($form), 'done' => [], 'misses' => 0,
                    'settings' => $form['settings'], 'languages' => array_values((array) $form['languages']),
                    'outline' => $this->outline($form),
                ];
                $ctx->checkpoint($progress);
            }

            // 2. One short call per part, checkpointed after each.
            while ($progress['step'] === 'parts') {
                $index = count($progress['done']);
                if ($index >= count($progress['todo'])) {
                    $progress['step'] = 'validate';
                    $progress['repaired'] = false;
                    $ctx->checkpoint($progress);
                    break;
                }
                if (!$this->canStart()) {
                    return JobResult::yield($progress);
                }
                $built = $progress['mode'] === 'generate'
                    ? $this->writeSection($p, $progress, $index)
                    : $this->revisePart($p, $progress, $index);
                if ($built === null) {
                    // One unusable answer is retried on the next pass; two in a row stop the job.
                    if (++$progress['misses'] >= 2) {
                        return JobResult::done(['error' => 'invalid_output']);
                    }
                    $ctx->checkpoint($progress);

                    return JobResult::yield($progress);
                }
                $progress['done'][] = $built;
                $progress['misses'] = 0;
                $ctx->checkpoint($progress);
            }

            // 3. Assemble, autofix, validate; repair faulty parts once (short calls too).
            $validator = FormValidator::default();
            [$json, $result] = $this->assemble($progress, $validator);
            while (!$result->isValid()) {
                $faulty = $this->faultyParts($result->errors(), count($progress['done']));
                if ($progress['repaired'] === true || $faulty === []) {
                    $first = $result->errors()[0] ?? null;

                    return JobResult::done([
                        'error' => 'invalid_output',
                        'detail' => $first !== null ? $first['path'] . ' : ' . mb_substr($first['message'], 0, 160) : '',
                        'details' => array_slice($result->errors(), 0, 10),
                    ]);
                }
                $progress['repair_todo'] ??= $faulty;
                while ($progress['repair_todo'] !== []) {
                    if (!$this->canStart()) {
                        return JobResult::yield($progress);
                    }
                    $i = (int) array_shift($progress['repair_todo']);
                    $errors = array_filter($result->errors(), static fn(array $e): bool => str_starts_with($e['path'], '/items/' . $i . '/') || $e['path'] === '/items/' . $i);
                    $answer = $this->call('form_repair', 'form_item_repair', "Erreurs :\n"
                        . implode("\n", array_map(static fn(array $e): string => $e['path'] . ' : ' . $e['message'], array_slice($errors, 0, 10)))
                        . "\n\nListes de choix disponibles : " . json_encode($progress['done'][$i]['choice_lists'], JSON_UNESCAPED_UNICODE)
                        . "\n\nPartie :\n" . json_encode($progress['done'][$i]['group'], JSON_UNESCAPED_UNICODE), 'form_item')->json();
                    $fixed = $answer === null ? null : FormAssembler::item($answer, $progress['done'][$i], $progress['languages'][0]);
                    if ($fixed !== null) {
                        $progress['done'][$i] = $fixed;
                    }
                    $ctx->checkpoint($progress);
                }
                $progress['repaired'] = true;
                unset($progress['repair_todo']);
                $ctx->checkpoint($progress);
                [$json, $result] = $this->assemble($progress, $validator);
            }

            // 4. Save, then charge credits (idempotent per job).
            $forms = new FormService($db, $validator, new AuditLogger($db));
            if ($mode === 'revise') {
                $current = $this->currentDraft((int) ($p['form_id'] ?? 0));
                if ($current === null) {
                    return JobResult::done(['error' => 'not_draft']);
                }
                $forms->updateDraft($auth, (int) $current['id'], $json);
                $formId = (int) ($p['form_id'] ?? 0);
            } else {
                $formId = $forms->createFromJson($auth, (int) ($p['project_id'] ?? 0), $json, 'ai')['form_id'];
            }
            $credits->charge($this->orgId, $this->userId, $cost, $operation, 'job:' . $ctx->job['id']);
            $publicId = (string) $db->fetchValue('SELECT public_id FROM forms WHERE id = ? AND organization_id = ?', [$formId, $this->orgId]);

            return JobResult::done([
                'form_public_id' => $publicId,
                'questions' => FormSchema::questionCount(json_decode($json, true, 64, JSON_THROW_ON_ERROR)),
                'warnings' => count($result->warnings()),
                'credits' => $cost,
            ]);
        } catch (AiException $e) {
            if ($e->reason === 'no_time') {
                return JobResult::yield($progress);
            }
            if (in_array($e->reason, ['budget', 'not_configured', 'auth', 'bad_request'], true)) {
                return JobResult::done(['error' => $e->reason, 'detail' => mb_substr($e->getMessage(), 0, 300)]);
            }
            throw $e; // timeout / network / rate limit / 5xx: retried with backoff, resuming from the last checkpoint
        } catch (QuotaExceededException $e) {
            return JobResult::done(['error' => 'too_many_questions', 'limit' => $e->limit, 'actual' => $e->actual]);
        } catch (FormInvalidException) {
            return JobResult::done(['error' => 'invalid_output']);
        } catch (ImmutableVersionException) {
            return JobResult::done(['error' => 'not_draft']);
        }
    }

    /** A short step fits when the process has no time limit (CRON) or enough time left (web). */
    private function canStart(): bool
    {
        return $this->ctx->canRunLong() || $this->ctx->secondsLeft() >= $this->ctx->app->config()->int('ai.step_seconds.form_part', 20);
    }

    private function call(string $operation, string $promptName, string $user, string $tokensKey): AiResponse
    {
        $system = (string) file_get_contents($this->ctx->app->root . '/app/Prompts/' . $promptName . '.v1.md');
        $tokens = $this->ctx->app->config()->int('ai.max_tokens.' . $tokensKey, 3000);

        return $this->gateway->complete($operation, 'form', $system, $user, $tokens, $this->orgId, $this->userId, (int) $this->ctx->job['id'], $this->ctx->canRunLong() ? null : $this->ctx->deadline());
    }

    /**
     * @param array<string, mixed> $p
     * @param array<string, mixed> $progress
     *
     * @return array{group: array<string, mixed>, choice_lists: array<string, mixed>}|null
     */
    private function writeSection(array $p, array $progress, int $index): ?array
    {
        $section = $progress['todo'][$index];
        $user = $this->briefText($p, $progress['languages'])
            . "\nPlan complet :\n" . json_encode($progress['plan'], JSON_UNESCAPED_UNICODE)
            . "\n\nSection à rédiger maintenant (" . ($index + 1) . '/' . count($progress['todo']) . ") :\n" . json_encode($section, JSON_UNESCAPED_UNICODE)
            . "\n\nIdentifiants déjà utilisés (interdits) : " . implode(', ', $this->usedNames($progress['done']))
            . "\nListes de choix déjà définies (réutilisables) : " . json_encode($this->knownLists($progress['done']), JSON_UNESCAPED_UNICODE);
        $answer = $this->call('form_section', 'form_section', $user, 'form_section')->json();

        return $answer === null ? null : FormAssembler::section($answer, (string) ($section['name'] ?? 'section_' . ($index + 1)), (string) ($section['title'] ?? 'Section ' . ($index + 1)), $progress['languages'][0]);
    }

    /**
     * @param array<string, mixed> $p
     * @param array<string, mixed> $progress
     *
     * @return array{group: array<string, mixed>, choice_lists: array<string, mixed>}|null
     */
    private function revisePart(array $p, array $progress, int $index): ?array
    {
        $part = $progress['todo'][$index];
        $user = "Consigne :\n" . mb_substr((string) ($p['instruction'] ?? ''), 0, 2000)
            . "\n\nLangues du questionnaire : " . implode(', ', $progress['languages'])
            . "\nAperçu du questionnaire :\n" . $progress['outline']
            . "\n\nListes de choix utilisées par cette partie : " . json_encode($part['choice_lists'], JSON_UNESCAPED_UNICODE)
            . "\n\nPartie " . ($index + 1) . '/' . count($progress['todo']) . " :\n" . json_encode($part['group'], JSON_UNESCAPED_UNICODE);
        $answer = $this->call('form_revision', 'form_item_revise', $user, 'form_item')->json();

        return $answer === null ? null : FormAssembler::item($answer, $part, $progress['languages'][0]);
    }

    /**
     * @param array<string, mixed> $progress
     *
     * @return array{0: string, 1: FormValidationResult}
     */
    private function assemble(array $progress, FormValidator $validator): array
    {
        $languages = $progress['languages'];
        if ($progress['mode'] === 'revise') {
            $languages = $this->withNewLanguages($languages, $progress['done']);
        }
        $form = FormAssembler::assemble($progress['done'], $progress['settings'], $languages);
        $json = (string) json_encode($form, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $result = $validator->validateJson($json);
        if (!$result->isValid()) {
            $fixed = (string) json_encode(FormAssembler::autofix(json_decode($json, true, 64, JSON_THROW_ON_ERROR)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $fixedResult = $validator->validateJson($fixed);
            if ($fixedResult->isValid() || count($fixedResult->errors()) < count($result->errors())) {
                return [$fixed, $fixedResult];
            }
        }

        return [$json, $result];
    }

    /**
     * Indexes of the top-level parts concerned by validation errors ("/items/3/…" → 3).
     *
     * @param list<array{path: string, code: string, message: string}> $errors
     *
     * @return list<int>
     */
    private function faultyParts(array $errors, int $count): array
    {
        $indexes = [];
        foreach ($errors as $e) {
            if (preg_match('#^/items/(\d+)#', $e['path'], $m) && (int) $m[1] < $count) {
                $indexes[(int) $m[1]] = true;
            }
        }
        ksort($indexes);

        return array_keys($indexes);
    }

    /**
     * A revision may add a language ("translate into English"): accept label codes it introduced.
     *
     * @param list<string> $languages
     * @param list<array{group: array<string, mixed>, choice_lists: array<string, mixed>}> $parts
     *
     * @return list<string>
     */
    private function withNewLanguages(array $languages, array $parts): array
    {
        $found = [];
        array_walk_recursive($parts, static function ($v, $k) use (&$found): void {
            if (is_string($k) && preg_match('/^[a-z]{2,3}$/', $k) && is_string($v)) {
                $found[$k] = true;
            }
        });
        foreach (array_keys($found) as $code) {
            if (!in_array($code, $languages, true) && in_array($code, ['fr', 'en', 'es', 'pt', 'de', 'ar', 'sw', 'wo', 'ff', 'ha', 'ln'], true)) {
                $languages[] = $code;
            }
        }

        return $languages;
    }

    /**
     * Compact outline of a form for revision prompts: "path (type) : label".
     *
     * @param array<string, mixed> $form
     */
    private function outline(array $form): string
    {
        $lines = [];
        foreach (FormSchema::questions($form) as $q) {
            $lines[] = '- ' . $q['path'] . ' (' . $q['type'] . ') : ' . mb_substr($q['label'], 0, 120);
        }

        return implode("\n", array_slice($lines, 0, 200));
    }

    /**
     * Sections of the plan, each {name, title, purpose, questions}; at most 8.
     *
     * @param array<string, mixed>|null $plan
     *
     * @return list<array<string, mixed>>
     */
    private function planSections(?array $plan): array
    {
        $out = [];
        foreach (is_array($plan['sections'] ?? null) ? $plan['sections'] : [] as $i => $section) {
            if (is_array($section)) {
                $out[] = [
                    'name' => Naming::slug((string) ($section['name'] ?? '')) ?: 'section_' . ($i + 1),
                    'title' => mb_substr((string) ($section['title'] ?? 'Section ' . ($i + 1)), 0, 200),
                    'purpose' => mb_substr((string) ($section['purpose'] ?? ''), 0, 500),
                    'questions' => max(1, min(12, (int) ($section['questions'] ?? 4))),
                ];
            }
        }

        return array_slice($out, 0, 8);
    }

    /**
     * @param array<string, mixed> $p
     * @param array<string, mixed> $plan
     * @param list<string> $languages
     *
     * @return array<string, mixed>
     */
    private function settings(array $p, array $plan, array $languages): array
    {
        $b = is_array($p['brief'] ?? null) ? $p['brief'] : [];
        $title = trim((string) ($plan['title'] ?? '')) !== '' ? mb_substr(trim((string) $plan['title']), 0, 200) : mb_substr(trim((string) ($b['objective'] ?? 'Questionnaire')), 0, 120);
        $settings = [
            'form_id' => (new Naming())->unique($title, 'form'),
            'title' => [$languages[0] => $title],
            'estimated_minutes' => max(1, min(240, (int) ($b['minutes'] ?? 5))),
            'sampling' => in_array($b['sampling'] ?? '', ['probabilistic', 'non_probabilistic', 'census'], true) ? $b['sampling'] : 'unknown',
        ];
        if (trim((string) ($b['objective'] ?? '')) !== '') {
            $settings['objective'] = mb_substr(trim((string) $b['objective']), 0, 2000);
        }
        if (trim((string) ($b['audience'] ?? '')) !== '') {
            $settings['target_population'] = mb_substr(trim((string) $b['audience']), 0, 1000);
        }

        return $settings;
    }

    /**
     * @param list<array{group: array<string, mixed>, choice_lists: array<string, mixed>}> $done
     *
     * @return list<string>
     */
    private function usedNames(array $done): array
    {
        $names = [];
        $walk = static function (array $node) use (&$walk, &$names): void {
            if (is_string($node['name'] ?? null)) {
                $names[] = $node['name'];
            }
            foreach (['items', 'rows'] as $key) {
                foreach (is_array($node[$key] ?? null) ? $node[$key] : [] as $child) {
                    if (is_array($child)) {
                        $walk($child);
                    }
                }
            }
        };
        foreach ($done as $section) {
            $walk($section['group']);
        }

        return $names;
    }

    /**
     * @param list<array{group: array<string, mixed>, choice_lists: array<string, mixed>}> $done
     *
     * @return array<string, list<string>> list name => codes (enough for the model to reuse it)
     */
    private function knownLists(array $done): array
    {
        $lists = [];
        foreach ($done as $section) {
            foreach ($section['choice_lists'] as $name => $choices) {
                $lists[(string) $name] = array_values(array_map(static fn($c): string => is_array($c) ? (string) ($c['value'] ?? '') : '', is_array($choices) ? $choices : []));
            }
        }

        return $lists;
    }

    /**
     * @param array<string, mixed> $p
     *
     * @return list<string>
     */
    private function languages(array $p): array
    {
        $langs = array_values(array_filter((array) ($p['brief']['languages'] ?? ['fr']), static fn($l): bool => is_string($l) && preg_match('/^[a-z]{2,3}$/', $l) === 1));

        return $langs === [] ? ['fr'] : array_values(array_unique($langs));
    }

    /**
     * @param array<string, mixed> $p
     * @param list<string> $languages
     */
    private function briefText(array $p, array $languages): string
    {
        $b = is_array($p['brief'] ?? null) ? $p['brief'] : [];
        $line = static fn(string $label, string $key): string => trim((string) ($b[$key] ?? '')) !== '' ? $label . ' : ' . mb_substr(trim((string) $b[$key]), 0, 1500) . "\n" : '';

        return "Demande de questionnaire\n"
            . $line('Objectif', 'objective')
            . $line('Public visé', 'audience')
            . $line('Analyses souhaitées', 'analyses')
            . $line('Contexte', 'context')
            . 'Langues : ' . implode(', ', $languages) . "\n"
            . 'Durée visée : ' . max(1, min(60, (int) ($b['minutes'] ?? 5))) . " minutes\n"
            . 'Échantillonnage : ' . (in_array($b['sampling'] ?? '', ['probabilistic', 'non_probabilistic', 'census'], true) ? $b['sampling'] : 'unknown') . "\n";
    }

    /** @return array<string, mixed>|null the current draft version of the form */
    private function currentDraft(int $formId): ?array
    {
        $db = $this->ctx->app->db();
        $form = $db->fetchOne('SELECT current_version_id FROM forms WHERE id = ? AND organization_id = ?', [$formId, $this->orgId]);
        if ($form === null || $form['current_version_id'] === null) {
            return null;
        }
        $version = (new FormRepository($db))->findVersion($this->orgId, (int) $form['current_version_id']);

        return $version !== null && $version['status'] === 'draft' ? $version : null;
    }
}
