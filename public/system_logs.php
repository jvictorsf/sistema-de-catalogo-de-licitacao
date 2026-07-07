<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/system_tools.php';

$filters = [
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
    'level' => strtoupper(trim((string) ($_GET['level'] ?? ''))),
    'user' => trim((string) ($_GET['user'] ?? '')),
    'route' => trim((string) ($_GET['route'] ?? '')),
    'message' => trim((string) ($_GET['message'] ?? '')),
    'file' => trim((string) ($_GET['file'] ?? '')),
];

$limit = max(50, min(1000, (int) ($_GET['limit'] ?? 500)));
$files = system_log_files();
$entries = read_system_logs($filters, null, $limit);
$levels = ['', 'FATAL', 'ERROR', 'WARNING', 'INFO', 'DEBUG'];

function log_level_badge(string $level): string
{
    return match (strtoupper($level)) {
        'FATAL' => 'text-bg-dark',
        'ERROR' => 'text-bg-danger',
        'WARNING' => 'text-bg-warning',
        'DEBUG' => 'text-bg-secondary',
        default => 'text-bg-info',
    };
}

require __DIR__ . '/../app/views/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Logs da aplicacao</h1>
        <p class="text-muted mb-0">Leitura administrativa dos arquivos em <code>storage/logs</code>.</p>
    </div>
    <a href="/environment_diagnostics.php" class="btn btn-outline-secondary">
        <i class="bi bi-activity me-2"></i>Diagnostico
    </a>
</div>

<div class="card mb-4">
    <div class="card-header fw-semibold"><i class="bi bi-funnel me-2"></i>Filtros</div>
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-3 col-xl-2">
                <label class="form-label">Data inicial</label>
                <input type="date" name="date_from" class="form-control" value="<?= e($filters['date_from']) ?>">
            </div>
            <div class="col-md-3 col-xl-2">
                <label class="form-label">Data final</label>
                <input type="date" name="date_to" class="form-control" value="<?= e($filters['date_to']) ?>">
            </div>
            <div class="col-md-3 col-xl-2">
                <label class="form-label">Nivel</label>
                <select name="level" class="form-select">
                    <?php foreach ($levels as $level): ?>
                        <option value="<?= e($level) ?>" <?= $filters['level'] === $level ? 'selected' : '' ?>><?= e($level === '' ? 'Todos' : $level) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 col-xl-2">
                <label class="form-label">Arquivo</label>
                <select name="file" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($files as $file): ?>
                        <option value="<?= e($file) ?>" <?= $filters['file'] === $file ? 'selected' : '' ?>><?= e($file) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 col-xl-2">
                <label class="form-label">Usuario</label>
                <input type="search" name="user" class="form-control" value="<?= e($filters['user']) ?>" placeholder="ID ou nome">
            </div>
            <div class="col-md-3 col-xl-2">
                <label class="form-label">Rota</label>
                <input type="search" name="route" class="form-control" value="<?= e($filters['route']) ?>" placeholder="project_show.php">
            </div>
            <div class="col-md-6 col-xl-4">
                <label class="form-label">Mensagem</label>
                <input type="search" name="message" class="form-control" value="<?= e($filters['message']) ?>" placeholder="Texto do erro ou evento">
            </div>
            <div class="col-md-3 col-xl-2">
                <label class="form-label">Limite</label>
                <input type="number" name="limit" class="form-control" min="50" max="1000" step="50" value="<?= e((string) $limit) ?>">
            </div>
            <div class="col-md-3 col-xl-2 d-flex gap-2">
                <button class="btn btn-primary flex-fill"><i class="bi bi-search me-2"></i>Filtrar</button>
                <a href="/system_logs.php" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="fw-semibold"><i class="bi bi-terminal me-2"></i>Eventos encontrados</span>
        <span class="badge text-bg-secondary"><?= count($entries) ?> registro(s)</span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Data</th>
                    <th>Nivel</th>
                    <th>Arquivo</th>
                    <th>Usuario</th>
                    <th>Rota</th>
                    <th>Mensagem</th>
                    <th>Contexto</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$entries): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Nenhum log encontrado para os filtros informados.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($entries as $entry): ?>
                    <tr>
                        <td class="text-nowrap"><?= e((string) $entry['date']) ?></td>
                        <td><span class="badge <?= log_level_badge((string) $entry['level']) ?>"><?= e((string) $entry['level']) ?></span></td>
                        <td><code><?= e((string) $entry['file']) ?></code></td>
                        <td><?= e((string) ($entry['user'] ?: '-')) ?></td>
                        <td><code><?= e((string) ($entry['route'] ?: '-')) ?></code></td>
                        <td class="text-break"><?= e((string) $entry['message']) ?></td>
                        <td>
                            <?php if (!empty($entry['context'])): ?>
                                <details>
                                    <summary class="small">ver</summary>
                                    <pre class="small bg-light border rounded p-2 mt-2 mb-0 text-break"><?= e(json_encode($entry['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
                                </details>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../app/views/footer.php'; ?>