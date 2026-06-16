<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';

$filename = basename((string) ($_GET['file'] ?? ''));

if ($filename === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $filename)) {
    http_response_code(404);
    exit('Arquivo nao encontrado.');
}

$paths = [
    supplier_quote_storage_dir() . '/' . $filename,
    __DIR__ . '/uploads/supplier_quotes/' . $filename,
];

$path = null;

foreach ($paths as $candidate) {
    if (is_file($candidate) && is_readable($candidate)) {
        $path = $candidate;
        break;
    }
}

if ($path === null) {
    http_response_code(404);
    exit('Arquivo nao encontrado.');
}

$mime = mime_content_type($path) ?: 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header(
    'Content-Disposition: inline; filename="'
    . str_replace(['"', '\\'], '', $filename)
    . '"'
);

readfile($path);
