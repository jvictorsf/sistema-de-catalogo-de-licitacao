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
$projectLocked = project_is_locked($project);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'delete_lot') {
            $lotId = (int) ($_POST['lot_id'] ?? 0);
            $lot = find_project_lot_denomination($lotId);

            if (!$lot || (int) $lot['project_id'] !== $projectId) {
                throw new RuntimeException('Denominacao nao encontrada neste projeto.');
            }

            delete_project_lot_denomination($lotId);
            redirect('/project_lots.php?id=' . $projectId . '&success=' . rawurlencode('Denominacao removida.'));
        }

        if ($action === 'copy_lots') {
            $sourceProjectId = (int) ($_POST['source_project_id'] ?? 0);
            $replaceExisting = !empty($_POST['replace_existing']);
            $copied = copy_project_lot_denominations_from_project($sourceProjectId, $projectId, $replaceExisting);
            redirect('/project_lots.php?id=' . $projectId . '&success=' . rawurlencode($copied . ' denominacao(oes) copiada(s).'));
        }

        if ($action === 'renumber_lots') {
            $changed = renumber_project_lots_by_insertion($projectId);
            $message = $changed > 0
                ? $changed . ' lote(s) renumerado(s) pela ordem de cadastro.'
                : 'Os lotes ja estavam sequenciados pela ordem de cadastro.';
            redirect('/project_lots.php?id=' . $projectId . '&success=' . rawurlencode($message));
        }
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
    }
}

$lots = get_project_lot_denominations($projectId);
$sourceProjects = get_projects_with_lot_denominations($projectId);

require __DIR__ . '/../app/views/header.php';

?>

<div class="page-header d-flex justify-content-between align-items-start mb-4">
    <div class="page-title">
        <h1 class="h3 mb-1">Denominacoes de lotes</h1>
        <p class="text-muted mb-0"><?= e($project['name']) ?></p>
    </div>

    <div class="page-actions d-flex gap-2 flex-wrap justify-content-end">
        <a href="/project_show.php?id=<?= (int) $project['id'] ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>Voltar
        </a>
        <?php if (!$projectLocked): ?>
            <?php if (count($lots) > 1): ?>
                <form method="post" onsubmit="return confirm('Renumerar os lotes pela ordem em que foram cadastrados?')">
                    <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
                    <input type="hidden" name="action" value="renumber_lots">
                    <button class="btn btn-outline-primary" title="Recompor a sequencia numerica dos lotes">
                        <i class="bi bi-sort-numeric-down"></i>Sequenciar lotes
                    </button>
                </form>
            <?php endif; ?>
            <a href="/project_lot_form.php?project_id=<?= (int) $project['id'] ?>" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i>Nova denominacao
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
    <div class="alert alert-warning"><?= e(project_locked_edit_message($project)) ?></div>
<?php endif; ?>

<div class="alert alert-info d-flex gap-3 align-items-start">
    <i class="bi bi-info-circle fs-5"></i>
    <div>
        <div class="fw-semibold">Agrupamento para licitacao por lote</div>
        <div>Use esta tela para consultar os lotes. Cadastro, edicao e vinculos ficam em telas proprias para evitar formularios longos e confusos.</div>
    </div>
</div>

<?php if (!$projectLocked && $sourceProjects): ?>
    <div class="card card-body mb-4">
        <form method="post" class="row g-3 align-items-end">
            <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
            <input type="hidden" name="action" value="copy_lots">

            <div class="col-lg-6">
                <label class="form-label">Copiar denominacoes de outro projeto</label>
                <select name="source_project_id" class="form-select" required>
                    <option value="">Selecione o projeto de origem</option>
                    <?php foreach ($sourceProjects as $sourceProject): ?>
                        <option value="<?= (int) $sourceProject['id'] ?>">
                            <?= e($sourceProject['name']) ?> (<?= (int) $sourceProject['lot_count'] ?> lote(s))
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-lg-3">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="replace_existing" value="1" id="replaceExistingLots">
                    <label class="form-check-label" for="replaceExistingLots">Substituir atuais</label>
                </div>
            </div>

            <div class="col-lg-3 d-grid">
                <button class="btn btn-outline-primary" onclick="return confirm('Copiar denominacoes e vinculos do projeto selecionado?')">
                    <i class="bi bi-copy"></i>Copiar denominacoes
                </button>
            </div>
        </form>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center gap-3 flex-wrap">
        <div>
            <div class="fw-semibold">Denominacoes cadastradas</div>
            <div class="text-muted small">Cada denominacao representa um lote de mercado utilizado nos anexos por lote.</div>
        </div>

        <span class="badge text-bg-light border">
            <?= count($lots) ?> lote(s)
        </span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 90px;">Lote</th>
                        <th>Denominacao</th>
                        <th>Justificativa</th>
                        <th style="width: 170px;">Vinculos</th>
                        <th class="text-end" style="width: 310px;">Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$lots): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">
                                <div class="mb-3">Nenhuma denominacao cadastrada para este projeto.</div>
                                <?php if (!$projectLocked): ?>
                                    <a href="/project_lot_form.php?project_id=<?= (int) $project['id'] ?>" class="btn btn-primary">
                                        <i class="bi bi-plus-lg"></i>Criar primeira denominacao
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($lots as $lot): ?>
                        <tr>
                            <td class="fw-semibold"><?= (int) $lot['lot_number'] ?></td>
                            <td>
                                <div class="fw-semibold"><?= e($lot['name']) ?></div>
                            </td>
                            <td class="small text-muted">
                                <?= e(mb_strimwidth((string) $lot['justification'], 0, 140, '...')) ?>
                            </td>
                            <td>
                                <span class="d-block"><?= (int) ($lot['item_assignment_count'] ?? 0) ?> produto(s)</span>
                                <span class="text-muted small"><?= (int) ($lot['category_assignment_count'] ?? 0) ?> categoria(s)</span>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                    <a
                                        href="/project_lot_assignments.php?id=<?= (int) $lot['id'] ?>"
                                        class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-link-45deg"></i>Vinculos
                                    </a>
                                    <?php if (!$projectLocked): ?>
                                    <a
                                        href="/project_lot_form.php?id=<?= (int) $lot['id'] ?>"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil-square"></i>Editar
                                    </a>
                                    <form method="post" onsubmit="return confirm('Deseja remover esta denominacao e seus vinculos?')">
                                        <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
                                        <input type="hidden" name="action" value="delete_lot">
                                        <input type="hidden" name="lot_id" value="<?= (int) $lot['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>Remover
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
