<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Explicit route table (no dynamic method invocation from the URL).
 * Patterns use {name} placeholders matching [A-Za-z0-9_-]+.
 */
final class Router
{
    /** @var list<array{method: string, regex: string, handler: array{class-string, string}, middleware: list<string>}> */
    private array $routes = [];

    /**
     * @param array{class-string, string} $handler
     * @param list<string> $middleware
     */
    public function get(string $pattern, array $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    /**
     * @param array{class-string, string} $handler
     * @param list<string> $middleware
     */
    public function post(string $pattern, array $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    /**
     * @param array{class-string, string} $handler
     * @param list<string> $middleware
     */
    public function add(string $method, string $pattern, array $handler, array $middleware = []): void
    {
        $regex = preg_replace('#\{([a-z_][a-z0-9_]*)\}#i', '(?P<$1>[A-Za-z0-9_-]+)', '/' . trim($pattern, '/'));
        $this->routes[] = [
            'method' => strtoupper($method),
            'regex' => '#^' . $regex . '$#',
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    /**
     * @return array{handler: array{class-string, string}, params: array<string, string>, middleware: list<string>}
     *
     * @throws HttpException 404 when no route matches, 405 when the method is not allowed
     */
    public function match(string $method, string $path): array
    {
        $method = strtoupper($method);
        $lookup = $method === 'HEAD' ? 'GET' : $method;
        $allowed = [];
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            if ($route['method'] !== $lookup) {
                $allowed[] = $route['method'];
                continue;
            }
            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }

            return ['handler' => $route['handler'], 'params' => $params, 'middleware' => $route['middleware']];
        }

        throw new HttpException($allowed === [] ? 404 : 405);
    }
}
