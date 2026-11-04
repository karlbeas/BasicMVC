<?php

declare(strict_types=1);

/*
 * Mammouth API price list, $ per million tokens [input, output].
 * Source: https://model-explorer.mammouth.ai/ — copied 2026-11-04 by the project owner.
 * Update this file when prices change (a new release, no .env change needed).
 * Models missing here are charged at ai.prices_default (conservative) so the daily budget still applies.
 */

return [
    'text-embedding-3-small' => [0.022, 0], // OpenAI, context 8K, max output n/a
    'llama-4-scout' => [0.08, 0.5], // Meta, context 131K, max output 8K
    'gpt-4.1-nano' => [0.1, 0.4], // OpenAI, context 1.0M, max output 33K
    'gpt-6-luna' => [0.1, 0.5], // OpenAI, context 1.1M, max output 128K
    'mistral-small-3.2-24b-instruct' => [0.1, 0.3], // Mistral, context n/a, max output n/a
    'gemini-2.5-flash-lite' => [0.1, 0.4], // Google, context 1.0M, max output 66K
    'claude-haiku-5-5' => [0.1, 0.5], // Anthropic, context 1M, max output 128K
    'qwen3.5-9b' => [0.1, 0.15], // Alibaba, context 256K, max output 256K
    'deepseek-v4-flash-0731' => [0.14, 0.28], // DeepSeek, context 1.1M, max output 384K
    'deepseek-v4-flash' => [0.14, 0.28], // DeepSeek, context 1.1M, max output 384K
    'text-embedding-3-large' => [0.143, 0], // OpenAI, context 8K, max output n/a
    'mammouth-recommended' => [0.15, 0.5], // Mammouth, context 1.0M, max output 128K
    'llama-4-maverick' => [0.15, 0.6], // Meta, context 131K, max output 131K
    'mistral-small-2603' => [0.15, 0.6], // Mistral, context n/a, max output n/a
    'glm-5.3-flash' => [0.15, 0.5], // Z.ai, context 1.0M, max output 128K
    'qwen3.8-27b' => [0.2, 2.5], // Alibaba, context 262K, max output 236K
    'gpt-5.6-luna' => [0.2, 1.2], // OpenAI, context 1.1M, max output 128K
    'gpt-5.4-nano' => [0.2, 1.25], // OpenAI, context 922K, max output 128K
    'deepseek-v4.1-flash' => [0.22, 0.66], // DeepSeek, context 1.0M, max output 1.0M
    'qwen3-coder' => [0.25, 1.45], // Alibaba, context n/a, max output n/a
    'gpt-5.1-codex-mini' => [0.25, 2], // OpenAI, context 272K, max output 128K
    'gemini-3.1-flash-lite-image' => [0.25, 1.5], // Google, context 66K, max output 33K
    'gemini-3.1-flash-lite-preview' => [0.25, 1.5], // Google, context n/a, max output n/a
    'qwen3.5-35b-a3b' => [0.25, 1.5], // Alibaba, context 262K, max output 262K
    'deepseek-v3.2' => [0.27, 0.4], // DeepSeek, context n/a, max output n/a
    'deepseek-v3.1-terminus' => [0.27, 1], // DeepSeek, context 131K, max output 33K
    'minimax-m3' => [0.3, 1.2], // Minimax, context 512K, max output 512K
    'gemini-2.5-flash' => [0.3, 2.5], // Google, context 1.0M, max output 66K
    'gemini-3.5-flash-lite' => [0.3, 2.5], // Google, context 1.0M, max output 66K
    'gemini-2.5-flash-image' => [0.3, 2.5], // Google, context 33K, max output 33K
    'codestral-2508' => [0.3, 0.9], // Mistral, context 256K, max output 256K
    'minimax-m2.7' => [0.3, 1.2], // Minimax, context 205K, max output 131K
    'gpt-4.1-mini' => [0.4, 1.6], // OpenAI, context 1.0M, max output 33K
    'devstral-2512' => [0.4, 2], // Mistral, context n/a, max output n/a
    'mistral-medium-3.1' => [0.4, 2], // Mistral, context 131K, max output 131K
    'gemini-3.1-flash-image-preview' => [0.5, 3], // Google, context 66K, max output 33K
    'mistral-large-3' => [0.5, 1.5], // Mistral, context n/a, max output n/a
    'deepseek-r1-0528' => [0.5, 2.18], // DeepSeek, context n/a, max output n/a
    'gemini-3-flash-preview' => [0.5, 3], // Google, context n/a, max output n/a
    'kimi-k2.5' => [0.6, 3], // Moonshot AI, context n/a, max output n/a
    'minimax-m2.7-highspeed' => [0.6, 2.4], // Minimax, context 205K, max output 131K
    'qwen3.5-397b-a17b' => [0.6, 3.6], // Alibaba, context 262K, max output 262K
    'kimi-k2.6' => [0.73, 3.49], // Moonshot AI, context 262K, max output 262K
    'gpt-5.4-mini' => [0.75, 4.5], // OpenAI, context 922K, max output 128K
    'kimi-k2.7-code' => [0.75, 3.5], // Moonshot AI, context 262K, max output 262K
    'glm-5' => [0.95, 2.55], // Z.ai, context 205K, max output 131K
    'claude-haiku-4-5' => [1, 5], // Anthropic, context 1M, max output 64K
    'glm-5.1' => [1.05, 3.5], // Z.ai, context 203K, max output 203K
    'glm-5-turbo' => [1.2, 4], // Z.ai, context 203K, max output 131K
    'gpt-5.1-chat' => [1.25, 10], // OpenAI, context 112K, max output 16K
    'gpt-5.1-codex-max' => [1.25, 10], // OpenAI, context 272K, max output 128K
    'gpt-5.1-codex' => [1.25, 10], // OpenAI, context 272K, max output 128K
    'gpt-5.1' => [1.25, 10], // OpenAI, context 272K, max output 128K
    'grok-4.3' => [1.25, 2.5], // xAI, context 1M, max output 1M
    'muse-spark-open' => [1.25, 4.25], // Meta, context 1.0M, max output 944K
    'mistral-large-4' => [1.36, 4.18], // Mistral, context 524K, max output 262K
    'glm-5.3' => [1.4, 4.4], // Z.ai, context 1.0M, max output 131K
    'glm-5.2' => [1.4, 4.4], // Z.ai, context 1.0M, max output 131K
    'mistral-medium-3-5' => [1.5, 7.5], // Mistral, context 262K, max output 262K
    'gemini-3.6-flash' => [1.5, 7.5], // Google, context 1.0M, max output 66K
    'gemini-3.7-flash' => [1.5, 7.5], // Google, context 1.0M, max output 66K
    'gemini-3.5-flash' => [1.5, 9], // Google, context 1.0M, max output 66K
    'gemini-3.8-flash' => [1.5, 7.5], // Google, context 1.0M, max output 66K
    'gemini-nano-banana-2.1' => [1.5, 7.5], // Google, context 66K, max output 59K
    'deepseek-v4-pro' => [1.74, 3.48], // DeepSeek, context 1.1M, max output 384K
    'gpt-5.3-codex' => [1.75, 14], // OpenAI, context 272K, max output 128K
    'gpt-5.2' => [1.75, 14], // OpenAI, context 272K, max output 128K
    'gpt-5.2-chat' => [1.75, 14], // OpenAI, context 112K, max output 16K
    'gpt-5.2-codex' => [1.75, 14], // OpenAI, context 272K, max output 128K
    'gpt-6.1-sol' => [2, 10], // OpenAI, context 1.1M, max output 128K
    'grok-4.7' => [2, 6], // xAI, context 500K, max output 500K
    'gemini-3.1-pro-preview' => [2, 12], // Google, context n/a, max output n/a
    'gpt-4.1' => [2, 8], // OpenAI, context 1.0M, max output 33K
    'gpt-5.6-terra' => [2, 12], // OpenAI, context 1.1M, max output 128K
    'grok-4.6' => [2, 6], // xAI, context 500K, max output 500K
    'gpt-6-sol' => [2, 10], // OpenAI, context 1.1M, max output 128K
    'grok-4.5' => [2, 6], // xAI, context 500K, max output 500K
    'gemini-3.1-pro-preview-customtools' => [2, 12], // Google, context 1.0M, max output 66K
    'kimi-k3.1-pro' => [2, 10], // Moonshot AI, context 1.0M, max output 944K
    'qwen3.8-2.4t-a95b' => [2, 6], // Alibaba, context 1.0M, max output 131K
    'gemini-3-pro-image-preview' => [2, 12], // Google, context 66K, max output 33K
    'claude-sonnet-5' => [2, 10], // Anthropic, context 1M, max output 128K
    'claude-sonnet-5-5' => [2, 10], // Anthropic, context 1M, max output 128K
    'gemini-2.5-pro' => [2.5, 15], // Google, context n/a, max output n/a
    'gpt-4o' => [2.5, 10], // OpenAI, context 128K, max output 16K
    'gpt-5.4' => [2.5, 15], // OpenAI, context 922K, max output 128K
    'claude-sonnet-4-5' => [3, 15], // Anthropic, context 1M, max output 64K
    'kimi-k3' => [3, 15], // Moonshot AI, context 1.0M, max output 1.0M
    'sonar-pro' => [3, 15], // Perplexity, context 200K, max output 8K
    'claude-sonnet-4-6' => [3, 15], // Anthropic, context 1M, max output 128K
    'claude-opus-5-5' => [4, 20], // Anthropic, context 1M, max output 128K
    'gemini-4-argon' => [4, 20], // Google, context 1.0M, max output 66K
    'kimi-k3.1-pro-highspeed' => [4, 20], // Moonshot AI, context 1.0M, max output 944K
    'kimi-k3-fast' => [4.5, 22.5], // Moonshot AI, context 1.0M, max output 1.0M
    'claude-opus-4-6' => [5, 25], // Anthropic, context 1M, max output 128K
    'gpt-5.5' => [5, 30], // OpenAI, context 1.1M, max output 128K
    'claude-opus-4-7' => [5, 25], // Anthropic, context 1M, max output 128K
    'gpt-5.6-sol' => [5, 30], // OpenAI, context 1.1M, max output 128K
    'claude-opus-4-5' => [5, 25], // Anthropic, context 200K, max output 64K
    'claude-opus-5' => [5, 25], // Anthropic, context 1M, max output 128K
    'claude-opus-4-8' => [5, 25], // Anthropic, context 1M, max output 128K
    'claude-opus-5-5-fast' => [8, 40], // Anthropic, context 1M, max output 128K
    'gpt-image-2' => [8, 15], // OpenAI, context n/a, max output n/a
    'claude-fable-5.1' => [10, 50], // Anthropic, context 1M, max output 128K
    'gpt-6-astra' => [10, 50], // OpenAI, context 1.1M, max output 128K
    'claude-fable-5' => [10, 50], // Anthropic, context 1M, max output 128K
    'claude-opus-5-fast' => [10, 50], // Anthropic, context 1M, max output 128K
];
