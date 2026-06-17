<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$project = $id ? find_project($id) : null;

$errors = [];
$projectLocked = $project ? project_is_closed($project) : false;
$projectInRectification = $project ? project_is_rectification($project) : false;

if ($id && !$project) {
    http_response_code(404);
    exit('Projeto não encontrado.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'status' => trim($_POST['status'] ?? 'draft'),
    ];

    if (!$data['name']) {
        $errors[] = 'O nome do projeto é obrigatório.';
    }

    if (!$errors) {
        try {
            if ($project) {
                update_project((int) $project['id'], $data);
                redirect('/project_show.php?id=' . (int) $project['id']);
            }

            $newId = create_project($data);
            redirect('/project_show.php?id=' . $newId);
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
    }

    $project = array_merge($project ?? [], $data);
    $projectLocked = $project ? project_is_closed($project) : false;
    $projectInRectification = $project ? project_is_rectification($project) : false;
}

require __DIR__ . '/../app/views/header.php';

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">
            <?= $project ? 'Editar projeto' : 'Novo projeto de contratação' ?>
        </h1>
        <p class="text-muted mb-0">
            Exemplo: Licitação de Informática 2026.
        </p>
    </div>

    <a href="/projects.php" class="btn btn-outline-secondary">
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

<?php if ($projectLocked): ?>
    <div class="alert alert-warning">
        <?= e(project_closed_edit_message()) ?>
    </div>
<?php elseif ($projectInRectification): ?>
    <div class="alert alert-danger">
        Projeto em retificacao. Depois de concluir as correcoes, altere o status para Fechado para gerar novo hash.
    </div>
<?php endif; ?>

<form method="post" class="card card-body shadow-sm">
    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label">Nome do projeto</label>
            <input
                type="text"
                name="name"
                class="form-control"
                required
                value="<?= e($project['name'] ?? '') ?>"
                <?= $projectLocked ? 'readonly' : '' ?>>
        </div>

        <div class="col-md-4">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <?php
                $statuses = [
                    'draft' => 'Rascunho',
                    'collecting' => 'Coletando demandas',
                    'review' => 'Em revisão',
                    'closed' => 'Fechado',
                ];
                ?>

                <?php foreach (project_status_options_for_form($project) as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($project['status'] ?? 'draft') === $value ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12">
            <label class="form-label">Descrição</label>
            <textarea
                name="description"
                rows="4"
                class="form-control"
                <?= $projectLocked ? 'readonly' : '' ?>><?= e($project['description'] ?? '') ?></textarea>
        </div>

        <div class="col-12 d-flex justify-content-end gap-2">
            <a href="/projects.php" class="btn btn-outline-secondary">Cancelar</a>
            <button class="btn btn-primary">Salvar</button>
        </div>
    </div>
</form>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
