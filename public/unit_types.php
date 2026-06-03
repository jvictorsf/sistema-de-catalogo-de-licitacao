<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$unitTypes = get_unit_types();

require __DIR__ . '/../app/views/header.php';

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Tipos de Unidade</h1>
        <p class="text-muted mb-0">
            Cadastre unidades como unidade, caixa, pacote, metro, licença, serviço e outros.
        </p>
    </div>

    <a href="/unit_type_form.php" class="btn btn-primary">
        Novo tipo
    </a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nome</th>
                    <th>Abreviação</th>
                    <th>Descrição</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>

            <tbody>
                <?php if (!$unitTypes): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">
                            Nenhum tipo de unidade cadastrado.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($unitTypes as $unitType): ?>
                    <tr>
                        <td><?= e($unitType['name']) ?></td>
                        <td><?= e($unitType['abbreviation']) ?></td>
                        <td><?= e($unitType['description']) ?></td>

                        <td class="text-end">
                            <a
                                href="/unit_type_form.php?id=<?= (int) $unitType['id'] ?>"
                                class="btn btn-sm btn-outline-primary">
                                Editar
                            </a>

                            <form
                                action="/unit_type_delete.php"
                                method="post"
                                class="d-inline"
                                onsubmit="return confirm('Deseja excluir este tipo de unidade?')">

                                <input type="hidden" name="id" value="<?= (int) $unitType['id'] ?>">

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