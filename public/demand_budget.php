<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$id = (int) ($_GET['id'] ?? 0);
$demand = find_demand_list($id);

if (!$demand) {
    http_response_code(404);
    exit('Demanda não encontrada.');
}

$project = find_project((int) $demand['project_id']);
$projectLocked = project_is_closed($project);
$budget = get_demand_budget_report($id);
$quotes = $budget['quotes'];
$items = $budget['items'];
$supplierTotals = $budget['supplier_totals'];
$totalAverage = (float) $budget['total_average'];

require __DIR__ . '/../app/views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 print-hide">
    <div>
        <h1 class="h3 mb-1">Orçamento geral da Prefeitura</h1>
        <p class="text-muted mb-0">
            Demanda: <?= e($demand['name']) ?>
        </p>
    </div>

    <div class="d-flex gap-2 flex-wrap justify-content-end">
        <button type="button" class="btn btn-outline-primary" onclick="window.print()">
            <i class="bi bi-printer"></i>Imprimir/PDF
        </button>

        <?php if (!$projectLocked): ?>
        <a href="/demand_supplier_quote_form.php?demand_id=<?= (int) $demand['id'] ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i>Adicionar orçamento
        </a>

        <a href="/demand_price_bank.php?id=<?= (int) $demand['id'] ?>" class="btn btn-outline-success">
            <i class="bi bi-archive"></i>Banco de preços
        </a>

        <?php endif; ?>

        <a href="/demand_show.php?id=<?= (int) $demand['id'] ?>" class="btn btn-outline-secondary">
            Voltar
        </a>
    </div>
</div>

<?php if ($projectLocked): ?>
    <div class="alert alert-warning print-hide">
        <?= e(project_closed_edit_message()) ?>
    </div>
<?php endif; ?>

<div class="card card-body mb-4">
    <div class="text-center mb-3">
        <?= render_municipal_logo() ?>
        <h2 class="h4 mb-1">Orçamento geral da Prefeitura</h2>
        <div class="text-muted">Mapa comparativo de fornecedores e média de preços</div>
    </div>

    <div class="row g-3">
        <div class="col-md-3">
            <div class="text-muted small">Projeto</div>
            <div class="fw-semibold"><?= e($project['name'] ?? '-') ?></div>
        </div>
        <div class="col-md-3">
            <div class="text-muted small">Demanda</div>
            <div class="fw-semibold"><?= e($demand['name']) ?></div>
        </div>
        <div class="col-md-3">
            <div class="text-muted small">Unidade/Setor</div>
            <div class="fw-semibold"><?= e($demand['requester_department'] ?: '-') ?></div>
        </div>
        <div class="col-md-3">
            <div class="text-muted small">Secretaria</div>
            <div class="fw-semibold"><?= e($demand['secretariat_name'] ?? '-') ?></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card card-body stat-card">
            <div class="text-muted small">Fornecedores cotados</div>
            <div class="h3 mb-0"><?= e((string) count($quotes)) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-body stat-card">
            <div class="text-muted small">Preços históricos selecionados</div>
            <div class="h3 mb-0"><?= e((string) count($budget['historical_references'] ?? [])) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-body stat-card">
            <div class="text-muted small">Valor médio geral</div>
            <div class="h3 mb-0">R$ <?= number_format($totalAverage, 2, ',', '.') ?></div>
        </div>
    </div>
</div>

<?php if (!$quotes): ?>
    <div class="alert alert-warning">
        Nenhum orçamento de fornecedor foi vinculado a esta demanda.
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header fw-semibold">
        Fornecedores e anexos
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Fornecedor</th>
                    <th>Documento</th>
                    <th>Orçamento</th>
                    <th>Total informado</th>
                    <th class="print-hide">Anexo</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$quotes): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            Nenhum fornecedor cotado.
                        </td>
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
                                <div class="small text-muted">Data: <?= date('d/m/Y', strtotime($quote['quote_date'])) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($quote['validity_date'])): ?>
                                <div class="small text-muted">Validade: <?= date('d/m/Y', strtotime($quote['validity_date'])) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="fw-semibold">R$ <?= number_format((float) ($supplierTotals[$quoteId] ?? 0), 2, ',', '.') ?></td>
                        <td class="print-hide">
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
</div>

