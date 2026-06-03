<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/data.php');
}

$scope = trim($_POST['scope'] ?? 'all');
$file = $_FILES['json_file'] ?? null;

if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    redirect('/data.php?error=' . rawurlencode('Informe um arquivo JSON valido.'));
}

$rawJson = file_get_contents($file['tmp_name']);
$payload = json_decode((string) $rawJson, true);

if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
    redirect('/data.php?error=' . rawurlencode('O arquivo enviado nao possui JSON valido.'));
}

try {
    $summary = import_catalog_data($scope, $payload);
    $total = array_sum($summary);
    $message = 'Importacao concluida com ' . $total . ' registro(s) processado(s).';

    redirect('/data.php?success=' . rawurlencode($message));
} catch (Throwable $exception) {
    redirect('/data.php?error=' . rawurlencode($exception->getMessage()));
}
