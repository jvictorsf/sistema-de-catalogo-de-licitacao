<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';
require_once __DIR__ . '/../app/demand_confirmations.php';

$id = (int) ($_GET['id'] ?? 0);

$demand = find_demand_list($id);
$kits = get_item_kits();

if (!$demand) {
    http_response_code(404);
    exit('Demanda não encontrada.');
}

$project = find_project((int) $demand['project_id']);
$projectLocked = project_is_locked($project);
$isDirectPurchase = project_is_direct_purchase($project);
$items = get_demand_items($id);
$catalogItems = search_items();
$supplierQuotes = get_demand_supplier_quotes($id);
$budgetReport = get_demand_budget_report($id);
$confirmationRequests = get_demand_confirmation_requests($id);
$actionError = trim((string) ($_GET['error'] ?? ''));
$budgetItemsByDemandItem = [];

foreach ($budgetReport['items'] as $budgetItem) {
    $budgetItemsByDemandItem[(int) $budgetItem['id']] = $budgetItem;
}

$quoteStatusLabels = [
    'received' => 'Recebido',
    'draft' => 'Em coleta',
    'discarded' => 'Desconsiderado',
];

require __DIR__ . '/../app/views/header.php';

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><?= e($demand['name']) ?></h1>

        <p class="text-muted mb-0">
            Projeto: <?= e($project['name'] ?? '-') ?>
        </p>
    </div>

    <div class="d-flex gap-2 flex-wrap justify-content-end">
        <?php if (!$projectLocked): ?>
        <a href="/demand_form.php?id=<?= (int) $demand['id'] ?>" class="btn btn-outline-secondary">
            Editar dados
        </a>

        <a href="/demand_budget.php?id=<?= (int) $demand['id'] ?>" class="btn btn-outline-success">
            <i class="bi bi-calculator"></i>Orçamento geral
        </a>

        <a href="/demand_price_bank.php?id=<?= (int) $demand['id'] ?>" class="btn btn-outline-success">
            <i class="bi bi-archive"></i>Banco de preços
        </a>

        <?php endif; ?>

        <a href="/demand_export_word.php?id=<?= (int) $demand['id'] ?>" class="btn btn-outline-primary">
            Exportar Word
        </a>

        <a href="/demand_pdf.php?id=<?= (int) $demand['id'] ?>" target="_blank" class="btn btn-outline-danger">
            PDF
        </a>

        <a href="/project_show.php?id=<?= (int) $demand['project_id'] ?>" class="btn btn-outline-secondary">
            Voltar ao projeto
        </a>
    </div>
</div>

<?php if ($actionError): ?>
    <div class="alert alert-danger">
        <?= e($actionError) ?>
    </div>
<?php endif; ?>

<?php if ($projectLocked): ?>
    <div class="alert alert-warning">
        <?= e(project_locked_edit_message($project)) ?>
    </div>
<?php endif; ?>

