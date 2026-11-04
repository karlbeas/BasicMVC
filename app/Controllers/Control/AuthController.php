<?php

declare(strict_types=1);

namespace App\Controllers\Control;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\TooManyAttemptsException;
use App\Services\ValidationException;

final class AuthController extends Controller
{
    public function showLogin(Request $request): Response
    {
        return $this->view('auth/login', ['email' => '', 'error' => null]);
    }

    public function login(Request $request): Response
    {
        $email = $request->string('email');
        $password = (string) $request->input('password', '');
        try {
            $ok = $this->app->auth()->attempt($email, $password, $request->ip());
        } catch (TooManyAttemptsException) {
            return $this->view('auth/login', ['email' => $email, 'error' => t('auth.throttled')], 429);
        }
        if (!$ok) {
            return $this->view('auth/login', ['email' => $email, 'error' => t('auth.failed')], 422);
        }
        $this->app->csrf()->rotate();
        $intended = $this->app->session()->get('intended');
        $this->app->session()->remove('intended');

        return $this->redirect(is_string($intended) && str_starts_with($intended, '/') && !str_starts_with($intended, '//') ? $intended : '/');
    }

    public function showRegister(Request $request): Response
    {
        return $this->view('auth/register', ['old' => [], 'errors' => []]);
    }

    public function register(Request $request): Response
    {
        $old = [
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'organization' => $request->string('organization'),
        ];
        if ($request->input('accept_terms') !== '1') {
            return $this->view('auth/register', ['old' => $old, 'errors' => ['accept_terms' => 'validation.accept_terms']], 422);
        }
        try {
            $this->app->auth()->register(
                $old['name'],
                $old['email'],
                (string) $request->input('password', ''),
                $old['organization'],
                $this->app->lang()->locale(),
                $request->ip(),
            );
        } catch (ValidationException $e) {
            return $this->view('auth/register', ['old' => $old, 'errors' => $e->errors], 422);
        }
        $this->app->csrf()->rotate();
        $this->flash('success', t('auth.welcome'));

        return $this->redirect('/');
    }

    public function logout(Request $request): Response
    {
        $this->app->auth()->logout();

        return $this->redirect('/');
    }
}
