<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/projects.php');
}

$id = (int) ($_POST['id'] ?? 0);
$demandListId = (int) ($_POST['demand_list_id'] ?? 0);

$quantity = (float) ($_POST['quantity'] ?? 0);

$approvedQuantity = $_POST['approved_quantity'] !== ''
    ? (float) $_POST['approved_quantity']
    : $quantity;

$estimatedUnitPrice = $_POST['estimated_unit_price'] !== ''
    ? (float) $_POST['estimated_unit_price']
    : null;

$data = [
    'quantity' => $quantity,
    'approved_quantity' => $approvedQuantity,
    'estimated_unit_price' => $estimatedUnitPrice,
    'notes' => trim($_POST['notes'] ?? ''),
];

if (
    $id > 0 &&
    $demandListId > 0 &&
    $quantity > 0 &&
    $approvedQuantity >= 0
) {
    update_demand_item($id, $data);
}

redirect('/demand_show.php?id=' . $demandListId);