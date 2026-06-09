<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$filters = [
    'q' => trim($_GET['q'] ?? ''),
    'category_id' => (int) ($_GET['category_id'] ?? 0),
    'subcategory_id' => (int) ($_GET['subcategory_id'] ?? 0),
    'level' => trim($_GET['level'] ?? ''),
    'status' => trim($_GET['status'] ?? ''),
    'unit_type_id' => (int) ($_GET['unit_type_id'] ?? 0),
    'sort' => trim($_GET['sort'] ?? 'created_at'),
    'direction' => strtolower(trim($_GET['direction'] ?? 'desc')),
];

$items = search_items($filters);

$parentCategories = get_parent_categories();
$subcategories = get_subcategories();
$unitTypes = get_unit_types();

$statusLabels = [
    'draft' => 'Rascunho',
    'review' => 'Em revisão',
    'standardized' => 'Padronizado',
    'deprecated' => 'Descontinuado',
    'blocked' => 'Bloqueado',
];

function sort_header(string $column, string $label, array $filters): string
{
    $isCurrent = ($filters['sort'] ?? '') === $column;
    $nextDirection = $isCurrent && ($filters['direction'] ?? 'desc') === 'asc' ? 'desc' : 'asc';
    $icon = 'bi-arrow-down-up';

    if ($isCurrent) {
        $icon = ($filters['direction'] ?? 'desc') === 'asc'
            ? 'bi-sort-alpha-down'
            : 'bi-sort-alpha-up';
    }

    $params = array_filter($filters, static fn ($value) => $value !== '' && $value !== 0 && $value !== null);
    $params['sort'] = $column;
    $params['direction'] = $nextDirection;

    return sprintf(
        '<a class="table-sort-link" href="/?%s">%s <i class="bi %s"></i></a>',
        e(http_build_query($params)),
        e($label),
        e($icon)
    );
}

require __DIR__ . '/../app/views/header.php';

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Itens para Licitação</h1>
        <p class="text-muted mb-0">
            Cadastro de itens, especificações, justificativas, impactos ambientais e planejamento de demandas.
        </p>
    </div>

    <div class="d-flex gap-2">
        <a href="/catalog_export_word.php" class="btn btn-outline-primary">
            <i class="bi bi-file-earmark-word"></i>Exportar Word
        </a>

        <a href="/catalog_pdf.php" target="_blank" class="btn btn-outline-danger">
            <i class="bi bi-file-earmark-pdf"></i>PDF Catalogo
        </a>

        <a href="/item_form.php" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i>Novo item
        </a>
    </div>
</div>

