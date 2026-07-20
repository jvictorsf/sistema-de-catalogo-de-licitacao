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
    'need_type' => trim((string) ($_POST['need_type'] ?? '')),
    'need_justification' => trim((string) ($_POST['need_justification'] ?? '')),
    'intended_use' => trim((string) ($_POST['intended_use'] ?? '')),
    'destination' => trim((string) ($_POST['destination'] ?? '')),
    'priority' => trim((string) ($_POST['priority'] ?? 'MEDIUM')),
    'needed_by_date' => trim((string) ($_POST['needed_by_date'] ?? '')),
    'related_assets' => trim((string) ($_POST['related_assets'] ?? '')),
    'related_project' => trim((string) ($_POST['related_project'] ?? '')),
    'evidence_references' => trim((string) ($_POST['evidence_references'] ?? '')),
    'validation_status' => trim((string) ($_POST['validation_status'] ?? 'PENDING')),
    'validation_notes' => trim((string) ($_POST['validation_notes'] ?? '')),
    'notes' => trim($_POST['notes'] ?? ''),
];

if (
    $data['demand_list_id'] > 0 &&
    $data['procurement_item_id'] > 0 &&
    $data['quantity'] > 0
) {
    try {
        add_demand_item($data);
    } catch (Throwable $exception) {
        redirect('/demand_show.php?id=' . $demandListId . '&error=' . rawurlencode($exception->getMessage()));
    }
}

redirect('/demand_show.php?id=' . $demandListId);
