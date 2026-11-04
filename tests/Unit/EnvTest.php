<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Env;
use PHPUnit\Framework\TestCase;

final class EnvTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'env') ?: '';
        file_put_contents($this->file, <<<'ENV'
            # comment
            F2S_T_PLAIN=hello
            F2S_T_QUOTED="with spaces # not a comment"
            F2S_T_SINGLE='raw $value'
            F2S_T_INLINE=value # trailing comment
            export F2S_T_EXPORTED=1
            F2S_T_EXISTING=from-file
            not a pair
            ENV);
        $_ENV['F2S_T_EXISTING'] = 'from-env';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        foreach (array_keys($_ENV) as $key) {
            if (str_starts_with((string) $key, 'F2S_T_')) {
                unset($_ENV[$key]);
            }
        }
    }

    public function testParsesValuesAndKeepsRealEnvironment(): void
    {
        Env::load($this->file);

        self::assertSame('hello', Env::get('F2S_T_PLAIN'));
        self::assertSame('with spaces # not a comment', Env::get('F2S_T_QUOTED'));
        self::assertSame('raw $value', Env::get('F2S_T_SINGLE'));
        self::assertSame('value', Env::get('F2S_T_INLINE'));
        self::assertTrue(Env::bool('F2S_T_EXPORTED'));
        self::assertSame('from-env', Env::get('F2S_T_EXISTING'));
        self::assertNull(Env::get('F2S_T_MISSING'));
        self::assertSame(7, Env::int('F2S_T_MISSING', 7));
    }
}
