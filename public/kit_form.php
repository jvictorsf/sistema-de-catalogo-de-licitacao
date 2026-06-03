<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$kit = $id ? find_item_kit($id) : null;

if ($id && !$kit) {
    http_response_code(404);
    exit('Kit não encontrado.');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'is_active' => isset($_POST['is_active']),
    ];

    if (!$data['name']) {
        $errors[] = 'O nome do kit é obrigatório.';
    }

    if (!$errors) {
        if ($kit) {
            update_item_kit((int) $kit['id'], $data);
            redirect('/kit_show.php?id=' . (int) $kit['id']);
        }

        $newId = create_item_kit($data);
        redirect('/kit_show.php?id=' . $newId);
    }

    $kit = array_merge($kit ?? [], $data);
}

require __DIR__ . '/../app/views/header.php';

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">
            <?= $kit ? 'Editar kit' : 'Novo kit' ?>
        </h1>
        <p class="text-muted mb-0">
            Cadastre agrupamentos de itens recorrentes.
        </p>
    </div>

    <a href="/kits.php" class="btn btn-outline-secondary">Voltar</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $error): ?>
            <div><?= e($error) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" class="card card-body">
    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label">Nome</label>
            <input type="text" name="name" class="form-control" required value="<?= e($kit['name'] ?? '') ?>">
        </div>

        <div class="col-md-4">
            <label class="form-label d-block">Status</label>

            <div class="form-check mt-2">
                <input type="checkbox" name="is_active" class="form-check-input" <?= ($kit['is_active'] ?? true) ? 'checked' : '' ?>>
                <label class="form-check-label">Ativo</label>
            </div>
        </div>

        <div class="col-12">
            <label class="form-label">Descrição</label>
            <textarea name="description" rows="4" class="form-control"><?= e($kit['description'] ?? '') ?></textarea>
        </div>

        <div class="col-12 d-flex justify-content-end gap-2">
            <a href="/kits.php" class="btn btn-outline-secondary">Cancelar</a>
            <button class="btn btn-primary">Salvar</button>
        </div>
    </div>
</form>

<?php require __DIR__ . '/../app/views/footer.php'; ?>