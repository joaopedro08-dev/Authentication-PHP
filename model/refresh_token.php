<?php

declare(strict_types=1);

/**
 * Model for refresh_tokens table CRUD operations.
 */
final class RefreshTokenModel
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Creates a refresh token row.
     */
    public function create(int $userId, string $token, string $expiresAt): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO refresh_tokens (user_id, token, expires_at, created_at)
             VALUES (:user_id, :token, :expires_at, NOW())'
        );

        return $stmt->execute([
            ':user_id' => $userId,
            ':token' => $token,
            ':expires_at' => $expiresAt,
        ]);
    }

    /**
     * Finds a valid refresh token by token hash.
     */
    public function findValidToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, token, expires_at, created_at
             FROM refresh_tokens
             WHERE token = :token AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([':token' => $token]);
        $refreshToken = $stmt->fetch();

        return $refreshToken !== false ? $refreshToken : null;
    }

    /**
     * Deletes a refresh token by token hash.
     */
    public function deleteByToken(string $token): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM refresh_tokens WHERE token = :token');

        return $stmt->execute([':token' => $token]);
    }

    /**
     * Deletes all refresh tokens from one user.
     */
    public function deleteByUserId(int $userId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM refresh_tokens WHERE user_id = :user_id');

        return $stmt->execute([':user_id' => $userId]);
    }

    /**
     * Deletes expired refresh tokens.
     */
    public function deleteExpired(): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM refresh_tokens WHERE expires_at <= NOW()');

        return $stmt->execute();
    }
}