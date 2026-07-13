<?php

declare(strict_types=1);

function auth_pg_bool(mixed $value): string
{
    if (is_string($value)) {
        $normalized = strtolower(trim($value));

        if (in_array($normalized, ['1', 'true', 't', 'yes', 'on'], true)) {
            return 'true';
        }

        if (in_array($normalized, ['0', 'false', 'f', 'no', 'off', ''], true)) {
            return 'false';
        }
    }

    return $value ? 'true' : 'false';
}

function auth_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    session_name('catalogo_licitacao_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function auth_current_page(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

    return $path === '/' ? 'index.php' : basename($path);
}

function auth_public_pages(): array
{
    return [
        'login.php',
        'logout.php',
        'setup_admin.php',
        'demand_confirmation_sign.php',
    ];
}

function auth_is_public_demand_confirmation_request(?string $page = null): bool
{
    $page = $page ?? auth_current_page();

    if ($page !== 'index.php') {
        return false;
    }

    $action = trim((string) ($_GET['public_action'] ?? $_POST['public_action'] ?? ''));
    $token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));

    return $action === 'demand_confirmation_sign' && $token !== '';
}

function auth_is_public_page(?string $page = null): bool
{
    $page = $page ?? auth_current_page();

    return in_array($page, auth_public_pages(), true)
        || auth_is_public_demand_confirmation_request($page);
}

function auth_redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function auth_schema_available(): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    try {
        $stmt = db()->prepare('SELECT to_regclass(:table_name)');
        $stmt->execute(['table_name' => 'public.app_users']);
        $available = (bool) $stmt->fetchColumn();
    } catch (Throwable $exception) {
        app_log_exception($exception);
        $available = false;
    }

    return $available;
}

function auth_user_count(): int
{
    if (!auth_schema_available()) {
        return 0;
    }

    return (int) db()->query('SELECT COUNT(*) FROM app_users')->fetchColumn();
}

function auth_roles(): array
{
    return [
        'admin' => 'Administrador',
        'manager' => 'Gestor',
        'operator' => 'Operador',
        'viewer' => 'Consulta',
    ];
}

function auth_role_label(?string $role): string
{
    $roles = auth_roles();

    return $roles[$role ?? ''] ?? 'Sem perfil';
}

function auth_permission_labels(): array
{
    return [
        'system.manage_users' => 'Gerenciar usuarios e perfis',
        'system.manage_data' => 'Exportar/importar dados do sistema',
        'system.view_diagnostics' => 'Visualizar diagnostico do ambiente',
        'system.view_logs' => 'Visualizar logs da aplicacao',
        'catalog.manage' => 'Gerenciar catalogo, kits e biblioteca',
        'projects.manage' => 'Gerenciar projetos, demandas, lotes e DOD',
        'budgets.manage' => 'Gerenciar orcamentos e banco de precos',
        'suppliers.manage' => 'Gerenciar fornecedores',
        'requesters.manage' => 'Gerenciar secretarias, unidades e colaboradores',
        'confirmations.manage' => 'Solicitar e revogar confirmacoes formais',
        'reports.view' => 'Visualizar relatorios e exportacoes',
        'bi.view' => 'Visualizar gestao de projetos/BI',
        'hashes.view' => 'Validar hashes de documentos',
        'ai.use' => 'Usar sugestoes assistidas por IA',
    ];
}

function auth_role_permissions(string $role): array
{
    $all = array_keys(auth_permission_labels());

    return match ($role) {
        'admin' => $all,
        'manager' => [
            'catalog.manage',
            'projects.manage',
            'budgets.manage',
            'suppliers.manage',
            'requesters.manage',
            'confirmations.manage',
            'reports.view',
            'bi.view',
            'hashes.view',
            'ai.use',
        ],
        'operator' => [
            'catalog.manage',
            'projects.manage',
            'budgets.manage',
            'suppliers.manage',
            'requesters.manage',
            'confirmations.manage',
            'reports.view',
            'hashes.view',
        ],
        'viewer' => [
            'reports.view',
            'bi.view',
            'hashes.view',
        ],
        default => [],
    };
}

function auth_normalize_user_data(array $data, bool $requirePassword = false): array
{
    $data['name'] = trim((string) ($data['name'] ?? ''));
    $data['username'] = strtolower(trim((string) ($data['username'] ?? '')));
    $data['email'] = strtolower(trim((string) ($data['email'] ?? '')));
    $data['role'] = (string) ($data['role'] ?? 'operator');
    $data['password'] = (string) ($data['password'] ?? '');
    $data['is_active'] = array_key_exists('is_active', $data)
        ? filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN)
        : true;
    $data['must_change_password'] = array_key_exists('must_change_password', $data)
        ? filter_var($data['must_change_password'], FILTER_VALIDATE_BOOLEAN)
        : false;

    if ($data['name'] === '') {
        throw new InvalidArgumentException('Informe o nome do usuario.');
    }

    if (!preg_match('/^[a-z0-9._-]{3,80}$/', $data['username'])) {
        throw new InvalidArgumentException('Informe um usuario com 3 a 80 caracteres, usando letras, numeros, ponto, hifen ou sublinhado.');
    }

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Informe um e-mail valido.');
    }

    if (!array_key_exists($data['role'], auth_roles())) {
        throw new InvalidArgumentException('Perfil de usuario invalido.');
    }

    if (($requirePassword || $data['password'] !== '') && strlen($data['password']) < 8) {
        throw new InvalidArgumentException('A senha deve ter pelo menos 8 caracteres.');
    }

    return $data;
}

