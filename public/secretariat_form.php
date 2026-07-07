<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$id = (int) ($_GET['id'] ?? 0);
$secretariat = $id ? find_secretariat($id) : null;

if ($id && !$secretariat) {
    http_response_code(404);
    exit('Secretaria nao encontrada.');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'is_active' => isset($_POST['is_active']),
    ];

    if (!$data['name']) {
        $errors[] = 'O nome da secretaria e obrigatorio.';
    }

    if (!$errors) {
        if ($secretariat) {
            update_secretariat((int) $secretariat['id'], $data);
        } else {
            create_secretariat($data);
        }

        redirect('/requester_units.php');
    }

    $secretariat = array_merge($secretariat ?? [], $data);
}

require __DIR__ . '/../app/views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><?= $secretariat ? 'Editar secretaria' : 'Nova secretaria' ?></h1>
        <p class="text-muted mb-0">Organize as unidades administrativas por secretaria.</p>
    </div>

    <a href="/requester_units.php" class="btn btn-outline-secondary">
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
        <div class="col-md-8">
            <label class="form-label">Nome da secretaria</label>
            <input type="text" name="name" class="form-control" required value="<?= e(old($secretariat ?? [], 'name')) ?>">
        </div>

        <div class="col-md-4 d-flex align-items-end">
            <div class="form-check form-switch">
                <input
                    class="form-check-input"
                    type="checkbox"
                    role="switch"
                    id="is_active"
                    name="is_active"
                    <?= old($secretariat ?? [], 'is_active', true) ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_active">Ativa</label>
            </div>
        </div>

        <div class="col-12 d-flex justify-content-end gap-2">
            <a href="/requester_units.php" class="btn btn-outline-secondary">Cancelar</a>
            <button class="btn btn-primary">Salvar</button>
        </div>
    </div>
</form>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
