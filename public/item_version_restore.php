<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/');
}

$versionId = (int) ($_POST['version_id'] ?? 0);

if ($versionId <= 0) {
    redirect('/');
}

try {
    $itemId = restore_item_version($versionId);
    redirect('/item_show.php?id=' . $itemId);
} catch (Throwable $exception) {
    redirect('/');
}