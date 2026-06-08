<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$id = (int) ($_GET['id'] ?? 0);
$kit = find_item_kit($id);

if (!$kit) {
    http_response_code(404);
    exit('Kit nao encontrado.');
}

$kitItems = get_item_kit_items($id);
$catalogItems = search_items();

require __DIR__ . '/../app/views/header.php';

?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1"><?= e($kit['name']) ?></h1>
        <p class="text-muted mb-0"><?= e($kit['description']) ?></p>
    </div>

    <a href="/kits.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>Voltar
    </a>
</div>

<div class="card card-body mb-4">
    <h2 class="h5 mb-3">
        <i class="bi bi-plus-circle me-2"></i>Adicionar item ao kit
    </h2>

    <form action="/kit_item_add.php" method="post" class="row g-3">
        <input type="hidden" name="kit_id" value="<?= (int) $kit['id'] ?>">

        <div class="col-md-5">
            <label class="form-label">Pesquisar item</label>
            <input
                type="search"
                id="kitItemSearch"
                class="form-control"
                placeholder="Digite codigo, nome ou categoria"
                autofocus
            >
        </div>

        <div class="col-md-7">
            <label class="form-label">Item</label>

            <select name="procurement_item_id" id="kitItemSelect" class="form-select" required>
                <option value="">Selecione...</option>

                <?php foreach ($catalogItems as $item): ?>
                    <option
                        value="<?= (int) $item['id'] ?>"
                        data-search="<?= e($item['tracking_code'] . ' ' . $item['name'] . ' ' . ($item['category_name'] ?? '') . ' ' . ($item['subcategory_name'] ?? '')) ?>">
                        <?= e($item['tracking_code'] . ' - ' . $item['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div class="invalid-feedback">
                Nenhum item encontrado para a busca informada.
            </div>
        </div>

        <div class="col-md-2">
            <label class="form-label">Quantidade</label>
            <input type="number" name="quantity" class="form-control" min="0.01" step="0.01" value="1" required>
        </div>

        <div class="col-md-10">
            <label class="form-label">Observacao</label>
            <input type="text" name="notes" class="form-control">
        </div>

        <div class="col-12 text-end">
            <button class="btn btn-primary">
                <i class="bi bi-plus-lg"></i>Adicionar
            </button>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header fw-semibold">
        <i class="bi bi-list-check me-2"></i>Itens do kit
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Codigo</th>
                    <th>Item</th>
                    <th>Quantidade</th>
                    <th>Observacao</th>
                    <th class="text-end">Acoes</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($kitItems as $item): ?>
                    <tr>
                        <td>
                            <span class="badge text-bg-dark"><?= e($item['tracking_code']) ?></span>
                        </td>

                        <td><?= e($item['item_name']) ?></td>
                        <td><?= e((string) $item['quantity']) ?></td>
                        <td><?= e($item['notes']) ?></td>

                        <td class="text-end">
                            <form action="/kit_item_delete.php" method="post" class="d-inline">
                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                <input type="hidden" name="kit_id" value="<?= (int) $kit['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>Remover
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$kitItems): ?>
                    <tr>
                        <td colspan="5" class="empty-state">
                            Nenhum item no kit.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
