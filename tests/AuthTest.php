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
auth_test_assert_true(in_array('system.manage_editor_settings', auth_role_permissions('admin'), true), 'Administrador deve configurar editor e documentos.');
auth_test_assert_true(!in_array('system.manage_users', auth_role_permissions('viewer'), true), 'Consulta nao deve gerenciar usuarios.');
auth_test_assert_true(!in_array('system.manage_editor_settings', auth_role_permissions('manager'), true), 'Gestor nao deve alterar padroes globais do editor.');
auth_test_assert_true(!in_array('system.view_logs', auth_role_permissions('operator'), true), 'Operador nao deve acessar logs administrativos.');
auth_test_assert_true(function_exists('auth_ldap_config'), 'Funcoes LDAP devem ser carregadas pelo auth.php.');
$ldapConfig = auth_ldap_config([
    'AUTH_LDAP_ENABLED' => '1',
    'AUTH_LDAP_HOST' => 'ad.local',
    'AUTH_LDAP_PORT' => '636',
    'AUTH_LDAP_USE_SSL' => 'true',
    'AUTH_LDAP_AUTO_CREATE' => 'yes',
    'AUTH_LDAP_DEFAULT_ROLE' => 'operator',
    'AUTH_LDAP_ADMIN_GROUPS' => 'Catalogo Admins;CN=TI Admins,OU=Grupos,DC=local',
]);
auth_test_assert_same(true, $ldapConfig['enabled'], 'LDAP habilitado deve ser convertido para booleano.');
auth_test_assert_same('ad.local', $ldapConfig['host'], 'Host LDAP deve vir da configuracao.');
auth_test_assert_same(636, $ldapConfig['port'], 'Porta LDAP deve virar inteiro.');
auth_test_assert_same(true, $ldapConfig['use_ssl'], 'SSL LDAP deve virar booleano.');
auth_test_assert_same('operator', $ldapConfig['default_role'], 'Perfil padrao LDAP deve aceitar papel valido.');
auth_test_assert_same(2, count($ldapConfig['role_groups']['admin']), 'Grupos LDAP devem aceitar separacao por ponto e virgula.');

auth_test_assert_same('joao.ferreira', auth_ldap_local_username('ESTURVO\\Joao.Ferreira'), 'Login DOMAIN\\usuario deve virar username local.');
auth_test_assert_same('maria', auth_ldap_local_username('maria@esturvo.intra'), 'UPN deve virar username local.');
auth_test_assert_same('usrab', auth_ldap_local_username('ab'), 'Username curto deve receber prefixo seguro.');

$filter = auth_ldap_user_filter('joao*', ['user_filter' => '(sAMAccountName={login})']);
auth_test_assert_same('(sAMAccountName=joao\\2a)', $filter, 'Filtro LDAP deve escapar caracteres especiais.');

$role = auth_ldap_role_from_groups(
    ['CN=Catalogo Admins,OU=Grupos,DC=local'],
    ['admin' => ['Catalogo Admins'], 'manager' => [], 'operator' => [], 'viewer' => []],
    'viewer'
);
auth_test_assert_same('admin', $role, 'Grupo LDAP deve mapear para Administrador pelo CN.');

auth_test_assert_same('viewer', auth_ldap_role_from_groups([], ['admin' => [], 'manager' => [], 'operator' => [], 'viewer' => []], 'viewer'), 'Sem grupo deve usar perfil padrao.');

$diagnostic = auth_ldap_diagnostic();
auth_test_assert_true(is_array($diagnostic), 'Diagnostico LDAP deve retornar array.');
auth_test_assert_true(!array_key_exists('bind_password', $diagnostic), 'Diagnostico LDAP nao deve expor senha de bind.');

echo "AuthTest: OK\n";
