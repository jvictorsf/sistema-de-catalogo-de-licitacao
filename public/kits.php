<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$kits = get_item_kits();

require __DIR__ . '/../app/views/header.php';

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Kits de Itens</h1>
        <p class="text-muted mb-0">Agrupe itens recorrentes para adicionar rapidamente às demandas.</p>
    </div>

    <a href="/kit_form.php" class="btn btn-primary">Novo kit</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Kit</th>
                    <th>Descrição</th>
                    <th>Status</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($kits as $kit): ?>
                    <tr>
                        <td><strong><?= e($kit['name']) ?></strong></td>
                        <td><?= e($kit['description']) ?></td>
                        <td>
                            <?= $kit['is_active']
                                ? '<span class="badge text-bg-success">Ativo</span>'
                                : '<span class="badge text-bg-secondary">Inativo</span>' ?>
                        </td>
                        <td class="text-end">
                            <a href="/kit_show.php?id=<?= (int) $kit['id'] ?>" class="btn btn-sm btn-outline-primary">
                                Abrir
                            </a>

                            <a href="/kit_form.php?id=<?= (int) $kit['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                Editar
                            </a>

                            <form action="/kit_delete.php" method="post" class="d-inline" onsubmit="return confirm('Excluir este kit?')">
                                <input type="hidden" name="id" value="<?= (int) $kit['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger">
                                    Excluir
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$kits): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">
                            Nenhum kit cadastrado.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../app/views/footer.php'; ?>