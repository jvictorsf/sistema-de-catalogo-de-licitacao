<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';

header('Content-Type: application/json; charset=utf-8');

$cnpj = (string) ($_GET['cnpj'] ?? '');

try {
    echo json_encode([
        'success' => true,
        'data' => lookup_cnpj_brasilapi($cnpj),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
