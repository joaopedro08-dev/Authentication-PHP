<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/controller/auth.controller.php';

/**
 * Handles POST auth endpoints.
 */
function authPostHandler(string $action): array
{
	$controller = AuthController::create();
	$payload = json_decode((string) file_get_contents('php://input'), true);
	if (!is_array($payload)) {
		$payload = [];
	}

	// Rate limiting hint: use Redis/APCu sliding-window counters per IP and endpoint.
	return match ($action) {
		'register' => withStatus($controller->register($payload), 201),
		'login' => withStatus($controller->login($payload), 200),
		'refresh' => withStatus($controller->refresh($payload), 200),
		'logout' => withStatus($controller->logout($payload), 200),
		default => [
			'status' => 404,
			'body' => $controller->error('POST auth endpoint not found.'),
		],
	};
}

/**
 * Creates a response tuple with success/error status code.
 */
function withStatus(array $response, int $successStatus): array
{
	return [
		'status' => $response['success'] ? $successStatus : 400,
		'body' => $response,
	];
}
