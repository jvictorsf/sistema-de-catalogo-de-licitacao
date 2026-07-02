<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

header('Content-Type: application/json; charset=utf-8');

$cnpj = (string) ($_GET['cnpj'] ?? '');

try {
    $data = lookup_cnpj_brasilapi($cnpj);
    $data['main_cnae'] = enrich_supplier_cnae_from_reference(normalize_supplier_cnae($data['main_cnae'] ?? []));
    $data['secondary_cnaes'] = array_map(
        static fn (array $cnae): array => enrich_supplier_cnae_from_reference($cnae) ?? $cnae,
        normalize_supplier_cnae_list($data['secondary_cnaes'] ?? [])
    );

    echo json_encode([
        'success' => true,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
