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

$projectLocked = project_is_locked($project);
$demands = get_project_demands($projectId);
$suppliers = get_suppliers(true);
$selectedSupplierId = (int) ($_POST['supplier_id'] ?? $_GET['supplier_id'] ?? 0);
$globalPriceKey = trim((string) ($_GET['global_price_key'] ?? ''));
$globalPriceMonths = max(0, (int) ($_GET['months'] ?? 0));
$globalPriceCandidate = null;
$errors = [];
$postedPrices = is_array($_POST['prices'] ?? null) ? $_POST['prices'] : [];
$postedNotes = is_array($_POST['item_notes'] ?? null) ? $_POST['item_notes'] : [];
$postedSourceQuoteItemIds = is_array($_POST['source_quote_item_ids'] ?? null) ? $_POST['source_quote_item_ids'] : [];
$postedQuoteDocuments = is_array($_POST['quote_documents'] ?? null) ? $_POST['quote_documents'] : [];
$preserveBlankPriceKeys = is_array($_POST['preserve_blank_prices'] ?? null) ? $_POST['preserve_blank_prices'] : [];
$removeAttachment = !empty($_POST['remove_attachment']);
$quoteDefaults = [
    'quote_number' => trim($_POST['quote_number'] ?? ''),
    'quote_date' => trim($_POST['quote_date'] ?? ''),
    'validity_date' => trim($_POST['validity_date'] ?? ''),
    'quoted_by' => trim($_POST['quoted_by'] ?? ''),
    'collected_by' => trim($_POST['collected_by'] ?? ''),
    'notes' => trim($_POST['notes'] ?? ''),
    'status' => trim($_POST['status'] ?? 'received'),
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $globalPriceKey !== '') {
    $globalPriceCandidate = find_project_global_price_bank_candidate($projectId, $globalPriceKey, $globalPriceMonths);

    if ($globalPriceCandidate) {
        $selectedSupplierId = (int) $globalPriceCandidate['supplier_id'];
        $postedPrices = $globalPriceCandidate['prices'] ?? [];
        $postedNotes = $globalPriceCandidate['item_notes'] ?? [];
        $postedSourceQuoteItemIds = $globalPriceCandidate['source_quote_item_ids'] ?? [];
        $quoteDefaults = [
            'quote_number' => (string) ($globalPriceCandidate['quote_number'] ?? ''),
            'quote_date' => (string) ($globalPriceCandidate['quote_date'] ?? ''),
            'validity_date' => (string) ($globalPriceCandidate['validity_date'] ?? ''),
            'quoted_by' => (string) ($globalPriceCandidate['quoted_by'] ?? ''),
            'collected_by' => (string) ($globalPriceCandidate['collected_by'] ?? ''),
            'notes' => (string) ($globalPriceCandidate['notes'] ?? ''),
            'status' => (string) ($globalPriceCandidate['status'] ?? 'received'),
        ];
    } else {
        $errors[] = 'Orcamento geral historico nao encontrado ou sem itens compativeis para este projeto.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($projectLocked) {
        $errors[] = project_locked_edit_message($project);
    }

    if ($selectedSupplierId <= 0) {
        $errors[] = 'Selecione o fornecedor.';
    }

    if (!$demands) {
        $errors[] = 'Cadastre ao menos uma demanda antes de lançar orçamento geral.';
    }

    $quoteDocuments = $postedQuoteDocuments;

    if ($removeAttachment) {
        foreach ($quoteDocuments as $documentIndex => $document) {
            if (is_array($document)) {
                unset($quoteDocuments[$documentIndex]['attachment_path']);
            }
        }
    }

    if (!$errors) {
        try {
            foreach (normalize_uploaded_file_list($_FILES['quote_document_files'] ?? []) as $documentIndex => $file) {
                $documentPath = upload_supplier_quote_file($file);

                if ($documentPath === null) {
                    continue;
                }

                if (!isset($quoteDocuments[$documentIndex]) || !is_array($quoteDocuments[$documentIndex])) {
                    $quoteDocuments[$documentIndex] = [];
                }

                $quoteDocuments[$documentIndex]['attachment_path'] = $documentPath;
            }
        } catch (RuntimeException $exception) {
            $errors[] = $exception->getMessage();
        }
    }

    foreach ($quoteDocuments as $document) {
        if (!is_array($document)) {
            continue;
        }

        $submittedQuoteNumber = trim((string) ($document['quote_number'] ?? ''));
        $submittedQuoteDate = trim((string) ($document['quote_date'] ?? ''));
        $submittedValidityDate = trim((string) ($document['validity_date'] ?? ''));

        if ($submittedQuoteNumber === '' && $submittedQuoteDate === '' && $submittedValidityDate === '') {
            continue;
        }

        $quoteDefaults['quote_number'] = $submittedQuoteNumber;
        $quoteDefaults['quote_date'] = $submittedQuoteDate;
        $quoteDefaults['validity_date'] = $submittedValidityDate;
        break;
    }

    $quoteDocuments = normalize_supplier_quote_documents($quoteDocuments);

    if ($quoteDocuments) {
        if (($quoteDocuments[0]['quote_number'] ?? null) === null && $quoteDefaults['quote_number'] !== '') {
            $quoteDocuments[0]['quote_number'] = $quoteDefaults['quote_number'];
        }

        if (($quoteDocuments[0]['quote_date'] ?? null) === null && $quoteDefaults['quote_date'] !== '') {
            $quoteDocuments[0]['quote_date'] = $quoteDefaults['quote_date'];
        }

        if (($quoteDocuments[0]['validity_date'] ?? null) === null && $quoteDefaults['validity_date'] !== '') {
            $quoteDocuments[0]['validity_date'] = $quoteDefaults['validity_date'];
        }

        $primaryQuoteDocument = $quoteDocuments[0];
        $quoteDefaults['quote_number'] = (string) ($primaryQuoteDocument['quote_number'] ?? '');
        $quoteDefaults['quote_date'] = (string) ($primaryQuoteDocument['quote_date'] ?? '');
        $quoteDefaults['validity_date'] = (string) ($primaryQuoteDocument['validity_date'] ?? '');
    }

    if (!$errors) {
        try {
        $summary = save_project_supplier_quote([
            'project_id' => $projectId,
            'supplier_id' => $selectedSupplierId,
            'price_key' => 'procurement_item_id',
            'quote_number' => $quoteDefaults['quote_number'],
            'quote_date' => $quoteDefaults['quote_date'],
            'validity_date' => $quoteDefaults['validity_date'],
            'quoted_by' => $quoteDefaults['quoted_by'],
            'collected_by' => $quoteDefaults['collected_by'],
            'attachment_path' => null,
            'quote_documents' => $quoteDocuments,
            'remove_attachment' => $removeAttachment,
            'notes' => $quoteDefaults['notes'],
            'status' => $quoteDefaults['status'],
        ], $postedPrices, $postedNotes, $preserveBlankPriceKeys, $postedSourceQuoteItemIds);

        $message = sprintf(
            'Orçamento geral salvo em %d demanda(s), com %d item(ns) precificado(s).',
            (int) $summary['quotes'],
            (int) $summary['priced_items']
        );

        redirect('/project_show.php?id=' . $projectId . '&quote_success=' . rawurlencode($message));
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
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

$selectedSupplierLabel = '';

if ($selectedSupplier) {
    $selectedSupplierLabel = trim((string) $selectedSupplier['name'] . (!empty($selectedSupplier['document']) ? ' - ' . format_brazil_document((string) $selectedSupplier['document']) : ''));
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

        $quoteAttachments = is_array($quote['attachments'] ?? null) ? $quote['attachments'] : [];

        if (!$quoteAttachments && trim((string) ($quote['attachment_path'] ?? '')) !== '') {
            $quoteAttachments[] = [
                'quote_number' => $quote['quote_number'] ?? '',
                'quote_date' => $quote['quote_date'] ?? '',
                'validity_date' => $quote['validity_date'] ?? '',
                'attachment_path' => $quote['attachment_path'],
                'notes' => '',
            ];
        }

        foreach ($quoteAttachments as $quoteAttachment) {
            $attachmentPath = trim((string) ($quoteAttachment['attachment_path'] ?? ''));

            if ($attachmentPath === '') {
                continue;
            }

            $attachmentKey = implode('|', [
                $attachmentPath,
                (string) ($quoteAttachment['quote_number'] ?? ''),
                (string) ($quoteAttachment['quote_date'] ?? ''),
                (string) ($quoteAttachment['validity_date'] ?? ''),
            ]);
            $existingAttachments[$attachmentKey]['attachment_path'] = $attachmentPath;
            $existingAttachments[$attachmentKey]['quote_number'] = (string) ($quoteAttachment['quote_number'] ?? '');
            $existingAttachments[$attachmentKey]['quote_date'] = (string) ($quoteAttachment['quote_date'] ?? '');
            $existingAttachments[$attachmentKey]['validity_date'] = (string) ($quoteAttachment['validity_date'] ?? '');
            $existingAttachments[$attachmentKey]['notes'] = (string) ($quoteAttachment['notes'] ?? '');
            $existingAttachments[$attachmentKey]['demands'][] = (string) $demand['name'];
        }
    }

    if ($quote && !$quoteDefaultsLoaded && !$globalPriceCandidate && $_SERVER['REQUEST_METHOD'] !== 'POST' && $quoteDefaults['quote_number'] === '') {
        $quoteDefaults = [
            'quote_number' => (string) ($quote['quote_number'] ?? ''),
            'quote_date' => (string) ($quote['quote_date'] ?? ''),
            'validity_date' => (string) ($quote['validity_date'] ?? ''),
            'quoted_by' => (string) ($quote['quoted_by'] ?? ''),
            'collected_by' => (string) ($quote['collected_by'] ?? ''),
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
$quoteDocumentRows = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? array_values($postedQuoteDocuments)
    : $existingAttachments;

if (!$quoteDocumentRows) {
    $quoteDocumentRows[] = [
        'quote_number' => $quoteDefaults['quote_number'],
        'quote_date' => $quoteDefaults['quote_date'],
        'validity_date' => $quoteDefaults['validity_date'],
        'attachment_path' => '',
        'notes' => '',
    ];
}

foreach ($quoteDocumentRows as $documentIndex => $documentRow) {
    if (!is_array($documentRow)) {
        $documentRow = [];
    }

    $quoteDocumentRows[$documentIndex] = [
        'quote_number' => (string) ($documentRow['quote_number'] ?? ''),
        'quote_date' => (string) ($documentRow['quote_date'] ?? ''),
        'validity_date' => (string) ($documentRow['validity_date'] ?? ''),
        'attachment_path' => (string) ($documentRow['attachment_path'] ?? ''),
        'notes' => (string) ($documentRow['notes'] ?? ''),
    ];
}

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
$canEditQuoteValues = $selectedSupplierId > 0 && $selectedSupplier !== null;

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
        <?php if (!$projectLocked): ?>
            <a href="/project_global_price_bank.php?id=<?= (int) $projectId ?>" class="btn btn-outline-success">
                <i class="bi bi-archive"></i>Banco de precos
            </a>
        <?php endif; ?>

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

<?php if ($globalPriceCandidate): ?>
    <div class="alert alert-success">
        Orcamento historico carregado de <strong><?= e($globalPriceCandidate['source_project_name']) ?></strong>
        para o fornecedor <strong><?= e($globalPriceCandidate['supplier_name']) ?></strong>.
        Foram preenchidos <?= (int) $globalPriceCandidate['matched_item_count'] ?> de <?= (int) $globalPriceCandidate['target_item_count'] ?> item(ns) compativeis.
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

<?php if ($projectLocked): ?>
    <div class="alert alert-warning">
        <?= e(project_locked_edit_message($project)) ?>
    </div>
<?php else: ?>
<form method="post" enctype="multipart/form-data" class="card card-body shadow-sm project-quote-form">
    <input type="hidden" name="project_id" value="<?= (int) $projectId ?>">

    <div class="row g-3">
        <div class="col-lg-5">
            <label class="form-label" for="supplierSearchInput">Fornecedor</label>
            <div class="supplier-combobox position-relative" data-supplier-combobox>
                <input
                    type="hidden"
                    name="supplier_id"
                    id="supplierSelect"
                    value="<?= $selectedSupplierId > 0 ? (int) $selectedSupplierId : '' ?>">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input
                        type="search"
                        id="supplierSearchInput"
                        class="form-control"
                        value="<?= e($selectedSupplierLabel) ?>"
                        data-selected-label="<?= e($selectedSupplierLabel) ?>"
                        placeholder="Digite nome, CNPJ, contato, e-mail ou cidade"
                        autocomplete="off"
                        aria-controls="supplierSearchDropdown"
                        aria-expanded="false">
                    <button type="button" class="btn btn-outline-secondary" id="clearSupplierSearch" title="Limpar fornecedor">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="invalid-feedback d-none" id="supplierSearchInvalid">Selecione um fornecedor da lista.</div>
                <div
                    class="supplier-search-results list-group d-none"
                    id="supplierSearchDropdown"
                    role="listbox">
                    <?php foreach ($suppliers as $supplier): ?>
                        <?php
                        $supplierLabel = trim((string) $supplier['name'] . (!empty($supplier['document']) ? ' - ' . format_brazil_document((string) $supplier['document']) : ''));
                        $supplierMeta = array_filter([
                            $supplier['contact_name'] ?? '',
                            $supplier['email'] ?? '',
                            trim((string) (($supplier['city'] ?? '') . (($supplier['state'] ?? '') ? ' / ' . $supplier['state'] : ''))),
                        ], static fn ($value): bool => trim((string) $value) !== '');
                        $supplierSearch = implode(' ', [
                            $supplierLabel,
                            $supplier['contact_name'] ?? '',
                            $supplier['email'] ?? '',
                            $supplier['phone'] ?? '',
                            $supplier['city'] ?? '',
                            $supplier['state'] ?? '',
                            $supplier['company_size'] ?? '',
                        ]);
                        ?>
                        <button
                            type="button"
                            class="list-group-item list-group-item-action"
                            data-supplier-option
                            data-supplier-id="<?= (int) $supplier['id'] ?>"
                            data-supplier-label="<?= e($supplierLabel) ?>"
                            data-supplier-search="<?= e($supplierSearch) ?>">
                            <span class="fw-semibold d-block"><?= e($supplier['name']) ?></span>
                            <span class="small text-muted d-block">
                                <?= $supplier['document'] ? e(format_brazil_document((string) $supplier['document'])) : 'Sem documento' ?>
                                <?= $supplierMeta ? ' - ' . e(implode(' - ', $supplierMeta)) : '' ?>
                            </span>
                        </button>
                    <?php endforeach; ?>
                    <div class="list-group-item text-muted small d-none" data-supplier-empty>
                        Nenhum fornecedor encontrado para a busca.
                    </div>
                </div>
            </div>
            <div class="form-text">
                Ao salvar, cada preco informado sera aplicado em todas as demandas que possuem o produto.
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
                                            href="<?= e($attachment['attachment_path']) ?>"
                                            target="_blank"
                                            class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-paperclip"></i>
                                            <?= trim((string) ($attachment['quote_number'] ?? '')) !== '' ? 'Orcamento ' . e((string) $attachment['quote_number']) : 'Documento ' . ($attachmentIndex + 1) ?>
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

        <div class="col-md-6 col-lg-3">
            <label class="form-label">Quem realizou a cotacao</label>
            <input type="text" name="quoted_by" class="form-control" value="<?= e($quoteDefaults['quoted_by']) ?>" placeholder="Contato do fornecedor, se vazio">
        </div>

        <div class="col-md-6 col-lg-3">
            <label class="form-label">Quem coletou a cotacao</label>
            <input type="text" name="collected_by" class="form-control" value="<?= e($quoteDefaults['collected_by']) ?>">
        </div>

        <div class="col-12">
            <div class="border rounded-3 bg-light p-3">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                    <div>
                        <h2 class="h6 mb-1">Documentos do orçamento</h2>
                        <div class="small text-muted">Cada documento pode ter número, data, validade e arquivo próprios.</div>
                    </div>
                    <div class="d-flex flex-column flex-sm-row gap-2 align-items-stretch align-items-sm-end">
                        <div>
                            <label class="form-label small mb-1" for="quoteStatus">Status geral</label>
                            <select name="status" id="quoteStatus" class="form-select form-select-sm">
                                <?php foreach ($statusOptions as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= $quoteDefaults['status'] === $value ? 'selected' : '' ?>>
                                        <?= e($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="addQuoteDocument">
                            <i class="bi bi-plus-lg"></i> Adicionar mais um orcamento
                        </button>
                    </div>
                </div>

                <?php if ($existingAttachments): ?>
                    <div class="form-check mb-3">
                        <input
                            type="checkbox"
                            name="remove_attachment"
                            value="1"
                            class="form-check-input"
                            id="removeAttachment">
                        <label class="form-check-label" for="removeAttachment">
                            Remover documentos atuais ao salvar
                        </label>
                    </div>
                <?php endif; ?>

                <div class="vstack gap-3" id="quoteDocumentList" data-next-index="<?= count($quoteDocumentRows) ?>">
                    <?php foreach ($quoteDocumentRows as $documentIndex => $document): ?>
                        <div class="quote-document-row border rounded bg-white p-3" data-quote-document-row>
                            <input
                                type="hidden"
                                name="quote_documents[<?= (int) $documentIndex ?>][attachment_path]"
                                value="<?= e($document['attachment_path']) ?>">

                            <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                                <div>
                                    <div class="fw-semibold">Documento <?= (int) $documentIndex + 1 ?></div>
                                    <?php if (trim((string) $document['attachment_path']) !== ''): ?>
                                        <a href="<?= e($document['attachment_path']) ?>" target="_blank" class="small">
                                            <i class="bi bi-paperclip"></i> Arquivo atual
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-remove-quote-document>
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Nº do orçamento</label>
                                    <input
                                        type="text"
                                        name="quote_documents[<?= (int) $documentIndex ?>][quote_number]"
                                        class="form-control"
                                        value="<?= e($document['quote_number']) ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Data</label>
                                    <input
                                        type="date"
                                        name="quote_documents[<?= (int) $documentIndex ?>][quote_date]"
                                        class="form-control"
                                        value="<?= e($document['quote_date']) ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Validade</label>
                                    <input
                                        type="date"
                                        name="quote_documents[<?= (int) $documentIndex ?>][validity_date]"
                                        class="form-control"
                                        value="<?= e($document['validity_date']) ?>">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Arquivo do orçamento</label>
                                    <input
                                        type="file"
                                        name="quote_document_files[<?= (int) $documentIndex ?>]"
                                        class="form-control"
                                        accept="application/pdf,.pdf,.doc,.docx,image/jpeg,image/png,image/webp">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Observação do documento</label>
                                    <input
                                        type="text"
                                        name="quote_documents[<?= (int) $documentIndex ?>][notes]"
                                        class="form-control"
                                        value="<?= e($document['notes']) ?>">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <label class="form-label">Observações gerais</label>
            <textarea name="notes" rows="2" class="form-control"><?= e($quoteDefaults['notes']) ?></textarea>
        </div>
    </div>

    <hr class="my-4">

    <?php if ($projectItems): ?>
        <?php if (!$canEditQuoteValues): ?>
            <div class="alert alert-info d-flex gap-3 align-items-start mb-3">
                <i class="bi bi-info-circle fs-4"></i>
                <div>
                    <div class="fw-semibold">Selecione um fornecedor antes de preencher os valores.</div>
                    <div class="small mb-0">Os campos de valor unitario ficam bloqueados para evitar perder os dados quando o fornecedor for escolhido e a pagina recarregar.</div>
                </div>
            </div>
        <?php endif; ?>

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
                        $sourceQuoteItemId = (int) ($postedSourceQuoteItemIds[$procurementItemId] ?? $postedSourceQuoteItemIds[(string) $procurementItemId] ?? 0);
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
                                    data-requires-supplier
                                    data-quantity="<?= e((string) (float) $item['total_reference_quantity']) ?>"
                                    value="<?= e($item['price_value'] !== '' && $item['price_value'] !== null ? number_format((float) $item['price_value'], 2, '.', '') : '') ?>"
                                    <?= !$canEditQuoteValues ? 'disabled' : '' ?>>
                                <?php if ($sourceQuoteItemId > 0): ?>
                                    <input type="hidden" name="source_quote_item_ids[<?= $procurementItemId ?>]" value="<?= $sourceQuoteItemId ?>">
                                    <div class="form-text">Valor carregado do banco de precos geral.</div>
                                <?php endif; ?>
                                <?php if ($item['has_mixed_prices']): ?>
                                    <div class="form-text">Valores diferentes cadastrados; preencha para unificar.</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <input
                                    type="text"
                                    name="item_notes[<?= $procurementItemId ?>]"
                                    class="form-control form-control-sm"
                                    data-requires-supplier
                                    value="<?= e($item['note_value']) ?>"
                                    placeholder="Opcional"
                                    <?= !$canEditQuoteValues ? 'disabled' : '' ?>>
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
        <button class="btn btn-primary" <?= !$suppliers || !$hasDemandItems || !$canEditQuoteValues ? 'disabled' : '' ?> data-save-project-quote data-base-disabled="<?= !$suppliers || !$hasDemandItems ? '1' : '0' ?>">
            Salvar orçamento geral
        </button>
    </div>
</form>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const supplierSelect = document.getElementById('supplierSelect');
        const quoteTotalValue = document.getElementById('quoteTotalValue');
        const quoteTotalPricedCount = document.getElementById('quoteTotalPricedCount');
        const priceInputs = document.querySelectorAll('[data-quote-price-input]');
        const quoteDocumentList = document.getElementById('quoteDocumentList');
        const addQuoteDocumentButton = document.getElementById('addQuoteDocument');
        const supplierSearchInput = document.getElementById('supplierSearchInput');
        const supplierDropdown = document.getElementById('supplierSearchDropdown');
        const supplierClearButton = document.getElementById('clearSupplierSearch');
        const supplierInvalidFeedback = document.getElementById('supplierSearchInvalid');
        const supplierOptions = supplierDropdown ? Array.from(supplierDropdown.querySelectorAll('[data-supplier-option]')) : [];
        const supplierEmptyState = supplierDropdown ? supplierDropdown.querySelector('[data-supplier-empty]') : null;
        const quoteForm = document.querySelector('.project-quote-form');
        const supplierDependentFields = document.querySelectorAll('[data-requires-supplier]');
        const saveProjectQuoteButton = document.querySelector('[data-save-project-quote]');

        function createQuoteDocumentRow(index) {
            const wrapper = document.createElement('div');
            wrapper.className = 'quote-document-row border rounded bg-white p-3';
            wrapper.dataset.quoteDocumentRow = '';
            wrapper.innerHTML = `
                <input type="hidden" name="quote_documents[${index}][attachment_path]" value="">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div class="fw-semibold" data-quote-document-title>Documento ${index + 1}</div>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-remove-quote-document>
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Nº do orçamento</label>
                        <input type="text" name="quote_documents[${index}][quote_number]" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Data</label>
                        <input type="date" name="quote_documents[${index}][quote_date]" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Validade</label>
                        <input type="date" name="quote_documents[${index}][validity_date]" class="form-control">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Arquivo do orçamento</label>
                        <input type="file" name="quote_document_files[${index}]" class="form-control" accept="application/pdf,.pdf,.doc,.docx,image/jpeg,image/png,image/webp">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Observação do documento</label>
                        <input type="text" name="quote_documents[${index}][notes]" class="form-control">
                    </div>
                </div>`;

            return wrapper;
        }

        function updateQuoteDocumentTitles() {
            if (!quoteDocumentList) {
                return;
            }

            quoteDocumentList.querySelectorAll('[data-quote-document-row]').forEach(function(row, index) {
                const title = row.querySelector('[data-quote-document-title], .fw-semibold');

                if (title) {
                    title.textContent = 'Documento ' + (index + 1);
                }
            });
        }

        if (addQuoteDocumentButton && quoteDocumentList) {
            addQuoteDocumentButton.addEventListener('click', function() {
                const nextIndex = Number(quoteDocumentList.dataset.nextIndex || 0);
                quoteDocumentList.appendChild(createQuoteDocumentRow(nextIndex));
                quoteDocumentList.dataset.nextIndex = String(nextIndex + 1);
                updateQuoteDocumentTitles();
            });

            quoteDocumentList.addEventListener('click', function(event) {
                const removeButton = event.target.closest('[data-remove-quote-document]');

                if (!removeButton) {
                    return;
                }

                const row = removeButton.closest('[data-quote-document-row]');

                if (!row) {
                    return;
                }

                if (quoteDocumentList.querySelectorAll('[data-quote-document-row]').length <= 1) {
                    row.querySelectorAll('input').forEach(function(input) {
                        input.value = '';
                    });
                    return;
                }

                row.remove();
                updateQuoteDocumentTitles();
            });
        }

        function syncSupplierDependentFields() {
            const hasSupplier = Boolean(supplierSelect && supplierSelect.value);

            supplierDependentFields.forEach(function(field) {
                field.disabled = !hasSupplier;
            });

            if (saveProjectQuoteButton) {
                saveProjectQuoteButton.disabled = saveProjectQuoteButton.dataset.baseDisabled === '1' || !hasSupplier;
            }
        }

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
        syncSupplierDependentFields();

        function normalizeSupplierSearch(value) {
            return String(value || '')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase();
        }

        function showSupplierDropdown() {
            if (!supplierDropdown || !supplierSearchInput) {
                return;
            }

            supplierDropdown.classList.remove('d-none');
            supplierSearchInput.setAttribute('aria-expanded', 'true');
        }

        function hideSupplierDropdown() {
            if (!supplierDropdown || !supplierSearchInput) {
                return;
            }

            supplierDropdown.classList.add('d-none');
            supplierSearchInput.setAttribute('aria-expanded', 'false');
        }

        function filterSupplierOptions() {
            if (!supplierDropdown) {
                return;
            }

            const query = normalizeSupplierSearch(supplierSearchInput ? supplierSearchInput.value : '');
            let visibleCount = 0;

            supplierOptions.forEach(function(option) {
                const haystack = normalizeSupplierSearch(option.dataset.supplierSearch || option.dataset.supplierLabel || option.textContent);
                const matches = !query || haystack.includes(query);

                option.classList.toggle('d-none', !matches);

                if (matches) {
                    visibleCount++;
                }
            });

            if (supplierEmptyState) {
                supplierEmptyState.classList.toggle('d-none', visibleCount > 0);
            }
        }

        function navigateToSupplier(supplierId) {
            if (!supplierId || document.querySelector('.alert-danger')) {
                return;
            }

            const url = new URL(window.location.href);
            url.searchParams.set('project_id', '<?= (int) $projectId ?>');
            url.searchParams.set('supplier_id', supplierId);
            url.searchParams.delete('global_price_key');
            window.location.href = url.toString();
        }

        function selectSupplierOption(option) {
            if (!supplierSelect || !supplierSearchInput || !option) {
                return;
            }

            const supplierId = option.dataset.supplierId || '';
            const supplierLabel = option.dataset.supplierLabel || option.textContent.trim();
            const previousSupplierId = supplierSelect.value;

            supplierSelect.value = supplierId;
            syncSupplierDependentFields();
            supplierSearchInput.value = supplierLabel;
            supplierSearchInput.dataset.selectedLabel = supplierLabel;
            supplierSearchInput.classList.remove('is-invalid');

            if (supplierInvalidFeedback) {
                supplierInvalidFeedback.classList.add('d-none');
            }

            hideSupplierDropdown();

            if (supplierId && supplierId !== previousSupplierId) {
                navigateToSupplier(supplierId);
            }
        }

        if (supplierSearchInput && supplierDropdown && supplierSelect) {
            filterSupplierOptions();

            supplierSearchInput.addEventListener('focus', function() {
                filterSupplierOptions();
                showSupplierDropdown();
            });

            supplierSearchInput.addEventListener('input', function() {
                if (supplierSearchInput.value !== (supplierSearchInput.dataset.selectedLabel || '')) {
                    supplierSelect.value = '';
                    syncSupplierDependentFields();
                }

                supplierSearchInput.classList.remove('is-invalid');

                if (supplierInvalidFeedback) {
                    supplierInvalidFeedback.classList.add('d-none');
                }

                filterSupplierOptions();
                showSupplierDropdown();
            });

            supplierSearchInput.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    hideSupplierDropdown();
                    return;
                }

                if (event.key !== 'Enter') {
                    return;
                }

                const firstVisibleOption = supplierOptions.find(function(option) {
                    return !option.classList.contains('d-none');
                });

                if (firstVisibleOption) {
                    event.preventDefault();
                    selectSupplierOption(firstVisibleOption);
                }
            });

            supplierOptions.forEach(function(option) {
                option.addEventListener('click', function() {
                    selectSupplierOption(option);
                });
            });

            if (supplierClearButton) {
                supplierClearButton.addEventListener('click', function() {
                    supplierSelect.value = '';
                    syncSupplierDependentFields();
                    supplierSearchInput.value = '';
                    supplierSearchInput.dataset.selectedLabel = '';
                    filterSupplierOptions();
                    showSupplierDropdown();
                    supplierSearchInput.focus();
                });
            }

            document.addEventListener('click', function(event) {
                if (!event.target.closest('[data-supplier-combobox]')) {
                    hideSupplierDropdown();
                }
            });
        }

        if (quoteForm && supplierSelect && supplierSearchInput) {
            quoteForm.addEventListener('submit', function(event) {
                if (supplierSelect.value) {
                    return;
                }

                event.preventDefault();
                supplierSearchInput.classList.add('is-invalid');

                if (supplierInvalidFeedback) {
                    supplierInvalidFeedback.classList.remove('d-none');
                }

                filterSupplierOptions();
                showSupplierDropdown();
                supplierSearchInput.focus();
            });
        }
    });
</script>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
