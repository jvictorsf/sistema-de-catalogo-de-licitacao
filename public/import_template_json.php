<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$scope = trim($_GET['scope'] ?? 'all');

try {
    $payload = catalog_json_import_template($scope);
} catch (Throwable $exception) {
    http_response_code(400);
    exit($exception->getMessage());
}

$filename = 'template-importacao-catalogo-' . $scope . '.json';

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
