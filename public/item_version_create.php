<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/');
}

$itemId = (int) ($_POST['item_id'] ?? 0);
$notes = trim($_POST['notes'] ?? '');

if ($itemId > 0) {
    create_item_version($itemId, $notes ?: null);
}

redirect('/item_show.php?id=' . $itemId);