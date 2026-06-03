<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/projects.php');
}

$id = (int) ($_POST['id'] ?? 0);
$demandListId = (int) ($_POST['demand_list_id'] ?? 0);

if ($id > 0) {
    delete_demand_item($id);
}

redirect('/demand_show.php?id=' . $demandListId);