<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/');
}

$itemId = (int) ($_POST['item_id'] ?? 0);
$imageId = (int) ($_POST['image_id'] ?? 0);

if ($itemId > 0 && $imageId > 0) {
    set_item_primary_image($itemId, $imageId);
}

redirect('/item_show.php?id=' . $itemId);