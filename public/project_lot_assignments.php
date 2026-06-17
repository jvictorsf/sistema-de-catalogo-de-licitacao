<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$lotId = (int) ($_GET['id'] ?? $_GET['lot_id'] ?? $_POST['lot_id'] ?? 0);
$lot = find_project_lot_denomination($lotId);

if (!$lot) {
    http_response_code(404);
    exit('Denominacao nao encontrada.');
}

$projectId = (int) $lot['project_id'];
$project = find_project($projectId);

if (!$project) {
    http_response_code(404);
    exit('Projeto nao encontrado.');
}

$errors = [];
$success = trim((string) ($_GET['success'] ?? ''));
$projectLocked = project_is_closed($project);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'add_assignment') {
            $assignmentType = (string) ($_POST['assignment_type'] ?? 'item');

            add_project_lot_assignment(
                $lotId,
                $assignmentType,
                (int) ($_POST['procurement_item_id'] ?? 0) ?: null,
                (int) ($_POST['category_id'] ?? 0) ?: null
            );

            redirect('/project_lot_assignments.php?id=' . $lotId . '&success=' . rawurlencode('Vinculo adicionado.'));
        }

        if ($action === 'delete_assignment') {
            $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
            $currentAssignments = get_project_lot_assignments($projectId);
            $belongsToLot = false;

            foreach ($currentAssignments as $assignment) {
                if ((int) $assignment['id'] === $assignmentId && (int) $assignment['project_lot_id'] === $lotId) {
                    $belongsToLot = true;
                    break;
                }
            }

            if (!$belongsToLot) {
                throw new RuntimeException('Vinculo nao encontrado nesta denominacao.');
            }

            delete_project_lot_assignment($assignmentId);
            redirect('/project_lot_assignments.php?id=' . $lotId . '&success=' . rawurlencode('Vinculo removido.'));
        }
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
    }
}

$items = get_project_consolidated_items($projectId);
$categories = get_categories_tree();
$assignments = array_values(array_filter(
    get_project_lot_assignments($projectId),
    static fn (array $assignment): bool => (int) $assignment['project_lot_id'] === $lotId
));

require __DIR__ . '/../app/views/header.php';

?>

<div class="page-header d-flex justify-content-between align-items-start mb-4">
    <div class="page-title">
        <h1 class="h3 mb-1">Vinculos da denominacao</h1>
        <p class="text-muted mb-0">
            Lote <?= (int) $lot['lot_number'] ?> - <?= e($lot['name']) ?>
        </p>
    </div>

    <div class="page-actions d-flex gap-2 flex-wrap justify-content-end">
        <a href="/project_lots.php?id=<?= (int) $project['id'] ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>Voltar
        </a>
        <?php if (!$projectLocked): ?>
            <a href="/project_lot_form.php?id=<?= (int) $lot['id'] ?>" class="btn btn-outline-primary">
                <i class="bi bi-pencil-square"></i>Editar denominacao
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div>
<?php endif; ?>

