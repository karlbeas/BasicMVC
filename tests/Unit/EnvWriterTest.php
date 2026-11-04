<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Env;
use App\Services\EnvWriter;
use PHPUnit\Framework\TestCase;

final class EnvWriterTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach (array_keys($_ENV) as $key) {
            if (str_starts_with((string) $key, 'F2S_W_')) {
                unset($_ENV[$key]);
            }
        }
    }

    public function testRoundTripThroughEnvLoader(): void
    {
        $values = [
            'F2S_W_PASSWORD' => 'p@ss"word\\with $dollar # hash \'quote\'',
            'F2S_W_BOOL' => false,
            'F2S_W_INT' => 3306,
            'F2S_W_EMPTY' => '',
        ];
        $file = sys_get_temp_dir() . '/f2s-env-' . bin2hex(random_bytes(4));
        EnvWriter::write($file, $values, "header\nsecond line");
        Env::load($file);
        @unlink($file);

        self::assertSame($values['F2S_W_PASSWORD'], Env::get('F2S_W_PASSWORD'));
        self::assertFalse(Env::bool('F2S_W_BOOL', true));
        self::assertSame(3306, Env::int('F2S_W_INT'));
        self::assertSame('', Env::get('F2S_W_EMPTY'));
    }

    public function testRejectsLineBreaksAndBadKeys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EnvWriter::render(['F2S_W_X' => "a\nINJECTED=1"]);
    }

    public function testNeverOverwritesExistingFile(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'env') ?: '';
        try {
            $this->expectException(\RuntimeException::class);
            EnvWriter::write($file, ['F2S_W_A' => 'b']);
        } finally {
            @unlink($file);
        }
    }
}
