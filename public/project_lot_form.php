<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$lotId = (int) ($_GET['id'] ?? $_POST['lot_id'] ?? 0);
$projectId = (int) ($_GET['project_id'] ?? $_POST['project_id'] ?? 0);
$lot = $lotId > 0 ? find_project_lot_denomination($lotId) : null;

if ($lotId > 0 && !$lot) {
    http_response_code(404);
    exit('Denominacao nao encontrada.');
}

if ($lot) {
    $projectId = (int) $lot['project_id'];
}

$project = find_project($projectId);

if (!$project) {
    http_response_code(404);
    exit('Projeto nao encontrado.');
}

$errors = [];
$projectLocked = project_is_closed($project);
$isEditing = $lot !== null;
$formData = [
    'lot_number' => (string) ($lot['lot_number'] ?? get_next_project_lot_number($projectId)),
    'name' => (string) ($lot['name'] ?? ''),
    'justification' => (string) ($lot['justification'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'lot_number' => trim((string) ($_POST['lot_number'] ?? '')),
        'name' => trim((string) ($_POST['name'] ?? '')),
        'justification' => trim((string) ($_POST['justification'] ?? '')),
    ];

    $data = [
        'project_id' => $projectId,
        'lot_number' => (int) $formData['lot_number'],
        'name' => $formData['name'],
        'justification' => $formData['justification'],
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

    if (!$errors) {
        try {
            if ($isEditing) {
                update_project_lot_denomination($lotId, $data);
                redirect('/project_lots.php?id=' . $projectId . '&success=' . rawurlencode('Denominacao atualizada.'));
            }

            $createdId = create_project_lot_denomination($data);
            redirect('/project_lot_assignments.php?id=' . $createdId . '&success=' . rawurlencode('Denominacao criada. Agora adicione os vinculos.'));
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
    }
}

require __DIR__ . '/../app/views/header.php';

?>

<div class="page-header d-flex justify-content-between align-items-start mb-4">
    <div class="page-title">
        <h1 class="h3 mb-1"><?= $isEditing ? 'Editar denominacao' : 'Nova denominacao' ?></h1>
        <p class="text-muted mb-0"><?= e($project['name']) ?></p>
    </div>

    <div class="page-actions d-flex gap-2 flex-wrap justify-content-end">
        <a href="/project_lots.php?id=<?= (int) $project['id'] ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>Voltar
        </a>
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div>
<?php endif; ?>

<?php if ($projectLocked): ?>
    <div class="alert alert-warning"><?= e(project_closed_edit_message()) ?></div>
<?php else: ?>

<div class="row g-4">
    <div class="col-lg-8">
        <form method="post" class="card">
            <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
            <?php if ($isEditing): ?>
                <input type="hidden" name="lot_id" value="<?= (int) $lotId ?>">
            <?php endif; ?>

            <div class="card-header fw-semibold">
                Dados da denominacao
            </div>

            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Numero do lote</label>
                        <input
                            type="number"
                            name="lot_number"
                            class="form-control"
                            min="1"
                            step="1"
                            value="<?= e($formData['lot_number']) ?>"
                            required>
                    </div>

                    <div class="col-md-9">
                        <label class="form-label">Denominacao</label>
                        <input
                            type="text"
                            name="name"
                            class="form-control"
                            maxlength="255"
                            value="<?= e($formData['name']) ?>"
                            required>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Justificativa do agrupamento</label>
                        <textarea name="justification" class="form-control" rows="7" required><?= e($formData['justification']) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card-footer d-flex gap-2 justify-content-end flex-wrap">
                <a href="/project_lots.php?id=<?= (int) $project['id'] ?>" class="btn btn-outline-secondary">
                    Cancelar
                </a>
                <button class="btn btn-primary">
                    <i class="bi bi-check2-circle"></i><?= $isEditing ? 'Salvar alteracoes' : 'Criar denominacao' ?>
                </button>
            </div>
        </form>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header fw-semibold">Proximo passo</div>
            <div class="card-body text-muted">
                Depois de salvar a denominacao, adicione os produtos ou categorias que pertencem ao lote. Qualquer alteracao invalida os anexos anteriores e exige nova geracao.
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
