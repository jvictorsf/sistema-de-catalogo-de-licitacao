<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/repository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo nao permitido.');
}

$id = (int) ($_POST['id'] ?? 0);

if ($id) {
    deactivate_requester_unit($id);
}

redirect('/requester_units.php');
