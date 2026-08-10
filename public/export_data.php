<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';
require_once __DIR__ . '/../app/data_exports.php';

$scope = trim((string) ($_GET['scope'] ?? 'all'));
$format = strtolower(trim((string) ($_GET['format'] ?? 'json')));
$formats = catalog_data_export_formats();

if (!isset($formats[$format])) {
    http_response_code(400);
    exit('Formato de exportação inválido.');
}

try {
    $payload = export_catalog_data($scope);
} catch (Throwable $exception) {
    app_log('error', 'Falha ao consultar dados para exportação.', [
        'scope' => $scope,
        'format' => $format,
        'error' => $exception->getMessage(),
        'exception_type' => get_class($exception),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
    ]);
    http_response_code(400);
    exit($exception->getMessage());
}

$baseName = 'dados-' . preg_replace('/[^a-z0-9_-]+/i', '-', $scope) . '-' . date('Ymd-His');

try {
    if ($format === 'json') {
        send_download_headers('application/json; charset=utf-8', $baseName . '.json');
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($format === 'pdf') {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, max-age=0');
        echo catalog_data_export_pdf_html($payload);
        exit;
    }

    if ($format === 'csv') {
        $bundle = catalog_data_export_csv_bundle($payload);
        send_download_headers($bundle['content_type'], $baseName . '.' . $bundle['extension']);
        readfile($bundle['path']);
        @unlink($bundle['path']);
        exit;
    }

    $path = catalog_data_export_write_xlsx($payload);
    send_download_headers('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $baseName . '.xlsx');
    readfile($path);
    @unlink($path);
} catch (Throwable $exception) {
    app_log('error', 'Falha ao gerar arquivo de exportação.', [
        'scope' => $scope,
        'format' => $format,
        'error' => $exception->getMessage(),
        'exception_type' => get_class($exception),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
    ]);

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }

    echo 'Não foi possível gerar a exportação: ' . $exception->getMessage();
}
