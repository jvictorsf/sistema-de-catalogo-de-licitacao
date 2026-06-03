<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/kits.php');
}

$id = (int) ($_POST['id'] ?? 0);

if ($id > 0) {
    delete_item_kit($id);
}

redirect('/kits.php');