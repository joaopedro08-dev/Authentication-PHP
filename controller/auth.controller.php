<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/model/users.php';
require_once dirname(__DIR__) . '/model/refresh_token.php';

/**
 * Authentication business logic layer.
 */
final class AuthController
{
    private const ACCESS_TOKEN_TTL = 900;
    private const REFRESH_TOKEN_TTL = 604800;

    public function __construct(
        private readonly UsersModel $usersModel,
        private readonly RefreshTokenModel $refreshTokenModel,
        private readonly string $jwtSecret
    ) {
    }

    /**
     * Factory with default dependencies.
     */
    public static function create(): self
    {
        $pdo = Database::getInstance();
        $secret = (string) ($_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?: 'change_me');

        return new self(new UsersModel($pdo), new RefreshTokenModel($pdo), $secret);
    }

    /**
     * Registers a new user.
     */
    public function register(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $email = filter_var((string) ($input['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $password = (string) ($input['password'] ?? '');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            return $this->error('Invalid registration input.');
        }

        if ($this->usersModel->findByEmail($email) !== null) {
            return $this->error('E-mail is already registered.');
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $createdUser = $this->usersModel->create(
            htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            $email,
            $passwordHash
        );

        if ($createdUser === null) {
            return $this->error('Could not create user.');
        }

        return $this->success($createdUser, 'User registered successfully.');
    }

    /**
     * Authenticates a user and issues tokens.
     */
    public function login(array $input): array
    {
        $email = filter_var((string) ($input['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $password = (string) ($input['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            return $this->error('Invalid login input.');
        }

        $user = $this->usersModel->findByEmail($email);
        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            return $this->error('Invalid credentials.');
        }

        $accessToken = $this->generateAccessToken((int) $user['id'], (string) $user['email']);
        $refreshPlain = bin2hex(random_bytes(64));
        $refreshHash = hash('sha256', $refreshPlain);
        $expiresAt = gmdate('Y-m-d H:i:s', time() + self::REFRESH_TOKEN_TTL);

        $this->refreshTokenModel->deleteExpired();
        $saved = $this->refreshTokenModel->create((int) $user['id'], $refreshHash, $expiresAt);
        if (!$saved) {
            return $this->error('Could not create refresh token.');
        }

        return $this->success(
            [
                'access_token' => $accessToken,
                'token_type' => 'Bearer',
                'expires_in' => self::ACCESS_TOKEN_TTL,
                'refresh_token' => $refreshPlain,
                'user' => [
                    'id' => (int) $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                ],
            ],
            'Login successful.'
        );
    }

    /**
     * Issues a new access token from a refresh token.
     */
    public function refresh(array $input): array
    {
        $refreshToken = trim((string) ($input['refresh_token'] ?? ''));
        if ($refreshToken === '') {
            return $this->error('Refresh token is required.');
        }

        $refreshHash = hash('sha256', $refreshToken);
        $stored = $this->refreshTokenModel->findValidToken($refreshHash);
        if ($stored === null) {
            return $this->error('Invalid or expired refresh token.');
        }

        $user = $this->usersModel->findById((int) $stored['user_id']);
        if ($user === null) {
            return $this->error('User not found.');
        }

        $newAccessToken = $this->generateAccessToken((int) $user['id'], (string) $user['email']);

        return $this->success(
            [
                'access_token' => $newAccessToken,
                'token_type' => 'Bearer',
                'expires_in' => self::ACCESS_TOKEN_TTL,
            ],
            'Access token refreshed successfully.'
        );
    }

    /**
     * Logs out by removing refresh token.
     */
    public function logout(array $input): array
    {
        $refreshToken = trim((string) ($input['refresh_token'] ?? ''));
        if ($refreshToken === '') {
            return $this->error('Refresh token is required.');
        }

        $refreshHash = hash('sha256', $refreshToken);
        $this->refreshTokenModel->deleteByToken($refreshHash);

        return $this->success(null, 'Logout successful.');
    }

    /**
     * Verifies a JWT access token and returns payload.
     */
    public function verifyAccessToken(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return $this->error('Invalid token format.');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $signedData = $encodedHeader . '.' . $encodedPayload;
        $expected = $this->base64UrlEncode(hash_hmac('sha256', $signedData, $this->jwtSecret, true));

        if (!hash_equals($expected, $encodedSignature)) {
            return $this->error('Invalid token signature.');
        }

        $payload = json_decode($this->base64UrlDecode($encodedPayload), true);
        if (!is_array($payload) || !isset($payload['exp']) || time() >= (int) $payload['exp']) {
            return $this->error('Token expired or malformed.');
        }

        return $this->success($payload, 'Token is valid.');
    }

    /**
     * Returns currently authenticated user by Authorization header.
     */
    public function currentUserFromBearer(): array
    {
        $bearer = $this->extractBearerToken();
        if ($bearer === null) {
            return $this->error('Missing Authorization Bearer token.');
        }

        $verified = $this->verifyAccessToken($bearer);
        if ($verified['success'] !== true) {
            return $verified;
        }

        $payload = $verified['data'];
        $userId = (int) ($payload['sub'] ?? 0);
        $user = $this->usersModel->findById($userId);

        if ($user === null) {
            return $this->error('User not found.');
        }

        return $this->success($user, 'Authenticated user fetched successfully.');
    }

    /**
     * Updates profile for authenticated user.
     */
    public function updateProfile(array $input): array
    {
        $auth = $this->currentUserFromBearer();
        if ($auth['success'] !== true) {
            return $auth;
        }

        $user = $auth['data'];
        $name = trim((string) ($input['name'] ?? ''));
        $email = filter_var((string) ($input['email'] ?? ''), FILTER_SANITIZE_EMAIL);

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Invalid profile input.');
        }

        $ok = $this->usersModel->updateProfile(
            (int) $user['id'],
            htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            $email
        );

        if (!$ok) {
            return $this->error('Could not update profile.');
        }

        $updated = $this->usersModel->findById((int) $user['id']);

        return $this->success($updated, 'Profile updated successfully.');
    }

    /**
     * Updates password for authenticated user.
     */
    public function updatePassword(array $input): array
    {
        $auth = $this->currentUserFromBearer();
        if ($auth['success'] !== true) {
            return $auth;
        }

        $currentPassword = (string) ($input['current_password'] ?? '');
        $newPassword = (string) ($input['new_password'] ?? '');

        if ($currentPassword === '' || strlen($newPassword) < 8) {
            return $this->error('Invalid password input.');
        }

        $user = $this->usersModel->findByEmail((string) $auth['data']['email']);
        if ($user === null || !password_verify($currentPassword, (string) $user['password_hash'])) {
            return $this->error('Current password is incorrect.');
        }

        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $updated = $this->usersModel->updatePassword((int) $user['id'], $newHash);

        if (!$updated) {
            return $this->error('Could not update password.');
        }

        $this->refreshTokenModel->deleteByUserId((int) $user['id']);

        return $this->success(null, 'Password updated. Please log in again.');
    }

    /**
     * Standard success response payload.
     */
    public function success(mixed $data, string $message): array
    {
        return [
            'success' => true,
            'data' => $data,
            'message' => $message,
        ];
    }

    /**
     * Standard error response payload.
     */
    public function error(string $message): array
    {
        return [
            'success' => false,
            'data' => null,
            'message' => $message,
        ];
    }

    /**
     * Generates a signed JWT access token.
     */
    private function generateAccessToken(int $userId, string $email): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $issuedAt = time();

        $payload = [
            'sub' => $userId,
            'email' => $email,
            'iat' => $issuedAt,
            'exp' => $issuedAt + self::ACCESS_TOKEN_TTL,
        ];

        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES) ?: '{}');
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '{}');
        $signature = hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, $this->jwtSecret, true);
        $encodedSignature = $this->base64UrlEncode($signature);

        return $encodedHeader . '.' . $encodedPayload . '.' . $encodedSignature;
    }

    /**
     * Encodes bytes/string in base64url without padding.
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Decodes base64url string.
     */
    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder > 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }

    /**
     * Extracts bearer token from Authorization header.
     */
    private function extractBearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? '';
        if ($header === '' && function_exists('getallheaders')) {
            $headers = getallheaders();
            $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }

        if (!is_string($header) || !str_starts_with($header, 'Bearer ')) {
            return null;
        }

        return trim(substr($header, 7));
    }
}