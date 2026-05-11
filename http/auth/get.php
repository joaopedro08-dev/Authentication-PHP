<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/controller/auth.controller.php';

/**
 * Handles GET auth endpoints.
 */
function authGetHandler(string $action): array
{
	$controller = AuthController::create();

	return match ($action) {
		'verify' => handleVerify($controller),
		'me' => $controller->currentUserFromBearer(),
		default => [
			'status' => 404,
			'body' => $controller->error('GET auth endpoint not found.'),
		],
	};
}

/**
 * Verifies Authorization Bearer JWT.
 */
function handleVerify(AuthController $controller): array
{
	$authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

	if (!str_starts_with($authorization, 'Bearer ')) {
		return [
			'status' => 401,
			'body' => $controller->error('Authorization Bearer token is required.'),
		];
	}

	$jwt = trim(substr($authorization, 7));
	$result = $controller->verifyAccessToken($jwt);

	return [
		'status' => $result['success'] ? 200 : 401,
		'body' => $result,
	];
}
