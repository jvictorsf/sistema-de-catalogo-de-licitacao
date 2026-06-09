<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$isEditing = $id > 0;
$quote = $isEditing ? find_demand_supplier_quote($id) : null;

if ($isEditing && !$quote) {
    http_response_code(404);
    exit('Orçamento não encontrado.');
}

$demandId = (int) ($isEditing
    ? ($quote['demand_list_id'] ?? 0)
    : ($_GET['demand_id'] ?? $_POST['demand_id'] ?? 0));
$demand = find_demand_list($demandId);

if (!$demand) {
    http_response_code(404);
    exit('Demanda não encontrada.');
}

$project = find_project((int) $demand['project_id']);
$items = get_demand_items($demandId);
$suppliers = get_suppliers(!$isEditing);
$quoteItems = $isEditing ? get_demand_supplier_quote_items($id) : [];
$reusableQuoteItems = get_reusable_project_quote_items_for_demand($demandId);
$errors = [];
$postedPrices = $_POST['prices'] ?? null;
$postedNotes = $_POST['item_notes'] ?? [];
$postedSourceQuoteItemIds = $_POST['source_quote_item_ids'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attachmentPath = $quote['attachment_path'] ?? null;

    try {
        $uploadedPath = upload_supplier_quote_file($_FILES['attachment'] ?? []);

        if ($uploadedPath) {
            $attachmentPath = $uploadedPath;
        }
    } catch (RuntimeException $exception) {
        $errors[] = $exception->getMessage();
    }

    if (!empty($_POST['remove_attachment'])) {
        $attachmentPath = null;
    }

    $data = [
        'demand_list_id' => $demandId,
        'supplier_id' => (int) ($_POST['supplier_id'] ?? 0),
        'quote_number' => trim($_POST['quote_number'] ?? ''),
        'quote_date' => trim($_POST['quote_date'] ?? ''),
        'validity_date' => trim($_POST['validity_date'] ?? ''),
        'attachment_path' => $attachmentPath,
        'notes' => trim($_POST['notes'] ?? ''),
        'status' => trim($_POST['status'] ?? 'received'),
    ];

    if (!$data['supplier_id']) {
        $errors[] = 'Selecione o fornecedor.';
    }

    $existingQuote = $data['supplier_id']
        ? find_demand_supplier_quote_by_supplier($demandId, (int) $data['supplier_id'])
        : null;

    if ($existingQuote && (!$isEditing || (int) $existingQuote['id'] !== $id)) {
        $errors[] = 'Este fornecedor já possui orçamento vinculado a esta demanda.';
    }

    if (!$errors) {
        if ($isEditing) {
            update_demand_supplier_quote($id, $data);
            $quoteId = $id;
        } else {
            $quoteId = create_demand_supplier_quote($data);
        }

        save_demand_supplier_quote_items(
            $quoteId,
            is_array($postedPrices) ? $postedPrices : [],
            is_array($postedNotes) ? $postedNotes : [],
            is_array($postedSourceQuoteItemIds) ? $postedSourceQuoteItemIds : []
        );

        redirect('/demand_show.php?id=' . $demandId);
    }

    $quote = array_merge($quote ?? [], $data);
}

$statusOptions = [
    'received' => 'Recebido',
    'draft' => 'Em coleta',
    'discarded' => 'Desconsiderado',
];

require __DIR__ . '/../app/views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><?= $isEditing ? 'Editar orçamento' : 'Novo orçamento' ?></h1>
        <p class="text-muted mb-0">
            Demanda: <?= e($demand['name']) ?><?= $project ? ' - Projeto: ' . e($project['name']) : '' ?>
        </p>
    </div>

    <a href="/demand_show.php?id=<?= (int) $demandId ?>" class="btn btn-outline-secondary">
        Voltar
    </a>
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

