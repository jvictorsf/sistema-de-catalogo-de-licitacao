<?php

declare(strict_types=1);

function auth_config_value(string $key, mixed $default = null, array $overrides = []): mixed
{
    if (array_key_exists($key, $overrides)) {
        return $overrides[$key];
    }

    if (defined($key)) {
        return constant($key);
    }

    $value = getenv($key);

    return ($value === false || $value === '') ? $default : $value;
}

function auth_config_bool(string $key, bool $default = false, array $overrides = []): bool
{
    $value = auth_config_value($key, $default, $overrides);

    if (is_bool($value)) {
        return $value;
    }

    return in_array(strtolower(trim((string) $value)), ['1', 'true', 't', 'yes', 'y', 'on'], true);
}

function auth_config_int(string $key, int $default = 0, array $overrides = []): int
{
    $value = auth_config_value($key, $default, $overrides);

    return is_numeric($value) ? (int) $value : $default;
}

function auth_split_list(mixed $value): array
{
    if (is_array($value)) {
        return array_values(array_filter(array_map('trim', $value), static fn (string $item): bool => $item !== ''));
    }

    return array_values(array_filter(array_map('trim', preg_split('/[;\\r\\n]+/', (string) $value) ?: []), static fn (string $item): bool => $item !== ''));
}

function auth_ldap_config(array $overrides = []): array
{
    $defaultRole = (string) auth_config_value('AUTH_LDAP_DEFAULT_ROLE', 'viewer', $overrides);

    if (!array_key_exists($defaultRole, auth_roles())) {
        $defaultRole = 'viewer';
    }

    return [
        'enabled' => auth_config_bool('AUTH_LDAP_ENABLED', false, $overrides),
        'host' => trim((string) auth_config_value('AUTH_LDAP_HOST', '', $overrides)),
        'port' => auth_config_int('AUTH_LDAP_PORT', 389, $overrides),
        'use_ssl' => auth_config_bool('AUTH_LDAP_USE_SSL', false, $overrides),
        'use_tls' => auth_config_bool('AUTH_LDAP_USE_TLS', false, $overrides),
        'timeout' => auth_config_int('AUTH_LDAP_TIMEOUT', 5, $overrides),
        'base_dn' => trim((string) auth_config_value('AUTH_LDAP_BASE_DN', '', $overrides)),
        'bind_dn' => trim((string) auth_config_value('AUTH_LDAP_BIND_DN', '', $overrides)),
        'bind_password' => (string) auth_config_value('AUTH_LDAP_BIND_PASSWORD', '', $overrides),
        'user_filter' => trim((string) auth_config_value('AUTH_LDAP_USER_FILTER', '(|(sAMAccountName={login})(userPrincipalName={login})(mail={login}))', $overrides)),
        'account_suffix' => trim((string) auth_config_value('AUTH_LDAP_ACCOUNT_SUFFIX', '', $overrides)),
        'domain' => trim((string) auth_config_value('AUTH_LDAP_DOMAIN', '', $overrides)),
        'auto_create' => auth_config_bool('AUTH_LDAP_AUTO_CREATE', true, $overrides),
        'sync_profile' => auth_config_bool('AUTH_LDAP_SYNC_PROFILE', true, $overrides),
        'sync_role' => auth_config_bool('AUTH_LDAP_SYNC_ROLE', true, $overrides),
        'default_role' => $defaultRole,
        'local_fallback' => auth_config_bool('AUTH_LDAP_LOCAL_FALLBACK', true, $overrides),
        'fallback_email_domain' => trim((string) auth_config_value('AUTH_LDAP_FALLBACK_EMAIL_DOMAIN', 'ldap.local', $overrides)),
        'role_groups' => [
            'admin' => auth_split_list(auth_config_value('AUTH_LDAP_ADMIN_GROUPS', '', $overrides)),
            'manager' => auth_split_list(auth_config_value('AUTH_LDAP_MANAGER_GROUPS', '', $overrides)),
            'operator' => auth_split_list(auth_config_value('AUTH_LDAP_OPERATOR_GROUPS', '', $overrides)),
            'viewer' => auth_split_list(auth_config_value('AUTH_LDAP_VIEWER_GROUPS', '', $overrides)),
        ],
    ];
}

