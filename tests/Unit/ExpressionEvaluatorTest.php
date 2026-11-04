<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Collect\ExpressionEvaluator;
use App\Services\Collect\UnsupportedExpressionException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExpressionEvaluatorTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../fixtures/expressions.json';

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function cases(): iterable
    {
        foreach (json_decode((string) file_get_contents(self::FIXTURE), true, 16, JSON_THROW_ON_ERROR) as $i => $case) {
            yield '#' . $i . ' ' . $case['expr'] => [$case];
        }
    }

    /** @param array<string, mixed> $case */
    #[DataProvider('cases')]
    public function testSharedFixture(array $case): void
    {
        $evaluator = new ExpressionEvaluator();
        if (($case['error'] ?? false) === true) {
            $this->expectException(UnsupportedExpressionException::class);
        }
        $result = $evaluator->evaluate((string) $case['expr'], $case['values'] ?? [], (string) ($case['current'] ?? ''));
        if (is_int($case['expect']) || is_float($case['expect'])) {
            self::assertEqualsWithDelta((float) $case['expect'], $result, 1e-9);
        } else {
            self::assertSame($case['expect'], $result);
        }
    }

    public function testJavascriptTwinGivesTheSameResults(): void
    {
        $node = trim((string) shell_exec('command -v node 2>/dev/null'));
        if ($node === '') {
            self::markTestSkipped('node not installed');
        }
        exec(escapeshellarg($node) . ' ' . escapeshellarg(__DIR__ . '/../js/expressions.test.cjs') . ' 2>&1', $output, $code);
        self::assertSame(0, $code, implode("\n", $output));
    }
}
