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

if (!$project) {
    http_response_code(404);
    exit('Projeto não encontrado.');
}

$items = get_demand_items($id);
$events = get_demand_approval_events($id);
$projectLocked = project_is_locked($project);
$error = trim((string) ($_GET['error'] ?? ''));
$currentStatus = (string) ($demand['approval_status'] ?? '');
$selectedStatus = isset(demand_approval_decision_options()[$currentStatus])
    ? $currentStatus
    : 'APPROVED';

require __DIR__ . '/../app/views/header.php';

?>

<div class="page-header d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
    <div>
        <div class="text-muted small mb-1">Análise administrativa da demanda</div>
        <h1 class="h3 mb-1"><?= e($demand['name']) ?></h1>
        <div class="text-muted">
            <?= e($project['name']) ?>
            · <?= e($demand['secretariat_name'] ?? 'Sem secretaria') ?>
            · <?= e($demand['requester_department'] ?: 'Sem unidade') ?>
        </div>
    </div>

    <a href="/demand_show.php?id=<?= (int) $demand['id'] ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>Voltar à demanda
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($projectLocked): ?>
    <div class="alert alert-warning d-flex gap-2 align-items-start">
        <i class="bi bi-lock-fill"></i>
        <div><?= e(project_locked_edit_message($project)) ?> A decisão permanece disponível apenas para consulta.</div>
    </div>
<?php endif; ?>

<div class="d-flex align-items-center gap-2 flex-wrap mb-4">
    <span class="text-muted">Situação atual:</span>
    <span class="badge <?= e(demand_approval_status_badge_class($demand['approval_status'] ?? null)) ?>">
        <?= e(demand_approval_status_label($demand['approval_status'] ?? null)) ?>
    </span>
    <?php if (!empty($demand['approval_decided_at'])): ?>
        <span class="small text-muted">
            <?= date('d/m/Y H:i', strtotime((string) $demand['approval_decided_at'])) ?>
            <?php if (!empty($demand['approval_decided_by_name'])): ?>
                · <?= e($demand['approval_decided_by_name']) ?>
            <?php endif; ?>
        </span>
    <?php endif; ?>
</div>

<?php if (!$projectLocked): ?>
<form action="/demand_approval_save.php" method="post" id="demandApprovalForm">
    <input type="hidden" name="demand_list_id" value="<?= (int) $demand['id'] ?>">

    <section class="mb-4">
        <h2 class="h5 mb-3">1. Decisão</h2>

        <div class="row g-2" role="radiogroup" aria-label="Decisão da demanda">
            <?php
                $decisionIcons = [
                    'APPROVED' => 'bi-check-circle',
                    'APPROVED_WITH_RESERVATIONS' => 'bi-exclamation-circle',
                    'REJECTED' => 'bi-x-circle',
                ];
                $decisionClasses = [
                    'APPROVED' => 'success',
                    'APPROVED_WITH_RESERVATIONS' => 'warning',
                    'REJECTED' => 'danger',
                ];
            ?>
            <?php foreach (demand_approval_decision_options() as $status => $label): ?>
                <div class="col-md-4">
                    <input
                        class="btn-check"
                        type="radio"
                        name="approval_status"
                        id="decision<?= e($status) ?>"
                        value="<?= e($status) ?>"
                        <?= $selectedStatus === $status ? 'checked' : '' ?>
                        required>
                    <label class="btn btn-outline-<?= e($decisionClasses[$status]) ?> w-100 text-start p-3" for="decision<?= e($status) ?>">
                        <i class="bi <?= e($decisionIcons[$status]) ?> me-2"></i>
                        <span class="fw-semibold"><?= e($label) ?></span>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="mb-4">
        <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap mb-3">
            <div>
                <h2 class="h5 mb-1">2. Quantitativos aprovados</h2>
                <div class="text-muted small">Revise cada quantidade. Diferenças exigem aprovação com ressalva e justificativa.</div>
            </div>
            <span class="badge text-bg-light border"><?= count($items) ?> item(ns)</span>
        </div>

        <div class="table-responsive border rounded">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="min-width: 280px;">Item</th>
                        <th class="text-end">Solicitada</th>
                        <th style="min-width: 150px;">Aprovada</th>
                        <th style="min-width: 280px;">Justificativa do item</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$items): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">Adicione itens antes de analisar a demanda.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($items as $item): ?>
                        <?php
                            $requestedQuantity = (float) ($item['quantity'] ?? 0);
                            $approvedQuantity = (float) ($item['approved_quantity'] ?? $requestedQuantity);
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e($item['item_name']) ?></div>
                                <div class="small text-muted">
                                    <?= e($item['tracking_code'] ?? '-') ?>
                                    · <?= e(licitation_annex_unit_text($item)) ?>
                                </div>
                            </td>
                            <td class="text-end fw-semibold"><?= e(format_decimal_quantity($requestedQuantity)) ?></td>
                            <td>
                                <input
                                    type="number"
                                    class="form-control text-end"
                                    name="approved_quantities[<?= (int) $item['id'] ?>]"
                                    value="<?= e(rtrim(rtrim(number_format($approvedQuantity, 2, '.', ''), '0'), '.')) ?>"
                                    min="0"
                                    step="0.01"
                                    data-approved-quantity
                                    data-requested="<?= e((string) $requestedQuantity) ?>"
                                    data-current="<?= e((string) $approvedQuantity) ?>"
                                    aria-label="Quantidade aprovada de <?= e($item['item_name']) ?>"
                                    required>
                            </td>
                            <td>
                                <input
                                    type="text"
                                    class="form-control"
                                    name="item_notes[<?= (int) $item['id'] ?>]"
                                    value="<?= e((string) ($item['validation_notes'] ?? '')) ?>"
                                    maxlength="1000"
                                    placeholder="Obrigatória se negar ou ajustar">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="mb-4">
        <h2 class="h5 mb-3">3. Justificativa geral</h2>
        <textarea
            class="form-control"
            name="approval_notes"
            id="approvalNotes"
            rows="4"
            maxlength="5000"
            placeholder="Informe as ressalvas, os motivos da negativa ou observações administrativas."><?= e((string) ($demand['approval_notes'] ?? '')) ?></textarea>
        <div class="form-text" id="approvalNotesHelp">Obrigatória para aprovação com ressalva e para negativa.</div>
    </section>

    <div class="d-flex justify-content-end gap-2 border-top pt-3 mb-5">
        <a href="/demand_show.php?id=<?= (int) $demand['id'] ?>" class="btn btn-outline-secondary">Cancelar</a>
        <button class="btn btn-primary" <?= !$items ? 'disabled' : '' ?>>
            <i class="bi bi-check2-square"></i>Registrar decisão
        </button>
    </div>
