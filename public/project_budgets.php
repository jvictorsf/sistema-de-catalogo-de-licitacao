<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$id = (int) ($_GET['id'] ?? 0);
$project = find_project($id);

if (!$project) {
    http_response_code(404);
    exit('Projeto nao encontrado.');
}

$demands = get_project_demands($id);
$reports = [];
$totalQuotes = 0;
$totalAverage = 0.0;

foreach ($demands as $demand) {
    $report = get_demand_budget_report((int) $demand['id']);
    $reports[(int) $demand['id']] = $report;
    $totalQuotes += count($report['quotes'] ?? []);
    $totalAverage += (float) ($report['total_average'] ?? 0);
}

require __DIR__ . '/../app/views/header.php';
?>

<div class="d-flex justify-content-between align-items-start gap-3 mb-4 print-hide">
    <div>
        <h1 class="h3 mb-1">Orcamentos do projeto</h1>
        <p class="text-muted mb-0"><?= e($project['name']) ?></p>
    </div>

    <div class="d-flex gap-2 flex-wrap justify-content-end">
        <button type="button" class="btn btn-outline-primary" onclick="window.print()">
            <i class="bi bi-printer"></i>Imprimir/PDF
        </button>
        <a href="/project_show.php?id=<?= (int) $project['id'] ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>Voltar
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card card-body stat-card">
            <div class="text-muted small">Demandas</div>
            <div class="h3 mb-0"><?= count($demands) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-body stat-card">
            <div class="text-muted small">Orcamentos vinculados</div>
            <div class="h3 mb-0"><?= $totalQuotes ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-body stat-card">
            <div class="text-muted small">Valor medio geral</div>
            <div class="h3 mb-0">R$ <?= number_format($totalAverage, 2, ',', '.') ?></div>
        </div>
    </div>
</div>

<?php if (!$demands): ?>
    <div class="alert alert-warning">Nenhuma demanda cadastrada neste projeto.</div>
<?php endif; ?>

<?php foreach ($demands as $demand): ?>
    <?php
        $report = $reports[(int) $demand['id']] ?? [];
        $quotes = $report['quotes'] ?? [];
        $items = $report['items'] ?? [];
        $supplierTotals = $report['supplier_totals'] ?? [];
    ?>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <div class="fw-semibold"><?= e($demand['name']) ?></div>
                <div class="text-muted small">
                    <?= e($demand['secretariat_name'] ?? 'Sem secretaria') ?>
                    <?php if (!empty($demand['requester_department'])): ?>
                        | <?= e($demand['requester_department']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <span class="badge text-bg-success">
                R$ <?= number_format((float) ($report['total_average'] ?? 0), 2, ',', '.') ?>
            </span>
        </div>

        <div class="card-body">
            <div class="table-responsive mb-3">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Fornecedor</th>
                            <th>Documento</th>
                            <th>Orcamento</th>
                            <th>Total informado</th>
                            <th>Anexo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$quotes): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">Nenhum fornecedor cotado.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($quotes as $quote): ?>
                            <?php $quoteId = (int) $quote['id']; ?>
                            <tr>
                                <td><?= e($quote['supplier_name']) ?></td>
                                <td><?= e($quote['supplier_document'] ? format_brazil_document($quote['supplier_document']) : '-') ?></td>
                                <td>
                                    <?= e($quote['quote_number'] ?: '-') ?>
                                    <?php if (!empty($quote['quote_date'])): ?>
                                        <div class="small text-muted">Data: <?= date('d/m/Y', strtotime((string) $quote['quote_date'])) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-semibold">R$ <?= number_format((float) ($supplierTotals[$quoteId] ?? 0), 2, ',', '.') ?></td>
                                <td>
                                    <?php if (!empty($quote['attachment_path'])): ?>
                                        <a href="<?= e($quote['attachment_path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-paperclip"></i>Abrir
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 budget-matrix">
                    <thead class="table-light">
                        <tr>
                            <th>Codigo</th>
                            <th>Item</th>
                            <th>Qtd.</th>
                            <?php foreach ($quotes as $quote): ?>
                                <th><?= e($quote['supplier_name']) ?></th>
                            <?php endforeach; ?>
                            <th>Media unit.</th>
                            <th>Total medio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$items): ?>
                            <tr>
                                <td colspan="<?= 5 + count($quotes) ?>" class="text-center text-muted py-3">Nenhum item demandado.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><span class="badge text-bg-dark"><?= e($item['tracking_code']) ?></span></td>
                                <td><?= e($item['item_name']) ?></td>
                                <td><?= e((string) $item['budget_quantity']) ?></td>
                                <?php foreach ($quotes as $quote): ?>
                                    <?php $price = $item['supplier_prices'][(int) $quote['id']] ?? null; ?>
                                    <td><?= $price !== null ? 'R$ ' . number_format((float) $price, 2, ',', '.') : '-' ?></td>
                                <?php endforeach; ?>
                                <td class="fw-semibold">
                                    <?= ($item['average_unit_price'] ?? null) !== null ? 'R$ ' . number_format((float) $item['average_unit_price'], 2, ',', '.') : '-' ?>
                                </td>
                                <td class="fw-semibold">
                                    <?= ($item['average_total'] ?? null) !== null ? 'R$ ' . number_format((float) $item['average_total'], 2, ',', '.') : '-' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php require __DIR__ . '/../app/views/footer.php'; ?>