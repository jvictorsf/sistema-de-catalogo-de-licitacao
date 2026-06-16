<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$projectId = (int) ($_GET['project_id'] ?? $_POST['project_id'] ?? $_GET['id'] ?? 0);
$project = find_project($projectId);

if (!$project) {
    http_response_code(404);
    exit('Projeto não encontrado.');
}

$demands = get_project_demands($projectId);
$suppliers = get_suppliers(true);
$selectedSupplierId = (int) ($_POST['supplier_id'] ?? $_GET['supplier_id'] ?? 0);
$errors = [];
$postedPrices = is_array($_POST['prices'] ?? null) ? $_POST['prices'] : [];
$postedNotes = is_array($_POST['item_notes'] ?? null) ? $_POST['item_notes'] : [];
$preserveBlankPriceKeys = is_array($_POST['preserve_blank_prices'] ?? null) ? $_POST['preserve_blank_prices'] : [];
$removeAttachment = !empty($_POST['remove_attachment']);
$quoteDefaults = [
    'quote_number' => trim($_POST['quote_number'] ?? ''),
    'quote_date' => trim($_POST['quote_date'] ?? ''),
    'validity_date' => trim($_POST['validity_date'] ?? ''),
    'notes' => trim($_POST['notes'] ?? ''),
    'status' => trim($_POST['status'] ?? 'received'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($selectedSupplierId <= 0) {
        $errors[] = 'Selecione o fornecedor.';
    }

    if (!$demands) {
        $errors[] = 'Cadastre ao menos uma demanda antes de lançar orçamento geral.';
    }

    $attachmentPath = null;

    if (!$errors) {
        try {
            $attachmentPath = upload_supplier_quote_file($_FILES['attachment'] ?? []);
        } catch (RuntimeException $exception) {
            $errors[] = $exception->getMessage();
        }
    }

    if (!$errors) {
        $summary = save_project_supplier_quote([
            'project_id' => $projectId,
            'supplier_id' => $selectedSupplierId,
            'price_key' => 'procurement_item_id',
            'quote_number' => $quoteDefaults['quote_number'],
            'quote_date' => $quoteDefaults['quote_date'],
            'validity_date' => $quoteDefaults['validity_date'],
            'attachment_path' => $attachmentPath,
            'remove_attachment' => $removeAttachment,
            'notes' => $quoteDefaults['notes'],
            'status' => $quoteDefaults['status'],
        ], $postedPrices, $postedNotes, $preserveBlankPriceKeys);

        $message = sprintf(
            'Orçamento geral salvo em %d demanda(s), com %d item(ns) precificado(s).',
            (int) $summary['quotes'],
            (int) $summary['priced_items']
        );

        redirect('/project_show.php?id=' . $projectId . '&quote_success=' . rawurlencode($message));
    }
}

$statusOptions = [
    'received' => 'Recebido',
    'draft' => 'Em coleta',
    'discarded' => 'Desconsiderado',
];

$projectItems = [];
$hasDemandItems = false;
$quoteDefaultsLoaded = false;
$selectedSupplier = null;
$existingQuoteCount = 0;
$existingQuoteDemandNames = [];
$existingAttachments = [];

foreach ($suppliers as $supplierOption) {
    if ((int) $supplierOption['id'] === $selectedSupplierId) {
        $selectedSupplier = $supplierOption;
        break;
    }
}

foreach ($demands as $demand) {
    $demandId = (int) $demand['id'];
    $quote = $selectedSupplierId > 0
        ? find_demand_supplier_quote_by_supplier($demandId, $selectedSupplierId)
        : null;
    $quoteItems = $quote ? get_demand_supplier_quote_items((int) $quote['id']) : [];
    $items = get_demand_items($demandId);

    if ($quote) {
        $existingQuoteCount++;
        $existingQuoteDemandNames[] = (string) $demand['name'];

        $attachmentPath = trim((string) ($quote['attachment_path'] ?? ''));

        if ($attachmentPath !== '') {
            $existingAttachments[$attachmentPath]['path'] = $attachmentPath;
            $existingAttachments[$attachmentPath]['demands'][] = (string) $demand['name'];
        }
    }

    if ($quote && !$quoteDefaultsLoaded && $_SERVER['REQUEST_METHOD'] !== 'POST' && $quoteDefaults['quote_number'] === '') {
        $quoteDefaults = [
            'quote_number' => (string) ($quote['quote_number'] ?? ''),
            'quote_date' => (string) ($quote['quote_date'] ?? ''),
            'validity_date' => (string) ($quote['validity_date'] ?? ''),
            'notes' => (string) ($quote['notes'] ?? ''),
            'status' => (string) ($quote['status'] ?? 'received'),
        ];
        $quoteDefaultsLoaded = true;
    }

    foreach ($items as $item) {
        $hasDemandItems = true;
        $procurementItemId = (int) $item['procurement_item_id'];
        $demandItemId = (int) $item['id'];
        $storedItem = $quoteItems[$demandItemId] ?? [];

        if (!isset($projectItems[$procurementItemId])) {
            $projectItems[$procurementItemId] = array_merge($item, [
                'total_reference_quantity' => 0.0,
                'demand_ids' => [],
                'demand_names' => [],
                'stored_price_values' => [],
                'stored_note_values' => [],
            ]);
        }

        $projectItems[$procurementItemId]['total_reference_quantity'] += (float) ($item['approved_quantity'] ?? $item['quantity'] ?? 0);
        $projectItems[$procurementItemId]['demand_ids'][$demandId] = true;
        $projectItems[$procurementItemId]['demand_names'][$demandId] = (string) $demand['name'];

        if (($storedItem['unit_price'] ?? null) !== null && $storedItem['unit_price'] !== '') {
            $priceValue = number_format((float) $storedItem['unit_price'], 2, '.', '');
            $projectItems[$procurementItemId]['stored_price_values'][$priceValue] = $priceValue;
        }

        $noteValue = trim((string) ($storedItem['notes'] ?? ''));

        if ($noteValue !== '') {
            $projectItems[$procurementItemId]['stored_note_values'][$noteValue] = $noteValue;
        }
    }
}

$existingAttachments = array_values($existingAttachments);
$quoteTotal = 0.0;
$quotePricedItemsCount = 0;

foreach ($projectItems as $procurementItemId => $item) {
    $storedPrices = array_values($item['stored_price_values']);
    $storedNotes = array_values($item['stored_note_values']);

    $projectItems[$procurementItemId]['price_value'] = array_key_exists((string) $procurementItemId, $postedPrices)
        ? $postedPrices[(string) $procurementItemId]
        : (count($storedPrices) === 1 ? $storedPrices[0] : '');
    $projectItems[$procurementItemId]['note_value'] = array_key_exists((string) $procurementItemId, $postedNotes)
        ? $postedNotes[(string) $procurementItemId]
        : (count($storedNotes) === 1 ? $storedNotes[0] : '');
    $projectItems[$procurementItemId]['has_mixed_prices'] = count($storedPrices) > 1;
    $projectItems[$procurementItemId]['has_mixed_notes'] = count($storedNotes) > 1;
    $projectItems[$procurementItemId]['demand_count'] = count($item['demand_ids']);
    $projectItems[$procurementItemId]['demand_names'] = array_values($item['demand_names']);

    $unitPrice = normalize_money_value($projectItems[$procurementItemId]['price_value']);

    if ($unitPrice !== null) {
        $quotePricedItemsCount++;
        $quoteTotal += $unitPrice * (float) $projectItems[$procurementItemId]['total_reference_quantity'];
    }
}

$quoteTotal = round($quoteTotal, 2);

uasort($projectItems, static function (array $left, array $right): int {
    return strnatcasecmp(
        (string) ($left['category_name'] ?? '') . ' ' . (string) $left['item_name'],
        (string) ($right['category_name'] ?? '') . ' ' . (string) $right['item_name']
    );
});

require __DIR__ . '/../app/views/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-start mb-4">
    <div class="page-title">
        <h1 class="h3 mb-1">Orçamento geral do projeto</h1>
        <p class="text-muted mb-0">
            <?= e($project['name']) ?>
        </p>
    </div>

    <div class="page-actions d-flex gap-2 flex-wrap justify-content-end">
        <a href="/project_show.php?id=<?= (int) $projectId ?>" class="btn btn-outline-secondary">
            Voltar
        </a>
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!$suppliers): ?>
    <div class="alert alert-warning">
        Cadastre ao menos um fornecedor ativo antes de lançar orçamentos.
        <a href="/supplier_form.php" class="alert-link">Cadastrar fornecedor</a>.
    </div>