</form>
<?php endif; ?>

<section>
    <div class="d-flex justify-content-between align-items-end gap-3 mb-3">
        <div>
            <h2 class="h5 mb-1">Histórico de decisões</h2>
            <div class="text-muted small">Cada registro preserva os quantitativos analisados e o responsável.</div>
        </div>
        <span class="badge text-bg-light border"><?= count($events) ?> evento(s)</span>
    </div>

    <div class="table-responsive border rounded">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Data</th>
                    <th>Decisão</th>
                    <th>Responsável</th>
                    <th>Quantitativos</th>
                    <th>Justificativa</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$events): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Nenhuma decisão registrada.</td></tr>
                <?php endif; ?>

                <?php foreach ($events as $event): ?>
                    <?php
                        $eventItems = $event['item_quantities'] ?? [];
                        $requestedTotal = array_sum(array_map(
                            static fn (array $item): float => (float) ($item['requested_quantity'] ?? 0),
                            $eventItems
                        ));
                        $approvedTotal = array_sum(array_map(
                            static fn (array $item): float => (float) ($item['approved_quantity'] ?? 0),
                            $eventItems
                        ));
                    ?>
                    <tr>
                        <td class="text-nowrap"><?= date('d/m/Y H:i', strtotime((string) $event['created_at'])) ?></td>
                        <td>
                            <span class="badge <?= e(demand_approval_status_badge_class($event['approval_status'] ?? null)) ?>">
                                <?= e(demand_approval_status_label($event['approval_status'] ?? null)) ?>
                            </span>
                        </td>
                        <td><?= e($event['decided_by_name'] ?: 'Sistema') ?></td>
                        <td>
                            <?= count($eventItems) ?> item(ns)
                            <div class="small text-muted">
                                <?= e(format_decimal_quantity($requestedTotal)) ?> solicitada
                                · <?= e(format_decimal_quantity($approvedTotal)) ?> aprovada
                            </div>
                        </td>
                        <td><?= nl2br(e((string) ($event['notes'] ?? '-'))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const decisionInputs = Array.from(document.querySelectorAll('input[name="approval_status"]'));
    const quantityInputs = Array.from(document.querySelectorAll('[data-approved-quantity]'));
    const notes = document.getElementById('approvalNotes');

    function updateDecisionState() {
        const selected = decisionInputs.find(function(input) { return input.checked; });
        const status = selected ? selected.value : '';
        const rejected = status === 'REJECTED';
        const requiresNotes = rejected || status === 'APPROVED_WITH_RESERVATIONS';

        if (notes) {
            notes.required = requiresNotes;
            notes.setAttribute('aria-required', requiresNotes ? 'true' : 'false');
        }

        quantityInputs.forEach(function(input) {
            if (rejected) {
                input.value = '0';
                input.disabled = true;
                return;
            }

            const wasDisabled = input.disabled;
            input.disabled = false;

            if (wasDisabled && Number(input.value) === 0) {
                const current = Number(input.dataset.current || 0);
                input.value = current > 0 ? current : input.dataset.requested;
            }
        });
    }

    decisionInputs.forEach(function(input) {
        input.addEventListener('change', updateDecisionState);
    });

    updateDecisionState();
});
</script>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