<form method="get" class="card card-body mb-4">
    <input type="hidden" name="sort" value="<?= e($filters['sort']) ?>">
    <input type="hidden" name="direction" value="<?= e($filters['direction']) ?>">

    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Busca livre</label>

            <input
                type="text"
                name="q"
                class="form-control"
                placeholder="Código, nome, categoria, justificativa..."
                value="<?= e($filters['q']) ?>">
        </div>

        <div class="col-md-4">
            <label class="form-label">Categoria</label>

            <select name="category_id" id="filter_category_id" class="form-select">
                <option value="">Todas</option>

                <?php foreach ($parentCategories as $category): ?>
                    <option
                        value="<?= (int) $category['id'] ?>"
                        <?= (int) $filters['category_id'] === (int) $category['id'] ? 'selected' : '' ?>>

                        <?= e($category['name']) ?>

                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">Subcategoria</label>

            <select
                name="subcategory_id"
                id="filter_subcategory_id"
                class="form-select">

                <option value="">Todas</option>

                <?php foreach ($subcategories as $subcategory): ?>
                    <option
                        value="<?= (int) $subcategory['id'] ?>"
                        data-parent="<?= (int) $subcategory['parent_id'] ?>"
                        <?= (int) $filters['subcategory_id'] === (int) $subcategory['id'] ? 'selected' : '' ?>>

                        <?= e($subcategory['name']) ?>

                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label">Nível</label>

            <select name="level" class="form-select">
                <option value="">Todos</option>

                <?php foreach (['A', 'B', 'C'] as $level): ?>
                    <option value="<?= $level ?>" <?= $filters['level'] === $level ? 'selected' : '' ?>>
                        <?= $level ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label">Status</label>

            <select name="status" class="form-select">
                <option value="">Todos</option>

                <?php foreach ($statusLabels as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label">Tipo de unidade</label>

            <select name="unit_type_id" class="form-select">
                <option value="">Todos</option>

                <?php foreach ($unitTypes as $unitType): ?>
                    <option
                        value="<?= (int) $unitType['id'] ?>"
                        <?= (int) $filters['unit_type_id'] === (int) $unitType['id'] ? 'selected' : '' ?>>

                        <?= e($unitType['name']) ?>
                        <?= $unitType['abbreviation'] ? ' (' . e($unitType['abbreviation']) . ')' : '' ?>

                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-3 d-flex align-items-end gap-2">
            <button class="btn btn-outline-primary w-100">
                <i class="bi bi-funnel"></i>Filtrar
            </button>

            <a href="/" class="btn btn-outline-secondary">
                <i class="bi bi-x-lg"></i>Limpar
            </a>
        </div>
    </div>
</form>

<div class="card mb-3">
    <div class="card-body py-3">
        <strong><?= count($items) ?></strong>
        item(ns) encontrado(s).
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Imagem</th>
                    <th><?= sort_header('tracking_code', 'Codigo', $filters) ?></th>
                    <th><?= sort_header('name', 'Nome', $filters) ?></th>
                    <th><?= sort_header('category', 'Categoria', $filters) ?></th>
                    <th><?= sort_header('unit_type', 'Unidade', $filters) ?></th>
                    <th><?= sort_header('level', 'Nivel', $filters) ?></th>
                    <th><?= sort_header('status', 'Status', $filters) ?></th>
                    <th><?= sort_header('warranty', 'Garantia', $filters) ?></th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>

            <tbody>
                <?php if (!$items): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            Nenhum item encontrado.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($items as $item): ?>
                    <?php $primaryImage = get_item_primary_image((int) $item['id']); ?>

                    <tr>
                        <td>
                            <?php if ($primaryImage): ?>
                                <img
                                    src="<?= e($primaryImage['image_path']) ?>"
                                    class="img-thumbnail"
                                    style="width: 64px; height: 64px; object-fit: cover;">
                            <?php else: ?>
                                <span class="text-muted small">Sem imagem</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <span class="badge text-bg-dark">
                                <?= e($item['tracking_code']) ?>
                            </span>
                        </td>

                        <td>
                            <strong><?= e($item['name']) ?></strong>

                            <div class="small text-muted">
                                <?= e(mb_strimwidth($item['justification'], 0, 90, '...')) ?>
                            </div>
                        </td>

                        <td>
                            <?= e($item['category_name']) ?>

                            <?php if ($item['subcategory_name']): ?>
                                <div class="small text-muted">
                                    <?= e($item['subcategory_name']) ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= e($item['unit_type_abbreviation'] ?: ($item['unit_type_name'] ?? '-')) ?>

                            <?php if (format_package_content($item) !== '-'): ?>
                                <div class="small text-muted">
                                    <?= e(format_package_content($item)) ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <td>
                            <span class="badge text-bg-info">
                                <?= e($item['level']) ?>
                            </span>
                        </td>

                        <td>
                            <span class="badge text-bg-secondary">
                                <?= e($statusLabels[$item['status']] ?? $item['status']) ?>
                            </span>
                        </td>

                        <td>
                            <?= e(mb_strimwidth((string) $item['warranty'], 0, 40, '...')) ?>
                        </td>

                        <td class="text-end">
                            <a
                                href="/item_show.php?id=<?= (int) $item['id'] ?>"
                                class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i>Ver
                            </a>

                            <a
                                href="/item_form.php?id=<?= (int) $item['id'] ?>"
                                class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i>Editar
                            </a>

                            <form
                                action="/item_duplicate.php"
                                method="post"
                                class="d-inline"
                                onsubmit="return confirm('Deseja copiar este item?')">

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int) $item['id'] ?>">

                                <button class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-copy"></i>Copiar
                                </button>
                            </form>

                            <form
                                action="/item_delete.php"
                                method="post"
                                class="d-inline"
                                onsubmit="return confirm('Deseja excluir este item?')">

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int) $item['id'] ?>">

                                <button class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>Excluir
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>

        </table>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const category = document.getElementById('filter_category_id');
        const subcategory = document.getElementById('filter_subcategory_id');

        function filterSubcategories() {
            if (!category || !subcategory) {
                return;
            }

            const selectedParent = category.value;

            Array.from(subcategory.options).forEach(function(option, index) {
                if (index === 0) {
                    option.hidden = false;
                    return;
                }

                if (!selectedParent) {
                    option.hidden = false;
                    return;
                }

                option.hidden = option.dataset.parent !== selectedParent;
            });

            const selectedOption = subcategory.options[subcategory.selectedIndex];

            if (selectedOption && selectedOption.hidden) {
                subcategory.value = '';
            }
        }

        if (category) {
            category.addEventListener('change', filterSubcategories);
            filterSubcategories();
        }
    });
</script>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
