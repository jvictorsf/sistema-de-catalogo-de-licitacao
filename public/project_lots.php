<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$projectId = (int) ($_GET['id'] ?? $_POST['project_id'] ?? 0);
$project = find_project($projectId);

if (!$project) {
    http_response_code(404);
    exit('Projeto nao encontrado.');
}

$errors = [];
$success = trim((string) ($_GET['success'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create_lot' || $action === 'update_lot') {
            $data = [
                'project_id' => $projectId,
                'lot_number' => (int) ($_POST['lot_number'] ?? 0),
                'name' => trim((string) ($_POST['name'] ?? '')),
                'justification' => trim((string) ($_POST['justification'] ?? '')),
            ];

            if ($data['lot_number'] <= 0) {
                $errors[] = 'Informe um numero de lote positivo.';
            }

            if ($data['name'] === '') {
                $errors[] = 'Informe a denominacao do lote.';
            }

            if ($data['justification'] === '') {
                $errors[] = 'Informe a justificativa da denominacao.';
            }

            if (!$errors && $action === 'create_lot') {
                create_project_lot_denomination($data);
                redirect('/project_lots.php?id=' . $projectId . '&success=' . rawurlencode('Denominacao criada.'));
            }

            if (!$errors && $action === 'update_lot') {
                update_project_lot_denomination((int) ($_POST['lot_id'] ?? 0), $data);
                redirect('/project_lots.php?id=' . $projectId . '&success=' . rawurlencode('Denominacao atualizada.'));
            }
        }

        if ($action === 'delete_lot') {
            delete_project_lot_denomination((int) ($_POST['lot_id'] ?? 0));
            redirect('/project_lots.php?id=' . $projectId . '&success=' . rawurlencode('Denominacao removida.'));
        }

        if ($action === 'add_assignment') {
            $assignmentType = (string) ($_POST['assignment_type'] ?? 'item');
            add_project_lot_assignment(
                (int) ($_POST['lot_id'] ?? 0),
                $assignmentType,
                (int) ($_POST['procurement_item_id'] ?? 0) ?: null,
                (int) ($_POST['category_id'] ?? 0) ?: null
            );
            redirect('/project_lots.php?id=' . $projectId . '&success=' . rawurlencode('Vinculo adicionado.'));
        }

        if ($action === 'delete_assignment') {
            delete_project_lot_assignment((int) ($_POST['assignment_id'] ?? 0));
            redirect('/project_lots.php?id=' . $projectId . '&success=' . rawurlencode('Vinculo removido.'));
        }
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
    }
}

$lots = get_project_lot_denominations($projectId);
$assignments = get_project_lot_assignments($projectId);
$assignmentsByLot = [];
$lotById = [];

foreach ($lots as $lot) {
    $lotById[(int) $lot['id']] = $lot;
}

foreach ($assignments as $assignment) {
    $assignmentsByLot[(int) $assignment['project_lot_id']][] = $assignment;
}

$items = get_project_consolidated_items($projectId);
$categories = get_categories();
$nextLotNumber = get_next_project_lot_number($projectId);
$selectedLotId = (int) ($_GET['lot_id'] ?? 0);
$editingLotId = (int) ($_GET['edit_lot_id'] ?? 0);
$selectedLotId = $selectedLotId > 0 ? $selectedLotId : ($lots ? (int) $lots[0]['id'] : 0);
$editingLot = $editingLotId > 0 ? ($lotById[$editingLotId] ?? null) : null;

require __DIR__ . '/../app/views/header.php';

?>

