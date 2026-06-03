<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$scope = trim($_GET['scope'] ?? 'all');

try {
    $payload = export_catalog_data($scope);
} catch (Throwable $exception) {
    http_response_code(400);
    exit($exception->getMessage());
}

$filename = 'catalogo-licitacao-' . $scope . '-' . date('Ymd-His') . '.json';

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