<div class="card card-body mb-4">
    <div class="row g-3">
        <div class="<?= $isDirectPurchase ? 'col-md-3' : 'col-md-4' ?>">
            <div class="text-muted small">Secretaria</div>
            <div class="fw-semibold"><?= e($demand['secretariat_name'] ?? 'Sem secretaria vinculada') ?></div>
        </div>

        <div class="<?= $isDirectPurchase ? 'col-md-3' : 'col-md-4' ?>">
            <div class="text-muted small">Unidade/Setor demandante</div>
            <div class="fw-semibold"><?= e($demand['requester_department'] ?: '-') ?></div>
        </div>

        <div class="<?= $isDirectPurchase ? 'col-md-3' : 'col-md-4' ?>">
            <div class="text-muted small"><?= $isDirectPurchase ? 'Requisitante' : 'Responsável' ?></div>
            <div class="fw-semibold"><?= e($demand['responsible_name'] ?: '-') ?></div>
        </div>

        <?php if ($isDirectPurchase): ?>
            <div class="col-md-3">
                <div class="text-muted small">Cotador</div>
                <div class="fw-semibold"><?= e($demand['quote_collector_name'] ?: '-') ?></div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="fw-semibold">
            Confirmacao formal da demanda
        </div>
        <?php if (!$projectLocked): ?>
            <a href="/demand_confirmation_form.php?demand_id=<?= (int) $demand['id'] ?>" class="btn btn-sm btn-primary">
                <i class="bi bi-link-45deg"></i>Novo link
            </a>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Responsavel</th>
                    <th>Status</th>
                    <th>Validade/assinatura</th>
                    <th>Hash</th>
                    <th class="text-end">Acoes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$confirmationRequests): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            Nenhuma confirmacao solicitada para esta demanda.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($confirmationRequests as $confirmationRequest): ?>
                    <?php $confirmationStatus = $confirmationRequest['effective_status'] ?? $confirmationRequest['status'] ?? 'pending'; ?>
                    <tr>
                        <td>
                            <strong><?= e($confirmationRequest['requester_name'] ?? '-') ?></strong>
                            <?php if (!empty($confirmationRequest['requester_role'])): ?>
                                <div class="small text-muted"><?= e($confirmationRequest['requester_role']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= e(demand_confirmation_status_badge_class($confirmationStatus)) ?>">
                                <?= e(demand_confirmation_status_label($confirmationStatus)) ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($confirmationRequest['signed_at'])): ?>
                                Assinada em <?= date('d/m/Y H:i', strtotime((string) $confirmationRequest['signed_at'])) ?>
                            <?php elseif (!empty($confirmationRequest['expires_at'])): ?>
                                Expira em <?= date('d/m/Y H:i', strtotime((string) $confirmationRequest['expires_at'])) ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="text-break small">
                            <?php if (!empty($confirmationRequest['content_hash'])): ?>
                                <code><?= e($confirmationRequest['content_hash']) ?></code>
                            <?php else: ?>
                                <span class="text-muted">Gerado apos assinatura</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if (!$projectLocked && $confirmationStatus === 'pending'): ?>
                                <form action="/demand_confirmation_revoke.php" method="post" class="d-inline" onsubmit="return confirm('Revogar este link de assinatura?')">
                                    <input type="hidden" name="id" value="<?= (int) $confirmationRequest['id'] ?>">
                                    <input type="hidden" name="demand_id" value="<?= (int) $demand['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger">Revogar</button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">Somente consulta</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="fw-semibold">
            Orçamentos de fornecedores
        </div>

        <div class="d-flex gap-2">
            <?php if (!$projectLocked): ?>
            <a href="/demand_supplier_quote_form.php?demand_id=<?= (int) $demand['id'] ?>" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg"></i>Adicionar orçamento
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Fornecedor</th>
                    <th>Orçamento</th>
                    <th>Itens com valor</th>
                    <th>Total informado</th>
                    <th>Anexo</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$supplierQuotes): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            Nenhum orçamento de fornecedor vinculado.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($supplierQuotes as $quote): ?>
                    <?php $quoteId = (int) $quote['id']; ?>
                    <tr>
                        <td>
                            <strong><?= e($quote['supplier_name']) ?></strong>
                            <?php if (!empty($quote['supplier_document'])): ?>
                                <div class="small text-muted"><?= e(format_brazil_document($quote['supplier_document'])) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= e($quote['quote_number'] ?: 'Sem número') ?>
                            <div class="small">
                                <span class="badge <?= ($quote['status'] ?? '') === 'discarded' ? 'text-bg-secondary' : 'text-bg-success' ?>">
                                    <?= e($quoteStatusLabels[$quote['status'] ?? 'received'] ?? (string) $quote['status']) ?>
                                </span>
                            </div>
                            <?php if (!empty($quote['quote_date'])): ?>
                                <div class="small text-muted">Data: <?= date('d/m/Y', strtotime($quote['quote_date'])) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($quote['validity_date'])): ?>
                                <div class="small text-muted">Validade: <?= date('d/m/Y', strtotime($quote['validity_date'])) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($quote['quoted_by'])): ?>
                                <div class="small text-muted">Fornecedor: <?= e($quote['quoted_by']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($quote['collected_by'])): ?>
                                <div class="small text-muted">Prefeitura: <?= e($quote['collected_by']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= e((string) ($quote['priced_items_count'] ?? 0)) ?></td>
                        <td class="fw-semibold">
                            R$ <?= number_format((float) ($budgetReport['supplier_totals'][$quoteId] ?? $quote['total_quote_value'] ?? 0), 2, ',', '.') ?>
                        </td>
                        <td>
                            <?= render_supplier_quote_document_buttons($quote) ?>
                        </td>
                        <td class="text-end">
                            <?php if (!$projectLocked): ?>
                            <a href="/demand_supplier_quote_form.php?id=<?= $quoteId ?>" class="btn btn-sm btn-outline-primary">
                                Editar
                            </a>

                            <form action="/demand_supplier_quote_delete.php" method="post" class="d-inline" onsubmit="return confirm('Remover este orçamento da demanda?')">
                                <input type="hidden" name="id" value="<?= $quoteId ?>">
                                <input type="hidden" name="demand_id" value="<?= (int) $demand['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger">
                                    Remover
                                </button>
                            </form>
                            <?php else: ?>
                                <span class="text-muted">Somente leitura</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (!$projectLocked): ?>
<div class="card card-body mb-4">
    <h2 class="h5 mb-3">Adicionar item à demanda</h2>

    <div class="row g-3 mb-3">
        <div class="col-md-12">
            <label class="form-label">Pesquisar item no catálogo</label>

            <input
                type="text"
                id="catalogSearch"
                class="form-control"
                placeholder="Digite o código, nome ou parte do item..."
                autofocus>
        </div>
    </div>

    <form action="/demand_item_add.php" method="post" class="row g-3">
        <input type="hidden" name="demand_list_id" value="<?= (int) $demand['id'] ?>">

        <div class="col-md-4">
            <label class="form-label">Item</label>

            <select name="procurement_item_id" id="catalogItemSelect" class="form-select" required>
                <option value="">Selecione...</option>

                <?php foreach ($catalogItems as $catalogItem): ?>
                    <option
                        value="<?= (int) $catalogItem['id'] ?>"
                        data-search="<?= e(mb_strtolower($catalogItem['tracking_code'] . ' ' . $catalogItem['name'])) ?>">

                        <?= e($catalogItem['tracking_code'] . ' - ' . $catalogItem['name']) ?>

                    </option>
                <?php endforeach; ?>
            </select>

            <div class="form-text">
                Use a pesquisa acima para filtrar a lista.
            </div>
        </div>

        <div class="col-md-2">
            <label class="form-label">Qtd. solicitada</label>

            <input
                type="number"
                name="quantity"
                class="form-control"
                min="0.01"
                step="0.01"
                value="1"
                required>
        </div>

        <div class="col-md-2">
            <label class="form-label">Qtd. aprovada</label>

            <input
                type="number"
                name="approved_quantity"
                class="form-control"
                min="0"
                step="0.01"
                placeholder="Igual à solicitada">
        </div>

        <div class="col-md-2">
            <label class="form-label">Valor unitário de referência</label>

            <input
                type="number"
                name="estimated_unit_price"
                class="form-control"
                min="0"
                step="0.01"
                placeholder="0,00">
        </div>

        <div class="col-md-2">
            <label class="form-label">Observação</label>

            <input
                type="text"
                name="notes"
                class="form-control"
                placeholder="Opcional">
        </div>

        <div class="col-12 d-flex justify-content-end">
            <button class="btn btn-primary">
                Adicionar item
            </button>
        </div>
    </form>
</div>

<div class="card card-body mb-4">
    <h2 class="h5 mb-3">Adicionar kit à demanda</h2>

    <form action="/demand_kit_add.php" method="post" class="row g-3">
        <input type="hidden" name="demand_list_id" value="<?= (int) $demand['id'] ?>">

        <div class="col-md-8">
            <label class="form-label">Kit</label>

            <select name="kit_id" class="form-select" required>
                <option value="">Selecione...</option>

                <?php foreach ($kits as $kit): ?>
                    <?php if ($kit['is_active']): ?>
                        <option value="<?= (int) $kit['id'] ?>">
                            <?= e($kit['name']) ?>
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label">Multiplicador</label>

            <input
                type="number"
                name="multiplier"
                class="form-control"
                min="0.01"
                step="0.01"
                value="1">
        </div>

        <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-primary w-100">
                Adicionar kit
            </button>
        </div>

        <div class="col-12">
            <div class="form-text">
                Exemplo: se o kit possui 1 notebook e 1 mouse, usando multiplicador 10 serão adicionados 10 notebooks e 10 mouses.
            </div>
        </div>
    </form>
</div>

<?php endif; ?>

<div class="card">
    <div class="card-header fw-semibold">
        Itens demandados
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Código</th>
                    <th>Item</th>
                    <th>Tipo de unidade</th>
                    <th>Qtd. solicitada</th>
                    <th>Qtd. aprovada</th>
                    <th>Valor unit. médio</th>
                    <th>Total médio estimado</th>
                    <th>Observação</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>

            <tbody>
                <?php if (!$items): ?>
                    <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                            Nenhum item adicionado.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($items as $item): ?>
                    <?php
                        $budgetItem = $budgetItemsByDemandItem[(int) $item['id']] ?? [];
                        $averageUnitPrice = $budgetItem['average_unit_price'] ?? null;
                        $averageTotal = $budgetItem['average_total'] ?? null;
                    ?>
                    <tr>
                        <td>
                            <span class="badge text-bg-dark">
                                <?= e($item['tracking_code']) ?>
                            </span>
                        </td>

                        <td>
                            <?= e($item['item_name']) ?>
                        </td>

                        <td>
                            <?= e($item['unit_type_abbreviation'] ?: ($item['unit_type_name'] ?? '-')) ?>

                            <?php if (format_package_content($item) !== '-'): ?>
                                <div class="small text-muted">
                                    <?= e(format_package_content($item)) ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= e((string) $item['quantity']) ?>
                        </td>

                        <td class="fw-semibold">
                            <?= e((string) ($item['approved_quantity'] ?? $item['quantity'])) ?>
                        </td>

                        <td>
                            <?php if ($averageUnitPrice !== null): ?>
                                R$ <?= number_format((float) $averageUnitPrice, 2, ',', '.') ?>
                            <?php else: ?>
                                <span class="text-muted">R$ <?= number_format((float) ($item['estimated_unit_price'] ?? 0), 2, ',', '.') ?></span>
                                <div class="small text-muted">referência manual</div>
                            <?php endif; ?>
                        </td>

                        <td class="fw-semibold">
                            <?php if ($averageTotal !== null): ?>
                                R$ <?= number_format((float) $averageTotal, 2, ',', '.') ?>
                            <?php else: ?>
                                <span class="text-muted">R$ <?= number_format((float) ($item['estimated_total'] ?? 0), 2, ',', '.') ?></span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= e($item['notes']) ?>
                        </td>

                        <td class="text-end">
                            <?php if (!$projectLocked): ?>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary"
                                data-bs-toggle="modal"
                                data-bs-target="#editDemandItemModal"
                                data-id="<?= (int) $item['id'] ?>"
                                data-demand-list-id="<?= (int) $demand['id'] ?>"
                                data-item-name="<?= e($item['item_name']) ?>"
                                data-tracking-code="<?= e($item['tracking_code']) ?>"
                                data-quantity="<?= e((string) $item['quantity']) ?>"
                                data-approved-quantity="<?= e((string) ($item['approved_quantity'] ?? $item['quantity'])) ?>"
                                data-estimated-unit-price="<?= e((string) ($item['estimated_unit_price'] ?? '')) ?>"
                                data-notes="<?= e($item['notes']) ?>">
                                Editar
                            </button>

                            <form
                                action="/demand_item_delete.php"
                                method="post"
                                class="d-inline"
                                onsubmit="return confirm('Remover este item da demanda?')">

                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                <input type="hidden" name="demand_list_id" value="<?= (int) $demand['id'] ?>">

                                <button class="btn btn-sm btn-outline-danger">
                                    Remover
                                </button>
                            </form>
                            <?php else: ?>
                                <span class="text-muted">Somente leitura</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>

        </table>
    </div>
</div>

<div
    class="modal fade"
    id="editDemandItemModal"
    tabindex="-1"
    aria-labelledby="editDemandItemModalLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-lg">
        <form action="/demand_item_update.php" method="post" class="modal-content">

            <input type="hidden" name="id" id="modalDemandItemId">
            <input type="hidden" name="demand_list_id" id="modalDemandListId">

            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="editDemandItemModalLabel">
                        Editar item da demanda
                    </h5>

                    <div class="small text-muted" id="modalItemDescription">
                        -
                    </div>
                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Fechar">
                </button>
            </div>

            <div class="modal-body">
                <div class="row g-3">

                    <div class="col-md-4">
                        <label class="form-label">Qtd. solicitada</label>

                        <input
                            type="number"
                            name="quantity"
                            id="modalQuantity"
                            class="form-control"
                            min="0.01"
                            step="0.01"
                            required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Qtd. aprovada</label>

                        <input
                            type="number"
                            name="approved_quantity"
                            id="modalApprovedQuantity"
                            class="form-control"
                            min="0"
                            step="0.01">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Valor unitário de referência</label>

                        <input
                            type="number"
                            name="estimated_unit_price"
                            id="modalEstimatedUnitPrice"
                            class="form-control"
                            min="0"
                            step="0.01">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Observação</label>

                        <textarea
                            name="notes"
                            id="modalNotes"
                            rows="3"
                            class="form-control"></textarea>
                    </div>

                    <div class="col-12">
                        <div class="alert alert-info mb-0">
                            Ao salvar, os totais da demanda e a consolidação financeira do projeto serão recalculados automaticamente.
                        </div>
                    </div>

                </div>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    data-bs-dismiss="modal">
                    Cancelar
                </button>

                <button class="btn btn-primary">
                    Salvar alterações
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('catalogSearch');
        const select = document.getElementById('catalogItemSelect');

        if (searchInput && select) {
            const options = Array.from(select.options);

            searchInput.addEventListener('input', function() {
                const term = this.value.toLowerCase().trim();

                options.forEach(function(option, index) {
                    if (index === 0) {
                        option.hidden = false;
                        return;
                    }

                    const searchable = option.dataset.search || option.textContent.toLowerCase();

                    option.hidden = term && !searchable.includes(term);
                });

                const selectedOption = select.options[select.selectedIndex];

                if (selectedOption && selectedOption.hidden) {
                    select.value = '';
                }
            });
        }

        const editModal = document.getElementById('editDemandItemModal');

        if (editModal) {
            editModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;

                const id = button.getAttribute('data-id');
                const demandListId = button.getAttribute('data-demand-list-id');
                const itemName = button.getAttribute('data-item-name');
                const trackingCode = button.getAttribute('data-tracking-code');
                const quantity = button.getAttribute('data-quantity');
                const approvedQuantity = button.getAttribute('data-approved-quantity');
                const estimatedUnitPrice = button.getAttribute('data-estimated-unit-price');
                const notes = button.getAttribute('data-notes');

                document.getElementById('modalDemandItemId').value = id;
                document.getElementById('modalDemandListId').value = demandListId;
                document.getElementById('modalQuantity').value = quantity;
                document.getElementById('modalApprovedQuantity').value = approvedQuantity;
                document.getElementById('modalEstimatedUnitPrice').value = estimatedUnitPrice;
                document.getElementById('modalNotes').value = notes || '';

                document.getElementById('modalItemDescription').textContent =
                    trackingCode + ' - ' + itemName;
            });
        }
    });
</script>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
