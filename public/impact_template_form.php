<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$template = $id ? find_environmental_impact_template($id) : null;
$categories = get_parent_categories();

if ($id && !$template) {
    http_response_code(404);
    exit('Impacto ambiental não encontrado.');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'title' => trim($_POST['title'] ?? ''),
        'content' => trim($_POST['content'] ?? ''),
        'category_id' => (int) ($_POST['category_id'] ?? 0),
        'is_active' => isset($_POST['is_active']),
    ];

    if (!$data['title']) {
        $errors[] = 'O título é obrigatório.';
    }

    if (!$data['content']) {
        $errors[] = 'O conteúdo é obrigatório.';
    }

    if (!$errors) {
        if ($template) {
            update_environmental_impact_template((int) $template['id'], $data);
        } else {
            create_environmental_impact_template($data);
        }

        redirect('/library.php');
    }

    $template = array_merge($template ?? [], $data);
}

require __DIR__ . '/../app/views/header.php';

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">
            <?= $template ? 'Editar impacto ambiental' : 'Novo impacto ambiental' ?>
        </h1>
        <p class="text-muted mb-0">
            Cadastre textos reutilizáveis para o campo de impactos ambientais.
        </p>
    </div>

    <a href="/library.php" class="btn btn-outline-secondary">Voltar</a>
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
            <label class="form-label">Título</label>
            <input type="text" name="title" class="form-control" required value="<?= e($template['title'] ?? '') ?>">
        </div>

        <div class="col-md-4">
            <label class="form-label">Categoria</label>
            <select name="category_id" class="form-select">
                <option value="">Geral</option>

                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>" <?= (int) ($template['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>>
                        <?= e($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12">
            <label class="form-label">Conteúdo</label>
            <textarea name="content" rows="8" class="form-control" required><?= e($template['content'] ?? '') ?></textarea>
        </div>

        <div class="col-12">
            <div class="form-check">
                <input type="checkbox" name="is_active" class="form-check-input" <?= ($template['is_active'] ?? true) ? 'checked' : '' ?>>
                <label class="form-check-label">Ativo</label>
            </div>
        </div>

        <div class="col-12 d-flex justify-content-end gap-2">
            <a href="/library.php" class="btn btn-outline-secondary">Cancelar</a>
            <button class="btn btn-primary">Salvar</button>
        </div>
    </div>
</form>

<?php require __DIR__ . '/../app/views/footer.php'; ?>