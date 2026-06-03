<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$categories = get_categories_tree();

require __DIR__ . '/../app/views/header.php';

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Categorias</h1>
        <p class="text-muted mb-0">
            Gerencie categorias e subcategorias.
        </p>
    </div>

    <a href="/category_form.php" class="btn btn-primary">
        Nova categoria
    </a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nome</th>
                    <th>Tipo</th>
                    <th>Categoria pai</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($categories as $category): ?>

                    <tr>

                        <td>
                            <?= e($category['name']) ?>
                        </td>

                        <td>
                            <?= $category['parent_id']
                                ? '<span class="badge text-bg-info">Subcategoria</span>'
                                : '<span class="badge text-bg-dark">Categoria</span>' ?>
                        </td>

                        <td>
                            <?= e($category['parent_name'] ?? '-') ?>
                        </td>

                        <td class="text-end">

                            <a
                                href="/category_form.php?id=<?= (int) $category['id'] ?>"
                                class="btn btn-sm btn-outline-primary">
                                Editar
                            </a>

                            <form
                                action="/category_delete.php"
                                method="post"
                                class="d-inline"
                                onsubmit="return confirm('Deseja excluir esta categoria?')">

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int) $category['id'] ?>">

                                <button class="btn btn-sm btn-outline-danger">
                                    Excluir
                                </button>

                            </form>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>
    </div>
</div>

<?php require __DIR__ . '/../app/views/footer.php'; ?>