<?php if (!empty($budget['historical_references'])): ?>
    <div class="card mb-4">
        <div class="card-header fw-semibold">
            Banco de preços selecionado
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Item</th>
                        <th>Fornecedor</th>
                        <th>Valor unitário</th>
                        <th>Total de referência</th>
                        <th>Origem</th>
                        <th class="print-hide">Anexo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($budget['historical_references'] as $reference): ?>
                        <tr>
                            <td>
                                <span class="badge text-bg-dark"><?= e($reference['target_tracking_code']) ?></span>
                                <?= e($reference['target_item_name']) ?>
                            </td>
                            <td><?= e($reference['supplier_name']) ?></td>
                            <td class="fw-semibold">R$ <?= number_format((float) $reference['unit_price'], 2, ',', '.') ?></td>
                            <td class="fw-semibold">R$ <?= number_format((float) $reference['reference_total'], 2, ',', '.') ?></td>
                            <td>
                                <?= e($reference['source_project_name']) ?>
                                <div class="small text-muted"><?= e($reference['source_demand_name']) ?></div>
                            </td>
                            <td class="print-hide">
                                <?php if (!empty($reference['attachment_path'])): ?>
                                    <a href="<?= e($reference['attachment_path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
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
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header fw-semibold">
        Mapa comparativo por item
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 budget-matrix">
            <thead class="table-light">
                <tr>
                    <th>Código</th>
                    <th>Item</th>
                    <th>Qtd.</th>
                    <?php foreach ($quotes as $quote): ?>
                        <th><?= e($quote['supplier_name']) ?></th>
                    <?php endforeach; ?>
                    <th>Média unitária</th>
                    <th>Total médio</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$items): ?>
                    <tr>
                        <td colspan="<?= 5 + count($quotes) ?>" class="text-center text-muted py-4">
                            Nenhum item demandado.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><span class="badge text-bg-dark"><?= e($item['tracking_code']) ?></span></td>
                        <td><?= e($item['item_name']) ?></td>
                        <td><?= e((string) $item['budget_quantity']) ?></td>
                        <?php foreach ($quotes as $quote): ?>
                            <?php
                                $quoteId = (int) $quote['id'];
                                $price = $item['supplier_prices'][$quoteId] ?? null;
                                $origin = $item['supplier_origins'][$quoteId] ?? null;
                            ?>
                            <td>
                                <?= $price !== null ? 'R$ ' . number_format((float) $price, 2, ',', '.') : '<span class="text-muted">-</span>' ?>
                                <?php if ($origin): ?>
                                    <div class="small text-muted">Origem: <?= e($origin) ?></div>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        <td class="fw-semibold">
                            <?= $item['average_unit_price'] !== null ? 'R$ ' . number_format((float) $item['average_unit_price'], 2, ',', '.') : '<span class="text-muted">-</span>' ?>
                            <?php if (!empty($item['historical_references'])): ?>
                                <div class="small text-muted">Inclui banco de preços</div>
                            <?php endif; ?>
                        </td>
                        <td class="fw-semibold">
                            <?= $item['average_total'] !== null ? 'R$ ' . number_format((float) $item['average_total'], 2, ',', '.') : '<span class="text-muted">-</span>' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>

            <?php if ($items): ?>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="3">Totais por fornecedor</th>
                        <?php foreach ($quotes as $quote): ?>
                            <?php $quoteId = (int) $quote['id']; ?>
                            <th>R$ <?= number_format((float) ($supplierTotals[$quoteId] ?? 0), 2, ',', '.') ?></th>
                        <?php endforeach; ?>
                        <th colspan="2">Valor médio geral: R$ <?= number_format($totalAverage, 2, ',', '.') ?></th>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<div class="card card-body mt-4">
    <div class="row g-4">
        <div class="col-md-6">
            <div class="text-muted small">Responsável pela demanda</div>
            <div class="fw-semibold"><?= e($demand['responsible_name'] ?: '-') ?></div>
        </div>
        <div class="col-md-6">
            <div class="text-muted small">Emissão</div>
            <div class="fw-semibold"><?= date('d/m/Y H:i') ?></div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
