<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$id = (int) ($_GET['id'] ?? 0);
$item = find_item($id);
$canManageCatalog = auth_can('catalog.manage');

if (!$item) {
    http_response_code(404);
    exit('Item não encontrado.');
}

require __DIR__ . '/../app/views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><?= e($item['name']) ?></h1>
        <p class="text-muted mb-0">
            Código de rastreio:
            <span class="badge text-bg-dark"><?= e($item['tracking_code']) ?></span>
        </p>
    </div>

    <div class="d-flex gap-2">
        <?php if ($canManageCatalog): ?>
        <a href="/item_form.php?id=<?= (int) $item['id'] ?>" class="btn btn-outline-primary">Editar</a>
        <form
            action="/item_duplicate.php"
            method="post"
            class="d-inline"
            onsubmit="return confirm('Deseja copiar este item?')">

            <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">

            <button class="btn btn-outline-success">
                Copiar item
            </button>
        </form>
        <?php endif; ?>
        <a href="/" class="btn btn-outline-secondary">Voltar</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header fw-semibold">Classificação</div>
            <div class="card-body">
                <dl class="mb-0">
                    <dt>Categoria</dt>
                    <dd><?= e($item['category_name'] ?? '-') ?></dd>

                    <dt>Subcategoria</dt>
                    <dd><?= e($item['subcategory_name'] ?? '-') ?></dd>

                    <dt>Tipo de unidade</dt>
                    <dd>
                        <?= e($item['unit_type_name'] ?? '-') ?>

                        <?php if (!empty($item['unit_type_abbreviation'])): ?>
                            <span class="text-muted">
                                (<?= e($item['unit_type_abbreviation']) ?>)
                            </span>
                        <?php endif; ?>
                    </dd>

                    <dt>Conteúdo da embalagem</dt>
                    <dd><?= render_package_content($item) ?></dd>

                    <dt>Classificação do item</dt>
                    <dd>
                        <span class="badge <?= e(item_supply_classification_badge_class($item)) ?>">
                            <?= e(item_supply_classification_label($item)) ?>
                        </span>
                    </dd>

                    <dt>Nível</dt>
                    <dd><span class="badge text-bg-info"><?= e($item['level']) ?></span></dd>

                    <dt>Status</dt>
                    <dd>
                        <span class="badge <?= e(item_status_badge_class($item['status'] ?? null)) ?>">
                            <?= e(item_status_label($item['status'] ?? null)) ?>
                        </span>
                    </dd>

                    <dt>Criado em</dt>
                    <dd><?= e($item['created_at']) ?></dd>
                </dl>
            </div>
        </div>
    </div>

    <?php $images = get_item_images((int) $item['id']); ?>

    <?php if ($images): ?>
        <div class="col-12">
        <div class="card mb-4">
            <div class="card-header fw-semibold">
                Imagens ilustrativas
            </div>

            <div class="card-body">
                <div class="row g-3">

                    <?php foreach ($images as $image): ?>
                        <div class="col-md-3">

                            <div class="border rounded p-2 h-100">

                                <img
                                    src="<?= e($image['image_path']) ?>"
                                    class="img-fluid rounded mb-2"
                                    style="height: 160px; width: 100%; object-fit: cover;">

                                <?php if ($image['is_primary']): ?>
                                    <span class="badge text-bg-success mb-2">
                                        Principal
                                    </span>
                                <?php endif; ?>

                                <div class="d-flex gap-2 mt-2">

                                    <?php if (!$image['is_primary']): ?>
                                        <form action="/item_image_primary.php" method="post">
                                            <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                            <input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>">

                                            <button class="btn btn-sm btn-outline-primary">
                                                Definir principal
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <form
                                        action="/item_image_delete.php"
                                        method="post"
                                        onsubmit="return confirm('Deseja remover esta imagem?')">

                                        <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                        <input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>">

                                        <button class="btn btn-sm btn-outline-danger">
                                            Remover
                                        </button>
                                    </form>

                                </div>

                            </div>

                        </div>
                    <?php endforeach; ?>

                </div>

                <p class="text-muted small mt-3 mb-0">
                    Imagens meramente ilustrativas, utilizadas exclusivamente como referência visual do objeto pretendido,
                    não constituindo indicação, preferência ou vinculação a marca, modelo, fornecedor, fabricante ou solução proprietária específica.
                </p>
            </div>
        </div>
        </div>
    <?php endif; ?>

    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header fw-semibold">Especificação técnica</div>
            <div class="card-body">
                <?= render_item_specification_html($item['specification']) ?>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header fw-semibold">Justificativa</div>
            <div class="card-body">
                <p class="mb-0"><?= nl2br(e($item['justification'])) ?></p>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header fw-semibold">Condições de fornecimento</div>
            <div class="card-body">
                <?php if (item_supply_conditions_migrated($item)): ?>
                    <dl class="mb-0">
                        <dt>Garantia mínima</dt>
                        <dd>
                            <strong><?= (int) $item['warranty_months'] ?> mes(es)</strong>
                            <div class="text-muted mt-1"><?= nl2br(e($item['warranty'])) ?></div>
                        </dd>

                        <dt>Validade mínima</dt>
                        <dd>
                            <?php if (boolish($item['minimum_validity_required'] ?? false, false)): ?>
                                <strong><?= (int) $item['minimum_validity_months'] ?> mes(es)</strong>
                                <div class="text-muted mt-1"><?= nl2br(e($item['minimum_validity_text'] ?? '')) ?></div>
                            <?php else: ?>
                                <span class="text-muted">Não aplicável.</span>
                            <?php endif; ?>
                        </dd>

                        <?php if (!empty($item['validity_exception_justification'])): ?>
                            <dt>Justificativa da exceção</dt>
                            <dd><?= nl2br(e($item['validity_exception_justification'])) ?></dd>
                        <?php endif; ?>
                    </dl>
                <?php else: ?>
                    <div class="alert alert-warning mb-3">
                        Este item ainda usa o modelo legado. A classificação e os prazos serão obrigatórios na próxima edição.
                    </div>
                    <p class="mb-0"><?= nl2br(e($item['warranty'] ?: 'Garantia legada não informada.')) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header fw-semibold">Possíveis impactos ambientais</div>
            <div class="card-body">
                <?= render_environmental_impacts_list($item['environmental_impacts']) ?>
            </div>
        </div>
    </div>

    <?php $versions = get_item_versions((int) $item['id']); ?>

    <div class="col-12">
    <div class="card mt-4">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
            <span>Versionamento do Item</span>

            <?php if ($canManageCatalog): ?>
            <button
                type="button"
                class="btn btn-sm btn-outline-primary"
                data-bs-toggle="modal"
                data-bs-target="#createVersionModal">
                Criar versão
            </button>
            <?php endif; ?>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Versão</th>
                        <th>Nome</th>
                        <th>Nível</th>
                        <th>Status</th>
                        <th>Classificação</th>
                        <th>Unidade</th>
                        <th>Observação</th>
                        <th>Criada em</th>
                        <th>Responsável</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (!$versions): ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">
                                Nenhuma versão salva.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($versions as $version): ?>
                        <tr>
                            <td>
                                <span class="badge text-bg-dark">
                                    v<?= e((string) $version['version_number']) ?>
                                </span>
                            </td>

                            <td><?= e($version['name']) ?></td>

                            <td><?= e($version['level']) ?></td>

                            <td>
                                <span class="badge <?= e(item_status_badge_class($version['status'] ?? null)) ?>">
                                    <?= e(item_status_label($version['status'] ?? null)) ?>
                                </span>
                            </td>

                            <td>
                                <span class="badge <?= e(item_supply_classification_badge_class($version)) ?>">
                                    <?= e(item_supply_classification_label($version)) ?>
                                </span>
                            </td>

                            <td>
                                <?= e($version['unit_type_abbreviation'] ?: ($version['unit_type_name'] ?? '-')) ?>
                                <?php if (format_package_content($version) !== '-'): ?>
                                    <div class="small text-muted">
                                        Conteudo: <?= e(format_package_content($version)) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td><?= e($version['notes']) ?></td>

                            <td><?= e($version['created_at']) ?></td>

                            <td><?= e(($version['created_by_user_name'] ?? '') ?: '-') ?></td>

                            <td class="text-end">
                                <a
                                    href="/item_version_show.php?id=<?= (int) $version['id'] ?>"
                                    class="btn btn-sm btn-outline-primary">
                                    Ver
                                </a>

                                <?php if ($canManageCatalog): ?>
                                <form
                                    action="/item_version_restore.php"
                                    method="post"
                                    class="d-inline"
                                    onsubmit="return confirm('Restaurar esta versão? Uma versão de segurança será criada antes da restauração.')">

                                    <input type="hidden" name="version_id" value="<?= (int) $version['id'] ?>">

                                    <button
                                        class="btn btn-sm btn-outline-warning"
                                        <?= item_supply_conditions_migrated($item) ? '' : 'disabled title="Classifique e salve o item antes de restaurar versões"' ?>>
                                        Restaurar
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>

            </table>
        </div>
    </div>
    </div>

    <div
        class="modal fade"
        id="createVersionModal"
        tabindex="-1"
        aria-hidden="true">

        <div class="modal-dialog">
            <form action="/item_version_create.php" method="post" class="modal-content">
                <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">

                <div class="modal-header">
                    <h5 class="modal-title">
                        Criar versão do item
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Fechar">
                    </button>
                </div>

                <div class="modal-body">
                    <label class="form-label">Observação da versão</label>

                    <textarea
                        name="notes"
                        rows="4"
                        class="form-control"
                        placeholder="Ex.: versão revisada para licitação 2026"></textarea>

                    <div class="form-text">
                        Será salvo um snapshot da especificação, justificativa, garantia, impactos, nível, status e unidade atual.
                    </div>
                </div>

                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <button class="btn btn-primary">
                        Salvar versão
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
