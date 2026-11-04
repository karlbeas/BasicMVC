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
];
