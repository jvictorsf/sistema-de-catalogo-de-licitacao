<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/projects.php');
}

$demandListId = (int) ($_POST['demand_list_id'] ?? 0);

$quantity = (float) ($_POST['quantity'] ?? 1);

$approvedQuantity = $_POST['approved_quantity'] !== ''
    ? (float) $_POST['approved_quantity']
    : $quantity;

$data = [
    'demand_list_id' => $demandListId,
    'procurement_item_id' => (int) ($_POST['procurement_item_id'] ?? 0),
    'quantity' => $quantity,
    'approved_quantity' => $approvedQuantity,
    'estimated_unit_price' => $_POST['estimated_unit_price'] !== ''
        ? (float) $_POST['estimated_unit_price']
        : null,
    'notes' => trim($_POST['notes'] ?? ''),
];

if (
    $data['demand_list_id'] > 0 &&
    $data['procurement_item_id'] > 0 &&
    $data['quantity'] > 0
) {
    add_demand_item($data);
}

redirect('/demand_show.php?id=' . $demandListId);