<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/projects.php');
}

$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {
    redirect('/projects.php');
}

try {
    $newId = duplicate_project($id);
    redirect('/project_form.php?id=' . $newId);
} catch (Throwable $exception) {
    redirect('/projects.php');
}