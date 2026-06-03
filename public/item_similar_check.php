<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

header('Content-Type: application/json; charset=utf-8');

$name = trim($_GET['name'] ?? '');
$ignoreId = isset($_GET['ignore_id']) ? (int) $_GET['ignore_id'] : null;

if (mb_strlen($name) < 3) {
    echo json_encode([
        'success' => true,
        'items' => [],
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$items = find_similar_items($name, $ignoreId);

echo json_encode([
    'success' => true,
    'items' => $items,
], JSON_UNESCAPED_UNICODE);
