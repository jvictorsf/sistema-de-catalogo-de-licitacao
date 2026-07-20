<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$projectId = (int) ($_GET['id'] ?? $_POST['project_id'] ?? 0);
$project = find_project($projectId);

if (!$project) {
    http_response_code(404);
    exit('Projeto não encontrado.');
}

$projectLocked = project_is_locked($project);
$error = trim((string) ($_GET['error'] ?? ''));
$success = trim((string) ($_GET['success'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    auth_require_permission('projects.manage');

    try {
        $created = initialize_project_quantity_memories($projectId);
        redirect('/project_quantity_memories.php?id=' . $projectId . '&success=' . rawurlencode(
            $created > 0
                ? $created . ' memória(s) inicializada(s) como rascunho.'
                : 'Todos os itens já possuem memória de cálculo.'
        ));
    } catch (Throwable $exception) {
        redirect('/project_quantity_memories.php?id=' . $projectId . '&error=' . rawurlencode($exception->getMessage()));
    }
}

$schemaAvailable = project_quantity_memory_tables_exist();
$items = get_project_consolidated_items($projectId);
$memoryCount = count(array_filter($items, static fn (array $item): bool => (int) ($item['quantity_memory_id'] ?? 0) > 0));
$validatedCount = count(array_filter($items, static fn (array $item): bool => ($item['quantity_memory_status'] ?? '') === 'VALIDATED'));
$reviewCount = count(array_filter($items, static fn (array $item): bool => !empty($item['quantity_memory_needs_review'])));

require __DIR__ . '/../app/views/header.php';
?>

<div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
    <div>
        <h1 class="h3 mb-1">Memórias de cálculo dos quantitativos</h1>
        <p class="text-muted mb-0"><?= e($project['name']) ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (!$projectLocked && auth_can('projects.manage') && $schemaAvailable): ?>
            <form method="post" onsubmit="return confirm('Inicializar as memórias ainda inexistentes com as quantidades aprovadas atuais?')">
                <input type="hidden" name="project_id" value="<?= $projectId ?>">
                <button class="btn btn-primary">
                    <i class="bi bi-calculator"></i>Inicializar memórias
                </button>
            </form>
        <?php endif; ?>
        <a href="/project_show.php?id=<?= $projectId ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>Voltar
        </a>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<?php if (!$schemaAvailable): ?>
    <div class="alert alert-warning">
        Aplique <code>database/schema.sql</code> para habilitar as memórias de cálculo.
    </div>
<?php endif; ?>

<?php if ($projectLocked): ?>
    <div class="alert alert-warning">
        <?= e(project_locked_edit_message($project)) ?> As memórias permanecem disponíveis para consulta.
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="border-start border-4 border-primary ps-3 py-2">
            <div class="text-muted small">Itens consolidados</div>
            <div class="h4 mb-0"><?= count($items) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="border-start border-4 border-info ps-3 py-2">
            <div class="text-muted small">Memórias iniciadas</div>
            <div class="h4 mb-0"><?= $memoryCount ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="border-start border-4 border-success ps-3 py-2">
            <div class="text-muted small">Validadas</div>
            <div class="h4 mb-0"><?= $validatedCount ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="border-start border-4 border-warning ps-3 py-2">
            <div class="text-muted small">Revisão pendente</div>
            <div class="h4 mb-0"><?= $reviewCount ?></div>
        </div>
    </div>
</div>

<div class="table-responsive border rounded">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th>Item</th>
                <th>Unidade</th>
                <th class="text-end">Solicitada</th>
                <th class="text-end">Aprovada</th>
                <th class="text-end">Calculada</th>
                <th class="text-end">Final</th>
                <th>Método</th>
                <th>Situação</th>
                <th class="text-end">Ação</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$items): ?>
                <tr>
                    <td colspan="9" class="text-center text-muted py-5">Nenhum item consolidado neste projeto.</td>
                </tr>
            <?php endif; ?>

            <?php foreach ($items as $item): ?>
                <?php
                    $hasMemory = (int) ($item['quantity_memory_id'] ?? 0) > 0;
                    $status = $item['quantity_memory_status'] ?? null;
                ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-start gap-2">
                            <span class="badge text-bg-dark"><?= e((string) ($item['licitation_number'] ?? '-')) ?></span>
                            <div>
                                <strong><?= e($item['item_name']) ?></strong>
                                <div class="small text-muted"><?= e($item['tracking_code']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?= e(licitation_annex_unit_text($item)) ?></td>
                    <td class="text-end"><?= e(format_decimal_quantity($item['total_quantity'] ?? 0)) ?></td>
                    <td class="text-end"><?= e(format_decimal_quantity($item['total_approved_quantity'] ?? 0)) ?></td>
                    <td class="text-end"><?= e(format_decimal_quantity($item['calculated_quantity'] ?? 0)) ?></td>
                    <td class="text-end fw-semibold"><?= e(format_decimal_quantity($item['effective_quantity'] ?? 0)) ?></td>
                    <td>
                        <?= $hasMemory ? e(quantity_memory_method_label($item['calculation_method'] ?? null)) : '<span class="text-muted">Legado</span>' ?>
                    </td>
                    <td>
                        <?php if (!$hasMemory): ?>
                            <span class="badge text-bg-light text-dark border">Não iniciada</span>
                        <?php else: ?>
                            <span class="badge <?= $status === 'VALIDATED' ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                <?= e(quantity_memory_status_label($status)) ?>
                            </span>
                            <?php if (!empty($item['quantity_memory_needs_review'])): ?>
                                <span class="badge text-bg-warning">Revisar</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <?php if ($hasMemory): ?>
                            <a href="/project_quantity_memory_form.php?project_id=<?= $projectId ?>&item_id=<?= (int) $item['procurement_item_id'] ?>" class="btn btn-sm btn-outline-primary">
                                <?= $projectLocked || !auth_can('projects.manage') ? 'Consultar' : 'Editar' ?>
                            </a>
                        <?php else: ?>
                            <span class="small text-muted">Use a inicialização</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
