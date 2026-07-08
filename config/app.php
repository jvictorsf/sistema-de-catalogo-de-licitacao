<?php

declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

if (!function_exists('env_value')) {
    function env_value(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);

        if ($value === false || $value === '') {
            return $default;
        }

        return $value;
    }
}

if (!function_exists('env_bool')) {
    function env_bool(string $key, bool $default = false): bool
    {
        $value = env_value($key, $default ? 'true' : 'false');

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 't', 'yes', 'y', 'on'], true);
    }
}

if (!function_exists('env_int')) {
    function env_int(string $key, int $default = 0): int
    {
        $value = env_value($key, (string) $default);

        return is_numeric($value) ? (int) $value : $default;
    }
}
$envFile = BASE_PATH . '/.env';

if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");

        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

defined('APP_NAME') || define('APP_NAME', env_value('APP_NAME', 'Sistema Interno de Compras e Licitacoes'));
defined('APP_URL') || define('APP_URL', env_value('APP_URL', 'https://catalogo-licitacao.esturvo.intra'));
defined('APP_ENV') || define('APP_ENV', env_value('APP_ENV', 'production'));
defined('APP_STORAGE_PATH') || define('APP_STORAGE_PATH', BASE_PATH . '/storage');
defined('MUNICIPAL_LOGO_PATH') || define('MUNICIPAL_LOGO_PATH', env_value('MUNICIPAL_LOGO_PATH', '/assets/brasao-municipio.png'));
defined('DOD_ENTITY_NAME') || define('DOD_ENTITY_NAME', env_value('DOD_ENTITY_NAME', 'PREFEITURA MUNICIPAL DE ESPIRITO SANTO DO TURVO'));
defined('DOD_ENTITY_STATE') || define('DOD_ENTITY_STATE', env_value('DOD_ENTITY_STATE', 'ESTADO DE SAO PAULO'));
defined('DOD_ENTITY_CITY') || define('DOD_ENTITY_CITY', env_value('DOD_ENTITY_CITY', 'Espirito Santo do Turvo - SP'));
defined('DOD_ENTITY_CNPJ') || define('DOD_ENTITY_CNPJ', env_value('DOD_ENTITY_CNPJ', '57.264.509/0001-69'));
defined('DOD_LOGO_LEFT_PATH') || define('DOD_LOGO_LEFT_PATH', env_value('DOD_LOGO_LEFT_PATH', '/assets/municipio-agro.png'));
defined('DOD_LOGO_RIGHT_PATH') || define('DOD_LOGO_RIGHT_PATH', env_value('DOD_LOGO_RIGHT_PATH', '/assets/municipio-verde-azul.png'));

defined('DB_HOST') || define('DB_HOST', env_value('DB_HOST', 'localhost'));
defined('DB_PORT') || define('DB_PORT', env_value('DB_PORT', '5432'));
defined('DB_NAME') || define('DB_NAME', env_value('DB_NAME', 'catalogo_licitacao'));
defined('DB_USER') || define('DB_USER', env_value('DB_USER', 'postgres'));
defined('DB_PASS') || define('DB_PASS', env_value('DB_PASS', ''));

