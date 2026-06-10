<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$summary = get_dashboard_summary();
$itemsByStatus = get_items_by_status();
$itemsByCategory = get_items_by_category();
$projectRanking = get_project_financial_ranking();

require __DIR__ . '/../app/views/header.php';

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Dashboard</h1>
        <p class="text-muted mb-0">
            Visão geral do catálogo, projetos e demandas.
        </p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card card-body">
            <div class="text-muted small">Itens cadastrados</div>
            <div class="h3 mb-0"><?= e((string) $summary['total_items']) ?></div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card card-body">
            <div class="text-muted small">Projetos</div>
            <div class="h3 mb-0"><?= e((string) $summary['total_projects']) ?></div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card card-body">
            <div class="text-muted small">Demandas</div>
            <div class="h3 mb-0"><?= e((string) $summary['total_demands']) ?></div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card card-body">
            <div class="text-muted small">Valor total estimado</div>
            <div class="h4 mb-0">
                R$ <?= number_format((float) $summary['total_estimated_value'], 2, ',', '.') ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header fw-semibold">
                Itens por Status
            </div>

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
                                <td colspan="2" class="text-center text-muted py-4">
                                    Nenhum item cadastrado.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header fw-semibold">
                Itens por Categoria
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Categoria</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($itemsByCategory as $row): ?>
                            <tr>
                                <td><?= e($row['category_name']) ?></td>
                                <td class="text-end fw-semibold"><?= e((string) $row['total']) ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$itemsByCategory): ?>
                            <tr>
                                <td colspan="2" class="text-center text-muted py-4">
                                    Nenhuma categoria encontrada.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header fw-semibold">
                Projetos por Valor Estimado
            </div>

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
                                <td colspan="2" class="text-center text-muted py-4">
                                    Nenhum projeto encontrado.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