function auth_current_user(): ?array
{
    static $currentUser = null;
    static $loaded = false;

    auth_start_session();

    if ($loaded) {
        return $currentUser;
    }

    $loaded = true;
    $userId = (int) ($_SESSION['auth_user_id'] ?? 0);

    if ($userId <= 0 || !auth_schema_available()) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM app_users WHERE id = :id AND is_active = TRUE');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        unset($_SESSION['auth_user_id']);
        return null;
    }

    $currentUser = $user;

    return $currentUser;
}

function auth_can(string $permission): bool
{
    $user = auth_current_user();

    if (!$user) {
        return false;
    }

    return in_array($permission, auth_role_permissions((string) $user['role']), true);
}

function auth_require_permission(string $permission): void
{
    if (auth_can($permission)) {
        return;
    }

    http_response_code(403);
    echo 'Acesso negado.';
    exit;
}

function auth_route_required_permission(string $page): ?string
{
    $permissions = [
        'system.manage_users' => ['users.php', 'user_form.php', 'user_toggle.php'],
        'system.manage_data' => ['data.php', 'export_json.php', 'import_json.php', 'import_template_json.php'],
        'system.view_diagnostics' => ['environment_diagnostics.php'],
        'system.view_logs' => ['system_logs.php'],
        'catalog.manage' => [
            'ai_suggest.php', 'item_form.php', 'item_delete.php', 'item_duplicate.php', 'item_image_delete.php',
            'item_image_primary.php', 'item_version_create.php', 'item_version_restore.php', 'category_form.php',
            'category_delete.php', 'unit_type_form.php', 'unit_type_delete.php', 'kit_form.php', 'kit_delete.php',
            'kit_item_add.php', 'kit_item_delete.php', 'justification_template_form.php',
            'justification_template_delete.php', 'impact_template_form.php', 'impact_template_delete.php',
        ],
        'projects.manage' => [
            'project_form.php', 'project_delete.php', 'project_duplicate.php', 'project_lots.php', 'project_lot_form.php',
            'project_lot_assignments.php', 'project_licitation_numbers.php', 'direct_purchase_dod.php',
            'demand_form.php', 'demand_delete.php', 'demand_item_add.php', 'demand_item_delete.php',
            'demand_item_update.php', 'demand_kit_add.php',
        ],
        'budgets.manage' => [
            'demand_budget.php', 'demand_supplier_quote_form.php', 'demand_supplier_quote_delete.php',
            'demand_price_bank.php', 'project_supplier_quote_form.php', 'project_budgets.php',
            'project_global_price_bank.php',
        ],
        'suppliers.manage' => ['suppliers.php', 'supplier_form.php', 'supplier_delete.php', 'supplier_cnpj_lookup.php', 'supplier_cep_lookup.php', 'cnae_lookup.php'],
        'requesters.manage' => ['requester_units.php', 'requester_unit_form.php', 'requester_unit_delete.php', 'secretariat_form.php', 'secretariat_delete.php', 'collaborators.php', 'collaborator_form.php', 'collaborator_toggle.php'],
        'confirmations.manage' => ['demand_confirmation_form.php', 'demand_confirmation_revoke.php'],
        'bi.view' => ['project_bi.php', 'annual_price_comparison.php', 'annual_price_comparison_export.php'],
        'hashes.view' => ['document_hash_validate.php'],
    ];

    foreach ($permissions as $permission => $pages) {
        if (in_array($page, $pages, true)) {
            return $permission;
        }
    }

    return null;
}

function auth_attempt_local(string $login, string $password): bool
{
    if (!auth_schema_available()) {
        return false;
    }

    $login = strtolower(trim($login));
    $stmt = db()->prepare('SELECT * FROM app_users WHERE (username = :login OR email = :login) AND is_active = TRUE LIMIT 1');
    $stmt->execute(['login' => $login]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        return false;
    }

    auth_start_session();
    session_regenerate_id(true);
    $_SESSION['auth_user_id'] = (int) $user['id'];

    $update = db()->prepare('UPDATE app_users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id');
    $update->execute(['id' => (int) $user['id']]);

    return true;
}

function auth_logout(): void
{
    auth_start_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    }

    session_destroy();
}

function auth_get_users(): array
{
    if (!auth_schema_available()) {
        return [];
    }

    return db()->query('SELECT * FROM app_users ORDER BY is_active DESC, lower(name)')->fetchAll();
}

