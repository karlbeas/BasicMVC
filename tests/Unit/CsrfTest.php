<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Csrf;
use App\Core\Session;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    public function testTokenIsStableAndValidated(): void
    {
        $csrf = new Csrf(new Session('/nonexistent', false, false));
        $token = $csrf->token();

        self::assertSame(64, strlen($token));
        self::assertSame($token, $csrf->token());
        self::assertTrue($csrf->validate($token));
        self::assertFalse($csrf->validate('x' . substr($token, 1)));
        self::assertFalse($csrf->validate(''));
        self::assertFalse($csrf->validate(null));
        self::assertFalse($csrf->validate(['array']));
    }

    public function testRotateInvalidatesPreviousToken(): void
    {
        $csrf = new Csrf(new Session('/nonexistent', false, false));
        $old = $csrf->token();
        $csrf->rotate();

        self::assertFalse($csrf->validate($old));
        self::assertNotSame($old, $csrf->token());
    }
}
