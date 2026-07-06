<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';
require_once __DIR__ . '/../app/demand_confirmations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/collaborators.php');
}

$id = (int) ($_POST['id'] ?? 0);
$active = (string) ($_POST['is_active'] ?? '0') === '1';

if ($id > 0) {
    set_collaborator_active($id, $active);
}

redirect('/collaborators.php');