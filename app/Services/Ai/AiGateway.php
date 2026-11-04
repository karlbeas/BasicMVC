<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Core\Config;
use App\Core\Database;

/**
 * Single entry point to the AI provider (Mammouth, OpenAI-compatible API).
 * Server-side only, called from queued jobs, never from a web controller.
 * Models come from configuration; every attempt is logged in token_usage;
 * a daily budget per organization is enforced before each call.
 * Never send respondents' personal data here (MANIFEST rule 4).
 */
final class AiGateway
{
    public function __construct(
        private Database $db,
        private Config $config,
        private HttpTransport $transport = new CurlTransport(),
    ) {}

    public function isConfigured(): bool
    {
        return $this->config->string('ai.api_key') !== '';
    }

    public function timeout(): int
    {
        return max(5, $this->config->int('ai.timeout_seconds', 25));
    }

    /**
     * @param string $modelKey key in config ai.models ("form", "report"…); "<key>_fallback" is tried on failure
     *
     * @throws AiException
     */
    public function complete(string $operation, string $modelKey, string $system, string $user, int $maxTokens, int $organizationId, ?int $userId = null, ?int $jobId = null): AiResponse
    {
        if (!$this->isConfigured()) {
            throw new AiException('not_configured', 'MAMMOUTH_API_KEY is not set');
        }
        $this->assertBudget($organizationId);
        $models = array_values(array_filter([
            $this->config->string('ai.models.' . $modelKey),
            $this->config->string('ai.models.' . $modelKey . '_fallback'),
        ]));
        if ($models === []) {
            throw new AiException('not_configured', 'No model configured for ' . $modelKey);
        }
        $last = new AiException('http', 'AI call failed');
        foreach ($models as $model) {
            try {
                return $this->call($operation, $model, $system, $user, $maxTokens, $organizationId, $userId, $jobId);
            } catch (AiException $e) {
                $last = $e;
                if ($e->reason === 'budget') {
                    throw $e;
                }
            }
        }
        throw $last;
    }

    /** Spend of the organization today (UTC), in micro-dollars. */
    public function spentToday(int $organizationId): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COALESCE(SUM(cost_micro_usd), 0) FROM token_usage WHERE organization_id = ? AND created_at >= ?',
            [$organizationId, gmdate('Y-m-d 00:00:00')],
        );
    }

    public function costMicroUsd(string $model, int $promptTokens, int $completionTokens): int
    {
        // Model names contain dots (deepseek-v4.1-flash): index the array, no dot-notation lookup.
        $table = $this->config->get('ai.prices', []);
        $prices = is_array($table) ? ($table[$model] ?? null) : null;
        if (!is_array($prices)) {
            // Unknown model: never count it as free, otherwise the daily budget would not apply.
            $prices = $this->config->get('ai.prices_default', [2.0, 10.0]);
            $prices = is_array($prices) ? $prices : [2.0, 10.0];
        }
        // Prices are $ per million tokens, so tokens × price = micro-dollars.
        return (int) round($promptTokens * (float) ($prices[0] ?? 0) + $completionTokens * (float) ($prices[1] ?? 0));
    }

    private function assertBudget(int $organizationId): void
    {
        $budget = (int) round(((float) $this->config->get('ai.daily_budget_usd_per_org', 0.5)) * 1_000_000);
        if ($budget > 0 && $this->spentToday($organizationId) >= $budget) {
            throw new AiException('budget', 'Daily AI budget reached for this organization');
        }
    }

    private function call(string $operation, string $model, string $system, string $user, int $maxTokens, int $organizationId, ?int $userId, ?int $jobId): AiResponse
    {
        $url = rtrim($this->config->string('ai.base_url', 'https://api.mammouth.ai/v1'), '/') . '/chat/completions';
        $body = json_encode([
            'model' => $model,
            'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]],
            'temperature' => 0.2,
            'max_tokens' => $maxTokens,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $started = microtime(true);
        try {
            $response = $this->transport->postJson($url, ['Authorization' => 'Bearer ' . $this->config->string('ai.api_key')], $body, $this->timeout());
        } catch (AiException $e) {
            $this->log($organizationId, $userId, $jobId, $operation, $model, 0, 0, 0, false, $started);
            throw $e;
        }
        $data = json_decode($response['body'], true);
        if ($response['status'] < 200 || $response['status'] >= 300 || !is_array($data)) {
            $this->log($organizationId, $userId, $jobId, $operation, $model, 0, 0, 0, false, $started);
            $detail = is_array($data) && is_array($data['error'] ?? null) ? (string) ($data['error']['message'] ?? '') : '';
            throw new AiException('http', 'AI API HTTP ' . $response['status'] . ($detail !== '' ? ': ' . mb_substr($detail, 0, 200) : ''));
        }
        $content = $data['choices'][0]['message']['content'] ?? null;
        $promptTokens = (int) ($data['usage']['prompt_tokens'] ?? 0);
        $completionTokens = (int) ($data['usage']['completion_tokens'] ?? 0);
        $cost = $this->costMicroUsd($model, $promptTokens, $completionTokens);
        $this->log($organizationId, $userId, $jobId, $operation, $model, $promptTokens, $completionTokens, $cost, is_string($content), $started);
        if (!is_string($content) || $content === '') {
            throw new AiException('http', 'Empty AI answer');
        }

        return new AiResponse($content, $model, $promptTokens, $completionTokens, $cost);
    }

    private function log(int $organizationId, ?int $userId, ?int $jobId, string $operation, string $model, int $in, int $out, int $cost, bool $success, float $started): void
    {
        $this->db->insert('token_usage', [
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'job_id' => $jobId,
            'operation' => mb_substr($operation, 0, 48),
            'model' => mb_substr($model, 0, 80),
            'prompt_tokens' => $in,
            'completion_tokens' => $out,
            'cost_micro_usd' => $cost,
            'success' => $success ? 1 : 0,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ]);
    }
}
