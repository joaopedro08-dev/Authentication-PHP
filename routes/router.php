<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/http/auth/get.php';
require_once dirname(__DIR__) . '/http/auth/post.php';
require_once dirname(__DIR__) . '/http/auth/put.php';

/**
 * Request router: maps method + path to HTTP handlers.
 */
final class Router
{
    /**
     * Dispatches the current request.
     */
    public function dispatch(): array
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = trim((string) parse_url($uri, PHP_URL_PATH), '/');
        $segments = $path === '' ? [] : explode('/', $path);

        if (($segments[0] ?? '') !== 'api' || ($segments[1] ?? '') !== 'auth') {
            return $this->response(404, false, null, 'Route not found.');
        }

        $action = $segments[2] ?? '';

        $result = match ($method) {
            'GET' => authGetHandler($action),
            'POST' => authPostHandler($action),
            'PUT' => authPutHandler($action),
            default => [
                'status' => 405,
                'body' => [
                    'success' => false,
                    'data' => null,
                    'message' => 'Method Not Allowed.',
                ],
            ],
        };

        return [
            'status' => (int) ($result['status'] ?? 500),
            'body' => $result['body'] ?? $this->response(500, false, null, 'Unexpected response body.')['body'],
        ];
    }

    /**
     * Builds a standard response structure.
     */
    private function response(int $status, bool $success, mixed $data, string $message): array
    {
        return [
            'status' => $status,
            'body' => [
                'success' => $success,
                'data' => $data,
                'message' => $message,
            ],
        ];
    }
}