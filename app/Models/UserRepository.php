<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Uuid;

final class UserRepository
{
    public function __construct(private Database $db) {}

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, public_id, name, email, locale, status, last_login_at, created_at FROM users WHERE id = ?',
            [$id],
        );
    }

    /** @return array<string, mixed>|null includes password_hash — never pass to a view */
    public function findByEmail(string $email): ?array
    {
        return $this->db->fetchOne('SELECT * FROM users WHERE email = ?', [mb_strtolower($email)]);
    }

    public function create(string $name, string $email, string $passwordHash, string $locale): int
    {
        return $this->db->insert('users', [
            'public_id' => Uuid::v4(),
            'name' => $name,
            'email' => mb_strtolower($email),
            'password_hash' => $passwordHash,
            'locale' => $locale,
            'status' => 'active',
        ]);
    }

    public function updatePasswordHash(int $id, string $hash): void
    {
        $this->db->update('users', ['password_hash' => $hash], ['id' => $id]);
    }

    public function touchLogin(int $id): void
    {
        $this->db->update('users', ['last_login_at' => gmdate('Y-m-d H:i:s')], ['id' => $id]);
    }
}
