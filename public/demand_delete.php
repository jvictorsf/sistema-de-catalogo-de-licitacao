<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/projects.php');
}

$id = (int) ($_POST['id'] ?? 0);
$projectId = (int) ($_POST['project_id'] ?? 0);

if ($id > 0) {
    try {
        delete_demand_list($id);
    } catch (Throwable $exception) {
        redirect('/project_show.php?id=' . $projectId . '&project_error=' . rawurlencode($exception->getMessage()));
    }
}

redirect('/project_show.php?id=' . $projectId);
