<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';
require_once __DIR__ . '/../app/demand_confirmations.php';

$requestId = (int) ($_GET['id'] ?? 0);
$kind = trim((string) ($_GET['kind'] ?? ''));
$attachmentId = (int) ($_GET['attachment_id'] ?? 0);
$file = demand_confirmation_file_info($requestId, $kind, $attachmentId);

if (!$file || !is_readable((string) $file['path'])) {
    http_response_code(404);
    exit('Arquivo nao encontrado.');
}

$downloadName = preg_replace('/[^A-Za-z0-9._ -]/', '_', (string) $file['download_name']) ?: 'comprovante';

header('Content-Type: ' . (string) $file['mime']);
header('Content-Length: ' . (string) filesize((string) $file['path']));
header('Content-Disposition: attachment; filename="' . str_replace(['"', '\\'], '', $downloadName) . '"');
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
readfile((string) $file['path']);