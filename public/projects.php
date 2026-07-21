<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$projects = get_projects();
$canManageProjects = auth_can('projects.manage');

require __DIR__ . '/../app/views/header.php';

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Projetos de Compras e Licitacoes</h1>
        <p class="text-muted mb-0">
            Organize licitacoes, compras diretas, demandas por unidade/setor e consolidacao de quantitativos.
        </p>
    </div>

    <?php if ($canManageProjects): ?>
    <a href="/project_form.php" class="btn btn-primary">
        Novo projeto
    </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Projeto</th>
                    <th>Modalidade</th>
                    <th>Status</th>
                    <th>Criado em</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>

            <tbody>
                <?php if (!$projects): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            Nenhum projeto cadastrado.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($projects as $project): ?>
                    <tr>
                        <td>
                            <strong><?= e($project['name']) ?></strong>
                            <div class="small text-muted">
                                <?= e(mb_strimwidth((string) $project['description'], 0, 100, '...')) ?>
                            </div>
                        </td>

                        <td>
                            <span class="badge <?= e(project_process_type_badge_class($project['process_type'] ?? null)) ?>">
                                <?= e(project_process_type_label($project['process_type'] ?? null)) ?>
                            </span>
                        </td>

                        <td>
                            <span class="badge <?= e(project_status_badge_class($project['status'] ?? null)) ?>">
                                <?= e(project_status_label($project['status'] ?? null)) ?>
                            </span>
                        </td>

                        <td><?= e($project['created_at']) ?></td>

                        <td class="text-end">
                            <a href="/project_show.php?id=<?= (int) $project['id'] ?>" class="btn btn-sm btn-outline-primary">
                                Abrir
                            </a>

                            <?php if ($canManageProjects): ?>
                            <a href="/project_form.php?id=<?= (int) $project['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                Editar
                            </a>

                            <form
                                action="/project_duplicate.php"
                                method="post"
                                class="d-inline"
                                onsubmit="return confirm('Deseja duplicar este projeto?')">

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int) $project['id'] ?>">

                                <button class="btn btn-sm btn-outline-success">
                                    Duplicar
                                </button>
                            </form>
                            <?php if (!project_is_locked($project)): ?>
                                <form action="/project_delete.php" method="post" class="d-inline" onsubmit="return confirm('Deseja excluir este projeto?')">
                                    <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger">
                                        Excluir
                                    </button>
                                </form>
                            <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>

        </table>
    </div>
</div>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
