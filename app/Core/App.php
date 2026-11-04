<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\AuditLogger;
use App\Services\AuthService;
use App\Services\SchemaUpgrader;

/**
 * Application kernel: configuration, lazy services, routing and error handling.
 */
final class App
{
    private static ?self $instance = null;

    private Config $config;
    private ?Database $db = null;
    private ?Session $session = null;
    private ?Csrf $csrf = null;
    private ?Lang $lang = null;
    private ?View $view = null;
    private ?AuthService $auth = null;
    private ?Logger $logger = null;
    private ?Router $router = null;
    private string $basePath = '';

    private function __construct(public readonly string $root)
    {
        $this->config = Config::fromDirectory($root . '/config');
        date_default_timezone_set('UTC');
    }

    public static function boot(string $root, bool $loadEnv = true): self
    {
        if ($loadEnv) {
            Env::load($root . '/.env');
        }
        $app = new self($root);
        self::$instance = $app;

        $debug = $app->config->bool('app.debug');
        ini_set('display_errors', $debug ? '1' : '0');
        error_reporting(E_ALL);

        return $app;
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            throw new \LogicException('App not booted');
        }

        return self::$instance;
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function db(): Database
    {
        return $this->db ??= new Database([
            'host' => $this->config->string('database.host', '127.0.0.1'),
            'port' => $this->config->int('database.port', 3306),
            'database' => $this->config->string('database.database'),
            'username' => $this->config->string('database.username'),
            'password' => $this->config->string('database.password'),
        ]);
    }

    public function setDatabase(Database $db): void
    {
        $this->db = $db;
    }

    public function session(): Session
    {
        return $this->session ??= new Session(
            $this->storagePath('sessions'),
            $this->config->bool('app.secure_cookies', true),
            PHP_SAPI !== 'cli',
        );
    }

    public function csrf(): Csrf
    {
        return $this->csrf ??= new Csrf($this->session());
    }

    public function lang(): Lang
    {
        if ($this->lang === null) {
            $locale = $this->session()->get('locale');
            $this->lang = new Lang(
                $this->root . '/lang',
                is_string($locale) ? $locale : $this->config->string('app.locale', 'fr'),
                $this->config->string('app.fallback_locale', 'en'),
            );
        }

        return $this->lang;
    }

    public function view(): View
    {
        return $this->view ??= new View($this->root . '/app/Views');
    }

    public function auth(): AuthService
    {
        return $this->auth ??= new AuthService($this->db(), $this->session(), new AuditLogger($this->db()));
    }

    public function schemaUpgrader(): SchemaUpgrader
    {
        return new SchemaUpgrader($this->db(), $this->root . '/database/migrations', $this->storagePath('cache'));
    }

    public function logger(): Logger
    {
        return $this->logger ??= new Logger($this->storagePath('logs'));
    }

    public function router(): Router
    {
        if ($this->router === null) {
            $this->router = new Router();
            $routes = require $this->root . '/config/routes.php';
            $routes($this->router);
        }

        return $this->router;
    }

    public function storagePath(string $sub = ''): string
    {
        $base = $this->config->string('app.storage_path', $this->root . '/storage');

        return rtrim($base, '/') . ($sub !== '' ? '/' . $sub : '');
    }

