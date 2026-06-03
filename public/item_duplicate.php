<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/');
}

$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {
    redirect('/');
}

try {
    $newId = duplicate_item($id);
    redirect('/item_form.php?id=' . $newId);
} catch (Throwable $exception) {
    redirect('/');
}