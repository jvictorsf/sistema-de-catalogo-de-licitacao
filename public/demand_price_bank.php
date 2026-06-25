<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$demand = find_demand_list($id);

if (!$demand) {
    http_response_code(404);
    exit('Demanda não encontrada.');
}

$project = find_project((int) $demand['project_id']);
$projectLocked = project_is_locked($project);
$items = get_demand_items($id);
$months = (int) ($_GET['months'] ?? $_POST['months'] ?? 0);
$selectedReferences = get_selected_demand_price_references($id);
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($projectLocked) {
            throw new RuntimeException(project_locked_edit_message($project));
        }

        save_demand_price_references($id, is_array($_POST['references'] ?? null) ? $_POST['references'] : []);

        redirect('/demand_price_bank.php?id=' . $id . '&months=' . $months . '&success=1');
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$success = ($_GET['success'] ?? '') === '1';
$candidates = get_demand_price_bank_candidates($id, $months);

require __DIR__ . '/../app/views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Banco de Preços</h1>
        <p class="text-muted mb-0">
            Demanda: <?= e($demand['name']) ?><?= $project ? ' - Projeto: ' . e($project['name']) : '' ?>
        </p>
    </div>

    <div class="d-flex gap-2 flex-wrap justify-content-end">
        <a href="/demand_budget.php?id=<?= (int) $demand['id'] ?>" class="btn btn-outline-success">
            Orçamento geral
        </a>

        <a href="/demand_show.php?id=<?= (int) $demand['id'] ?>" class="btn btn-outline-secondary">
            Voltar
        </a>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success">
        Base de preços atualizada para esta demanda.
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<?php if ($projectLocked): ?>
    <div class="alert alert-warning">
        <?= e(project_locked_edit_message($project)) ?>
    </div>
<?php endif; ?>

<div class="card card-body mb-4">
    <form method="get" class="row g-3 align-items-end">
        <input type="hidden" name="id" value="<?= (int) $id ?>">

        <div class="col-md-4">
            <label class="form-label">Período histórico</label>
            <select name="months" class="form-select">
                <option value="0" <?= $months === 0 ? 'selected' : '' ?>>Todos os períodos</option>
                <option value="3" <?= $months === 3 ? 'selected' : '' ?>>Últimos 3 meses</option>
                <option value="6" <?= $months === 6 ? 'selected' : '' ?>>Últimos 6 meses</option>
                <option value="12" <?= $months === 12 ? 'selected' : '' ?>>Últimos 12 meses</option>
                <option value="24" <?= $months === 24 ? 'selected' : '' ?>>Últimos 24 meses</option>
            </select>
        </div>

        <div class="col-md-3">
            <button class="btn btn-outline-primary w-100">
                Filtrar
            </button>
        </div>

        <div class="col-md-5">
            <div class="alert alert-info mb-0 py-2">
                Marque apenas os preços que devem compor a média da licitação.
            </div>
        </div>
    </form>
</div>

<form method="post">
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <input type="hidden" name="months" value="<?= (int) $months ?>">

    <?php foreach ($items as $item): ?>
        <?php
            $itemId = (int) $item['id'];
            $itemCandidates = $candidates[$itemId] ?? [];
            $itemSelectedReferences = $selectedReferences[$itemId] ?? [];
            $selectedPrices = [];

            foreach ($itemSelectedReferences as $reference) {
                $selectedPrices[] = (float) $reference['unit_price'];
            }

            $selectedAverage = $selectedPrices
                ? array_sum($selectedPrices) / count($selectedPrices)
                : null;
        ?>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <div class="fw-semibold">
                        <span class="badge text-bg-dark me-2"><?= e($item['tracking_code']) ?></span>
                        <?= e($item['item_name']) ?>
                    </div>
                    <div class="small text-muted">
                        Quantidade de referência: <?= e((string) ($item['approved_quantity'] ?? $item['quantity'])) ?>
                    </div>
                </div>

                <div class="text-end">
                    <div class="small text-muted">Preços selecionados</div>
                    <div class="fw-semibold">
                        <?= count($itemSelectedReferences) ?>
                        <?= $selectedAverage !== null ? ' - Média R$ ' . number_format($selectedAverage, 2, ',', '.') : '' ?>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 72px;">Usar</th>
                            <th>Fornecedor</th>
                            <th>Valor unitário</th>
                            <th>Data</th>
                            <th>Origem</th>
                            <th>Anexo</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (!$itemCandidates): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    Nenhum preço histórico encontrado para este item.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($itemCandidates as $candidate): ?>
                            <?php
                                $sourceId = (int) $candidate['source_quote_item_id'];
                                $checked = isset($itemSelectedReferences[$sourceId]);
                            ?>
                            <tr>
                                <td>
                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        name="references[<?= $itemId ?>][<?= $sourceId ?>]"
                                        value="1"
                                        <?= $checked ? 'checked' : '' ?>>
                                </td>
                                <td>
                                    <strong><?= e($candidate['supplier_name']) ?></strong>
                                    <?php if (!empty($candidate['supplier_document'])): ?>
                                        <div class="small text-muted"><?= e(format_brazil_document($candidate['supplier_document'])) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-semibold">
                                    R$ <?= number_format((float) $candidate['unit_price'], 2, ',', '.') ?>
                                </td>
                                <td>
                                    <?= !empty($candidate['quote_date']) ? date('d/m/Y', strtotime($candidate['quote_date'])) : '-' ?>
                                    <?php if (!empty($candidate['validity_date'])): ?>
                                        <div class="small text-muted">Validade: <?= date('d/m/Y', strtotime($candidate['validity_date'])) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= e($candidate['source_project_name']) ?>
                                    <div class="small text-muted">
                                        <?= e($candidate['source_demand_name']) ?><?= $candidate['quote_number'] ? ' - ' . e($candidate['quote_number']) : '' ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($candidate['attachment_path'])): ?>
                                        <a href="<?= e($candidate['attachment_path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
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
    <?php endforeach; ?>

    <div class="d-flex justify-content-end gap-2">
        <a href="/demand_show.php?id=<?= (int) $demand['id'] ?>" class="btn btn-outline-secondary">
            Cancelar
        </a>

        <button class="btn btn-primary" <?= $projectLocked ? 'disabled' : '' ?>>
            Salvar base de preços
        </button>
    </div>
</form>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