function auth_ldap_enabled(): bool
{
    return auth_ldap_config()['enabled'];
}

function auth_ldap_escape_filter(string $value): string
{
    if (function_exists('ldap_escape')) {
        return ldap_escape($value, '', LDAP_ESCAPE_FILTER);
    }

    return strtr($value, [
        '\\' => '\\5c',
        '*' => '\\2a',
        '(' => '\\28',
        ')' => '\\29',
        "\x00" => '\\00',
    ]);
}

function auth_ldap_local_username(string $login): string
{
    $login = strtolower(trim($login));

    if (str_contains($login, '\\')) {
        $parts = explode('\\', $login);
        $login = (string) end($parts);
    }

    if (str_contains($login, '@')) {
        $login = strstr($login, '@', true) ?: $login;
    }

    $login = preg_replace('/[^a-z0-9._-]+/', '.', $login) ?: '';
    $login = trim($login, '._-');

    return strlen($login) >= 3 ? $login : 'usr' . $login;
}

function auth_ldap_user_filter(string $login, ?array $config = null): string
{
    $config ??= auth_ldap_config();
    $filter = (string) ($config['user_filter'] ?? '');

    if ($filter === '') {
        $filter = '(|(sAMAccountName={login})(userPrincipalName={login})(mail={login}))';
    }

    return str_replace(
        ['{login}', '{username}'],
        [auth_ldap_escape_filter($login), auth_ldap_escape_filter(auth_ldap_local_username($login))],
        $filter
    );
}

function auth_ldap_extract_cn(string $dn): string
{
    if (preg_match('/CN=([^,]+)/i', $dn, $matches)) {
        return trim(str_replace('\\,', ',', $matches[1]));
    }

    return trim($dn);
}

function auth_ldap_group_matches(string $memberOf, string $expected): bool
{
    $memberOf = strtolower(trim($memberOf));
    $expected = strtolower(trim($expected));

    if ($expected === '') {
        return false;
    }

    return $memberOf === $expected || strtolower(auth_ldap_extract_cn($memberOf)) === $expected;
}

function auth_ldap_role_from_groups(array $groups, ?array $roleGroups = null, ?string $defaultRole = null): string
{
    $config = auth_ldap_config();
    $roleGroups ??= $config['role_groups'];
    $defaultRole ??= (string) $config['default_role'];

    foreach (['admin', 'manager', 'operator', 'viewer'] as $role) {
        foreach ($groups as $group) {
            foreach (($roleGroups[$role] ?? []) as $expected) {
                if (auth_ldap_group_matches((string) $group, (string) $expected)) {
                    return $role;
                }
            }
        }
    }

    return array_key_exists($defaultRole, auth_roles()) ? $defaultRole : 'viewer';
}
function auth_ldap_entry_values(array $entry, string $key): array
{
    $values = $entry[strtolower($key)] ?? $entry[$key] ?? [];

    if (!is_array($values)) {
        return [(string) $values];
    }

    $count = isset($values['count']) ? (int) $values['count'] : count($values);
    $result = [];

    for ($index = 0; $index < $count; $index++) {
        if (isset($values[$index])) {
            $result[] = (string) $values[$index];
        }
    }

    return $result;
}

function auth_ldap_user_from_entry(array $entry, string $login, array $config): array
{
    $username = strtolower(auth_ldap_entry_values($entry, 'samaccountname')[0] ?? auth_ldap_local_username($login));
    $name = auth_ldap_entry_values($entry, 'displayname')[0]
        ?? auth_ldap_entry_values($entry, 'cn')[0]
        ?? $username;
    $email = strtolower(auth_ldap_entry_values($entry, 'mail')[0] ?? '');
    $groups = auth_ldap_entry_values($entry, 'memberof');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $domain = (string) ($config['fallback_email_domain'] ?? 'ldap.local');
        $email = $username . '@' . ltrim($domain, '@');
    }

    return [
        'username' => $username,
        'name' => $name,
        'email' => $email,
        'role' => auth_ldap_role_from_groups($groups, $config['role_groups'], (string) $config['default_role']),
        'groups' => $groups,
    ];
}

