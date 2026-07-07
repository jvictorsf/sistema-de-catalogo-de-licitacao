<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';

function auth_test_assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Esperado: ' . var_export($expected, true) . ' Obtido: ' . var_export($actual, true));
    }
}

function auth_test_assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

auth_test_assert_true(function_exists('auth_pg_bool'), 'Funcao auth_pg_bool deve existir para criar usuarios.');
auth_test_assert_same('true', auth_pg_bool(true), 'Boolean true deve virar true textual do PostgreSQL.');
auth_test_assert_same('false', auth_pg_bool(false), 'Boolean false deve virar false textual do PostgreSQL.');
auth_test_assert_same('true', auth_pg_bool('on'), 'String on deve ser aceita como true.');
auth_test_assert_same('false', auth_pg_bool(''), 'String vazia deve ser aceita como false.');
auth_test_assert_true(in_array('system.manage_users', auth_role_permissions('admin'), true), 'Administrador deve gerenciar usuarios.');
auth_test_assert_true(!in_array('system.manage_users', auth_role_permissions('viewer'), true), 'Consulta nao deve gerenciar usuarios.');
auth_test_assert_true(!in_array('system.view_logs', auth_role_permissions('operator'), true), 'Operador nao deve acessar logs administrativos.');

echo "AuthTest: OK\n";