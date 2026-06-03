<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/');
}

$itemId = (int) ($_POST['item_id'] ?? 0);
$imageId = (int) ($_POST['image_id'] ?? 0);

if ($imageId > 0) {
    delete_item_image($imageId);
}

redirect('/item_show.php?id=' . $itemId);