<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$project = find_project($id);

if (!$project) {
    http_response_code(404);
    exit('Projeto nao encontrado.');
}

$errors = [];
$success = trim((string) ($_GET['success'] ?? ''));
$projectLocked = project_is_locked($project);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (($_POST['action'] ?? '') === 'renumber') {
            renumber_project_licitation_items($id);
            redirect('/project_licitation_numbers.php?id=' . $id . '&success=' . rawurlencode('Numeracao reorganizada.'));
        }

        save_project_licitation_numbers(
            $id,
            is_array($_POST['licitation_numbers'] ?? null) ? $_POST['licitation_numbers'] : []
        );

        redirect('/project_licitation_numbers.php?id=' . $id . '&success=' . rawurlencode('Numeracao salva. Gere novamente os anexos.'));
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
    }
}

$items = get_project_consolidated_items($id);

require __DIR__ . '/../app/views/header.php';

?>

<div class="page-header d-flex justify-content-between align-items-start mb-4">
    <div class="page-title">
        <h1 class="h3 mb-1">Ordenacao dos itens da licitacao</h1>
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
    <div class="alert alert-danger">
        <?= e(implode(' ', $errors)) ?>
    </div>
<?php endif; ?>

<?php if ($projectLocked): ?>
    <div class="alert alert-warning">
        <?= e(project_locked_edit_message($project)) ?>
    </div>
<?php endif; ?>

<form method="post" class="card">
    <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">

    <div class="card-header d-flex justify-content-between align-items-center gap-3 flex-wrap">
        <div class="fw-semibold">Numero de licitacao por item</div>

        <div class="d-flex gap-2">
            <button type="submit" name="action" value="renumber" class="btn btn-outline-dark" <?= $projectLocked ? 'disabled' : '' ?>>
                <i class="bi bi-sort-numeric-down"></i>Renumerar
            </button>

            <button type="submit" name="action" value="save" class="btn btn-primary" <?= $projectLocked ? 'disabled' : '' ?>>
                <i class="bi bi-check2-circle"></i>Salvar
            </button>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 130px;">Numero</th>
                        <th>Codigo</th>
                        <th>Item</th>
                        <th>Unidade</th>
                        <th class="text-end">Qtd. aprovada</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (!$items): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                Nenhum item demandado.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <input
                                    type="number"
                                    name="licitation_numbers[<?= (int) $item['procurement_item_id'] ?>]"
                                    class="form-control form-control-sm"
                                    min="1"
                                    step="1"
                                    value="<?= e((string) ($item['licitation_number'] ?? '')) ?>"
                                    <?= $projectLocked ? 'readonly' : '' ?>
                                    required>
                            </td>
                            <td>
                                <span class="badge text-bg-dark"><?= e($item['tracking_code'] ?? '-') ?></span>
                            </td>
                            <td><?= e($item['item_name'] ?? '-') ?></td>
                            <td><?= e(licitation_annex_unit_text($item)) ?></td>
                            <td class="text-end">
                                <?= e(format_decimal_quantity($item['total_approved_quantity'] ?? 0)) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
