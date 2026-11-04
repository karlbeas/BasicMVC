<?php

declare(strict_types=1);

use App\Core\Env;

// Model names are configuration, never hard-coded (MANIFEST §5). Used from phase 2.
return [
    'base_url' => Env::get('MAMMOUTH_BASE_URL', 'https://api.mammouth.ai/v1'),
    'api_key' => Env::get('MAMMOUTH_API_KEY', ''),
    'timeout_seconds' => Env::int('AI_TIMEOUT_SECONDS', 25),
    'daily_budget_usd_per_org' => (float) Env::get('AI_DAILY_BUDGET_USD_PER_ORG', '0.50'),
    'models' => [
        'form' => Env::get('AI_MODEL_FORM', 'gpt-6-luna'),
        'form_fallback' => Env::get('AI_MODEL_FORM_FALLBACK', 'deepseek-v4.1-flash'),
        'analysis_plan' => Env::get('AI_MODEL_ANALYSIS_PLAN', 'deepseek-v4.1-flash'),
        'report' => Env::get('AI_MODEL_REPORT', 'gpt-6-luna'),
        'report_premium' => Env::get('AI_MODEL_REPORT_PREMIUM', 'claude-sonnet-5-5'),
        'embeddings' => Env::get('AI_MODEL_EMBEDDINGS', 'text-embedding-3-small'),
    ],
    // Max output tokens per operation (tight on purpose: cost control).
    'max_tokens' => [
        'form_plan' => 1500,
        'form_generation' => 9000,
        'form_revision' => 9000,
    ],
    // Published max prices, $ per million tokens [input, output] (MANIFEST §5, Nov. 2026 — re-check before going live).
    'prices' => [
        'gpt-6-luna' => [0.10, 0.50],
        'deepseek-v4.1-flash' => [0.22, 0.66],
        'claude-sonnet-5-5' => [2.0, 10.0],
        'text-embedding-3-small' => [0.02, 0.0],
    ],
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