<?php if ($projectLocked): ?>
    <div class="alert alert-warning"><?= e(project_closed_edit_message()) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-xl-4">
        <div class="card mb-4">
            <div class="card-header fw-semibold">Resumo</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5">Projeto</dt>
                    <dd class="col-7"><?= e($project['name']) ?></dd>
                    <dt class="col-5">Lote</dt>
                    <dd class="col-7"><?= (int) $lot['lot_number'] ?></dd>
                    <dt class="col-5">Denominacao</dt>
                    <dd class="col-7"><?= e($lot['name']) ?></dd>
                    <dt class="col-5">Vinculos</dt>
                    <dd class="col-7"><?= count($assignments) ?></dd>
                </dl>
            </div>
        </div>

        <?php if (!$projectLocked): ?>
        <form method="post" class="card">
            <input type="hidden" name="action" value="add_assignment">
            <input type="hidden" name="lot_id" value="<?= (int) $lot['id'] ?>">

            <div class="card-header fw-semibold">Adicionar vinculo</div>

            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Tipo de vinculo</label>
                    <select name="assignment_type" class="form-select" data-lot-assignment-type>
                        <option value="item">Produto do projeto</option>
                        <option value="category">Categoria/Subcategoria</option>
                    </select>
                </div>

                <div class="mb-3" data-lot-assignment-item>
                    <label class="form-label">Produto</label>
                    <input type="search" class="form-control mb-2" placeholder="Filtrar produtos" data-option-filter="#procurementItemSelect">
                    <select name="procurement_item_id" id="procurementItemSelect" class="form-select" size="8">
                        <option value="">Selecione</option>
                        <?php foreach ($items as $item): ?>
                            <?php
                                $itemLabel = trim((string) (($item['tracking_code'] ?? '-') . ' - ' . ($item['item_name'] ?? '-')));
                                $categoryLabel = trim((string) implode(' / ', array_filter([
                                    $item['category_name'] ?? '',
                                    $item['subcategory_name'] ?? '',
                                ])));
                            ?>
                            <option value="<?= (int) $item['procurement_item_id'] ?>">
                                <?= e($itemLabel . ($categoryLabel !== '' ? ' | ' . $categoryLabel : '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3 d-none" data-lot-assignment-category>
                    <label class="form-label">Categoria/Subcategoria</label>
                    <input type="search" class="form-control mb-2" placeholder="Filtrar categorias" data-option-filter="#categorySelect">
                    <select name="category_id" id="categorySelect" class="form-select" size="8">
                        <option value="">Selecione</option>
                        <?php foreach ($categories as $category): ?>
                            <?php
                                $categoryLabel = trim((string) ($category['parent_name'] ?? '')) !== ''
                                    ? $category['parent_name'] . ' / ' . $category['name']
                                    : $category['name'];
                            ?>
                            <option value="<?= (int) $category['id'] ?>">
                                <?= e($categoryLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="card-footer d-grid">
                <button class="btn btn-success">
                    <i class="bi bi-link-45deg"></i>Adicionar vinculo
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <div class="col-xl-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <div class="fw-semibold">Vinculos cadastrados</div>
                    <div class="text-muted small">Produtos especificos e categorias/subcategorias que compoem este lote.</div>
                </div>
                <span class="badge text-bg-light border"><?= count($assignments) ?> vinculo(s)</span>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 190px;">Tipo</th>
                                <th>Vinculo</th>
                                <th class="text-end" style="width: 130px;">Acao</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$assignments): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-5">
                                        Nenhum vinculo cadastrado para esta denominacao.
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($assignments as $assignment): ?>
                                <?php
                                    $isItem = ($assignment['assignment_type'] ?? '') === 'item';
                                    $label = $isItem
                                        ? (($assignment['tracking_code'] ?? '-') . ' - ' . ($assignment['item_name'] ?? '-'))
                                        : trim(implode(' / ', array_filter([
                                            $assignment['parent_category_name'] ?? '',
                                            $assignment['category_name'] ?? '',
                                        ])));
                                ?>
                                <tr>
                                    <td>
                                        <span class="badge <?= $isItem ? 'text-bg-primary' : 'text-bg-info' ?>">
                                            <?= $isItem ? 'Produto' : 'Categoria/Subcategoria' ?>
                                        </span>
                                    </td>
                                    <td><?= e($label !== '' ? $label : '-') ?></td>
                                    <td class="text-end">
                                        <?php if (!$projectLocked): ?>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Remover este vinculo?')">
                                            <input type="hidden" name="action" value="delete_assignment">
                                            <input type="hidden" name="lot_id" value="<?= (int) $lot['id'] ?>">
                                            <input type="hidden" name="assignment_id" value="<?= (int) $assignment['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>Remover
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
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[data-lot-assignment-type]').forEach(function(select) {
            const form = select.closest('form');
            const item = form.querySelector('[data-lot-assignment-item]');
            const category = form.querySelector('[data-lot-assignment-category]');

            function sync() {
                const categoryMode = select.value === 'category';
                item.classList.toggle('d-none', categoryMode);
                category.classList.toggle('d-none', !categoryMode);
            }

            select.addEventListener('change', sync);
            sync();
        });

        document.querySelectorAll('[data-option-filter]').forEach(function(input) {
            const select = document.querySelector(input.dataset.optionFilter || '');

            if (!select) {
                return;
            }

            input.addEventListener('input', function() {
                const term = input.value.trim().toLocaleLowerCase();

                Array.from(select.options).forEach(function(option) {
                    if (option.value === '') {
                        option.hidden = false;
                        return;
                    }

                    option.hidden = term !== '' && !option.text.toLocaleLowerCase().includes(term);
                });
            });
        });
    });
</script>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
