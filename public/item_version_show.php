<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$id = (int) ($_GET['id'] ?? 0);

$version = find_item_version($id);

if (!$version) {
    http_response_code(404);
    exit('Versão não encontrada.');
}

$item = find_item((int) $version['procurement_item_id']);

require __DIR__ . '/../app/views/header.php';

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">
            Versão v<?= e((string) $version['version_number']) ?>
        </h1>

        <p class="text-muted mb-0">
            Item: <?= e($item['tracking_code'] . ' - ' . $item['name']) ?>
        </p>
    </div>

    <div class="d-flex gap-2">
        <form
            action="/item_version_restore.php"
            method="post"
            onsubmit="return confirm('Restaurar esta versão?')">

            <input type="hidden" name="version_id" value="<?= (int) $version['id'] ?>">

            <button class="btn btn-warning">
                Restaurar versão
            </button>
        </form>

        <a href="/item_show.php?id=<?= (int) $item['id'] ?>" class="btn btn-outline-secondary">
            Voltar
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header fw-semibold">
                Dados da versão
            </div>

            <div class="card-body">
                <dl class="mb-0">
                    <dt>Nome</dt>
                    <dd><?= e($version['name']) ?></dd>

                    <dt>Nível</dt>
                    <dd><?= e($version['level']) ?></dd>

                    <dt>Status</dt>
                    <dd>
                        <span class="badge <?= e(item_status_badge_class($version['status'] ?? null)) ?>">
                            <?= e(item_status_label($version['status'] ?? null)) ?>
                        </span>
                    </dd>

                    <dt>Tipo de unidade</dt>
                    <dd>
                        <?= e($version['unit_type_name'] ?? '-') ?>

                        <?php if (!empty($version['unit_type_abbreviation'])): ?>
                            <span class="text-muted">
                                (<?= e($version['unit_type_abbreviation']) ?>)
                            </span>
                        <?php endif; ?>
                    </dd>

                    <dt>Conteúdo da embalagem</dt>
                    <dd><?= render_package_content($version) ?></dd>

                    <dt>Observação</dt>
                    <dd><?= nl2br(e($version['notes'])) ?></dd>

                    <dt>Criada em</dt>
                    <dd><?= e($version['created_at']) ?></dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header fw-semibold">
                Especificação técnica
            </div>

            <div class="card-body">
                <?= render_item_specification_html($version['specification']) ?>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header fw-semibold">
                Justificativa
            </div>

            <div class="card-body">
                <?= nl2br(e($version['justification'])) ?>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header fw-semibold">
                Garantia
            </div>

            <div class="card-body">
                <?= nl2br(e($version['warranty'])) ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header fw-semibold">
                Impactos ambientais
            </div>

            <div class="card-body">
                <?= nl2br(e($version['environmental_impacts'])) ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
