<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$scopes = catalog_json_scopes();
$success = trim((string) ($_GET['success'] ?? ''));
$error = trim((string) ($_GET['error'] ?? ''));

$scopeDetails = [
    'all' => [
        'badge' => 'text-bg-dark',
        'icon' => 'bi-archive',
        'description' => 'Backup operacional em JSON de cadastros, projetos, demandas, orcamentos, DOD, anexos, fornecedores, colaboradores, kits e bibliotecas.',
        'tables' => 'Todos os escopos abaixo',
        'notes' => 'Nao substitui dump PostgreSQL nem backup dos arquivos gravados em storage/uploads.',
    ],
    'items' => [
        'badge' => 'text-bg-primary',
        'icon' => 'bi-box-seam',
        'description' => 'Itens do catalogo com categorias, tipos de unidade, composicao da embalagem, imagens e historico de versoes.',
        'tables' => 'categories, unit_types, procurement_items, procurement_item_images, procurement_item_versions',
        'notes' => 'Use para migrar ou atualizar o catalogo de materiais e servicos.',
    ],
    'projects' => [
        'badge' => 'text-bg-primary',
        'icon' => 'bi-folder2-open',
        'description' => 'Projetos de licitacao e compra direta com demandas, orcamentos, documentos, anexos, hashes, DOD e denominacoes de lote.',
        'tables' => 'procurement_projects, demand_lists, demand_items, demand_supplier_quotes, anexos, DOD, lotes e eventos de status',
        'notes' => 'Inclui dependencias administrativas, fornecedores e colaboradores usados nos projetos.',
    ],
    'requesters' => [
        'badge' => 'text-bg-info',
        'icon' => 'bi-building',
        'description' => 'Secretarias, unidades administrativas, subunidades, contatos institucionais e colaboradores vinculados.',
        'tables' => 'secretariats, requester_units, collaborators',
        'notes' => 'Use para reaproveitar a estrutura administrativa e dados de rodape/assinatura.',
    ],
    'suppliers' => [
        'badge' => 'text-bg-success',
        'icon' => 'bi-truck',
        'description' => 'Cadastro de fornecedores com dados fiscais, contato, endereco, CNAE, porte, capital social e participacao em licitacao.',
        'tables' => 'suppliers',
        'notes' => 'Nao inclui os orcamentos lancados nas demandas; eles ficam no escopo Projetos e demandas.',
    ],
    'categories' => [
        'badge' => 'text-bg-secondary',
        'icon' => 'bi-diagram-3',
        'description' => 'Categorias e subcategorias usadas para classificar itens e denominacoes por lote.',
        'tables' => 'categories',
        'notes' => 'Importe antes de itens quando for montar uma base nova por etapas.',
    ],
    'unit_types' => [
        'badge' => 'text-bg-secondary',
        'icon' => 'bi-rulers',
        'description' => 'Tipos de unidade, abreviaturas e descricoes usados nos itens e na composicao da embalagem.',
        'tables' => 'unit_types',
        'notes' => 'Importe antes de itens quando houver vinculo por identificador.',
    ],
    'kits' => [
        'badge' => 'text-bg-warning',
        'icon' => 'bi-collection',
        'description' => 'Kits de itens e seus componentes para reutilizacao em demandas e projetos.',
        'tables' => 'item_kits, item_kit_items',
        'notes' => 'Depende dos itens ja existirem quando o template usar IDs de produtos.',
    ],
    'templates' => [
        'badge' => 'text-bg-secondary',
        'icon' => 'bi-journal-text',
        'description' => 'Bibliotecas reutilizaveis de justificativas e impactos ambientais.',
        'tables' => 'justification_templates, environmental_impact_templates',
        'notes' => 'Use para padronizar textos institucionais e impactos ambientais.',
    ],
];

$scopeHelp = [];
foreach ($scopes as $value => $label) {
    $detail = $scopeDetails[$value] ?? [];
    $scopeHelp[$value] = [
        'label' => $label,
        'description' => $detail['description'] ?? 'Exportacao e importacao JSON deste escopo.',
        'tables' => $detail['tables'] ?? '',
        'notes' => $detail['notes'] ?? '',
    ];
}

