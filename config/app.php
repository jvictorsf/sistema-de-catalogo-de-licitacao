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

defined('APP_NAME') || define('APP_NAME', env_value('APP_NAME', 'Catalogo de Licitacao'));
defined('APP_URL') || define('APP_URL', env_value('APP_URL', 'https://catalogo-licitacao.esturvo.intra'));
defined('APP_ENV') || define('APP_ENV', env_value('APP_ENV', 'production'));
defined('APP_STORAGE_PATH') || define('APP_STORAGE_PATH', BASE_PATH . '/storage');
defined('MUNICIPAL_LOGO_PATH') || define('MUNICIPAL_LOGO_PATH', env_value('MUNICIPAL_LOGO_PATH', '/assets/brasao-municipio.png'));

defined('DB_HOST') || define('DB_HOST', env_value('DB_HOST', 'localhost'));
defined('DB_PORT') || define('DB_PORT', env_value('DB_PORT', '5432'));
defined('DB_NAME') || define('DB_NAME', env_value('DB_NAME', 'catalogo_licitacao'));
defined('DB_USER') || define('DB_USER', env_value('DB_USER', 'postgres'));
defined('DB_PASS') || define('DB_PASS', env_value('DB_PASS', ''));

defined('OPENAI_MODEL') || define('OPENAI_MODEL', env_value('OPENAI_MODEL', 'gpt-4.1-mini'));
