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
use App\Services\AuditLogger;
use App\Services\ContextFactory;
use App\Services\FormInvalidException;
use App\Services\FormSchema;
use App\Services\FormService;
use App\Services\FormValidator;
use App\Services\ImmutableVersionException;
use App\Services\QuotaExceededException;

/**
 * AI Studio job (types "ai.form_generate" and "ai.form_revise").
 * generate: plan of sections → questionnaire → validation (+ one automatic repair) → draft form.
 * revise:   current draft + instruction → validation (+ one repair) → same draft updated.
 * Each AI call is a resumable step: if the remaining time cannot fit a call, the job yields.
 * Credits are charged once, only when a valid form has been saved. Never stores an invalid form.
 *
 * Payload: {mode, user_id, project_id?, form_id?, brief?: {...}, instruction?: string}
 */
final class FormGenerationHandler implements JobHandler
{
    public static ?AiGateway $gatewayOverride = null; // tests

    public function handle(JobContext $ctx): JobResult
    {
        $db = $ctx->app->db();
        $orgId = (int) $ctx->organizationId();
        $p = $ctx->payload;
        $mode = ($p['mode'] ?? 'generate') === 'revise' ? 'revise' : 'generate';
        $operation = $mode === 'revise' ? 'form_revision' : 'form_generation';
        $cost = $ctx->app->config()->int('ai.credits.' . $operation, $mode === 'revise' ? 1 : 5);
        $userId = (int) ($p['user_id'] ?? 0);
        $gateway = self::$gatewayOverride ?? new AiGateway($db, $ctx->app->config());
        $credits = new CreditLedger($db);
        $progress = $ctx->progress ?? ['step' => $mode === 'revise' ? 'form' : 'plan'];

        try {
            $auth = ContextFactory::forUser($db, $orgId, $userId);
        } catch (\DomainException) {
            return JobResult::done(['error' => 'forbidden']);
        }
        if (!$auth->can($mode === 'revise' ? 'form.edit' : 'form.create')) {
            return JobResult::done(['error' => 'forbidden']);
        }
        if ($credits->available($orgId) < $cost) {
            return JobResult::done(['error' => 'insufficient_credits']);
        }
        if (!$gateway->isConfigured()) {
            return JobResult::done(['error' => 'not_configured']);
        }

        $languages = $this->languages($p);
        $maxTokens = static fn(string $key, int $default): int => $ctx->app->config()->int('ai.max_tokens.' . $key, $default);
        $prompt = static fn(string $name): string => (string) file_get_contents($ctx->app->root . '/app/Prompts/' . $name . '.v1.md');
        $call = function (string $op, string $system, string $user, int $tokens) use ($ctx, $gateway, $orgId, $userId): AiResponse {
            return $gateway->complete($op, 'form', $system, $user, $tokens, $orgId, $userId, (int) $ctx->job['id']);
        };
        $needsTime = $gateway->timeout() + 3;

        try {
            // Step 1 (generate only): plan of sections.
            if ($progress['step'] === 'plan') {
                if ($ctx->secondsLeft() < $needsTime) {
                    return JobResult::yield($progress);
                }
                $plan = $call('form_plan', $prompt('form_plan'), $this->briefText($p, $languages), $maxTokens('form_plan', 1500))->json();
                if ($plan === null) {
                    return JobResult::done(['error' => 'invalid_output']);
                }
                $progress = ['step' => 'form', 'plan' => $plan];
                if ($ctx->secondsLeft() < $needsTime) {
                    return JobResult::yield($progress);
                }
            }

            // Step 2: questionnaire (or revision).
            if ($progress['step'] === 'form') {
                if ($ctx->secondsLeft() < $needsTime) {
                    return JobResult::yield($progress);
                }
                if ($mode === 'revise') {
                    $current = $this->currentDraft($ctx, $orgId, (int) ($p['form_id'] ?? 0));
                    if ($current === null) {
                        return JobResult::done(['error' => 'not_draft']);
                    }
                    $user = "Consigne :\n" . mb_substr((string) ($p['instruction'] ?? ''), 0, 2000) . "\n\nQuestionnaire actuel :\n" . $current['schema_json'];
                    $answer = $call('form_revision', $prompt('form_revision'), $user, $maxTokens('form_revision', 9000));
                } else {
                    $user = $this->briefText($p, $languages) . "\n\nPlan validé :\n" . json_encode($progress['plan'] ?? [], JSON_UNESCAPED_UNICODE);
                    $answer = $call('form_generation', $prompt('form_generation'), $user, $maxTokens('form_generation', 9000));
                }
                $progress = ['step' => 'validate', 'candidate' => $answer->content, 'repaired' => false] + $progress;
            }

            // Step 3: validation, with at most one automatic repair.
            $validator = FormValidator::default();
            $candidate = AiResponse::extractJson((string) ($progress['candidate'] ?? ''));
            $json = $candidate === null ? '' : (string) json_encode($this->normalize($candidate, $languages, $mode), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $result = $validator->validateJson($json === '' ? '{}' : $json);
            if (!$result->isValid()) {
                if ($progress['repaired'] === true) {
                    return JobResult::done(['error' => 'invalid_output', 'details' => array_slice($result->errors(), 0, 5)]);
                }
                if ($ctx->secondsLeft() < $needsTime) {
                    return JobResult::yield($progress);
                }
                $errors = implode("\n", array_map(static fn(array $e): string => $e['path'] . ' : ' . $e['message'], array_slice($result->errors(), 0, 15)));
                $answer = $call('form_repair', $prompt('form_repair'), "Erreurs :\n" . $errors . "\n\nJSON :\n" . ($json !== '' ? $json : (string) $progress['candidate']), $maxTokens($operation, 9000));
                $progress = ['candidate' => $answer->content, 'repaired' => true] + $progress;
                $candidate = $answer->json();
                $json = $candidate === null ? '' : (string) json_encode($this->normalize($candidate, $languages, $mode), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $result = $validator->validateJson($json === '' ? '{}' : $json);
                if (!$result->isValid()) {
                    return JobResult::done(['error' => 'invalid_output', 'details' => array_slice($result->errors(), 0, 5)]);
                }
            }

            // Step 4: save, then charge credits (idempotent per job).
            $forms = new FormService($db, $validator, new AuditLogger($db));
            if ($mode === 'revise') {
                $current = $this->currentDraft($ctx, $orgId, (int) ($p['form_id'] ?? 0));
                if ($current === null) {
                    return JobResult::done(['error' => 'not_draft']);
                }
                $forms->updateDraft($auth, (int) $current['id'], $json);
                $formId = (int) ($p['form_id'] ?? 0);
            } else {
                $formId = $forms->createFromJson($auth, (int) ($p['project_id'] ?? 0), $json, 'ai')['form_id'];
            }
            $credits->charge($orgId, $userId, $cost, $operation, 'job:' . $ctx->job['id']);
            $publicId = (string) $db->fetchValue('SELECT public_id FROM forms WHERE id = ? AND organization_id = ?', [$formId, $orgId]);

            return JobResult::done([
                'form_public_id' => $publicId,
                'questions' => FormSchema::questionCount(json_decode($json, true, 64, JSON_THROW_ON_ERROR)),
                'warnings' => count($result->warnings()),
                'credits' => $cost,
            ]);
        } catch (AiException $e) {
            if (in_array($e->reason, ['budget', 'not_configured'], true)) {
                return JobResult::done(['error' => $e->reason]);
            }
            throw $e; // network / HTTP errors: retried with backoff by the queue
        } catch (QuotaExceededException $e) {
            return JobResult::done(['error' => 'too_many_questions', 'limit' => $e->limit, 'actual' => $e->actual]);
        } catch (FormInvalidException) {
            return JobResult::done(['error' => 'invalid_output']);
        } catch (ImmutableVersionException) {
            return JobResult::done(['error' => 'not_draft']);
        }
    }

    /**
     * Server-side fixes that do not change meaning: schema version, languages, default language.
     *
     * @param array<string, mixed> $form
     * @param list<string> $languages
     *
     * @return array<string, mixed>
     */
    private function normalize(array $form, array $languages, string $mode): array
    {
        $form['schema_version'] = '1.0';
        if ($mode === 'generate') {
            $form['languages'] = $languages;
            $form['default_language'] = $languages[0];
        }
        if (!isset($form['choice_lists']) || $form['choice_lists'] === []) {
            $form['choice_lists'] = new \stdClass();
        }

        return $form;
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
    private function currentDraft(JobContext $ctx, int $orgId, int $formId): ?array
    {
        $form = $ctx->app->db()->fetchOne('SELECT current_version_id FROM forms WHERE id = ? AND organization_id = ?', [$formId, $orgId]);
        if ($form === null || $form['current_version_id'] === null) {
            return null;
        }
        $version = (new FormRepository($ctx->app->db()))->findVersion($orgId, (int) $form['current_version_id']);

        return $version !== null && $version['status'] === 'draft' ? $version : null;
    }
}