function auth_find_user(int $id): ?array
{
    if (!auth_schema_available()) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM app_users WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function auth_active_admin_count(): int
{
    if (!auth_schema_available()) {
        return 0;
    }

    return (int) db()->query("SELECT COUNT(*) FROM app_users WHERE role = 'admin' AND is_active = TRUE")->fetchColumn();
}

function auth_create_user(array $data, bool $forceAdmin = false): int
{
    if (!auth_schema_available()) {
        throw new RuntimeException('Tabela de usuarios nao encontrada. Rode o schema atualizado.');
    }

    $data = auth_normalize_user_data($data, true);

    if ($forceAdmin) {
        $data['role'] = 'admin';
        $data['is_active'] = true;
        $data['must_change_password'] = false;
    }

    $stmt = db()->prepare("
        INSERT INTO app_users (name, username, email, password_hash, role, is_active, must_change_password)
        VALUES (:name, :username, :email, :password_hash, :role, :is_active, :must_change_password)
        RETURNING id
    ");
    $stmt->execute([
        'name' => $data['name'],
        'username' => $data['username'],
        'email' => $data['email'],
        'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
        'role' => $data['role'],
        'is_active' => auth_pg_bool($data['is_active']),
        'must_change_password' => auth_pg_bool($data['must_change_password']),
    ]);

    return (int) $stmt->fetchColumn();
}

function auth_update_user(int $id, array $data): void
{
    $existing = auth_find_user($id);

    if (!$existing) {
        throw new InvalidArgumentException('Usuario nao encontrado.');
    }

    $data = auth_normalize_user_data($data, false);
    $current = auth_current_user();
    $wouldRemoveLastAdmin = (string) ($existing['role'] ?? '') === 'admin'
        && auth_active_admin_count() <= 1
        && ($data['role'] !== 'admin' || !$data['is_active']);

    if ($wouldRemoveLastAdmin) {
        throw new RuntimeException('Mantenha pelo menos um administrador ativo.');
    }

    if ($current && (int) $current['id'] === $id && !$data['is_active']) {
        throw new RuntimeException('Voce nao pode desativar o proprio usuario.');
    }

    $fields = [
        'name = :name',
        'username = :username',
        'email = :email',
        'role = :role',
        'is_active = :is_active',
        'must_change_password = :must_change_password',
    ];
    $params = [
        'id' => $id,
        'name' => $data['name'],
        'username' => $data['username'],
        'email' => $data['email'],
        'role' => $data['role'],
        'is_active' => auth_pg_bool($data['is_active']),
        'must_change_password' => auth_pg_bool($data['must_change_password']),
    ];

    if ($data['password'] !== '') {
        $fields[] = 'password_hash = :password_hash';
        $params['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
    }

    $stmt = db()->prepare('UPDATE app_users SET ' . implode(', ', $fields) . ' WHERE id = :id');
    $stmt->execute($params);
}

function auth_set_user_active(int $id, bool $active): void
{
    $user = auth_find_user($id);

    if (!$user) {
        throw new InvalidArgumentException('Usuario nao encontrado.');
    }

    if ((string) ($user['role'] ?? '') === 'admin' && auth_active_admin_count() <= 1 && !$active) {
        throw new RuntimeException('Mantenha pelo menos um administrador ativo.');
    }

    $current = auth_current_user();

    if ($current && (int) $current['id'] === $id && !$active) {
        throw new RuntimeException('Voce nao pode desativar o proprio usuario.');
    }

    $stmt = db()->prepare('UPDATE app_users SET is_active = :is_active WHERE id = :id');
    $stmt->execute(['id' => $id, 'is_active' => auth_pg_bool($active)]);
}

function auth_change_current_password(string $currentPassword, string $newPassword): void
{
    $user = auth_current_user();

    if (!$user) {
        throw new RuntimeException('Sessao expirada. Entre novamente.');
    }

    if (!password_verify($currentPassword, (string) $user['password_hash'])) {
        throw new RuntimeException('Senha atual incorreta.');
    }

    if (strlen($newPassword) < 8) {
        throw new RuntimeException('A nova senha deve ter pelo menos 8 caracteres.');
    }

    $stmt = db()->prepare('UPDATE app_users SET password_hash = :password_hash, must_change_password = FALSE WHERE id = :id');
    $stmt->execute([
        'id' => (int) $user['id'],
        'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
    ]);
}

require_once __DIR__ . '/auth_ldap.php';

function auth_boot(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    auth_start_session();

    $page = auth_current_page();

    if (auth_is_public_page($page)) {
        return;
    }

    if (!auth_schema_available()) {
        auth_redirect('/login.php?error=schema');
    }

    if (auth_user_count() === 0) {
        auth_redirect('/setup_admin.php');
    }

    $user = auth_current_user();

    if (!$user) {
        $return = $_SERVER['REQUEST_URI'] ?? '/';
        auth_redirect('/login.php?return=' . rawurlencode($return));
    }

    if (!empty($user['must_change_password']) && $page !== 'profile.php') {
        auth_redirect('/profile.php?must_change=1');
    }

    $permission = auth_route_required_permission($page);

    if ($permission !== null) {
        auth_require_permission($permission);
    }
}