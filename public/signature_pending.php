<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';
require_once __DIR__ . '/../app/demand_confirmations.php';

$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'project_id' => (int) ($_GET['project_id'] ?? 0),
    'secretariat_id' => (int) ($_GET['secretariat_id'] ?? 0),
    'mode' => trim((string) ($_GET['mode'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
];
$summaryFilters = $filters;
$summaryFilters['status'] = '';
$allRows = get_demand_signature_pending_rows($summaryFilters);
$rows = $filters['status'] === '' ? $allRows : get_demand_signature_pending_rows($filters);
$summary = demand_signature_pending_summary($allRows);
$projects = get_projects();
$secretariats = get_secretariats();

require __DIR__ . '/../app/views/header.php';
?>

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Pendências de assinaturas</h1>
        <p class="text-muted mb-0">Acompanhe etapas liberadas, sequenciais e vencidas em todas as demandas.</p>
    </div>
</div>

<?php if (!demand_signature_flows_table_exists()): ?>
    <div class="alert alert-warning">O banco ainda não possui o módulo ampliado de assinaturas. Rode o <code>database/schema.sql</code> atualizado.</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="border rounded bg-body p-3 h-100"><div class="small text-muted">Total em aberto</div><div class="h3 mb-0"><?= (int) $summary['total'] ?></div></div></div>
    <div class="col-6 col-lg-3"><div class="border rounded bg-body p-3 h-100"><div class="small text-muted">Liberadas</div><div class="h3 mb-0 text-primary"><?= (int) $summary['pending'] ?></div></div></div>
    <div class="col-6 col-lg-3"><div class="border rounded bg-body p-3 h-100"><div class="small text-muted">Aguardando etapa</div><div class="h3 mb-0 text-info-emphasis"><?= (int) $summary['waiting'] ?></div></div></div>
    <div class="col-6 col-lg-3"><div class="border rounded bg-body p-3 h-100"><div class="small text-muted">Expiradas</div><div class="h3 mb-0 text-warning-emphasis"><?= (int) $summary['expired'] ?></div></div></div>
</div>

<form method="get" class="border rounded bg-body p-3 mb-4">
    <div class="row g-3 align-items-end">
        <div class="col-lg-4">
            <label class="form-label">Pesquisa</label>
            <div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input type="search" name="q" class="form-control" value="<?= e($filters['q']) ?>" placeholder="Projeto, demanda, secretaria ou assinante"></div>
        </div>
        <div class="col-md-6 col-lg-3">
            <label class="form-label">Projeto</label>
            <select name="project_id" class="form-select">
                <option value="">Todos</option>
                <?php foreach ($projects as $project): ?><option value="<?= (int) $project['id'] ?>" <?= $filters['project_id'] === (int) $project['id'] ? 'selected' : '' ?>><?= e($project['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6 col-lg-3">
            <label class="form-label">Secretaria</label>
            <select name="secretariat_id" class="form-select">
                <option value="">Todas</option>
                <?php foreach ($secretariats as $secretariat): ?><option value="<?= (int) $secretariat['id'] ?>" <?= $filters['secretariat_id'] === (int) $secretariat['id'] ? 'selected' : '' ?>><?= e($secretariat['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-6 col-lg-2">
            <label class="form-label">Situação</label>
            <select name="status" class="form-select">
                <option value="">Todas</option>
                <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>Liberada</option>
                <option value="waiting" <?= $filters['status'] === 'waiting' ? 'selected' : '' ?>>Aguardando etapa</option>
                <option value="expired" <?= $filters['status'] === 'expired' ? 'selected' : '' ?>>Expirada</option>
            </select>
        </div>
        <div class="col-sm-6 col-lg-3">
            <label class="form-label">Fluxo</label>
            <select name="mode" class="form-select">
                <option value="">Todos</option>
                <option value="parallel" <?= $filters['mode'] === 'parallel' ? 'selected' : '' ?>>Paralelo</option>
                <option value="sequential" <?= $filters['mode'] === 'sequential' ? 'selected' : '' ?>>Sequencial</option>
                <option value="legacy" <?= $filters['mode'] === 'legacy' ? 'selected' : '' ?>>Individual antigo</option>
            </select>
        </div>
        <div class="col-lg-9 d-flex justify-content-end gap-2">
            <a href="/signature_pending.php" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i>Limpar</a>
            <button class="btn btn-primary"><i class="bi bi-funnel"></i>Filtrar</button>
        </div>
    </div>
</form>

<div class="border rounded bg-body overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Projeto e demanda</th><th>Assinante</th><th>Fluxo</th><th>Situação</th><th>Prazo</th><th class="text-end">Ações</th></tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-check2-circle d-block fs-2 mb-2"></i>Nenhuma pendência encontrada.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <?php $status = (string) ($row['effective_status'] ?? 'pending'); ?>
                <tr>
                    <td>
                        <strong><?= e($row['project_name'] ?? '-') ?></strong>
                        <div class="small"><a href="/demand_show.php?id=<?= (int) $row['demand_list_id'] ?>"><?= e($row['demand_name'] ?? '-') ?></a></div>
                        <div class="small text-muted"><?= e($row['secretariat_name'] ?? 'Sem secretaria') ?></div>
                    </td>
                    <td><strong><?= e($row['requester_name'] ?? '-') ?></strong><?php if (!empty($row['requester_role'])): ?><div class="small text-muted"><?= e($row['requester_role']) ?></div><?php endif; ?></td>
                    <td>
                        <div><?= e($row['flow_title'] ?? 'Assinatura individual') ?></div>
                        <div class="small text-muted"><?= e(demand_signature_flow_mode_label($row['flow_mode'] ?? null)) ?> · etapa <?= (int) ($row['signer_order'] ?? 1) ?>/<?= (int) ($row['flow_signer_count'] ?? 1) ?></div>
                    </td>
                    <td><span class="badge <?= e(demand_confirmation_status_badge_class($status)) ?>"><?= e(demand_confirmation_status_label($status)) ?></span></td>
                    <td><?= !empty($row['expires_at']) ? date('d/m/Y H:i', strtotime((string) $row['expires_at'])) : 'Sem prazo' ?></td>
                    <td class="text-end"><a href="/demand_show.php?id=<?= (int) $row['demand_list_id'] ?>" class="btn btn-sm btn-outline-primary" title="Abrir demanda"><i class="bi bi-box-arrow-up-right"></i></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../app/views/footer.php'; ?>