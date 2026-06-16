<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$summary = get_dashboard_summary();
$itemsByStatus = get_items_by_status();
$itemsByCategory = get_items_by_category();
$projectRanking = get_project_financial_ranking();
$projectsByStatus = get_projects_by_status();
$recentProjects = get_recent_projects_for_dashboard();
$annexAttention = get_dashboard_annex_attention();
$annexSummary = $annexAttention['summary'];
$maxCategoryTotal = max(array_map(static fn (array $row): int => (int) $row['total'], $itemsByCategory) ?: [0]);
$maxProjectStatusTotal = max(array_map(static fn (array $row): int => (int) $row['total'], $projectsByStatus) ?: [0]);

require __DIR__ . '/../app/views/header.php';

?>

<div class="page-header d-flex justify-content-between align-items-start mb-4">
    <div class="page-title">
        <h1 class="h3 mb-1">Dashboard administrativo</h1>
        <p class="text-muted mb-0">
            Visao operacional do catalogo, projetos, fornecedores e anexos.
        </p>
    </div>

    <div class="page-actions d-flex gap-2 flex-wrap justify-content-end">
        <a href="/project_form.php" class="btn btn-primary">
            <i class="bi bi-folder-plus"></i>Novo projeto
        </a>
        <a href="/item_form.php" class="btn btn-outline-primary">
            <i class="bi bi-plus-lg"></i>Novo item
        </a>
        <a href="/supplier_form.php" class="btn btn-outline-success">
            <i class="bi bi-truck"></i>Novo fornecedor
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card card-body dashboard-kpi">
            <div class="dashboard-kpi-icon text-bg-primary"><i class="bi bi-box-seam"></i></div>
            <div>
                <div class="text-muted small">Itens cadastrados</div>
                <div class="h3 mb-0"><?= e((string) $summary['total_items']) ?></div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card card-body dashboard-kpi">
            <div class="dashboard-kpi-icon text-bg-info"><i class="bi bi-folder2-open"></i></div>
            <div>
                <div class="text-muted small">Projetos</div>
                <div class="h3 mb-0"><?= e((string) $summary['total_projects']) ?></div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card card-body dashboard-kpi">
            <div class="dashboard-kpi-icon text-bg-warning"><i class="bi bi-building"></i></div>
            <div>
                <div class="text-muted small">Demandas registradas</div>
                <div class="h3 mb-0"><?= e((string) $summary['total_demands']) ?></div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card card-body dashboard-kpi">
            <div class="dashboard-kpi-icon text-bg-success"><i class="bi bi-currency-dollar"></i></div>
            <div>
                <div class="text-muted small">Valor estimado</div>
                <div class="h4 mb-0">R$ <?= number_format((float) $summary['total_estimated_value'], 2, ',', '.') ?></div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card card-body dashboard-kpi dashboard-kpi-compact">
            <div class="dashboard-kpi-icon text-bg-dark"><i class="bi bi-truck"></i></div>
            <div>
                <div class="text-muted small">Fornecedores ativos</div>
                <div class="h4 mb-0">
                    <?= e((string) $summary['active_suppliers']) ?>
                    <span class="text-muted fs-6">/ <?= e((string) $summary['total_suppliers']) ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card card-body dashboard-kpi dashboard-kpi-compact">
            <div class="dashboard-kpi-icon text-bg-secondary"><i class="bi bi-diagram-3"></i></div>
            <div>
                <div class="text-muted small">Unidades demandantes</div>
                <div class="h4 mb-0"><?= e((string) $summary['total_requester_units']) ?></div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card card-body dashboard-kpi dashboard-kpi-compact">
            <div class="dashboard-kpi-icon text-bg-primary"><i class="bi bi-bank"></i></div>
            <div>
                <div class="text-muted small">Secretarias</div>
                <div class="h4 mb-0"><?= e((string) $summary['total_secretariats']) ?></div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card card-body dashboard-kpi dashboard-kpi-compact">
            <div class="dashboard-kpi-icon text-bg-danger"><i class="bi bi-exclamation-triangle"></i></div>
            <div>
                <div class="text-muted small">Anexos a revisar</div>
                <div class="h4 mb-0"><?= e((string) $annexAttention['total_attention']) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header fw-semibold">Projetos por status</div>
            <div class="card-body">
                <?php if (!$projectsByStatus): ?>
                    <div class="empty-state">Nenhum projeto cadastrado.</div>
                <?php endif; ?>

                <?php foreach ($projectsByStatus as $row): ?>
                    <?php
                        $total = (int) $row['total'];
                        $percent = $maxProjectStatusTotal > 0 ? max(8, (int) round(($total / $maxProjectStatusTotal) * 100)) : 0;
                    ?>
                    <div class="dashboard-progress-row">
                        <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
                            <span class="badge <?= e(project_status_badge_class($row['status'] ?? null)) ?>">
                                <?= e(project_status_label($row['status'] ?? null)) ?>
                            </span>
                            <strong><?= e((string) $total) ?></strong>
                        </div>
                        <div class="progress dashboard-progress" role="progressbar" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar" style="width: <?= $percent ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center gap-2">
                <span class="fw-semibold">Controle dos anexos</span>
                <span class="badge text-bg-light border"><?= e((string) array_sum($annexSummary)) ?> registros</span>
            </div>
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <div class="dashboard-mini-stat">
                            <span class="badge text-bg-success">Atuais</span>
                            <strong><?= e((string) ($annexSummary['valid'] ?? 0)) ?></strong>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="dashboard-mini-stat">
                            <span class="badge text-bg-warning">Regenerar</span>
                            <strong><?= e((string) ($annexSummary['stale'] ?? 0)) ?></strong>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="dashboard-mini-stat">
                            <span class="badge text-bg-secondary">Pendentes</span>
                            <strong><?= e((string) ($annexSummary['pending'] ?? 0)) ?></strong>
                        </div>
                    </div>
                </div>

                <?php if (!$annexAttention['items']): ?>
                    <div class="empty-state py-3">Nenhum anexo pendente.</div>
                <?php endif; ?>

                <?php foreach ($annexAttention['items'] as $item): ?>
                    <a href="/project_show.php?id=<?= (int) $item['project_id'] ?>" class="dashboard-attention-item">
                        <span class="badge <?= $item['status'] === 'stale' ? 'text-bg-warning' : 'text-bg-secondary' ?>">
                            <?= $item['status'] === 'stale' ? 'Regenerar' : 'Pendente' ?>
                        </span>
                        <span>
                            <strong><?= e($item['project_name']) ?></strong>
                            <small><?= e($item['label']) ?></small>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header fw-semibold">Projetos recentes</div>
            <div class="list-group list-group-flush">
                <?php if (!$recentProjects): ?>
                    <div class="empty-state">Nenhum projeto cadastrado.</div>
                <?php endif; ?>

                <?php foreach ($recentProjects as $project): ?>
                    <a href="/project_show.php?id=<?= (int) $project['id'] ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex justify-content-between gap-2">
                            <strong><?= e($project['name']) ?></strong>
                            <span class="badge <?= e(project_status_badge_class($project['status'] ?? null)) ?>">
                                <?= e(project_status_label($project['status'] ?? null)) ?>
                            </span>
                        </div>
                        <div class="small text-muted mt-1">
                            <?= (int) $project['demand_count'] ?> demanda(s)
                            | R$ <?= number_format((float) $project['total_estimated_value'], 2, ',', '.') ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header fw-semibold">Itens por status</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Status</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itemsByStatus as $row): ?>
                            <tr>
                                <td>
                                    <span class="badge <?= e(item_status_badge_class($row['status'] ?? null)) ?>">
                                        <?= e(item_status_label($row['status'] ?? null)) ?>
                                    </span>
                                </td>
                                <td class="text-end fw-semibold"><?= e((string) $row['total']) ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$itemsByStatus): ?>
                            <tr>
                                <td colspan="2" class="text-center text-muted py-4">Nenhum item cadastrado.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header fw-semibold">Categorias mais usadas</div>
            <div class="card-body">
                <?php if (!$itemsByCategory): ?>
                    <div class="empty-state">Nenhuma categoria encontrada.</div>
                <?php endif; ?>

                <?php foreach (array_slice($itemsByCategory, 0, 8) as $row): ?>
                    <?php
                        $total = (int) $row['total'];
                        $percent = $maxCategoryTotal > 0 ? max(8, (int) round(($total / $maxCategoryTotal) * 100)) : 0;
                    ?>
                    <div class="dashboard-progress-row">
                        <div class="d-flex justify-content-between gap-2 mb-1">
                            <span><?= e($row['category_name']) ?></span>
                            <strong><?= e((string) $total) ?></strong>
                        </div>
                        <div class="progress dashboard-progress" role="progressbar" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar bg-success" style="width: <?= $percent ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header fw-semibold">Projetos por valor estimado</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Projeto</th>
                            <th class="text-end">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projectRanking as $row): ?>
                            <tr>
                                <td>
                                    <a href="/project_show.php?id=<?= (int) $row['id'] ?>">
                                        <?= e($row['name']) ?>
                                    </a>
                                </td>
                                <td class="text-end fw-semibold">
                                    R$ <?= number_format((float) $row['total_estimated_value'], 2, ',', '.') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$projectRanking): ?>
                            <tr>
                                <td colspan="2" class="text-center text-muted py-4">Nenhum projeto encontrado.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
