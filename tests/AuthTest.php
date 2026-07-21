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
auth_test_assert_same(count(auth_permission_labels()), count(auth_role_permissions('admin')), 'Administrador deve possuir todas as permissoes cadastradas.');
auth_test_assert_true(!in_array('system.manage_users', auth_role_permissions('viewer'), true), 'Consulta nao deve gerenciar usuarios.');
auth_test_assert_true(!in_array('system.manage_editor_settings', auth_role_permissions('manager'), true), 'Gestor nao deve alterar padroes globais do editor.');
auth_test_assert_true(!in_array('system.view_logs', auth_role_permissions('operator'), true), 'Operador nao deve acessar logs administrativos.');
auth_test_assert_true(auth_role_can('viewer', 'catalog.view'), 'Consulta deve visualizar o catalogo.');
auth_test_assert_true(auth_role_can('viewer', 'projects.view'), 'Consulta deve visualizar projetos.');
auth_test_assert_true(auth_role_can('viewer', 'budgets.view'), 'Consulta deve visualizar orcamentos.');
auth_test_assert_true(!auth_role_can('viewer', 'projects.manage'), 'Consulta nao deve alterar projetos.');
auth_test_assert_true(!auth_role_can('operator', 'ai.use'), 'Operador nao deve usar IA sem permissao.');
auth_test_assert_true(auth_role_can('manager', 'ai.use'), 'Gestor deve poder usar IA.');
auth_test_assert_true(!auth_role_can('admin', 'permission.unknown'), 'Permissao desconhecida deve ser negada inclusive ao administrador.');

auth_test_assert_same('catalog.manage', auth_route_required_permission('item_delete.php', 'POST'), 'Exclusao de item deve exigir gestao do catalogo.');
auth_test_assert_same('ai.use', auth_route_required_permission('ai_suggest.php', 'POST'), 'Sugestao por IA deve exigir permissao especifica.');
auth_test_assert_same('projects.view', auth_route_required_permission('project_show.php', 'GET'), 'Consulta de projeto deve exigir visualizacao.');
auth_test_assert_same('projects.view', auth_route_required_permission('project_lots.php', 'GET'), 'Consulta de lotes deve exigir visualizacao.');
auth_test_assert_same('projects.manage', auth_route_required_permission('project_lots.php', 'POST'), 'Alteracao de lotes deve exigir gestao.');
auth_test_assert_same('projects.manage', auth_route_required_permission('project_lots.php', 'DELETE'), 'Metodo nao previsto em lotes deve usar a permissao mais restritiva.');
auth_test_assert_same('projects.view', auth_route_required_permission('project_quantity_memory_form.php', 'GET'), 'Memoria quantitativa deve aceitar consulta.');
auth_test_assert_same('projects.manage', auth_route_required_permission('project_quantity_memory_form.php', 'POST'), 'Edicao da memoria quantitativa deve exigir gestao.');
auth_test_assert_same('reports.view', auth_route_required_permission('project_lot_annex_iv.php', 'GET'), 'Anexo deve exigir permissao de relatorio.');
auth_test_assert_same('budgets.view', auth_route_required_permission('supplier_quote_file.php', 'GET'), 'Anexo privado de orcamento deve exigir consulta de orcamentos.');
auth_test_assert_true(auth_route_is_registered('dashboard.php'), 'Dashboard deve constar no registro de rotas autenticadas.');
auth_test_assert_true(!auth_route_is_registered('pagina_inexistente.php'), 'Rota nao cadastrada deve ser negada por padrao.');
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
