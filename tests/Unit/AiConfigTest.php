<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Core\Database;
use App\Services\Ai\AiGateway;
use PHPUnit\Framework\TestCase;

final class AiConfigTest extends TestCase
{
    public function testEveryDefaultModelIsInTheMammouthPriceList(): void
    {
        $config = Config::fromDirectory(__DIR__ . '/../../config');
        $prices = $config->get('ai.prices');
        self::assertIsArray($prices);
        self::assertGreaterThan(100, count($prices));
        foreach ((array) $config->get('ai.models') as $usage => $model) {
            self::assertArrayHasKey($model, $prices, "Model \"{$model}\" ({$usage}) is not on the Mammouth list: typo or retired model?");
        }
        foreach ($prices as $model => $p) {
            self::assertCount(2, $p, (string) $model);
            self::assertGreaterThanOrEqual(0, $p[0]);
        }
    }

    public function testFallbacksUseAnotherProviderThanTheMainModel(): void
    {
        $models = (array) Config::fromDirectory(__DIR__ . '/../../config')->get('ai.models');
        $provider = static fn(string $m): string => explode('-', $m)[0];
        foreach (['form', 'analysis_plan', 'report', 'report_premium'] as $usage) {
            self::assertNotSame($provider($models[$usage]), $provider($models[$usage . '_fallback']), $usage);
        }
    }

    public function testCostsAndUnknownModelsAreNeverFree(): void
    {
        $gateway = new AiGateway(new Database(['host' => 'x', 'port' => 1, 'database' => 'x', 'username' => 'x', 'password' => '']), Config::fromDirectory(__DIR__ . '/../../config'));

        self::assertSame(1100, $gateway->costMicroUsd('gpt-6-luna', 1000, 2000));
        self::assertSame(22, $gateway->costMicroUsd('text-embedding-3-small', 1000, 0));
        self::assertSame(22000, $gateway->costMicroUsd('model-that-does-not-exist', 1000, 2000), 'Unknown model charged at the conservative default (2 / 10 $)');
    }
}