function auth_ldap_bind_identities(string $login, array $config): array
{
    $login = trim($login);
    $identities = [$login];

    if (!str_contains($login, '@') && ($config['account_suffix'] ?? '') !== '') {
        $identities[] = $login . (string) $config['account_suffix'];
    }

    if (!str_contains($login, '\\') && ($config['domain'] ?? '') !== '') {
        $identities[] = (string) $config['domain'] . '\\' . $login;
    }

    return array_values(array_unique(array_filter($identities)));
}

function auth_ldap_connect(array $config): array
{
    if (!extension_loaded('ldap')) {
        return ['ok' => false, 'connection' => null, 'message' => 'Extensao PHP LDAP nao carregada.'];
    }

    if (($config['host'] ?? '') === '') {
        return ['ok' => false, 'connection' => null, 'message' => 'AUTH_LDAP_HOST nao configurado.'];
    }

    $scheme = !empty($config['use_ssl']) ? 'ldaps' : 'ldap';
    $uri = sprintf('%s://%s:%d', $scheme, (string) $config['host'], (int) $config['port']);
    $connection = @ldap_connect($uri);

    if (!$connection) {
        return ['ok' => false, 'connection' => null, 'message' => 'Nao foi possivel iniciar conexao LDAP.'];
    }

    @ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
    @ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
    @ldap_set_option($connection, LDAP_OPT_NETWORK_TIMEOUT, max(1, (int) ($config['timeout'] ?? 5)));
    @ldap_set_option($connection, LDAP_OPT_TIMEOUT, max(1, (int) ($config['timeout'] ?? 5)));

    if (!empty($config['use_tls']) && !@ldap_start_tls($connection)) {
        return ['ok' => false, 'connection' => $connection, 'message' => 'Falha ao iniciar TLS LDAP.'];
    }

    return ['ok' => true, 'connection' => $connection, 'message' => 'Conexao LDAP iniciada.'];
}

function auth_ldap_service_bind($connection, array $config): array
{
    $bindDn = (string) ($config['bind_dn'] ?? '');

    if ($bindDn === '') {
        return ['ok' => true, 'message' => 'Bind de servico nao configurado; usando bind anonimo quando permitido.'];
    }

    if (@ldap_bind($connection, $bindDn, (string) ($config['bind_password'] ?? ''))) {
        return ['ok' => true, 'message' => 'Bind de servico realizado.'];
    }

    return ['ok' => false, 'message' => ldap_error($connection) ?: 'Falha no bind de servico LDAP.'];
}

function auth_ldap_search_user($connection, string $login, array $config): array
{
    if (($config['base_dn'] ?? '') === '') {
        return ['ok' => false, 'message' => 'AUTH_LDAP_BASE_DN nao configurado.', 'entry' => null];
    }

    $attributes = ['dn', 'cn', 'displayname', 'samaccountname', 'userprincipalname', 'mail', 'memberof'];
    $search = @ldap_search($connection, (string) $config['base_dn'], auth_ldap_user_filter($login, $config), $attributes);

    if (!$search) {
        return ['ok' => false, 'message' => ldap_error($connection) ?: 'Falha ao consultar usuario no LDAP.', 'entry' => null];
    }

    $entries = ldap_get_entries($connection, $search);

    if (!is_array($entries) || (int) ($entries['count'] ?? 0) < 1) {
        return ['ok' => false, 'message' => 'Usuario nao localizado no LDAP.', 'entry' => null];
    }

    return ['ok' => true, 'message' => 'Usuario localizado no LDAP.', 'entry' => $entries[0]];
}

