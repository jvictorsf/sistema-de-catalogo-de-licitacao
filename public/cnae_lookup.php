<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

header('Content-Type: application/json; charset=utf-8');

$query = trim((string) ($_GET['q'] ?? ''));

try {
    $items = array_map(
        static function (array $reference): array {
            return array_merge(cnae_reference_to_supplier_cnae($reference), [
                'class_code' => $reference['class_code'] ?? null,
                'class_description' => $reference['class_description'] ?? null,
                'group_code' => $reference['group_code'] ?? null,
                'group_description' => $reference['group_description'] ?? null,
                'section_code' => $reference['section_code'] ?? null,
                'section_description' => $reference['section_description'] ?? null,
            ]);
        },
        search_cnae_references($query, 20)
    );

    echo json_encode([
        'success' => true,
        'data' => $items,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}