<?php endif; ?>

<?php if (!$hasDemandItems): ?>
    <div class="alert alert-warning">
        Nenhum item foi encontrado nas demandas deste projeto.
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card card-body shadow-sm project-quote-form">
    <input type="hidden" name="project_id" value="<?= (int) $projectId ?>">

    <div class="row g-3">
        <div class="col-lg-5">
            <label class="form-label">Fornecedor</label>
            <select name="supplier_id" id="supplierSelect" class="form-select" required>
                <option value="">Selecione...</option>
                <?php foreach ($suppliers as $supplier): ?>
                    <option value="<?= (int) $supplier['id'] ?>" <?= $selectedSupplierId === (int) $supplier['id'] ? 'selected' : '' ?>>
                        <?= e($supplier['name']) ?><?= $supplier['document'] ? ' - ' . e(format_brazil_document($supplier['document'])) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">
                Ao salvar, cada preço informado será aplicado em todas as demandas que possuem o produto.
            </div>
        </div>

        <?php if ($selectedSupplierId > 0 && $selectedSupplier): ?>
            <div class="col-12">
                <?php if ($existingQuoteCount > 0): ?>
                    <?php
                        $visibleDemandNames = array_slice($existingQuoteDemandNames, 0, 4);
                        $remainingDemandNames = max(0, count($existingQuoteDemandNames) - count($visibleDemandNames));
                    ?>
                    <div class="alert alert-info mb-0">
                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                            <div>
                                <strong><?= e($selectedSupplier['name']) ?></strong>
                                já possui orçamento lançado em <?= (int) $existingQuoteCount ?> demanda(s) deste projeto.
                                <div class="small mt-1">
                                    <?= e(implode(', ', $visibleDemandNames)) ?><?= $remainingDemandNames > 0 ? ' +' . $remainingDemandNames : '' ?>
                                </div>
                                <div class="small mt-1">
                                    Os dados foram carregados para edição. Ao salvar, os valores informados serão atualizados para este fornecedor.
                                </div>
                            </div>

                            <?php if ($existingAttachments): ?>
                                <div class="d-flex flex-column align-items-start align-items-lg-end gap-2">
                                    <?php foreach ($existingAttachments as $attachmentIndex => $attachment): ?>
                                        <a
                                            href="<?= e($attachment['path']) ?>"
                                            target="_blank"
                                            class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-paperclip"></i>
                                            Visualizar anexo <?= count($existingAttachments) > 1 ? $attachmentIndex + 1 : 'atual' ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="small text-muted">
                                    Este orçamento ainda não possui anexo digital.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-secondary mb-0">
                        <strong><?= e($selectedSupplier['name']) ?></strong>
                        ainda não possui orçamento lançado neste projeto.
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="col-md-3 col-lg-2">
            <label class="form-label">Nº do orçamento</label>
            <input type="text" name="quote_number" class="form-control" value="<?= e($quoteDefaults['quote_number']) ?>">
        </div>

        <div class="col-md-3 col-lg-2">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <?php foreach ($statusOptions as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $quoteDefaults['status'] === $value ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-3 col-lg-1">
            <label class="form-label">Data</label>
            <input type="date" name="quote_date" class="form-control" value="<?= e($quoteDefaults['quote_date']) ?>">
        </div>

        <div class="col-md-3 col-lg-2">
            <label class="form-label">Validade</label>
            <input type="date" name="validity_date" class="form-control" value="<?= e($quoteDefaults['validity_date']) ?>">
        </div>

        <div class="col-lg-5">
            <label class="form-label"><?= $existingAttachments ? 'Trocar anexo do orçamento' : 'Anexo do orçamento' ?></label>
            <input type="file" name="attachment" class="form-control" accept="application/pdf,.pdf,.doc,.docx,image/jpeg,image/png,image/webp">
            <div class="form-text">
                <?php if ($existingAttachments): ?>
                    Se enviado, o novo arquivo substituirá o anexo atual nas demandas deste fornecedor.
                <?php else: ?>
                    Se enviado, o mesmo anexo será associado às demandas do projeto.
                <?php endif; ?>
            </div>

            <?php if ($existingAttachments): ?>
                <div class="form-check mt-2">
                    <input
                        type="checkbox"
                        name="remove_attachment"
                        value="1"
                        class="form-check-input"
                        id="removeAttachment">
                    <label class="form-check-label" for="removeAttachment">
                        Remover anexo atual ao salvar
                    </label>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-7">
            <label class="form-label">Observações gerais</label>
            <textarea name="notes" rows="2" class="form-control"><?= e($quoteDefaults['notes']) ?></textarea>
        </div>
    </div>

    <hr class="my-4">

    <?php if ($projectItems): ?>
        <div class="d-flex justify-content-between align-items-start gap-3 mb-3 project-quote-summary">
            <div>
                <h2 class="h5 mb-1">Itens do projeto</h2>
                <div class="text-muted small">
                    <?= count($projectItems) ?> produto(s) consolidado(s) de <?= count($demands) ?> demanda(s).
                </div>
            </div>

            <div class="text-lg-end">
                <div class="text-muted small">Valor total do orçamento</div>
                <div
                    class="h4 mb-0"
                    id="quoteTotalValue"
                    data-initial-value="<?= e(number_format($quoteTotal, 2, '.', '')) ?>">
                    R$ <?= number_format($quoteTotal, 2, ',', '.') ?>
                </div>
                <div class="text-muted small">
                    <span id="quoteTotalPricedCount"><?= (int) $quotePricedItemsCount ?></span>
                    item(ns) com preço
                </div>
            </div>
        </div>

        <div class="table-responsive mb-4">
            <table class="table table-hover align-middle mb-0 project-quote-table">
                <thead class="table-light">
                    <tr>
                        <th>Código</th>
                        <th>Item</th>
                        <th>Qtd. total</th>
                        <th>Demandas</th>
                        <th>Valor unitário</th>
                        <th>Observação</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($projectItems as $item): ?>
                        <?php
                        $procurementItemId = (int) $item['procurement_item_id'];
                        $demandNames = array_slice($item['demand_names'], 0, 3);
                        $remainingDemandCount = max(0, (int) $item['demand_count'] - count($demandNames));
                        ?>
                        <tr>
                            <td><span class="badge text-bg-dark"><?= e($item['tracking_code']) ?></span></td>
                            <td>
                                <strong><?= e($item['item_name']) ?></strong>
                                <div class="small text-muted">
                                    <?= e($item['unit_type_abbreviation'] ?: ($item['unit_type_name'] ?? '-')) ?>
                                    <?php if (format_package_content($item) !== '-'): ?>
                                        · Conteúdo: <?= e(format_package_content($item)) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?= e(format_decimal_quantity($item['total_reference_quantity'])) ?></td>
                            <td>
                                <span class="badge text-bg-light border"><?= (int) $item['demand_count'] ?> demanda(s)</span>
                                <div class="small text-muted project-quote-demand-list">
                                    <?= e(implode(', ', $demandNames)) ?><?= $remainingDemandCount > 0 ? ' +' . $remainingDemandCount : '' ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($item['has_mixed_prices']): ?>
                                    <input type="hidden" name="preserve_blank_prices[<?= $procurementItemId ?>]" value="1">
                                <?php endif; ?>
                                <input
                                    type="number"
                                    name="prices[<?= $procurementItemId ?>]"
                                    class="form-control form-control-sm"
                                    min="0"
                                    step="0.01"
                                    data-quote-price-input
                                    data-quantity="<?= e((string) (float) $item['total_reference_quantity']) ?>"
                                    value="<?= e($item['price_value'] !== '' && $item['price_value'] !== null ? number_format((float) $item['price_value'], 2, '.', '') : '') ?>">
                                <?php if ($item['has_mixed_prices']): ?>
                                    <div class="form-text">Valores diferentes cadastrados; preencha para unificar.</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <input
                                    type="text"
                                    name="item_notes[<?= $procurementItemId ?>]"
                                    class="form-control form-control-sm"
                                    value="<?= e($item['note_value']) ?>"
                                    placeholder="Opcional">
                                <?php if ($item['has_mixed_notes']): ?>
                                    <div class="form-text">Observações diferentes cadastradas; preencha para unificar.</div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-end gap-2 flex-wrap">
        <a href="/project_show.php?id=<?= (int) $projectId ?>" class="btn btn-outline-secondary">
            Cancelar
        </a>
        <button class="btn btn-primary" <?= !$suppliers || !$hasDemandItems ? 'disabled' : '' ?>>
            Salvar orçamento geral
        </button>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const supplierSelect = document.getElementById('supplierSelect');
        const quoteTotalValue = document.getElementById('quoteTotalValue');
        const quoteTotalPricedCount = document.getElementById('quoteTotalPricedCount');
        const priceInputs = document.querySelectorAll('[data-quote-price-input]');

        function parseMoney(value) {
            const normalized = String(value || '').trim();

            if (!normalized) {
                return null;
            }

            const decimal = normalized.includes(',')
                ? normalized.replace(/\./g, '').replace(',', '.')
                : normalized;
            const number = Number(decimal);

            return Number.isFinite(number) ? number : null;
        }

        function formatCurrency(value) {
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL',
            }).format(value);
        }

        function updateQuoteTotal() {
            if (!quoteTotalValue || !quoteTotalPricedCount) {
                return;
            }

            let total = 0;
            let pricedCount = 0;

            priceInputs.forEach(function(input) {
                const unitPrice = parseMoney(input.value);
                const quantity = Number(input.dataset.quantity || 0);

                if (unitPrice === null || quantity <= 0) {
                    return;
                }

                total += unitPrice * quantity;
                pricedCount++;
            });

            quoteTotalValue.textContent = formatCurrency(total);
            quoteTotalPricedCount.textContent = String(pricedCount);
        }

        priceInputs.forEach(function(input) {
            input.addEventListener('input', updateQuoteTotal);
        });

        updateQuoteTotal();

        if (supplierSelect) {
            supplierSelect.addEventListener('change', function() {
                if (!supplierSelect.value || document.querySelector('.alert-danger')) {
                    return;
                }

                const url = new URL(window.location.href);
                url.searchParams.set('project_id', '<?= (int) $projectId ?>');
                url.searchParams.set('supplier_id', supplierSelect.value);
                window.location.href = url.toString();
            });
        }
    });
</script>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
