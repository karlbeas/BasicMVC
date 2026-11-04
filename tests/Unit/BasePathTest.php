<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\App;
use App\Core\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Shared-hosting layouts the front controller must support. */
final class BasePathTest extends TestCase
{
    /** @return iterable<string, array{string, string, string, string}> */
    public static function layouts(): iterable
    {
        yield 'document root = public/' => ['/index.php', '/projects/abc', '', '/projects/abc'];
        yield 'sub-folder install' => ['/f2s/index.php', '/f2s/projects/abc?x=1', '/f2s', '/projects/abc'];
        yield 'root .htaccess fallback into public/' => ['/public/index.php', '/login', '', '/login'];
        yield 'fallback in a sub-folder' => ['/f2s/public/index.php', '/f2s/login', '/f2s', '/login'];
        yield 'no rewrite (index.php in URL)' => ['/index.php', '/index.php/login', '', '/login'];
        yield 'root URL' => ['/index.php', '/', '', '/'];
    }

    #[DataProvider('layouts')]
    public function testDetectsBasePathAndNormalizesPath(string $script, string $uri, string $expectedBase, string $expectedPath): void
    {
        $base = App::detectBasePath(['SCRIPT_NAME' => $script, 'REQUEST_URI' => $uri]);

        self::assertSame($expectedBase, $base);
        self::assertSame($expectedPath, Request::normalizePath($uri, $base));
    }

    public function testAppUrlWins(): void
    {
        self::assertSame('/enquetes', App::detectBasePath(['SCRIPT_NAME' => '/x/index.php'], 'https://example.org/enquetes/'));
    }
}
