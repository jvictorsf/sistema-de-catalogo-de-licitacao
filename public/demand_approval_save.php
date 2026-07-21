<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/projects.php');
}

$demandListId = (int) ($_POST['demand_list_id'] ?? 0);

if ($demandListId <= 0) {
    redirect('/projects.php');
}

try {
    $decision = save_demand_approval($demandListId, [
        'approval_status' => $_POST['approval_status'] ?? '',
        'approval_notes' => $_POST['approval_notes'] ?? '',
        'approved_quantities' => $_POST['approved_quantities'] ?? [],
        'item_notes' => $_POST['item_notes'] ?? [],
    ]);

    redirect(
        '/demand_show.php?id=' . $demandListId
        . '&approval_success=' . rawurlencode(
            'Decisão registrada: ' . demand_approval_status_label($decision['approval_status'] ?? null) . '.'
        )
    );
} catch (Throwable $exception) {
    redirect(
        '/demand_approval.php?id=' . $demandListId
        . '&error=' . rawurlencode($exception->getMessage())
    );
}
