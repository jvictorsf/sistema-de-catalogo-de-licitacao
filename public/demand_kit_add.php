<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/projects.php');
}

$demandListId = (int) ($_POST['demand_list_id'] ?? 0);
$kitId = (int) ($_POST['kit_id'] ?? 0);
$multiplier = (float) ($_POST['multiplier'] ?? 1);

if ($multiplier <= 0) {
    $multiplier = 1;
}

if ($demandListId > 0 && $kitId > 0) {
    try {
        add_kit_to_demand($demandListId, $kitId, $multiplier, [
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
        ]);
    } catch (Throwable $exception) {
        redirect('/demand_show.php?id=' . $demandListId . '&error=' . rawurlencode($exception->getMessage()));
    }
}

redirect('/demand_show.php?id=' . $demandListId);
