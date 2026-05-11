<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/routes/router.php';

$router = new Router();
$response = $router->dispatch();

http_response_code((int) ($response['status'] ?? 500));
echo json_encode($response['body'], JSON_UNESCAPED_SLASHES);