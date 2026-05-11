<?php

declare(strict_types=1);

/**
 * Model for users table CRUD operations.
 */
final class UsersModel
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Inserts a new user.
     */
    public function create(string $name, string $email, string $passwordHash): ?array
    {
        $sql = 'INSERT INTO users (name, email, password_hash, created_at, updated_at)
                VALUES (:name, :email, :password_hash, NOW(), NOW())';

        $stmt = $this->pdo->prepare($sql);
        $ok = $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':password_hash' => $passwordHash,
        ]);

        if (!$ok) {
            return null;
        }

        return $this->findById((int) $this->pdo->lastInsertId());
    }

    /**
     * Finds user by e-mail.
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        return $user !== false ? $user : null;
    }

    /**
     * Finds user by id.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, email, created_at, updated_at FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();

        return $user !== false ? $user : null;
    }

    /**
     * Updates profile fields.
     */
    public function updateProfile(int $id, string $name, string $email): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET name = :name, email = :email, updated_at = NOW() WHERE id = :id'
        );

        return $stmt->execute([
            ':id' => $id,
            ':name' => $name,
            ':email' => $email,
        ]);
    }

    /**
     * Updates user password.
     */
    public function updatePassword(int $id, string $passwordHash): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET password_hash = :password_hash, updated_at = NOW() WHERE id = :id'
        );

        return $stmt->execute([
            ':id' => $id,
            ':password_hash' => $passwordHash,
        ]);
    }
}