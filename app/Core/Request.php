<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    /** @var array<string, string> Route parameters */
    public array $params = [];

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $server
     * @param array<string, mixed> $files
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private array $query = [],
        private array $post = [],
        private array $server = [],
        private array $files = [],
        private ?string $body = null,
    ) {}

    public static function fromGlobals(string $basePath): self
    {
        $method = strtoupper(is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : 'GET');
        $uri = is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/';

        return new self(
            $method,
            self::normalizePath($uri, $basePath),
            $_GET,
            $_POST,
            $_SERVER,
            $_FILES,
            null,
        );
    }

    public static function normalizePath(string $uri, string $basePath): string
    {
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        if ($basePath !== '' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) {
            $path = substr($path, strlen($basePath));
        }
        if (str_starts_with($path, '/index.php')) {
            $path = substr($path, strlen('/index.php'));
        }
        $path = '/' . trim($path, '/');

        return $path;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    /** @return array<string, mixed> all POST fields (form submissions) */
    public function post(): array
    {
        return $this->post;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /** @return array<string, mixed>|null */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        return is_array($file) ? $file : null;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (in_array($key, ['HTTP_CONTENT_TYPE', 'HTTP_CONTENT_LENGTH'], true)) {
            $key = substr($key, 5);
        }
        $value = $this->server[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    public function body(): string
    {
        if ($this->body === null) {
            $raw = file_get_contents('php://input');
            $this->body = $raw === false ? '' : $raw;
        }

        return $this->body;
    }

    /** @return array<mixed>|null Decoded JSON body, null if absent or invalid. */
    public function json(): ?array
    {
        $body = $this->body();
        if ($body === '') {
            return null;
        }
        try {
            $data = json_decode($body, true, 128, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($data) ? $data : null;
    }

    public function wantsJson(): bool
    {
        return str_starts_with($this->path, '/api/')
            || str_contains((string) $this->header('Accept'), 'application/json');
    }

    public function isSecure(): bool
    {
        $https = $this->server['HTTPS'] ?? '';

        return (is_string($https) && $https !== '' && strtolower($https) !== 'off')
            || ($this->server['SERVER_PORT'] ?? null) == 443
            || strtolower((string) $this->header('X-Forwarded-Proto')) === 'https';
    }

    /** Client IP. X-Forwarded-For is deliberately ignored (spoofable). */
    public function ip(): string
    {
        $ip = $this->server['REMOTE_ADDR'] ?? '';

        return is_string($ip) ? $ip : '';
    }

    public function userAgent(): string
    {
        return mb_substr((string) $this->header('User-Agent'), 0, 255);
    }
}
