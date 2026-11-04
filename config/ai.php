<?php

declare(strict_types=1);

use App\Core\Env;

// Model names are configuration, never hard-coded (MANIFEST §5). Used from phase 2.
return [
    'base_url' => Env::get('MAMMOUTH_BASE_URL', 'https://api.mammouth.ai/v1'),
    'api_key' => Env::get('MAMMOUTH_API_KEY', ''),
    // Max duration of one API call. In the CRON (CLI, no PHP time limit) long steps use it fully;
    // from a web request the call is capped by max_execution_time.
    'timeout_seconds' => Env::int('AI_TIMEOUT_SECONDS', 120),
    // Minimum time a web request must have left to attempt a step itself; otherwise the CRON does it.
    'step_seconds' => [
        // Every AI step is short (one section or one question): this much time left lets
        // a web request run it itself, so no CRON is needed to create or revise a form.
        'form_part' => 20,
    ],
    'daily_budget_usd_per_org' => (float) Env::get('AI_DAILY_BUDGET_USD_PER_ORG', '0.50'),
    // Model IDs as listed on https://model-explorer.mammouth.ai/ (checked 2026-11-04).
    // Each "<usage>_fallback" comes from another provider, used when the first one fails.
    'models' => [
        // Simple tasks: claude-haiku-5-5 (project owner's preference), gpt-6-luna as fallback.
        'form' => Env::get('AI_MODEL_FORM', 'claude-haiku-5-5'),
        'form_fallback' => Env::get('AI_MODEL_FORM_FALLBACK', 'gpt-6-luna'),
        'analysis_plan' => Env::get('AI_MODEL_ANALYSIS_PLAN', 'claude-haiku-5-5'),
        'analysis_plan_fallback' => Env::get('AI_MODEL_ANALYSIS_PLAN_FALLBACK', 'gpt-6-luna'),
        'report' => Env::get('AI_MODEL_REPORT', 'claude-haiku-5-5'),
        'report_fallback' => Env::get('AI_MODEL_REPORT_FALLBACK', 'gpt-6-luna'),
        'report_premium' => Env::get('AI_MODEL_REPORT_PREMIUM', 'claude-sonnet-5-5'),
        'report_premium_fallback' => Env::get('AI_MODEL_REPORT_PREMIUM_FALLBACK', 'gpt-6.1-sol'),
        'embeddings' => Env::get('AI_MODEL_EMBEDDINGS', 'text-embedding-3-small'),
    ],
    // Max output tokens per operation (tight on purpose: cost control).
    'max_tokens' => [
        'form_plan' => 1500,
        'form_section' => 3000,
        'form_item' => 3000,
    ],
    // $ per million tokens [input, output] for every Mammouth model (config/ai_prices.php).
    'prices' => require __DIR__ . '/ai_prices.php',
    // Applied to a model missing from the price list, so the daily budget still counts it.
    'prices_default' => [2.0, 10.0],
    // AI credit scale (MANIFEST §2).
    'credits' => [
        'form_generation' => 5,
        'form_revision' => 1,
        'word_pdf_conversion' => 5,
        'translation' => 2,
        'insight' => 1,
        'ask_my_survey' => 2,
        'open_coding_1000' => 10,
        'report_standard' => 15,
        'report_premium' => 40,
    ],
];
