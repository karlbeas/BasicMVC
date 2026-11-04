<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Uuid;
use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    public function testEscapesHtml(): void
    {
        self::assertSame('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', e('<script>alert("x")</script>'));
        self::assertSame('&#039;', e("'"));
        self::assertSame('', e(null));
        self::assertSame('', e(['x']));
        self::assertSame('42', e(42));
    }

    public function testUuidV4(): void
    {
        $a = Uuid::v4();
        self::assertTrue(Uuid::isValid($a));
        self::assertNotSame($a, Uuid::v4());
        self::assertFalse(Uuid::isValid('not-a-uuid'));
    }
}
