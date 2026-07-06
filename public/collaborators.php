<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';
require_once __DIR__ . '/../app/demand_confirmations.php';

$q = trim((string) ($_GET['q'] ?? ''));
$collaborators = get_collaborators(false, $q);
$schemaAvailable = collaborators_table_exists();

require __DIR__ . '/../app/views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Colaboradores</h1>
        <p class="text-muted mb-0">Base de responsaveis, requisitantes e tecnicos usados nas confirmacoes de demanda.</p>
    </div>

    <a href="/collaborator_form.php" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i>Novo colaborador
    </a>
</div>

<?php if (!$schemaAvailable): ?>
    <div class="alert alert-warning">
        A tabela de colaboradores ainda nao existe. Rode o schema atualizado no banco de dados.
    </div>
<?php endif; ?>

<form method="get" class="card card-body mb-3 shadow-sm">
    <div class="row g-3 align-items-end">
        <div class="col-lg-10">
            <label class="form-label">Pesquisar</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" name="q" class="form-control" placeholder="Nome, CPF, matricula, cargo, setor ou e-mail" value="<?= e($q) ?>">
            </div>
        </div>
        <div class="col-lg-2 d-grid">
            <button class="btn btn-primary"><i class="bi bi-funnel"></i>Filtrar</button>
        </div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Colaborador</th>
                    <th>Documento</th>
                    <th>Contato</th>
                    <th>Status</th>
                    <th class="text-end">Acoes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$collaborators): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">Nenhum colaborador cadastrado.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($collaborators as $collaborator): ?>
                    <tr>
                        <td>
                            <strong><?= e($collaborator['name']) ?></strong>
                            <?php if (!empty($collaborator['role'])): ?>
                                <div class="small text-muted"><?= e($collaborator['role']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($collaborator['department'])): ?>
                                <div class="small text-muted"><?= e($collaborator['department']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= e($collaborator['document_number'] ? format_brazil_document($collaborator['document_number']) : '-') ?>
                            <?php if (!empty($collaborator['registration_number'])): ?>
                                <div class="small text-muted">Matricula: <?= e($collaborator['registration_number']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= e($collaborator['email'] ?: '-') ?>
                            <?php if (!empty($collaborator['phone'])): ?>
                                <div class="small text-muted"><?= e(format_brazil_phone($collaborator['phone'])) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= boolish($collaborator['is_active'] ?? true, true) ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                <?= boolish($collaborator['is_active'] ?? true, true) ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="/collaborator_form.php?id=<?= (int) $collaborator['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                            <form action="/collaborator_toggle.php" method="post" class="d-inline">
                                <input type="hidden" name="id" value="<?= (int) $collaborator['id'] ?>">
                                <input type="hidden" name="is_active" value="<?= boolish($collaborator['is_active'] ?? true, true) ? '0' : '1' ?>">
                                <button class="btn btn-sm btn-outline-secondary">
                                    <?= boolish($collaborator['is_active'] ?? true, true) ? 'Desativar' : 'Ativar' ?>
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