<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';

function app_required_php_extensions(): array
{
    return ['pdo', 'pdo_pgsql', 'mbstring', 'fileinfo', 'curl', 'json', 'openssl', 'session'];
}

function app_path_status(string $label, string $path, bool $writeTest = false): array
{
    $exists = file_exists($path);
    $isDir = is_dir($path);
    $readable = is_readable($path);
    $writable = is_writable($path);
    $writeOk = null;
    $writeError = null;

    if ($writeTest && $isDir && $writable) {
        $testFile = rtrim($path, DIRECTORY_SEPARATOR . '/') . DIRECTORY_SEPARATOR . '.diagnostic-write-test';

        try {
            $writeOk = @file_put_contents($testFile, 'ok', LOCK_EX) !== false;

            if ($writeOk) {
                @unlink($testFile);
            }
        } catch (Throwable $exception) {
            $writeOk = false;
            $writeError = $exception->getMessage();
        }
    }

    return [
        'label' => $label,
        'path' => $path,
        'exists' => $exists,
        'is_dir' => $isDir,
        'readable' => $readable,
        'writable' => $writable,
        'write_test' => $writeOk,
        'write_error' => $writeError,
        'free_space' => $isDir ? @disk_free_space($path) : null,
    ];
}

function app_postgresql_diagnostic(): array
{
    try {
        $started = microtime(true);
        $stmt = db()->query("SELECT version() AS version, current_database() AS database_name, current_user AS user_name");
        $row = $stmt->fetch() ?: [];

        return [
            'ok' => true,
            'message' => 'Conexao estabelecida',
            'latency_ms' => round((microtime(true) - $started) * 1000, 2),
            'version' => (string) ($row['version'] ?? ''),
            'database' => (string) ($row['database_name'] ?? DB_NAME),
            'user' => (string) ($row['user_name'] ?? DB_USER),
            'host' => DB_HOST,
            'port' => DB_PORT,
        ];
    } catch (Throwable $exception) {
        return [
            'ok' => false,
            'message' => $exception->getMessage(),
            'latency_ms' => null,
            'version' => null,
            'database' => DB_NAME,
            'user' => DB_USER,
            'host' => DB_HOST,
            'port' => DB_PORT,
        ];
    }
}

