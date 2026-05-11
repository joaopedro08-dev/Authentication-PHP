<?php

declare(strict_types=1);

/**
 * README (runtime flow)
 *
 * 1) index.php is the single entry point through .htaccess rewrite rules.
 * 2) It loads environment variables from .env (via phpdotenv).
 * 3) Security headers and CORS are applied globally.
 * 4) Request is dispatched to routes/router.php, which maps:
 *    - GET  /api/auth/verify, /api/auth/me
 *    - POST /api/auth/register, /api/auth/login, /api/auth/refresh, /api/auth/logout
 *    - PUT  /api/auth/profile, /api/auth/password
 * 5) Handlers call AuthController for business logic and return a standard JSON format:
 *    {"success": bool, "data": mixed, "message": string}
 */

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

if (file_exists(__DIR__ . '/.env')) {
	Dotenv::createImmutable(__DIR__)->safeLoad();
}

require_once __DIR__ . '/config/cors.php';
require_once __DIR__ . '/routes/router.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

Cors::apply();

$router = new Router();
$response = $router->dispatch();

http_response_code((int) ($response['status'] ?? 500));
echo json_encode($response['body'], JSON_UNESCAPED_SLASHES);