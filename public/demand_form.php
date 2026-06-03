<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$projectId = (int) ($_GET['project_id'] ?? $_POST['project_id'] ?? 0);
$project = find_project($projectId);

if (!$project) {
    http_response_code(404);
    exit('Projeto não encontrado.');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'project_id' => $projectId,
        'name' => trim($_POST['name'] ?? ''),
        'requester_department' => trim($_POST['requester_department'] ?? ''),
        'responsible_name' => trim($_POST['responsible_name'] ?? ''),
        'notes' => trim($_POST['notes'] ?? ''),
    ];

    if (!$data['name']) {
        $errors[] = 'O nome da demanda é obrigatório.';
    }

    if (!$errors) {
        $demandId = create_demand_list($data);
        redirect('/demand_show.php?id=' . $demandId);
    }
}

require __DIR__ . '/../app/views/header.php';

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Nova demanda</h1>
        <p class="text-muted mb-0">
            Projeto: <?= e($project['name']) ?>
        </p>
    </div>

    <a href="/project_show.php?id=<?= (int) $project['id'] ?>" class="btn btn-outline-secondary">
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

<form method="post" class="card card-body shadow-sm">
    <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Nome da demanda</label>
            <input type="text" name="name" class="form-control" required placeholder="Ex.: Demanda da EMEF">
        </div>

        <div class="col-md-6">
            <label class="form-label">Unidade/Setor demandante</label>
            <input type="text" name="requester_department" class="form-control" placeholder="Ex.: EMEF">
        </div>

        <div class="col-md-6">
            <label class="form-label">Responsável</label>
            <input type="text" name="responsible_name" class="form-control" placeholder="Ex.: Diretor(a), Coordenador(a), Secretário(a)">
        </div>

        <div class="col-12">
            <label class="form-label">Observações</label>
            <textarea name="notes" rows="4" class="form-control"></textarea>
        </div>

        <div class="col-12 d-flex justify-content-end gap-2">
            <a href="/project_show.php?id=<?= (int) $project['id'] ?>" class="btn btn-outline-secondary">
                Cancelar
            </a>

            <button class="btn btn-primary">
                Salvar
            </button>
        </div>
    </div>
</form>

<?php require __DIR__ . '/../app/views/footer.php'; ?>