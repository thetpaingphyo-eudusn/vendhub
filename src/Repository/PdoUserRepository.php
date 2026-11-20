<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database\Connection;
use App\Model\User;

final class PdoUserRepository implements UserRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function create(string $name, string $email, string $passwordHash, string $role): User
    {
        $this->connection->execute(
            'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)',
            [$name, $email, $passwordHash, $role],
        );

        return new User($this->connection->lastInsertId(), $name, $email, $passwordHash, $role);
    }

    public function findById(int $id): ?User
    {
        $row = $this->connection->selectOne(
            'SELECT id, name, email, password_hash, role FROM users WHERE id = ?',
            [$id],
        );

        return $row === null ? null : $this->map($row);
    }

    public function findByEmail(string $email): ?User
    {
        $row = $this->connection->selectOne(
            'SELECT id, name, email, password_hash, role FROM users WHERE email = ?',
            [$email],
        );

        return $row === null ? null : $this->map($row);
    }

    public function all(): array
    {
        $rows = $this->connection->select(
            'SELECT id, name, email, password_hash, role FROM users ORDER BY name ASC',
        );

        return array_map($this->map(...), $rows);
    }

    public function update(User $user): void
    {
        $this->connection->execute(
            'UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?',
            [$user->name, $user->email, $user->role, $user->id],
        );
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $this->connection->execute(
            'UPDATE users SET password_hash = ? WHERE id = ?',
            [$passwordHash, $id],
        );
    }

    public function delete(int $id): void
    {
        $this->connection->execute('DELETE FROM users WHERE id = ?', [$id]);
    }

    public function countByRole(string $role): int
    {
        $row = $this->connection->selectOne(
            'SELECT COUNT(*) AS total FROM users WHERE role = ?',
            [$role],
        );

        return (int) ($row['total'] ?? 0);
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): User
    {
        return new User(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['email'],
            (string) $row['password_hash'],
            (string) $row['role'],
        );
    }
}
