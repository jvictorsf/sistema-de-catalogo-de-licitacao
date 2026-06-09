<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$scopes = catalog_json_scopes();
$success = trim($_GET['success'] ?? '');
$error = trim($_GET['error'] ?? '');

require __DIR__ . '/../app/views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Dados do sistema</h1>
        <p class="text-muted mb-0">Exporte e importe registros em JSON por módulo.</p>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success">
        <?= e($success) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold">
                <i class="bi bi-download me-2"></i>Exportar JSON
            </div>

            <div class="card-body">
                <form action="/export_json.php" method="get" class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Escopo</label>
                        <select name="scope" class="form-select" required>
                            <?php foreach ($scopes as $value => $label): ?>
                                <option value="<?= e($value) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-primary">
                            <i class="bi bi-filetype-json me-2"></i>Exportar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold">
                <i class="bi bi-upload me-2"></i>Importar JSON
            </div>

            <div class="card-body">
                <form action="/import_json.php" method="post" enctype="multipart/form-data" class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Escopo</label>
                        <select name="scope" class="form-select" required>
                            <?php foreach ($scopes as $value => $label): ?>
                                <option value="<?= e($value) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Arquivo JSON</label>
                        <input type="file" name="json_file" class="form-control" accept="application/json,.json" required>
                    </div>

                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-success">
                            <i class="bi bi-database-up me-2"></i>Importar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header fw-semibold">
        <i class="bi bi-info-circle me-2"></i>Escopos disponiveis
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Escopo</th>
                    <th>Uso recomendado</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><span class="badge text-bg-dark">Base completa</span></td>
                    <td>Backup operacional do catalogo, projetos, demandas, kits e biblioteca.</td>
                </tr>
                <tr>
                    <td><span class="badge text-bg-primary">Itens</span></td>
                    <td>Transferir itens, imagens cadastradas e historico de versoes.</td>
                </tr>
                <tr>
                    <td><span class="badge text-bg-primary">Projetos e demandas</span></td>
                    <td>Exportar planejamentos, listas demandantes, quantitativos, fornecedores vinculados e orçamentos.</td>
                </tr>
                <tr>
                    <td><span class="badge text-bg-primary">Fornecedores</span></td>
                    <td>Transferir cadastro básico de fornecedores para reutilização em cotações.</td>
                </tr>
                <tr>
                    <td><span class="badge text-bg-secondary">Outros</span></td>
                    <td>Categorias, tipos de unidade, kits e biblioteca podem ser tratados separadamente.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header fw-semibold">
        <i class="bi bi-filetype-json me-2"></i>Templates de importacao
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Escopo</th>
                    <th>Arquivo modelo</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($scopes as $value => $label): ?>
                    <tr>
                        <td><?= e($label) ?></td>
                        <td>
                            <a href="/import_template_json.php?scope=<?= e($value) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-download"></i>Baixar template
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
