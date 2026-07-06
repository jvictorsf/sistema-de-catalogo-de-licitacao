<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/users.php');
}

$id = (int) ($_POST['id'] ?? 0);
$active = (string) ($_POST['is_active'] ?? '0') === '1';

try {
    if ($id > 0) {
        auth_set_user_active($id, $active);
    }
    redirect('/users.php?success=' . rawurlencode('Status do usuario atualizado.'));
} catch (Throwable $exception) {
    redirect('/users.php?error=' . rawurlencode($exception->getMessage()));
}