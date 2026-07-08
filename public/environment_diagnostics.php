<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/system_tools.php';

$diagnostics = app_environment_diagnostics();
$ldapLogin = trim((string) ($_GET['ldap_login'] ?? ''));
$ldap = function_exists('auth_ldap_diagnostic')
    ? auth_ldap_diagnostic($ldapLogin !== '' ? $ldapLogin : null)
    : ($diagnostics['ldap'] ?? null);
$postgres = $diagnostics['postgresql'];
$missingExtensions = array_values(array_filter($diagnostics['extensions'], static fn (array $extension): bool => !$extension['loaded']));
$pathProblems = array_values(array_filter($diagnostics['paths'], static function (array $path): bool {
    return !$path['exists'] || !$path['is_dir'] || !$path['readable'] || !$path['writable'] || $path['write_test'] === false;
}));

function diagnostics_badge(bool $ok): string
{
    return $ok ? 'text-bg-success' : 'text-bg-danger';
}

function diagnostics_status_text(bool $ok): string
{
    return $ok ? 'OK' : 'Atencao';
}

function diagnostics_bytes(mixed $bytes): string
{
    if ($bytes === null || $bytes === false) {
        return '-';
    }

    $bytes = (float) $bytes;
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $index = 0;

    while ($bytes >= 1024 && $index < count($units) - 1) {
        $bytes /= 1024;
        $index++;
    }

    return number_format($bytes, $index === 0 ? 0 : 2, ',', '.') . ' ' . $units[$index];
}