    public function setBasePath(string $basePath): void
    {
        $this->basePath = rtrim($basePath, '/');
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function url(string $path = ''): string
    {
        $path = ltrim($path, '/');
        $appUrl = rtrim($this->config->string('app.url'), '/');
        if ($appUrl !== '') {
            return $appUrl . '/' . $path;
        }

        return $this->basePath . '/' . $path;
    }

    /**
     * Absolute URL for links shared outside the app (campaign link, QR code):
     * APP_URL when configured, otherwise scheme + Host of the current request.
     */
    public function absoluteUrl(string $path, Request $request): string
    {
        $url = $this->url($path);
        if (preg_match('#^https?://#', $url)) {
            return $url;
        }
        $host = preg_replace('/[^A-Za-z0-9.:\[\]-]/', '', (string) $request->header('Host'));

        return ($request->isSecure() ? 'https' : 'http') . '://' . ($host !== '' ? $host : 'localhost') . $url;
    }

    /**
     * Base path detection for shared hosting: the app may be served from the
     * domain root, a sub-folder, or through the root .htaccess fallback that
     * rewrites everything into public/ (SCRIPT_NAME then ends with /public/index.php
     * while public URLs do not contain /public).
     *
     * @param array<string, mixed> $server
     */
    public static function detectBasePath(array $server, string $appUrl = ''): string
    {
        if ($appUrl !== '') {
            $path = parse_url($appUrl, PHP_URL_PATH);

            return is_string($path) ? rtrim($path, '/') : '';
        }
        $script = is_string($server['SCRIPT_NAME'] ?? null) ? $server['SCRIPT_NAME'] : '/index.php';
        $uri = is_string($server['REQUEST_URI'] ?? null) ? $server['REQUEST_URI'] : '/';
        $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
        if ($base !== '' && str_ends_with($base, '/public') && !str_starts_with($uri, $base . '/') && $uri !== $base) {
            $base = substr($base, 0, -strlen('/public'));
        }

        return $base === '/' ? '' : $base;
    }

    public function handle(Request $request): Response
    {
        try {
            // Fresh upload (no .env yet): everything goes to the web installer.
            if (!is_file($this->root . '/.env') && Env::get('DB_DATABASE', '') === '' && !str_starts_with($request->path, '/install')
                && !str_starts_with($request->path, '/assets/')) {
                return $this->withSecurityHeaders(Response::redirect($this->url('install')));
            }
            // After an update (zip extracted over the install): the first visit applies the
            // quick migrations itself — no CRON needed; maintenance page only if that fails.
            if (is_file($this->root . '/.env') && !str_starts_with($request->path, '/cron/') && !str_starts_with($request->path, '/assets/')
                && $this->schemaPending()) {
                return $this->withSecurityHeaders($this->errorResponse($request, 503)->withHeader('Retry-After', '60'));
            }
            $route = $this->router()->match($request->method, $request->path);
            $request->params = $route['params'];
            $middleware = $route['middleware'];

            if (!in_array('nocsrf', $middleware, true) && !in_array($request->method, ['GET', 'HEAD', 'OPTIONS'], true)) {
                $token = $request->input('_csrf') ?? $request->header('X-CSRF-Token');
                if (!$this->csrf()->validate($token)) {
                    throw new HttpException(419);
                }
            }
            if (in_array('auth', $middleware, true) && $this->auth()->current() === null) {
                if ($request->wantsJson()) {
                    throw new HttpException(401);
                }
                $this->session()->set('intended', $request->path);

                return Response::redirect($this->url('login'));
            }
            if (in_array('guest', $middleware, true) && $this->auth()->current() !== null) {
                return Response::redirect($this->url(''));
            }

            [$class, $method] = $route['handler'];
            $controller = new $class($this);
            $response = $controller->{$method}($request);
            if (!$response instanceof Response) {
                throw new \LogicException($class . '::' . $method . ' must return a Response');
            }
        } catch (HttpException $e) {
            $response = $this->errorResponse($request, $e->status);
        } catch (\Throwable $e) {
            $this->logger()->error($e->getMessage(), [
                'exception' => $e::class,
                'file' => $e->getFile() . ':' . $e->getLine(),
                'path' => $request->path,
            ]);
            $response = $this->errorResponse($request, 500, $this->config->bool('app.debug') ? $e->getMessage() : null);
        }

        return $this->withSecurityHeaders($response);
    }

    /** True if migrations are still pending after trying to apply them. */
    private function schemaPending(): bool
    {
        $upgrader = $this->schemaUpgrader();
        if ($upgrader->upToDateMarker() || $upgrader->isUpToDate()) {
            return false;
        }
        try {
            $upgrader->upgrade();
        } catch (\Throwable $e) {
            $this->logger()->error('Schema upgrade failed', ['error' => $e->getMessage()]);

            return true;
        }

        return !$upgrader->upToDateMarker();
    }

    public function errorResponse(Request $request, int $status, ?string $detail = null): Response
    {
        $key = in_array($status, [401, 403, 404, 405, 419, 429, 503], true) ? 'error.' . $status : 'error.500';
        if ($request->wantsJson()) {
            return Response::json(['error' => ['status' => $status, 'message' => $this->lang()->get($key)]], $status);
        }
        try {
            $html = $this->view()->render('errors/error', [
                'status' => $status,
                'message' => $this->lang()->get($key),
                'detail' => $detail,
                'auth' => null,
                'flashes' => [],
                'locale' => $this->lang()->locale(),
            ]);
        } catch (\Throwable) {
            $html = '<h1>' . $status . '</h1>';
        }

        return Response::html($html, $status);
    }

    private function withSecurityHeaders(Response $response): Response
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Content-Security-Policy' => "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'",
        ];
        foreach ($headers as $name => $value) {
            if (!isset($response->headers[$name])) {
                $response->headers[$name] = $value;
            }
        }
        if (str_starts_with($response->headers['Content-Type'] ?? '', 'text/html')) {
            $response->headers['Cache-Control'] = 'no-store';
        }

        return $response;
    }
}
