<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/system_tools.php';

function logs_test_assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Esperado: ' . var_export($expected, true) . ' Obtido: ' . var_export($actual, true));
    }
}

function logs_test_assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$entry = parse_system_log_line('[2026-07-07 08:00:00] ERROR Falha ao salvar demanda {"user_id":7,"route":"demand_form.php","uri":"/demand_form.php?id=1"}', 'app.log');
logs_test_assert_true(is_array($entry), 'Linha de log da aplicacao deve ser parseada.');
logs_test_assert_same('ERROR', $entry['level'], 'Nivel do log deve ser extraido.');
logs_test_assert_same('7', $entry['user'], 'Usuario deve ser extraido do contexto.');
logs_test_assert_same('demand_form.php', $entry['route'], 'Rota deve ser extraida do contexto.');
logs_test_assert_true(system_log_entry_matches($entry, ['level' => 'ERROR', 'user' => '7', 'route' => 'demand', 'message' => 'salvar']), 'Filtros combinados devem encontrar o log.');
logs_test_assert_true(!system_log_entry_matches($entry, ['level' => 'WARNING']), 'Filtro de nivel divergente deve ocultar o log.');

$phpEntry = parse_system_log_line('[07-Jul-2026 08:05:00 America/Sao_Paulo] PHP Fatal error:  Uncaught Error in arquivo.php:10', 'php-error.log');
logs_test_assert_same('FATAL', $phpEntry['level'], 'Fatal error do PHP deve virar nivel FATAL.');

echo "LogsTest: OK\n";