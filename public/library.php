<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$justifications = get_justification_templates();
$impacts = get_environmental_impact_templates();

require __DIR__ . '/../app/views/header.php';

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Biblioteca Técnica</h1>
        <p class="text-muted mb-0">
            Gerencie textos reutilizáveis de justificativas e impactos ambientais.
        </p>
    </div>

    <div class="d-flex gap-2">
        <a href="/justification_template_form.php" class="btn btn-primary">
            Nova justificativa
        </a>

        <a href="/impact_template_form.php" class="btn btn-outline-primary">
            Novo impacto
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold">
                Justificativas
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Título</th>
                            <th>Categoria</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($justifications as $template): ?>
                            <tr>
                                <td>
                                    <strong><?= e($template['title']) ?></strong>
                                    <div class="small text-muted">
                                        <?= e(mb_strimwidth($template['content'], 0, 90, '...')) ?>
                                    </div>
                                </td>

                                <td><?= e($template['category_name'] ?? '-') ?></td>

                                <td>
                                    <?= $template['is_active']
                                        ? '<span class="badge text-bg-success">Ativo</span>'
                                        : '<span class="badge text-bg-secondary">Inativo</span>' ?>
                                </td>

                                <td class="text-end">
                                    <a href="/justification_template_form.php?id=<?= (int) $template['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        Editar
                                    </a>

                                    <form action="/justification_template_delete.php" method="post" class="d-inline" onsubmit="return confirm('Excluir esta justificativa?')">
                                        <input type="hidden" name="id" value="<?= (int) $template['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger">
                                            Excluir
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$justifications): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    Nenhuma justificativa cadastrada.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold">
                Impactos Ambientais
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Título</th>
                            <th>Categoria</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($impacts as $template): ?>
                            <tr>
                                <td>
                                    <strong><?= e($template['title']) ?></strong>
                                    <div class="small text-muted">
                                        <?= e(mb_strimwidth($template['content'], 0, 90, '...')) ?>
                                    </div>
                                </td>

                                <td><?= e($template['category_name'] ?? '-') ?></td>

                                <td>
                                    <?= $template['is_active']
                                        ? '<span class="badge text-bg-success">Ativo</span>'
                                        : '<span class="badge text-bg-secondary">Inativo</span>' ?>
                                </td>

                                <td class="text-end">
                                    <a href="/impact_template_form.php?id=<?= (int) $template['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        Editar
                                    </a>

                                    <form action="/impact_template_delete.php" method="post" class="d-inline" onsubmit="return confirm('Excluir este impacto?')">
                                        <input type="hidden" name="id" value="<?= (int) $template['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger">
                                            Excluir
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$impacts): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    Nenhum impacto cadastrado.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../app/views/footer.php'; ?>