require __DIR__ . '/../app/views/header.php';
?>

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Dados do sistema</h1>
        <p class="text-muted mb-0">Exporte, importe e baixe templates JSON conforme o modulo administrativo.</p>
    </div>

    <a href="/import_template_json.php?scope=all" class="btn btn-outline-primary">
        <i class="bi bi-file-earmark-code me-2"></i>Template geral
    </a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill"></i>
        <div><?= e($success) ?></div>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div><?= e($error) ?></div>
    </div>
<?php endif; ?>

<div class="alert alert-warning d-flex gap-3 align-items-start">
    <i class="bi bi-shield-exclamation fs-4"></i>
    <div>
        <div class="fw-semibold">Antes de importar em producao</div>
        <div class="small mb-0">
            Faca backup do PostgreSQL e do diretorio <code>storage/uploads</code>. A importacao JSON processa registros do escopo selecionado e pode atualizar registros existentes quando o arquivo trouxer o mesmo <code>id</code>.
        </div>
    </div>
</div>

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
                        <select name="scope" class="form-select" required data-scope-select data-scope-target="exportScopeHelp">
                            <?php foreach ($scopes as $value => $label): ?>
                                <option value="<?= e($value) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div id="exportScopeHelp" class="scope-help form-text border rounded bg-light p-3 mt-2"></div>
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
                        <select name="scope" class="form-select" required data-scope-select data-scope-target="importScopeHelp">
                            <?php foreach ($scopes as $value => $label): ?>
                                <option value="<?= e($value) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div id="importScopeHelp" class="scope-help form-text border rounded bg-light p-3 mt-2"></div>
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
    <div class="card-header fw-semibold d-flex align-items-center gap-2">
        <i class="bi bi-info-circle"></i>
        <span>Escopos disponiveis</span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 18rem;">Escopo</th>
                    <th>Conteudo exportado/importado</th>
                    <th>Tabelas principais</th>
                    <th class="text-end">Acoes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($scopes as $value => $label): ?>
                    <?php $detail = $scopeDetails[$value] ?? []; ?>
                    <tr>
                        <td>
                            <span class="badge <?= e($detail['badge'] ?? 'text-bg-secondary') ?>">
                                <i class="bi <?= e($detail['icon'] ?? 'bi-filetype-json') ?> me-1"></i><?= e($label) ?>
                            </span>
                            <div class="small text-muted mt-1"><code><?= e($value) ?></code></div>
                        </td>
                        <td>
                            <?= e($detail['description'] ?? 'Exportacao e importacao JSON deste escopo.') ?>
                            <?php if (!empty($detail['notes'])): ?>
                                <div class="small text-muted mt-1"><?= e($detail['notes']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="small"><?= e($detail['tables'] ?? '-') ?></span></td>
                        <td class="text-end text-nowrap">
                            <a href="/export_json.php?scope=<?= e($value) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-download"></i><span class="visually-hidden">Exportar <?= e($label) ?></span>
                            </a>
                            <a href="/import_template_json.php?scope=<?= e($value) ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-file-earmark-code"></i><span class="visually-hidden">Template <?= e($label) ?></span>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
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
                    <th>Modelo</th>
                    <th class="text-end">Arquivo</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($scopes as $value => $label): ?>
                    <?php $detail = $scopeDetails[$value] ?? []; ?>
                    <tr>
                        <td><?= e($label) ?></td>
                        <td><?= e($detail['description'] ?? 'Modelo JSON para importacao deste escopo.') ?></td>
                        <td class="text-end">
                            <a href="/import_template_json.php?scope=<?= e($value) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-download me-1"></i>Baixar template
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const scopeHelp = <?= json_encode($scopeHelp, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

    function renderHelp(select) {
        const target = document.getElementById(select.dataset.scopeTarget);

        if (!target) {
            return;
        }

        const data = scopeHelp[select.value] || {};
        const notes = data.notes ? '<div class="small text-muted mt-1">' + data.notes + '</div>' : '';
        const tables = data.tables ? '<div class="small mt-1"><span class="fw-semibold">Tabelas:</span> ' + data.tables + '</div>' : '';

        target.innerHTML = '<div class="fw-semibold">' + (data.label || select.value) + '</div>'
            + '<div>' + (data.description || 'Exportacao e importacao JSON deste escopo.') + '</div>'
            + tables
            + notes;
    }

    document.querySelectorAll('[data-scope-select]').forEach(function (select) {
        select.addEventListener('change', function () {
            renderHelp(select);
        });

        renderHelp(select);
    });
});
</script>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
