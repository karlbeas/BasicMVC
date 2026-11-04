<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PlanPolicy;
use App\Services\QuotaExceededException;
use PHPUnit\Framework\TestCase;

final class PlanPolicyTest extends TestCase
{
    public function testLimitsFlagsAndLists(): void
    {
        $plan = new PlanPolicy('free', ['questions_per_form' => 30, 'watermark' => true, 'exports' => ['csv', 'json']]);

        self::assertSame(30, $plan->limit('questions_per_form'));
        self::assertNull($plan->limit('unknown'));
        self::assertTrue($plan->flag('watermark'));
        self::assertSame(['csv', 'json'], $plan->list('exports'));
    }

    public function testAssertWithin(): void
    {
        $plan = new PlanPolicy('free', ['questions_per_form' => 30]);
        $plan->assertWithin('questions_per_form', 30);
        $plan->assertWithin('no_limit_defined', 10_000);

        try {
            $plan->assertWithin('questions_per_form', 31);
            self::fail('Expected quota exception');
        } catch (QuotaExceededException $e) {
            self::assertSame(30, $e->limit);
            self::assertSame(31, $e->actual);
        }
    }
}