function auth_ldap_authenticate(string $login, string $password): array
{
    $config = auth_ldap_config();

    if (!$config['enabled']) {
        return ['ok' => false, 'status' => 'skipped', 'reason' => 'disabled', 'message' => 'LDAP desabilitado.'];
    }

    if (trim($password) === '') {
        return ['ok' => false, 'status' => 'failed', 'reason' => 'empty_password', 'message' => 'Senha LDAP vazia.'];
    }

    $connectionResult = auth_ldap_connect($config);

    if (!$connectionResult['ok']) {
        return ['ok' => false, 'status' => 'failed', 'reason' => 'connect', 'message' => $connectionResult['message']];
    }

    $connection = $connectionResult['connection'];
    $serviceBind = auth_ldap_service_bind($connection, $config);

    if (!$serviceBind['ok']) {
        return ['ok' => false, 'status' => 'failed', 'reason' => 'service_bind', 'message' => $serviceBind['message']];
    }

    $search = auth_ldap_search_user($connection, $login, $config);

    if (!$search['ok']) {
        return ['ok' => false, 'status' => 'failed', 'reason' => 'user_search', 'message' => $search['message']];
    }

    $entry = $search['entry'];
    $dn = (string) ($entry['dn'] ?? '');
    $bound = $dn !== '' && @ldap_bind($connection, $dn, $password);

    if (!$bound) {
        foreach (auth_ldap_bind_identities($login, $config) as $identity) {
            if (@ldap_bind($connection, $identity, $password)) {
                $bound = true;
                break;
            }
        }
    }

    if (!$bound) {
        return ['ok' => false, 'status' => 'failed', 'reason' => 'user_bind', 'message' => ldap_error($connection) ?: 'Credenciais LDAP invalidas.'];
    }

    return ['ok' => true, 'status' => 'authenticated', 'user' => auth_ldap_user_from_entry($entry, $login, $config)];
}
function auth_find_user_by_identity(string $username, string $email): ?array
{
    if (!auth_schema_available()) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM app_users WHERE username = :username OR email = :email ORDER BY id LIMIT 1');
    $stmt->execute(['username' => strtolower($username), 'email' => strtolower($email)]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function auth_login_user(array $user): void
{
    auth_start_session();
    session_regenerate_id(true);
    $_SESSION['auth_user_id'] = (int) $user['id'];

    $update = db()->prepare('UPDATE app_users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id');
    $update->execute(['id' => (int) $user['id']]);
}

function auth_log_auth_failure(string $provider, string $login, string $reason, array $context = []): void
{
    if (!function_exists('app_log')) {
        return;
    }

    app_log('warning', 'Falha de autenticacao', array_merge([
        'provider' => $provider,
        'login' => $login,
        'reason' => $reason,
    ], $context));
}

function auth_upsert_ldap_user(array $ldapUser): ?array
{
    if (!auth_schema_available()) {
        return null;
    }

    $config = auth_ldap_config();
    $username = auth_ldap_local_username((string) ($ldapUser['username'] ?? ''));
    $name = trim((string) ($ldapUser['name'] ?? $username));
    $email = strtolower(trim((string) ($ldapUser['email'] ?? '')));
    $role = (string) ($ldapUser['role'] ?? $config['default_role']);

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email = $username . '@' . ltrim((string) $config['fallback_email_domain'], '@');
    }

    if (!array_key_exists($role, auth_roles())) {
        $role = (string) $config['default_role'];
    }

    $existing = auth_find_user_by_identity($username, $email);

    if ($existing && !filter_var($existing['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
        auth_log_auth_failure('ldap', $username, 'Usuario local espelhado esta inativo.');
        return null;
    }

    if (!$existing && !$config['auto_create']) {
        auth_log_auth_failure('ldap', $username, 'Usuario autenticado no LDAP, mas autocriacao esta desabilitada.');
        return null;
    }

    if ($existing) {
        $newRole = $config['sync_role'] ? $role : (string) $existing['role'];
        $wouldDemoteLastAdmin = (string) ($existing['role'] ?? '') === 'admin'
            && auth_active_admin_count() <= 1
            && $newRole !== 'admin';

        if ($wouldDemoteLastAdmin) {
            $newRole = 'admin';
        }

        $stmt = db()->prepare('
            UPDATE app_users
            SET name = :name,
                username = :username,
                email = :email,
                role = :role,
                must_change_password = FALSE
            WHERE id = :id
            RETURNING *
        ');
        $stmt->execute([
            'id' => (int) $existing['id'],
            'name' => $config['sync_profile'] ? $name : (string) $existing['name'],
            'username' => $username,
            'email' => $config['sync_profile'] ? $email : (string) $existing['email'],
            'role' => $newRole,
        ]);

        return $stmt->fetch() ?: auth_find_user((int) $existing['id']);
    }

    try {
        $stmt = db()->prepare('
            INSERT INTO app_users (name, username, email, password_hash, role, is_active, must_change_password)
            VALUES (:name, :username, :email, :password_hash, :role, TRUE, FALSE)
            RETURNING *
        ');
        $stmt->execute([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
            'role' => $role,
        ]);

        return $stmt->fetch() ?: null;
    } catch (Throwable $exception) {
        app_log_exception($exception);
        auth_log_auth_failure('ldap', $username, 'Falha ao criar usuario local espelhado.');

        return null;
    }
}

function auth_ldap_diagnostic(?string $login = null): array
{
    $config = auth_ldap_config();
    $result = [
        'enabled' => $config['enabled'],
        'ok' => !$config['enabled'],
        'message' => $config['enabled'] ? 'LDAP habilitado, aguardando teste.' : 'LDAP desabilitado.',
        'extension_loaded' => extension_loaded('ldap'),
        'host' => $config['host'],
        'port' => $config['port'],
        'use_ssl' => $config['use_ssl'],
        'use_tls' => $config['use_tls'],
        'base_dn' => $config['base_dn'],
        'bind_configured' => $config['bind_dn'] !== '',
        'default_role' => $config['default_role'],
        'auto_create' => $config['auto_create'],
        'local_fallback' => $config['local_fallback'],
        'group_mapping' => array_map('count', $config['role_groups']),
        'search' => null,
    ];

    if (!$config['enabled']) {
        return $result;
    }

    $connectionResult = auth_ldap_connect($config);

    if (!$connectionResult['ok']) {
        return array_merge($result, ['ok' => false, 'message' => (string) $connectionResult['message']]);
    }

    $connection = $connectionResult['connection'];
    $bind = auth_ldap_service_bind($connection, $config);

    if (!$bind['ok']) {
        return array_merge($result, ['ok' => false, 'message' => (string) $bind['message']]);
    }

    $result['ok'] = true;
    $result['message'] = (string) $bind['message'];

    if ($login !== null && trim($login) !== '') {
        $search = auth_ldap_search_user($connection, $login, $config);
        $result['search'] = [
            'ok' => $search['ok'],
            'message' => $search['message'],
            'login' => $login,
        ];

        if ($search['ok'] && is_array($search['entry'])) {
            $user = auth_ldap_user_from_entry($search['entry'], $login, $config);
            $result['search']['username'] = $user['username'];
            $result['search']['name'] = $user['name'];
            $result['search']['email'] = $user['email'];
            $result['search']['role'] = $user['role'];
        }
    }

    return $result;
}

function auth_attempt(string $login, string $password): bool
{
    if (!auth_schema_available()) {
        return false;
    }

    $login = strtolower(trim($login));

    if ($login === '' || $password === '') {
        return false;
    }

    if (auth_ldap_enabled()) {
        $ldap = auth_ldap_authenticate($login, $password);

        if (!empty($ldap['ok']) && isset($ldap['user']) && is_array($ldap['user'])) {
            $user = auth_upsert_ldap_user($ldap['user']);

            if ($user) {
                auth_login_user($user);
                return true;
            }

            return false;
        }

        auth_log_auth_failure('ldap', $login, (string) ($ldap['message'] ?? 'Falha LDAP'), [
            'reason' => $ldap['reason'] ?? null,
        ]);

        if (!auth_ldap_config()['local_fallback']) {
            return false;
        }
    }

    if (auth_attempt_local($login, $password)) {
        return true;
    }

    auth_log_auth_failure('local', $login, 'Usuario ou senha invalidos.');
    return false;
}
