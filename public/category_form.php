<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$id = isset($_GET['id'])
    ? (int) $_GET['id']
    : null;

$category = $id
    ? find_category($id)
    : null;

$categories = get_parent_categories();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $data = [
        'parent_id' => (int) ($_POST['parent_id'] ?? 0),
        'name' => trim($_POST['name'] ?? ''),
    ];

    if (!$data['name']) {
        $errors[] = 'O nome é obrigatório.';
    }

    if (!$errors) {

        if ($category) {

            update_category(
                (int) $category['id'],
                $data
            );

        } else {

            create_category($data);

        }

        redirect('/categories.php');
    }

    $category = array_merge(
        $category ?? [],
        $data
    );
}

require __DIR__ . '/../app/views/header.php';

?>

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>
        <h1 class="h3 mb-1">
            <?= $category ? 'Editar categoria' : 'Nova categoria' ?>
        </h1>

        <p class="text-muted mb-0">
            Cadastre categorias e subcategorias.
        </p>
    </div>

    <a href="/categories.php" class="btn btn-outline-secondary">
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

            <label class="form-label">
                Categoria pai
            </label>

            <select
                name="parent_id"
                class="form-select">

                <option value="">
                    Nenhuma (categoria principal)
                </option>

                <?php foreach ($categories as $parent): ?>

                    <option
                        value="<?= (int) $parent['id'] ?>"
                        <?= (int) ($category['parent_id'] ?? 0) === (int) $parent['id']
                            ? 'selected'
                            : '' ?>>

                        <?= e($parent['name']) ?>

                    </option>

                <?php endforeach; ?>

            </select>

            <div class="form-text">
                Se selecionar uma categoria pai,
                será criada uma subcategoria.
            </div>

        </div>

        <div class="col-md-6">

            <label class="form-label">
                Nome
            </label>

            <input
                type="text"
                name="name"
                class="form-control"
                required
                value="<?= e($category['name'] ?? '') ?>">

        </div>

        <div class="col-12 d-flex justify-content-end gap-2">

            <a
                href="/categories.php"
                class="btn btn-outline-secondary">

                Cancelar

            </a>

            <button class="btn btn-primary">
                Salvar
            </button>

        </div>

    </div>

</form>

<?php require __DIR__ . '/../app/views/footer.php'; ?>