function app_environment_diagnostics(): array
{
    $extensions = [];

    foreach (app_required_php_extensions() as $extension) {
        $extensions[] = [
            'name' => $extension,
            'loaded' => extension_loaded($extension),
        ];
    }

    $paths = [
        app_path_status('Storage', APP_STORAGE_PATH, true),
        app_path_status('Logs', APP_STORAGE_PATH . '/logs', true),
        app_path_status('Uploads publicos', BASE_PATH . '/public/uploads', true),
        app_path_status('Confirmacoes privadas', APP_STORAGE_PATH . '/uploads/demand_confirmations', true),
    ];

    return [
        'postgresql' => app_postgresql_diagnostic(),
        'php' => [
            'version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'os' => PHP_OS_FAMILY,
            'memory_limit' => ini_get('memory_limit'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_execution_time' => ini_get('max_execution_time'),
            'timezone' => date_default_timezone_get(),
        ],
        'extensions' => $extensions,
        'paths' => $paths,
        'config' => [
            'APP_NAME' => APP_NAME,
            'APP_ENV' => APP_ENV,
            'APP_URL' => APP_URL,
            'APP_STORAGE_PATH' => APP_STORAGE_PATH,
            'DB_HOST' => DB_HOST,
            'DB_PORT' => DB_PORT,
            'DB_NAME' => DB_NAME,
            'DB_USER' => DB_USER,
            'MUNICIPAL_LOGO_PATH' => MUNICIPAL_LOGO_PATH,
            'OPENAI_MODEL' => OPENAI_MODEL,
        ],
    ];
}

function system_log_files(?string $logDir = null): array
{
    $logDir ??= APP_STORAGE_PATH . '/logs';

    if (!is_dir($logDir)) {
        return [];
    }

    $files = glob(rtrim($logDir, DIRECTORY_SEPARATOR . '/') . DIRECTORY_SEPARATOR . '*.log') ?: [];
    sort($files);

    return array_map(static fn (string $file): string => basename($file), $files);
}

function parse_system_log_line(string $line, string $file = 'app.log'): ?array
{
    $line = trim($line);

    if ($line === '') {
        return null;
    }

    if (preg_match('/^\[(?<date>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+(?<level>[A-Z]+)\s+(?<rest>.*)$/', $line, $matches)) {
        $rest = trim($matches['rest']);
        $message = $rest;
        $context = [];
        $jsonPosition = strrpos($rest, ' {');

        if ($jsonPosition !== false) {
            $maybeJson = substr($rest, $jsonPosition + 1);
            $decoded = json_decode($maybeJson, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $context = $decoded;
                $message = trim(substr($rest, 0, $jsonPosition));
            }
        }

        $uri = (string) ($context['uri'] ?? '');
        $route = (string) ($context['route'] ?? basename((string) parse_url($uri, PHP_URL_PATH)));
        $user = (string) ($context['user'] ?? $context['username'] ?? $context['user_id'] ?? '');

        return [
            'file' => $file,
            'date' => $matches['date'],
            'timestamp' => strtotime($matches['date']) ?: 0,
            'level' => strtoupper($matches['level']),
            'message' => $message,
            'context' => $context,
            'route' => $route,
            'user' => $user,
            'raw' => $line,
        ];
    }

    if (preg_match('/^\[(?<date>[^\]]+)\]\s+PHP\s+(?<level>[^:]+):\s+(?<message>.*)$/', $line, $matches)) {
        $levelText = strtolower(trim($matches['level']));
        $level = str_contains($levelText, 'fatal') ? 'FATAL' : (str_contains($levelText, 'warning') ? 'WARNING' : 'ERROR');
        $timestamp = strtotime($matches['date']) ?: 0;

        return [
            'file' => $file,
            'date' => $timestamp ? date('Y-m-d H:i:s', $timestamp) : $matches['date'],
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => trim($matches['message']),
            'context' => [],
            'route' => '',
            'user' => '',
            'raw' => $line,
        ];
    }

    return [
        'file' => $file,
        'date' => '',
        'timestamp' => 0,
        'level' => 'INFO',
        'message' => $line,
        'context' => [],
        'route' => '',
        'user' => '',
        'raw' => $line,
    ];
}

function system_log_entry_matches(array $entry, array $filters): bool
{
    $level = strtoupper(trim((string) ($filters['level'] ?? '')));

    if ($level !== '' && strtoupper((string) ($entry['level'] ?? '')) !== $level) {
        return false;
    }

    $file = trim((string) ($filters['file'] ?? ''));

    if ($file !== '' && (string) ($entry['file'] ?? '') !== $file) {
        return false;
    }

    $dateFrom = trim((string) ($filters['date_from'] ?? ''));

    if ($dateFrom !== '' && (int) ($entry['timestamp'] ?? 0) < strtotime($dateFrom . ' 00:00:00')) {
        return false;
    }

    $dateTo = trim((string) ($filters['date_to'] ?? ''));

    if ($dateTo !== '' && (int) ($entry['timestamp'] ?? 0) > strtotime($dateTo . ' 23:59:59')) {
        return false;
    }

    foreach (['user', 'route', 'message'] as $field) {
        $needle = strtolower(trim((string) ($filters[$field] ?? '')));

        if ($needle === '') {
            continue;
        }

        $haystack = strtolower((string) ($entry[$field] ?? ''));

        if ($field === 'message') {
            $haystack .= ' ' . strtolower((string) ($entry['raw'] ?? ''));
        }

        if (!str_contains($haystack, $needle)) {
            return false;
        }
    }

    return true;
}

function read_system_logs(array $filters = [], ?string $logDir = null, int $limit = 500): array
{
    $logDir ??= APP_STORAGE_PATH . '/logs';
    $entries = [];
    $files = system_log_files($logDir);
    $selectedFile = trim((string) ($filters['file'] ?? ''));

    if ($selectedFile !== '') {
        $files = in_array($selectedFile, $files, true) ? [$selectedFile] : [];
    }

    foreach ($files as $file) {
        $path = rtrim($logDir, DIRECTORY_SEPARATOR . '/') . DIRECTORY_SEPARATOR . $file;
        $lines = is_readable($path) ? (file($path, FILE_IGNORE_NEW_LINES) ?: []) : [];

        foreach (array_reverse($lines) as $line) {
            $entry = parse_system_log_line((string) $line, $file);

            if ($entry === null || !system_log_entry_matches($entry, $filters)) {
                continue;
            }

            $entries[] = $entry;

            if (count($entries) >= $limit) {
                break 2;
            }
        }
    }

    usort($entries, static fn (array $left, array $right): int => ((int) $right['timestamp']) <=> ((int) $left['timestamp']));

    return $entries;
}