<?php if (!$suppliers && !$isEditing): ?>
    <div class="alert alert-warning">
        Cadastre ao menos um fornecedor antes de lançar orçamentos.
        <a href="/supplier_form.php" class="alert-link">Cadastrar fornecedor</a>.
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card card-body shadow-sm">
    <?php if ($isEditing): ?>
        <input type="hidden" name="id" value="<?= (int) $id ?>">
    <?php endif; ?>

    <input type="hidden" name="demand_id" value="<?= (int) $demandId ?>">

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Fornecedor</label>
            <select name="supplier_id" id="supplierSelect" class="form-select" required>
                <option value="">Selecione...</option>
                <?php foreach ($suppliers as $supplier): ?>
                    <option value="<?= (int) $supplier['id'] ?>" <?= (int) ($quote['supplier_id'] ?? 0) === (int) $supplier['id'] ? 'selected' : '' ?>>
                        <?= e($supplier['name']) ?><?= $supplier['document'] ? ' - ' . e(format_brazil_document($supplier['document'])) : '' ?><?= $supplier['is_active'] ? '' : ' (inativo)' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label">Nº do orçamento</label>
            <input type="text" name="quote_number" class="form-control" value="<?= e($quote['quote_number'] ?? '') ?>">
        </div>

        <div class="col-md-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <?php foreach ($statusOptions as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($quote['status'] ?? 'received') === $value ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label">Data do orçamento</label>
            <input type="date" name="quote_date" class="form-control" value="<?= e($quote['quote_date'] ?? '') ?>">
        </div>

        <div class="col-md-3">
            <label class="form-label">Validade</label>
            <input type="date" name="validity_date" class="form-control" value="<?= e($quote['validity_date'] ?? '') ?>">
        </div>

        <div class="col-md-6">
            <label class="form-label">Anexo do orçamento real</label>
            <input type="file" name="attachment" class="form-control" accept="application/pdf,.pdf,.doc,.docx,image/jpeg,image/png,image/webp">
            <div class="form-text">PDF, DOC, DOCX, JPG, PNG ou WEBP até 10 MB.</div>

            <?php if (!empty($quote['attachment_path'])): ?>
                <div class="form-check mt-2">
                    <input type="checkbox" name="remove_attachment" value="1" class="form-check-input" id="removeAttachment">
                    <label class="form-check-label" for="removeAttachment">
                        Remover anexo atual
                    </label>
                </div>
                <a href="<?= e($quote['attachment_path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary mt-2">
                    <i class="bi bi-paperclip"></i>Ver anexo atual
                </a>
            <?php endif; ?>
        </div>

        <div class="col-12">
            <label class="form-label">Observações do orçamento</label>
            <textarea name="notes" rows="3" class="form-control"><?= e($quote['notes'] ?? '') ?></textarea>
        </div>
    </div>

    <hr class="my-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h5 mb-1">Valores por item</h2>
            <p class="text-muted mb-0">Informe o valor unitário apresentado pelo fornecedor.</p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Código</th>
                    <th>Item</th>
                    <th>Qtd. referência</th>
                    <th style="min-width: 160px;">Valor unitário</th>
                    <th style="min-width: 220px;">Observação</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$items): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            Adicione itens à demanda antes de lançar valores de orçamento.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($items as $item): ?>
                    <?php
                        $itemId = (int) $item['id'];
                        $storedItem = $quoteItems[$itemId] ?? [];
                        $priceValue = is_array($postedPrices)
                            ? ($postedPrices[$itemId] ?? '')
                            : ($storedItem['unit_price'] ?? '');
                        $noteValue = is_array($postedNotes)
                            ? ($postedNotes[$itemId] ?? '')
                            : ($storedItem['notes'] ?? '');
                        $sourceQuoteItemId = is_array($postedSourceQuoteItemIds)
                            ? (int) ($postedSourceQuoteItemIds[$itemId] ?? 0)
                            : (int) ($storedItem['reused_from_quote_item_id'] ?? 0);
                        $reusableItems = $reusableQuoteItems[$itemId] ?? [];
                        $currentOrigin = $sourceQuoteItemId && !empty($storedItem['reused_supplier_name'])
                            ? trim($storedItem['reused_supplier_name'] . ' - ' . ($storedItem['reused_demand_name'] ?? ''))
                            : '';
                    ?>
                    <tr>
                        <td><span class="badge text-bg-dark"><?= e($item['tracking_code']) ?></span></td>
                        <td>
                            <?= e($item['item_name']) ?>

                            <?php if ($reusableItems): ?>
                                <div class="small text-muted mt-2 mb-1">
                                    Precos ja cotados neste projeto:
                                </div>

                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($reusableItems as $reusableItem): ?>
                                        <?php
                                            $reusableLabel = sprintf(
                                                '%s - R$ %s',
                                                $reusableItem['supplier_name'],
                                                number_format((float) $reusableItem['unit_price'], 2, ',', '.')
                                            );
                                            $reusableDescription = trim(($reusableItem['source_demand_name'] ?? '') . ' ' . ($reusableItem['quote_number'] ?? ''));
                                        ?>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-success reuse-quote-price"
                                            data-target-id="<?= $itemId ?>"
                                            data-source-id="<?= (int) $reusableItem['source_quote_item_id'] ?>"
                                            data-supplier-id="<?= (int) $reusableItem['supplier_id'] ?>"
                                            data-price="<?= e(number_format((float) $reusableItem['unit_price'], 2, '.', '')) ?>"
                                            data-label="<?= e($reusableLabel) ?>"
                                            data-description="<?= e($reusableDescription) ?>">
                                            <i class="bi bi-arrow-repeat"></i><?= e($reusableLabel) ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?= e((string) ($item['approved_quantity'] ?? $item['quantity'])) ?></td>
                        <td>
                            <input
                                type="hidden"
                                name="source_quote_item_ids[<?= $itemId ?>]"
                                id="sourceQuoteItem<?= $itemId ?>"
                                value="<?= $sourceQuoteItemId > 0 ? $sourceQuoteItemId : '' ?>">

                            <input
                                type="number"
                                name="prices[<?= $itemId ?>]"
                                id="priceInput<?= $itemId ?>"
                                class="form-control"
                                min="0"
                                step="0.01"
                                value="<?= e($priceValue !== '' && $priceValue !== null ? number_format((float) $priceValue, 2, '.', '') : '') ?>">

                            <div class="form-text" id="sourceInfo<?= $itemId ?>">
                                <?= $currentOrigin ? 'Origem: ' . e($currentOrigin) : '' ?>
                            </div>
                        </td>
                        <td>
                            <input
                                type="text"
                                name="item_notes[<?= $itemId ?>]"
                                id="noteInput<?= $itemId ?>"
                                class="form-control"
                                value="<?= e($noteValue) ?>"
                                placeholder="Opcional">
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-4">
        <a href="/demand_show.php?id=<?= (int) $demandId ?>" class="btn btn-outline-secondary">
            Cancelar
        </a>
        <button class="btn btn-primary">
            Salvar orçamento
        </button>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const supplierSelect = document.getElementById('supplierSelect');
        const reuseButtons = document.querySelectorAll('.reuse-quote-price');

        function filterReusablePrices() {
            const selectedSupplierId = supplierSelect ? supplierSelect.value : '';

            reuseButtons.forEach(function(button) {
                button.hidden = selectedSupplierId && button.dataset.supplierId !== selectedSupplierId;
            });
        }

        reuseButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const targetId = button.dataset.targetId;
                const supplierId = button.dataset.supplierId || '';
                const price = button.dataset.price || '';
                const label = button.dataset.label || 'preco selecionado';
                const description = button.dataset.description || '';

                if (
                    supplierSelect &&
                    supplierSelect.value &&
                    supplierSelect.value !== supplierId &&
                    !confirm('Este preco pertence a outro fornecedor. Trocar o fornecedor deste orcamento?')
                ) {
                    return;
                }

                if (supplierSelect && supplierId) {
                    supplierSelect.value = supplierId;
                    filterReusablePrices();
                }

                const priceInput = document.getElementById('priceInput' + targetId);
                const sourceInput = document.getElementById('sourceQuoteItem' + targetId);
                const sourceInfo = document.getElementById('sourceInfo' + targetId);

                if (priceInput) {
                    priceInput.value = price;
                }

                if (sourceInput) {
                    sourceInput.value = button.dataset.sourceId || '';
                }

                if (sourceInfo) {
                    sourceInfo.textContent = 'Origem: ' + label + (description ? ' (' + description + ')' : '');
                }
            });
        });

        if (supplierSelect) {
            supplierSelect.addEventListener('change', function() {
                document.querySelectorAll('[id^="sourceQuoteItem"]').forEach(function(input) {
                    input.value = '';
                });

                document.querySelectorAll('[id^="sourceInfo"]').forEach(function(info) {
                    info.textContent = '';
                });

                filterReusablePrices();
            });
        }

        document.querySelectorAll('[id^="priceInput"]').forEach(function(input) {
            input.addEventListener('input', function() {
                const targetId = input.id.replace('priceInput', '');
                const sourceInput = document.getElementById('sourceQuoteItem' + targetId);
                const sourceInfo = document.getElementById('sourceInfo' + targetId);

                if (sourceInput) {
                    sourceInput.value = '';
                }

                if (sourceInfo) {
                    sourceInfo.textContent = '';
                }
            });
        });

        filterReusablePrices();
    });
</script>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
