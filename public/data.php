<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';
require_once __DIR__ . '/../app/data_exports.php';

$scopes = catalog_json_scopes();
$formats = catalog_data_export_formats();
$success = trim((string) ($_GET['success'] ?? ''));
$error = trim((string) ($_GET['error'] ?? ''));

$scopeDetails = [
    'all' => [
        'badge' => 'text-bg-dark',
        'icon' => 'bi-archive',
        'description' => 'Cadastros, projetos, demandas, decisões e quantitativos aprovados, orçamentos, DOD, anexos, fornecedores, colaboradores, configurações do editor, kits e bibliotecas.',
        'tables' => 'Todos os escopos abaixo',
        'notes' => 'Não substitui o dump PostgreSQL nem o backup dos arquivos gravados em storage/uploads.',
    ],
    'items' => [
        'badge' => 'text-bg-primary',
        'icon' => 'bi-box-seam',
        'description' => 'Itens do catálogo com categorias, tipos de unidade, composição da embalagem, imagens e histórico de versões.',
        'tables' => 'categories, unit_types, procurement_items, procurement_item_images, procurement_item_versions',
        'notes' => 'Use para migrar ou analisar o catálogo de materiais e serviços.',
    ],
    'projects' => [
        'badge' => 'text-bg-primary',
        'icon' => 'bi-folder2-open',
        'description' => 'Projetos de licitação e compra direta com demandas, aprovações, orçamentos, documentos, anexos, hashes, DOD e denominações de lote.',
        'tables' => 'procurement_projects, demand_lists, demand_items, demand_approval_events, orçamentos, DOD, anexos, lotes e eventos de status',
        'notes' => 'Inclui dependências administrativas, fornecedores e colaboradores usados nos projetos.',
    ],
    'requesters' => [
        'badge' => 'text-bg-info',
        'icon' => 'bi-building',
        'description' => 'Secretarias, unidades administrativas, subunidades, contatos institucionais e colaboradores vinculados.',
        'tables' => 'secretariats, requester_units, collaborators',
        'notes' => 'Use para reaproveitar a estrutura administrativa e os dados de rodapé e assinatura.',
    ],
    'suppliers' => [
        'badge' => 'text-bg-success',
        'icon' => 'bi-truck',
        'description' => 'Cadastro de fornecedores com dados fiscais, contato, endereço, CNAE, porte, capital social e participação em licitação.',
        'tables' => 'suppliers',
        'notes' => 'Não inclui os orçamentos lançados nas demandas; eles ficam no escopo Projetos e demandas.',
    ],
    'categories' => [
        'badge' => 'text-bg-secondary',
        'icon' => 'bi-diagram-3',
        'description' => 'Categorias e subcategorias usadas para classificar itens e denominações por lote.',
        'tables' => 'categories',
        'notes' => 'Importe antes de itens quando for montar uma base nova por etapas.',
    ],
    'unit_types' => [
        'badge' => 'text-bg-secondary',
        'icon' => 'bi-rulers',
        'description' => 'Tipos de unidade, abreviaturas e descrições usados nos itens e na composição da embalagem.',
        'tables' => 'unit_types',
        'notes' => 'Importe antes de itens quando houver vínculo por identificador.',
    ],
    'kits' => [
        'badge' => 'text-bg-warning',
        'icon' => 'bi-collection',
        'description' => 'Kits de itens e seus componentes para reutilização em demandas e projetos.',
        'tables' => 'item_kits, item_kit_items',
        'notes' => 'Depende dos itens já existirem quando o template usar IDs de produtos.',
    ],
    'templates' => [
        'badge' => 'text-bg-secondary',
        'icon' => 'bi-journal-text',
        'description' => 'Bibliotecas reutilizáveis de justificativas e impactos ambientais.',
        'tables' => 'justification_templates, environmental_impact_templates',
        'notes' => 'Use para padronizar textos institucionais e impactos ambientais.',
    ],
];

$scopeHelp = [];
foreach ($scopes as $value => $label) {
    $detail = $scopeDetails[$value] ?? [];
    $scopeHelp[$value] = [
        'label' => $label,
        'description' => $detail['description'] ?? 'Dados disponíveis para exportação e importação JSON.',
        'tables' => $detail['tables'] ?? '',
        'notes' => $detail['notes'] ?? '',
    ];
}

