<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/kits.php');
}

$kitId = (int) ($_POST['kit_id'] ?? 0);

add_item_to_kit([
    'kit_id' => $kitId,
    'procurement_item_id' => (int) ($_POST['procurement_item_id'] ?? 0),
    'quantity' => (float) ($_POST['quantity'] ?? 1),
    'notes' => trim($_POST['notes'] ?? ''),
]);

redirect('/kit_show.php?id=' . $kitId);