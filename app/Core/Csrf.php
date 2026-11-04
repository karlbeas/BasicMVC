<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    private const KEY = '_csrf';

    public function __construct(private Session $session) {}

    public function token(): string
    {
        $token = $this->session->get(self::KEY);
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::KEY, $token);
        }

        return $token;
    }

    public function validate(mixed $token): bool
    {
        $expected = $this->session->get(self::KEY);

        return is_string($expected) && is_string($token) && $token !== '' && hash_equals($expected, $token);
    }

    public function rotate(): void
    {
        $this->session->remove(self::KEY);
    }
}
