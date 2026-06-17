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
        add_kit_to_demand($demandListId, $kitId, $multiplier);
    } catch (Throwable $exception) {
        redirect('/demand_show.php?id=' . $demandListId . '&error=' . rawurlencode($exception->getMessage()));
    }
}

redirect('/demand_show.php?id=' . $demandListId);