defined('OPENAI_MODEL') || define('OPENAI_MODEL', env_value('OPENAI_MODEL', 'gpt-4.1-mini'));
defined('AUTH_LDAP_ENABLED') || define('AUTH_LDAP_ENABLED', env_bool('AUTH_LDAP_ENABLED', false));
defined('AUTH_LDAP_HOST') || define('AUTH_LDAP_HOST', env_value('AUTH_LDAP_HOST', ''));
defined('AUTH_LDAP_PORT') || define('AUTH_LDAP_PORT', env_int('AUTH_LDAP_PORT', 389));
defined('AUTH_LDAP_USE_SSL') || define('AUTH_LDAP_USE_SSL', env_bool('AUTH_LDAP_USE_SSL', false));
defined('AUTH_LDAP_USE_TLS') || define('AUTH_LDAP_USE_TLS', env_bool('AUTH_LDAP_USE_TLS', false));
defined('AUTH_LDAP_TIMEOUT') || define('AUTH_LDAP_TIMEOUT', env_int('AUTH_LDAP_TIMEOUT', 5));
defined('AUTH_LDAP_BASE_DN') || define('AUTH_LDAP_BASE_DN', env_value('AUTH_LDAP_BASE_DN', ''));
defined('AUTH_LDAP_BIND_DN') || define('AUTH_LDAP_BIND_DN', env_value('AUTH_LDAP_BIND_DN', ''));
defined('AUTH_LDAP_BIND_PASSWORD') || define('AUTH_LDAP_BIND_PASSWORD', env_value('AUTH_LDAP_BIND_PASSWORD', ''));
defined('AUTH_LDAP_USER_FILTER') || define('AUTH_LDAP_USER_FILTER', env_value('AUTH_LDAP_USER_FILTER', '(|(sAMAccountName={login})(userPrincipalName={login})(mail={login}))'));
defined('AUTH_LDAP_ACCOUNT_SUFFIX') || define('AUTH_LDAP_ACCOUNT_SUFFIX', env_value('AUTH_LDAP_ACCOUNT_SUFFIX', ''));
defined('AUTH_LDAP_DOMAIN') || define('AUTH_LDAP_DOMAIN', env_value('AUTH_LDAP_DOMAIN', ''));
defined('AUTH_LDAP_AUTO_CREATE') || define('AUTH_LDAP_AUTO_CREATE', env_bool('AUTH_LDAP_AUTO_CREATE', true));
defined('AUTH_LDAP_SYNC_PROFILE') || define('AUTH_LDAP_SYNC_PROFILE', env_bool('AUTH_LDAP_SYNC_PROFILE', true));
defined('AUTH_LDAP_SYNC_ROLE') || define('AUTH_LDAP_SYNC_ROLE', env_bool('AUTH_LDAP_SYNC_ROLE', true));
defined('AUTH_LDAP_DEFAULT_ROLE') || define('AUTH_LDAP_DEFAULT_ROLE', env_value('AUTH_LDAP_DEFAULT_ROLE', 'viewer'));
defined('AUTH_LDAP_LOCAL_FALLBACK') || define('AUTH_LDAP_LOCAL_FALLBACK', env_bool('AUTH_LDAP_LOCAL_FALLBACK', true));
defined('AUTH_LDAP_FALLBACK_EMAIL_DOMAIN') || define('AUTH_LDAP_FALLBACK_EMAIL_DOMAIN', env_value('AUTH_LDAP_FALLBACK_EMAIL_DOMAIN', 'ldap.local'));
defined('AUTH_LDAP_ADMIN_GROUPS') || define('AUTH_LDAP_ADMIN_GROUPS', env_value('AUTH_LDAP_ADMIN_GROUPS', ''));
defined('AUTH_LDAP_MANAGER_GROUPS') || define('AUTH_LDAP_MANAGER_GROUPS', env_value('AUTH_LDAP_MANAGER_GROUPS', ''));
defined('AUTH_LDAP_OPERATOR_GROUPS') || define('AUTH_LDAP_OPERATOR_GROUPS', env_value('AUTH_LDAP_OPERATOR_GROUPS', ''));
defined('AUTH_LDAP_VIEWER_GROUPS') || define('AUTH_LDAP_VIEWER_GROUPS', env_value('AUTH_LDAP_VIEWER_GROUPS', ''));

if (!function_exists('app_log')) {
    function app_log(string $level, string $message, array $context = []): void
    {
        $logDir = APP_STORAGE_PATH . '/logs';

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        if (!isset($context['uri']) && isset($_SERVER['REQUEST_URI'])) {
            $context['uri'] = $_SERVER['REQUEST_URI'];
        }

        if (!isset($context['route']) && isset($_SERVER['SCRIPT_NAME'])) {
            $context['route'] = basename((string) $_SERVER['SCRIPT_NAME']);
        }

        if (!isset($context['method']) && isset($_SERVER['REQUEST_METHOD'])) {
            $context['method'] = $_SERVER['REQUEST_METHOD'];
        }

        if (!isset($context['user_id']) && session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['auth_user_id'])) {
            $context['user_id'] = (int) $_SESSION['auth_user_id'];
        }

        $line = sprintf(
            "[%s] %s %s %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
        );

        @file_put_contents($logDir . '/app.log', $line, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('app_log_exception')) {
    function app_log_exception(Throwable $exception): void
    {
        app_log('error', $exception->getMessage(), [
            'type' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'uri' => $_SERVER['REQUEST_URI'] ?? null,
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}

if (!defined('APP_ERROR_HANDLERS_REGISTERED')) {
    define('APP_ERROR_HANDLERS_REGISTERED', true);

    if (!is_dir(APP_STORAGE_PATH . '/logs')) {
        @mkdir(APP_STORAGE_PATH . '/logs', 0775, true);
    }

    ini_set('log_errors', '1');
    ini_set('error_log', APP_STORAGE_PATH . '/logs/php-error.log');
    ini_set('display_errors', APP_ENV === 'production' ? '0' : '1');

    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        app_log('warning', $message, [
            'severity' => $severity,
            'file' => $file,
            'line' => $line,
            'uri' => $_SERVER['REQUEST_URI'] ?? null,
        ]);

        return false;
    });

    set_exception_handler(static function (Throwable $exception): void {
        app_log_exception($exception);
        http_response_code(500);

        if (APP_ENV !== 'production') {
            echo '<h1>Erro interno</h1>';
            echo '<pre>' . htmlspecialchars((string) $exception, ENT_QUOTES, 'UTF-8') . '</pre>';
            return;
        }

        echo 'Erro interno do sistema. Consulte storage/logs/app.log.';
    });

    register_shutdown_function(static function (): void {
        $error = error_get_last();

        if (!$error || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }

        app_log('fatal', $error['message'], [
            'file' => $error['file'] ?? null,
            'line' => $error['line'] ?? null,
            'uri' => $_SERVER['REQUEST_URI'] ?? null,
        ]);
    });
}
