<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';
require_once __DIR__ . '/../app/demand_confirmations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/projects.php');
}

$id = (int) ($_POST['id'] ?? 0);
$demandId = (int) ($_POST['demand_id'] ?? 0);

try {
    if ($id > 0) {
        revoke_demand_confirmation_request($id);
    }
} catch (Throwable $exception) {
    $target = $demandId > 0 ? '/demand_show.php?id=' . $demandId : '/projects.php';
    $separator = str_contains($target, '?') ? '&' : '?';
    redirect($target . $separator . 'error=' . rawurlencode($exception->getMessage()));
}

redirect($demandId > 0 ? '/demand_show.php?id=' . $demandId : '/projects.php');