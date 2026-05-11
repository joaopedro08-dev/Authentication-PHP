<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/controller/auth.controller.php';

/**
 * Handles PUT auth endpoints.
 */
function authPutHandler(string $action): array
{
	$controller = AuthController::create();
	$payload = json_decode((string) file_get_contents('php://input'), true);
	if (!is_array($payload)) {
		$payload = [];
	}

	return match ($action) {
		'profile' => withPutStatus($controller->updateProfile($payload)),
		'password' => withPutStatus($controller->updatePassword($payload)),
		default => [
			'status' => 404,
			'body' => $controller->error('PUT auth endpoint not found.'),
		],
	};
}

/**
 * Creates status tuple for PUT endpoint responses.
 */
function withPutStatus(array $response): array
{
	return [
		'status' => $response['success'] ? 200 : 400,
		'body' => $response,
	];
}
