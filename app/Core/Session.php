<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Session wrapper. On shared hosting the default save path (/tmp) may be shared
 * between accounts, so sessions are stored under storage/sessions.
 * With $native = false (CLI, tests) an in-memory array is used.
 */
final class Session
{
    /** @var array<string, mixed> */
    private array $memory = [];
    private bool $started = false;

    public function __construct(
        private readonly string $savePath,
        private readonly bool $secure,
        private readonly bool $native = true,
        private readonly string $name = 'f2s_session',
    ) {}

    public function start(): void
    {
        if ($this->started) {
            return;
        }
        $this->started = true;
        if (!$this->native || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (is_dir($this->savePath) && is_writable($this->savePath)) {
            session_save_path($this->savePath);
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', '7200');
        session_name($this->name);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $this->secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();
        $store = $this->native ? $_SESSION : $this->memory;

        return $store[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        if ($this->native) {
            $_SESSION[$key] = $value;
        } else {
            $this->memory[$key] = $value;
        }
    }

    public function remove(string $key): void
    {
        $this->start();
        if ($this->native) {
            unset($_SESSION[$key]);
        } else {
            unset($this->memory[$key]);
        }
    }

    /** Must be called on every privilege change (login, logout). */
    public function regenerate(): void
    {
        $this->start();
        if ($this->native && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public function destroy(): void
    {
        $this->start();
        if ($this->native && session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
        $this->memory = [];
        $this->started = false;
    }

    public function flash(string $type, string $message): void
    {
        $flashes = $this->get('_flash', []);
        $flashes = is_array($flashes) ? $flashes : [];
        $flashes[] = ['type' => $type, 'message' => $message];
        $this->set('_flash', $flashes);
    }

    /** @return list<array{type: string, message: string}> */
    public function pullFlashes(): array
    {
        $flashes = $this->get('_flash', []);
        $this->remove('_flash');

        return is_array($flashes) ? array_values($flashes) : [];
    }
}