<div class="page-header d-flex justify-content-between align-items-start mb-4">
    <div class="page-title">
        <h1 class="h3 mb-1">Denominacoes de lotes</h1>
        <p class="text-muted mb-0"><?= e($project['name']) ?></p>
    </div>

    <div class="page-actions d-flex gap-2 flex-wrap justify-content-end">
        <a href="/project_show.php?id=<?= (int) $project['id'] ?>" class="btn btn-outline-secondary">
            Voltar
        </a>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <div class="fw-semibold">Denominacoes cadastradas</div>
                    <div class="text-muted small">Defina os lotes comerciais do projeto antes de gerar os anexos por lote.</div>
                </div>

                <a href="/project_lots.php?id=<?= (int) $project['id'] ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-plus-lg"></i>Nova denominacao
                </a>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 90px;">Lote</th>
                                <th>Denominacao</th>
                                <th>Justificativa</th>
                                <th style="width: 160px;">Vinculos</th>
                                <th class="text-end" style="width: 260px;">Acoes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$lots): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        Nenhuma denominacao cadastrada para este projeto.
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($lots as $lot): ?>
                                <tr>
                                    <td class="fw-semibold"><?= (int) $lot['lot_number'] ?></td>
                                    <td><?= e($lot['name']) ?></td>
                                    <td class="small text-muted"><?= e(mb_strimwidth((string) $lot['justification'], 0, 120, '...')) ?></td>
                                    <td>
                                        <?= (int) ($lot['item_assignment_count'] ?? 0) ?> produto(s)<br>
                                        <span class="text-muted small"><?= (int) ($lot['category_assignment_count'] ?? 0) ?> categoria(s)</span>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                            <a
                                                href="/project_lots.php?id=<?= (int) $project['id'] ?>&lot_id=<?= (int) $lot['id'] ?>#vinculos"
                                                class="btn btn-sm btn-outline-success">
                                                Vinculos
                                            </a>
                                            <a
                                                href="/project_lots.php?id=<?= (int) $project['id'] ?>&edit_lot_id=<?= (int) $lot['id'] ?>#form-denominacao"
                                                class="btn btn-sm btn-outline-primary">
                                                Editar
                                            </a>
                                            <form method="post" onsubmit="return confirm('Deseja remover esta denominacao e seus vinculos?')">
                                                <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
                                                <input type="hidden" name="action" value="delete_lot">
                                                <input type="hidden" name="lot_id" value="<?= (int) $lot['id'] ?>">
                                                <button class="btn btn-sm btn-outline-danger">Remover</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <form method="post" class="card" id="form-denominacao">
            <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
            <input type="hidden" name="action" value="<?= $editingLot ? 'update_lot' : 'create_lot' ?>">
            <?php if ($editingLot): ?>
                <input type="hidden" name="lot_id" value="<?= (int) $editingLot['id'] ?>">
            <?php endif; ?>

            <div class="card-header fw-semibold">
                <?= $editingLot ? 'Editar denominacao' : 'Nova denominacao' ?>
            </div>

            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Numero do lote</label>
                    <input
                        type="number"
                        name="lot_number"
                        class="form-control"
                        min="1"
                        step="1"
                        value="<?= e((string) ($editingLot['lot_number'] ?? $nextLotNumber)) ?>"
                        required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Denominacao</label>
                    <input
                        type="text"
                        name="name"
                        class="form-control"
                        maxlength="255"
                        value="<?= e($editingLot['name'] ?? '') ?>"
                        required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Justificativa</label>
                    <textarea name="justification" class="form-control" rows="6" required><?= e($editingLot['justification'] ?? '') ?></textarea>
                </div>

                <div class="d-flex gap-2 justify-content-end">
                    <?php if ($editingLot): ?>
                        <a href="/project_lots.php?id=<?= (int) $project['id'] ?>" class="btn btn-outline-secondary">
                            Cancelar edicao
                        </a>
                    <?php endif; ?>

                    <button class="btn btn-primary">
                        <i class="bi bi-check2-circle"></i><?= $editingLot ? 'Salvar alteracoes' : 'Criar denominacao' ?>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="col-lg-7">
        <div class="card" id="vinculos">
            <div class="card-header fw-semibold">Vincular produto ou categoria</div>

            <div class="card-body">
                <?php if (!$lots): ?>
                    <div class="empty-state">
                        Cadastre ao menos uma denominacao antes de criar vinculos.
                    </div>
                <?php else: ?>
                    <form method="post" class="row g-3 align-items-end">
                        <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
                        <input type="hidden" name="action" value="add_assignment">

                        <div class="col-md-5">
                            <label class="form-label">Denominacao</label>
                            <select name="lot_id" class="form-select" required>
                                <?php foreach ($lots as $lot): ?>
                                    <option
                                        value="<?= (int) $lot['id'] ?>"
                                        <?= (int) $lot['id'] === $selectedLotId ? 'selected' : '' ?>>
                                        Lote <?= (int) $lot['lot_number'] ?> - <?= e($lot['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Tipo</label>
                            <select name="assignment_type" class="form-select" data-lot-assignment-type>
                                <option value="item">Produto</option>
                                <option value="category">Categoria/Subcategoria</option>
                            </select>
                        </div>

                        <div class="col-md-8" data-lot-assignment-item>
                            <label class="form-label">Produto do projeto</label>
                            <select name="procurement_item_id" class="form-select">
                                <option value="">Selecione</option>
                                <?php foreach ($items as $item): ?>
                                    <option value="<?= (int) $item['procurement_item_id'] ?>">
                                        <?= e(($item['tracking_code'] ?? '-') . ' - ' . ($item['item_name'] ?? '-')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-8 d-none" data-lot-assignment-category>
                            <label class="form-label">Categoria/Subcategoria</label>
                            <select name="category_id" class="form-select">
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

                        <div class="col-md-4">
                            <button class="btn btn-outline-success w-100">
                                <i class="bi bi-link-45deg"></i>Adicionar vinculo
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header fw-semibold">Vinculos cadastrados</div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Lote</th>
                                <th>Tipo</th>
                                <th>Vinculo</th>
                                <th class="text-end">Acao</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$assignments): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        Nenhum vinculo cadastrado.
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
                                        Lote <?= (int) $assignment['lot_number'] ?> - <?= e($assignment['lot_name'] ?? '-') ?>
                                    </td>
                                    <td><?= $isItem ? 'Produto' : 'Categoria/Subcategoria' ?></td>
                                    <td><?= e($label !== '' ? $label : '-') ?></td>
                                    <td class="text-end">
                                        <form method="post" class="d-inline" onsubmit="return confirm('Remover este vinculo?')">
                                            <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
                                            <input type="hidden" name="action" value="delete_assignment">
                                            <input type="hidden" name="assignment_id" value="<?= (int) $assignment['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger">Remover</button>
                                        </form>
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
    });
</script>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
