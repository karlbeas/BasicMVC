<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Thin PDO wrapper: prepared statements only, lazy connection.
 * Compatible with MySQL 8+ and MariaDB 10.6+ (the usual shared-hosting engines).
 */
final class Database
{
    private ?PDO $pdo = null;

    /** @param array{host: string, port: int, database: string, username: string, password: string} $config */
    public function __construct(private array $config) {}

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $this->config['host'],
                $this->config['port'],
                $this->config['database'],
            );
            $this->pdo = new PDO($dsn, $this->config['username'], $this->config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
            // Store and compare all dates in UTC; strict mode avoids silent truncation.
            $this->pdo->exec("SET time_zone = '+00:00'");
            $this->pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        }

        return $this->pdo;
    }

    /**
     * @param array<string|int, mixed> $params
     *
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->run($sql, $params);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string|int, mixed> $params
     *
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        /** @var list<array<string, mixed>> */
        return $this->run($sql, $params)->fetchAll();
    }

    /** @param array<string|int, mixed> $params */
    public function fetchValue(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    /** @param array<string|int, mixed> $params */
    public function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    /** @param array<string, mixed> $data */
    public function insert(string $table, array $data): int
    {
        $columns = array_map([$this, 'identifier'], array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $this->identifier($table), implode(', ', $columns), $placeholders);
        $this->run($sql, array_values($data));

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Equality-only WHERE clause; callers must always include organization_id for tenant tables.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where
     */
    public function update(string $table, array $data, array $where): int
    {
        if ($where === []) {
            throw new \InvalidArgumentException('UPDATE without WHERE is not allowed');
        }
        $set = implode(', ', array_map(fn(string $c): string => $this->identifier($c) . ' = ?', array_keys($data)));
        $cond = implode(' AND ', array_map(fn(string $c): string => $this->identifier($c) . ' = ?', array_keys($where)));
        $sql = sprintf('UPDATE %s SET %s WHERE %s', $this->identifier($table), $set, $cond);

        return $this->run($sql, [...array_values($data), ...array_values($where)])->rowCount();
    }

    /**
     * @template T
     *
     * @param callable(self): T $fn
     *
     * @return T
     */
    public function transaction(callable $fn): mixed
    {
        $pdo = $this->pdo();
        if ($pdo->inTransaction()) {
            return $fn($this);
        }
        $pdo->beginTransaction();
        try {
            $result = $fn($this);
            $pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @param array<string|int, mixed> $params */
    private function run(string $sql, array $params): \PDOStatement
    {
        $stmt = $this->pdo()->prepare($sql);
        $positional = array_is_list($params);
        foreach ($params as $key => $value) {
            $name = $positional ? (int) $key + 1 : ':' . ltrim((string) $key, ':');
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $stmt->bindValue($name, $value, $type);
        }
        $stmt->execute();

        return $stmt;
    }

    private function identifier(string $name): string
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException('Invalid SQL identifier: ' . $name);
        }

        return '`' . $name . '`';
    }
}
