<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$unitType = $id ? find_unit_type($id) : null;

if ($id && !$unitType) {
    http_response_code(404);
    exit('Tipo de unidade não encontrado.');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'abbreviation' => trim($_POST['abbreviation'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
    ];

    if (!$data['name']) {
        $errors[] = 'O nome é obrigatório.';
    }

    if (!$errors) {
        if ($unitType) {
            update_unit_type((int) $unitType['id'], $data);
        } else {
            create_unit_type($data);
        }

        redirect('/unit_types.php');
    }

    $unitType = array_merge($unitType ?? [], $data);
}

require __DIR__ . '/../app/views/header.php';

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">
            <?= $unitType ? 'Editar tipo de unidade' : 'Novo tipo de unidade' ?>
        </h1>

        <p class="text-muted mb-0">
            Exemplo: Unidade, Caixa, Pacote, Metro, Serviço, Licença.
        </p>
    </div>

    <a href="/unit_types.php" class="btn btn-outline-secondary">
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
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Nome</label>

            <input
                type="text"
                name="name"
                class="form-control"
                required
                value="<?= e($unitType['name'] ?? '') ?>">
        </div>

        <div class="col-md-6">
            <label class="form-label">Abreviação</label>

            <input
                type="text"
                name="abbreviation"
                class="form-control"
                placeholder="Ex.: un, cx, pct, m"
                value="<?= e($unitType['abbreviation'] ?? '') ?>">
        </div>

        <div class="col-12">
            <label class="form-label">Descrição</label>

            <textarea
                name="description"
                rows="4"
                class="form-control"><?= e($unitType['description'] ?? '') ?></textarea>
        </div>

        <div class="col-12 d-flex justify-content-end gap-2">
            <a href="/unit_types.php" class="btn btn-outline-secondary">
                Cancelar
            </a>

            <button class="btn btn-primary">
                Salvar
            </button>
        </div>
    </div>
</form>

<?php require __DIR__ . '/../app/views/footer.php'; ?>