require __DIR__ . '/../app/views/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Diagnostico do ambiente</h1>
        <p class="text-muted mb-0">Status tecnico do servidor, banco, storage e extensoes PHP.</p>
    </div>
    <a href="/system_logs.php" class="btn btn-outline-secondary">
        <i class="bi bi-terminal me-2"></i>Ver logs
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small">PostgreSQL</div>
                        <div class="fs-5 fw-semibold"><?= e($postgres['message']) ?></div>
                    </div>
                    <span class="badge <?= diagnostics_badge((bool) $postgres['ok']) ?>"><?= diagnostics_status_text((bool) $postgres['ok']) ?></span>
                </div>
                <div class="small text-muted mt-2">
                    <?= e((string) $postgres['host']) ?>:<?= e((string) $postgres['port']) ?>
                    <?php if ($postgres['latency_ms'] !== null): ?>
                        - <?= e((string) $postgres['latency_ms']) ?> ms
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">PHP</div>
                <div class="fs-5 fw-semibold"><?= e($diagnostics['php']['version']) ?></div>
                <div class="small text-muted"><?= e($diagnostics['php']['sapi']) ?> - <?= e($diagnostics['php']['os']) ?></div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Extensoes PHP</div>
                <div class="fs-5 fw-semibold"><?= count($missingExtensions) === 0 ? 'Todas OK' : count($missingExtensions) . ' ausente(s)' ?></div>
                <div class="small text-muted">pdo_pgsql, mbstring, fileinfo, curl e demais</div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">AD/LDAP</div>
                <div class="fs-5 fw-semibold"><?= $ldap && !empty($ldap['enabled']) ? e((string) $ldap['message']) : 'Desabilitado' ?></div>
                <div class="small text-muted"><?= $ldap ? e((string) ($ldap['host'] ?: 'Sem host configurado')) : '-' ?></div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Storage</div>
                <div class="fs-5 fw-semibold"><?= count($pathProblems) === 0 ? 'Escrita OK' : count($pathProblems) . ' problema(s)' ?></div>
                <div class="small text-muted">storage, logs, uploads e confirmacoes</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="bi bi-database-check me-2"></i>PostgreSQL</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Status</dt>
                    <dd class="col-sm-8"><span class="badge <?= diagnostics_badge((bool) $postgres['ok']) ?>"><?= e($postgres['message']) ?></span></dd>
                    <dt class="col-sm-4">Banco</dt>
                    <dd class="col-sm-8"><?= e((string) $postgres['database']) ?></dd>
                    <dt class="col-sm-4">Usuario</dt>
                    <dd class="col-sm-8"><?= e((string) $postgres['user']) ?></dd>
                    <dt class="col-sm-4">Host</dt>
                    <dd class="col-sm-8"><?= e((string) $postgres['host']) ?>:<?= e((string) $postgres['port']) ?></dd>
                    <dt class="col-sm-4">Versao</dt>
                    <dd class="col-sm-8 small"><?= e((string) ($postgres['version'] ?: '-')) ?></dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="bi bi-sliders me-2"></i>Configuracoes PHP</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <tbody>
                        <?php foreach ($diagnostics['php'] as $key => $value): ?>
                            <tr>
                                <th><?= e((string) $key) ?></th>
                                <td><?= e((string) $value) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="bi bi-person-badge me-2"></i>AD/LDAP</div>
            <div class="card-body">
                <?php if (!$ldap): ?>
                    <p class="text-muted mb-0">Diagnostico LDAP indisponivel.</p>
                <?php else: ?>
                    <dl class="row mb-3">
                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8"><span class="badge <?= diagnostics_badge((bool) $ldap['ok']) ?>"><?= e((string) $ldap['message']) ?></span></dd>
                        <dt class="col-sm-4">Servidor</dt>
                        <dd class="col-sm-8"><?= e((string) ($ldap['host'] ?: '-')) ?><?= !empty($ldap['port']) ? ':' . e((string) $ldap['port']) : '' ?></dd>
                        <dt class="col-sm-4">Base DN</dt>
                        <dd class="col-sm-8 text-break"><?= e((string) ($ldap['base_dn'] ?: '-')) ?></dd>
                        <dt class="col-sm-4">Extensao PHP</dt>
                        <dd class="col-sm-8"><span class="badge <?= diagnostics_badge((bool) $ldap['extension_loaded']) ?>"><?= diagnostics_status_text((bool) $ldap['extension_loaded']) ?></span></dd>
                        <dt class="col-sm-4">Bind de servico</dt>
                        <dd class="col-sm-8"><?= !empty($ldap['bind_configured']) ? 'Configurado' : 'Nao configurado' ?></dd>
                        <dt class="col-sm-4">Perfil padrao</dt>
                        <dd class="col-sm-8"><?= e(auth_role_label((string) $ldap['default_role'])) ?></dd>
                        <dt class="col-sm-4">Fallback local</dt>
                        <dd class="col-sm-8"><?= !empty($ldap['local_fallback']) ? 'Ativo' : 'Inativo' ?></dd>
                    </dl>

                    <form method="get" class="row g-2 align-items-end">
                        <div class="col-sm-8">
                            <label for="ldap_login" class="form-label">Testar busca por login</label>
                            <input type="text" class="form-control" id="ldap_login" name="ldap_login" value="<?= e($ldapLogin) ?>" placeholder="usuario ou usuario@dominio">
                        </div>
                        <div class="col-sm-4">
                            <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search me-2"></i>Testar</button>
                        </div>
                    </form>

                    <?php if (!empty($ldap['search'])): ?>
                        <div class="alert <?= !empty($ldap['search']['ok']) ? 'alert-success' : 'alert-warning' ?> mt-3 mb-0">
                            <div class="fw-semibold"><?= e((string) $ldap['search']['message']) ?></div>
                            <?php if (!empty($ldap['search']['ok'])): ?>
                                <div class="small mt-1">
                                    <?= e((string) $ldap['search']['name']) ?> - <?= e((string) $ldap['search']['email']) ?> - <?= e(auth_role_label((string) $ldap['search']['role'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="bi bi-hdd-stack me-2"></i>Permissoes de storage</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Local</th>
                            <th>Status</th>
                            <th>Espaco livre</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($diagnostics['paths'] as $path): ?>
                            <?php $ok = $path['exists'] && $path['is_dir'] && $path['readable'] && $path['writable'] && $path['write_test'] !== false; ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= e($path['label']) ?></div>
                                    <div class="small text-muted text-break"><?= e($path['path']) ?></div>
                                </td>
                                <td><span class="badge <?= diagnostics_badge($ok) ?>"><?= diagnostics_status_text($ok) ?></span></td>
                                <td><?= e(diagnostics_bytes($path['free_space'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="bi bi-puzzle me-2"></i>Extensoes PHP</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Extensao</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($diagnostics['extensions'] as $extension): ?>
                            <tr>
                                <td><?= e($extension['name']) ?></td>
                                <td><span class="badge <?= diagnostics_badge((bool) $extension['loaded']) ?>"><?= diagnostics_status_text((bool) $extension['loaded']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header fw-semibold"><i class="bi bi-gear-wide-connected me-2"></i>Configuracoes principais</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <tbody>
                <?php foreach ($diagnostics['config'] as $key => $value): ?>
                    <tr>
                        <th class="w-25"><?= e($key) ?></th>
                        <td class="text-break"><?= e((string) $value) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../app/views/footer.php'; ?>