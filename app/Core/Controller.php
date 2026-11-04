<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\AuthContext;

abstract class Controller
{
    public function __construct(protected App $app) {}

    /** @param array<string, mixed> $data */
    protected function view(string $template, array $data = [], int $status = 200, ?string $layout = 'layouts/main'): Response
    {
        $data += [
            'auth' => $this->app->auth()->current(),
            'flashes' => $this->app->session()->pullFlashes(),
            'locale' => $this->app->lang()->locale(),
        ];

        return Response::html($this->app->view()->render($template, $data, $layout), $status);
    }

    /** @param array<mixed> $data */
    protected function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $path): Response
    {
        return Response::redirect($this->app->url($path));
    }

    protected function back(Request $request, string $fallback = '/'): Response
    {
        $referer = (string) $request->header('Referer');
        $base = $this->app->url('');
        // Only redirect back to our own URLs (no open redirect).
        if ($referer !== '' && str_starts_with($referer, $base)) {
            return Response::redirect($referer);
        }

        return $this->redirect($fallback);
    }

    protected function flash(string $type, string $message): void
    {
        $this->app->session()->flash($type, $message);
    }

    /** Authenticated context; routes using this must have the "auth" middleware. */
    protected function ctx(): AuthContext
    {
        $ctx = $this->app->auth()->current();
        if ($ctx === null) {
            throw new HttpException(401);
        }

        return $ctx;
    }

    protected function authorize(string $ability): AuthContext
    {
        $ctx = $this->ctx();
        if (!$ctx->can($ability)) {
            throw new HttpException(403);
        }

        return $ctx;
    }
}
