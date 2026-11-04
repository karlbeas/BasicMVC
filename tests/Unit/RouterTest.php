<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\HttpException;
use App\Core\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
        $this->router->get('/', [self::class, 'home']);
        $this->router->get('/forms/{form}/versions/{version}', [self::class, 'show'], ['auth']);
        $this->router->post('/forms/{form}/versions/{version}', [self::class, 'update'], ['auth']);
    }

    public function testMatchesStaticAndParameterizedRoutes(): void
    {
        self::assertSame([self::class, 'home'], $this->router->match('GET', '/')['handler']);

        $match = $this->router->match('POST', '/forms/abc-1/versions/v_2');
        self::assertSame([self::class, 'update'], $match['handler']);
        self::assertSame(['form' => 'abc-1', 'version' => 'v_2'], $match['params']);
        self::assertSame(['auth'], $match['middleware']);
    }

    public function testHeadIsTreatedAsGet(): void
    {
        self::assertSame([self::class, 'home'], $this->router->match('HEAD', '/')['handler']);
    }

    public function testUnknownPathIs404(): void
    {
        $this->expectExceptionObject(new HttpException(404));
        $this->router->match('GET', '/nope');
    }

    public function testWrongMethodIs405(): void
    {
        try {
            $this->router->match('DELETE', '/forms/a/versions/b');
            self::fail('Expected 405');
        } catch (HttpException $e) {
            self::assertSame(405, $e->status);
        }
    }

    public function testParametersRejectPathTraversalCharacters(): void
    {
        $this->expectException(HttpException::class);
        $this->router->match('GET', '/forms/../versions/x');
    }
}