require __DIR__ . '/../app/views/header.php';
?>

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Dados do sistema</h1>
        <p class="text-muted mb-0">Exporte dados em JSON, PDF, CSV ou XLSX e importe arquivos JSON por módulo administrativo.</p>
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
        <div class="fw-semibold">Antes de importar em produção</div>
        <div class="small mb-0">
            Faça backup do PostgreSQL e do diretório <code>storage/uploads</code>. A importação JSON pode atualizar registros existentes quando o arquivo trouxer o mesmo <code>id</code>.
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header fw-semibold">
                <i class="bi bi-download me-2"></i>Exportar dados
            </div>

            <div class="card-body">
                <form action="/export_data.php" method="get" class="row g-3" data-export-form>
                    <div class="col-12">
                        <label class="form-label" for="exportScope">Escopo</label>
                        <select id="exportScope" name="scope" class="form-select" required data-scope-select data-scope-target="exportScopeHelp">
                            <?php foreach ($scopes as $value => $label): ?>
                                <option value="<?= e($value) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div id="exportScopeHelp" class="scope-help form-text border rounded bg-light p-3 mt-2"></div>
                    </div>

                    <fieldset class="col-12">
                        <legend class="form-label mb-2">Formato</legend>
                        <div class="btn-group w-100" role="group" aria-label="Formato da exportação">
                            <?php foreach ($formats as $value => $format): ?>
                                <input type="radio" class="btn-check" name="format" value="<?= e($value) ?>" id="exportFormat<?= e(ucfirst($value)) ?>" autocomplete="off" <?= $value === 'json' ? 'checked' : '' ?>>
                                <label class="btn btn-outline-primary py-2" for="exportFormat<?= e(ucfirst($value)) ?>" title="<?= e($format['description']) ?>">
                                    <i class="bi <?= e($format['icon']) ?> d-block d-sm-inline me-sm-1"></i><?= e($format['label']) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-text mt-2" data-format-help><?= e($formats['json']['description']) ?></div>
                    </fieldset>

                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-primary" data-export-submit>
                            <i class="bi bi-download me-2"></i>Exportar dados
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header fw-semibold">
                <i class="bi bi-upload me-2"></i>Importar JSON
            </div>

            <div class="card-body">
                <form action="/import_json.php" method="post" enctype="multipart/form-data" class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="importScope">Escopo</label>
                        <select id="importScope" name="scope" class="form-select" required data-scope-select data-scope-target="importScopeHelp">
                            <?php foreach ($scopes as $value => $label): ?>
                                <option value="<?= e($value) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div id="importScopeHelp" class="scope-help form-text border rounded bg-light p-3 mt-2"></div>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="jsonFile">Arquivo JSON</label>
                        <input id="jsonFile" type="file" name="json_file" class="form-control" accept="application/json,.json" required>
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
        <span>Escopos disponíveis</span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 18rem;">Escopo</th>
                    <th>Conteúdo</th>
                    <th>Tabelas principais</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($scopes as $value => $label): ?>
                    <?php $detail = $scopeDetails[$value] ?? []; ?>
                    <tr>
                        <td>
                            <span class="badge <?= e($detail['badge'] ?? 'text-bg-secondary') ?>">
                                <i class="bi <?= e($detail['icon'] ?? 'bi-database') ?> me-1"></i><?= e($label) ?>
                            </span>
                            <div class="small text-muted mt-1"><code><?= e($value) ?></code></div>
                        </td>
                        <td>
                            <?= e($detail['description'] ?? 'Dados deste escopo.') ?>
                            <?php if (!empty($detail['notes'])): ?>
                                <div class="small text-muted mt-1"><?= e($detail['notes']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="small"><?= e($detail['tables'] ?? '-') ?></span></td>
                        <td class="text-end text-nowrap">
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-download me-1"></i>Exportar
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <?php foreach ($formats as $formatValue => $format): ?>
                                        <li>
                                            <a href="/export_data.php?scope=<?= e($value) ?>&amp;format=<?= e($formatValue) ?>" class="dropdown-item" <?= $formatValue === 'pdf' ? 'target="_blank" rel="noopener"' : '' ?>>
                                                <i class="bi <?= e($format['icon']) ?> me-2"></i><?= e($format['label']) ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <a href="/import_template_json.php?scope=<?= e($value) ?>" class="btn btn-sm btn-outline-secondary" title="Baixar template JSON">
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
        <i class="bi bi-filetype-json me-2"></i>Templates de importação
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
                        <td><?= e($detail['description'] ?? 'Modelo JSON para importação deste escopo.') ?></td>
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
    const formatHelp = <?= json_encode(array_map(static fn (array $format): string => $format['description'], $formats), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

    function renderScopeHelp(select) {
        const target = document.getElementById(select.dataset.scopeTarget);
        const data = scopeHelp[select.value] || {};

        if (!target) {
            return;
        }

        target.replaceChildren();

        const title = document.createElement('div');
        title.className = 'fw-semibold';
        title.textContent = data.label || select.value;
        target.appendChild(title);

        const description = document.createElement('div');
        description.textContent = data.description || 'Dados deste escopo.';
        target.appendChild(description);

        if (data.tables) {
            const tables = document.createElement('div');
            tables.className = 'small mt-1';
            tables.textContent = 'Tabelas: ' + data.tables;
            target.appendChild(tables);
        }

        if (data.notes) {
            const notes = document.createElement('div');
            notes.className = 'small text-muted mt-1';
            notes.textContent = data.notes;
            target.appendChild(notes);
        }
    }

    document.querySelectorAll('[data-scope-select]').forEach(function (select) {
        select.addEventListener('change', function () {
            renderScopeHelp(select);
        });
        renderScopeHelp(select);
    });

    const exportForm = document.querySelector('[data-export-form]');

    if (exportForm) {
        const help = exportForm.querySelector('[data-format-help]');

        function updateFormat() {
            const selected = exportForm.querySelector('input[name="format"]:checked');
            const format = selected ? selected.value : 'json';
            exportForm.target = format === 'pdf' ? '_blank' : '';

            if (help) {
                help.textContent = formatHelp[format] || '';
            }
        }

        exportForm.querySelectorAll('input[name="format"]').forEach(function (input) {
            input.addEventListener('change', updateFormat);
        });
        updateFormat();
    }
});
</script>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
