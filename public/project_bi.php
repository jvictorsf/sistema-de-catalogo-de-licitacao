<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

function project_bi_chart_json(mixed $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function project_bi_money(?float $value): string
{
    return $value !== null ? 'R$ ' . number_format($value, 2, ',', '.') : '-';
}

function project_bi_percent_text(?float $value): string
{
    return $value !== null ? number_format($value * 100, 1, ',', '.') . '%' : '-';
}

$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'status' => array_key_exists((string) ($_GET['status'] ?? ''), project_status_options()) ? (string) $_GET['status'] : '',
];

$projectRows = project_bi_project_rows($filters);
$statusSummary = project_bi_status_summary($projectRows);
$selectedProjectId = (int) ($_GET['project_id'] ?? 0);

if ($selectedProjectId <= 0 && $projectRows) {
    $selectedProjectId = (int) $projectRows[0]['id'];
}

$selectedProject = $selectedProjectId > 0 ? find_project($selectedProjectId) : null;
$itemOptions = $selectedProject ? get_project_consolidated_items($selectedProjectId) : [];
$selectedItemId = (int) ($_GET['item_id'] ?? 0);

if ($selectedItemId <= 0 && $itemOptions) {
    $selectedItemId = (int) $itemOptions[0]['procurement_item_id'];
}

$selectedItem = null;

foreach ($itemOptions as $itemOption) {
    if ((int) $itemOption['procurement_item_id'] === $selectedItemId) {
        $selectedItem = $itemOption;
        break;
    }
}

$itemSupplierPrices = $selectedProject && $selectedItemId > 0
    ? project_bi_item_supplier_prices($selectedProjectId, $selectedItemId)
    : [];
$priceValues = array_map(static fn (array $row): float => (float) $row['average_unit_price'], $itemSupplierPrices);
$priceStats = project_bi_price_statistics($priceValues);
$outlierCount = 0;

foreach ($itemSupplierPrices as $index => $row) {
    $isOutlier = project_bi_is_outlier((float) $row['average_unit_price'], $priceStats);
    $itemSupplierPrices[$index]['is_outlier'] = $isOutlier;

    if ($isOutlier) {
        $outlierCount++;
    }
}

$supplierRanking = project_bi_supplier_ranking($selectedProjectId, 10);
$totalEstimatedValue = array_sum(array_map(static fn (array $row): float => (float) ($row['total_estimated_value'] ?? 0), $projectRows));
$totalQuotes = array_sum(array_map(static fn (array $row): int => (int) ($row['quote_count'] ?? 0), $projectRows));
$projectsWithoutSuppliers = array_values(array_filter(
    $projectRows,
    static fn (array $row): bool => (int) ($row['item_count'] ?? 0) > 0 && (int) ($row['supplier_count'] ?? 0) === 0
));

$insights = [];

if ($selectedProject && !$itemOptions) {
    $insights[] = [
        'type' => 'warning',
        'icon' => 'bi-exclamation-triangle',
        'title' => 'Projeto sem itens demandados',
        'text' => 'Cadastre demandas e itens antes de analisar fornecedores e estimativas.',
    ];
}

if ($selectedItem) {
    if (($priceStats['count'] ?? 0) === 0) {
        $insights[] = [
            'type' => 'danger',
            'icon' => 'bi-x-octagon',
            'title' => 'Item sem cotacao de fornecedor',
            'text' => 'O item selecionado ainda nao possui preco informado por fornecedor neste projeto.',
        ];
    } elseif (($priceStats['count'] ?? 0) < 3) {
        $insights[] = [
            'type' => 'warning',
            'icon' => 'bi-clipboard2-data',
            'title' => 'Poucas cotacoes para analise robusta',
            'text' => 'Ha menos de tres fornecedores com preco para este item. A media pode ficar fragil para tomada de decisao.',
        ];
    }

    if ($outlierCount > 0) {
        $insights[] = [
            'type' => 'danger',
            'icon' => 'bi-activity',
            'title' => 'Possivel preco discrepante',
            'text' => $outlierCount . ' fornecedor(es) ficaram fora da faixa esperada pelo criterio estatistico aplicado.',
        ];
    }

    if (($priceStats['coefficient_variation'] ?? 0) > 0.25) {
        $insights[] = [
            'type' => 'warning',
            'icon' => 'bi-arrow-down-up',
            'title' => 'Alta dispersao de precos',
            'text' => 'O coeficiente de variacao ficou acima de 25%, indicando diferenca relevante entre fornecedores.',
        ];
    }
}

if ($projectsWithoutSuppliers) {
    $insights[] = [
        'type' => 'info',
        'icon' => 'bi-info-circle',
        'title' => 'Projetos com itens e sem fornecedores',
        'text' => count($projectsWithoutSuppliers) . ' projeto(s) filtrado(s) possuem itens demandados, mas nenhum fornecedor cotante registrado.',
    ];
}

if (!$insights) {
    $insights[] = [
        'type' => 'success',
        'icon' => 'bi-check-circle',
        'title' => 'Nenhum ponto critico evidente',
        'text' => 'Os filtros atuais nao indicaram outliers, ausencia de fornecedores ou dispersao relevante.',
    ];
}

$projectValueChart = [
    'labels' => array_map(static fn (array $row): string => mb_strimwidth((string) $row['name'], 0, 32, '...'), array_slice($projectRows, 0, 10)),
    'values' => array_map(static fn (array $row): float => round((float) ($row['total_estimated_value'] ?? 0), 2), array_slice($projectRows, 0, 10)),
];
$statusChart = [
    'labels' => array_map(static fn (array $row): string => (string) $row['label'], $statusSummary),
    'values' => array_map(static fn (array $row): int => (int) $row['total'], $statusSummary),
];
$supplierParticipationChart = [
    'labels' => array_map(static fn (array $row): string => mb_strimwidth((string) $row['name'], 0, 28, '...'), $supplierRanking),
    'values' => array_map(static fn (array $row): int => (int) $row['quote_count'], $supplierRanking),
];
$itemSupplierChart = [
    'labels' => array_map(static fn (array $row): string => mb_strimwidth((string) $row['supplier_name'], 0, 28, '...'), $itemSupplierPrices),
    'values' => array_map(static fn (array $row): float => round((float) $row['average_unit_price'], 2), $itemSupplierPrices),
    'colors' => array_map(static fn (array $row): string => !empty($row['is_outlier']) ? '#dc3545' : '#0d6efd', $itemSupplierPrices),
];

require __DIR__ . '/../app/views/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-start mb-4">
    <div class="page-title">
        <h1 class="h3 mb-1">Gestao de projetos</h1>
        <p class="text-muted mb-0">Business Intelligence governamental para valores, fornecedores, itens e riscos de precificacao.</p>
    </div>

    <div class="page-actions d-flex gap-2 flex-wrap justify-content-end">
        <a href="/projects.php" class="btn btn-outline-primary">
            <i class="bi bi-folder2-open"></i>Projetos
        </a>
        <a href="/dashboard.php" class="btn btn-outline-secondary">
            <i class="bi bi-speedometer2"></i>Dashboard
        </a>
    </div>
</div>

<form method="get" class="card card-body mb-4 project-bi-filter">
    <div class="row g-3 align-items-end">
        <div class="col-lg-3">
            <label class="form-label">Pesquisar projeto</label>
            <input type="search" name="q" class="form-control" value="<?= e($filters['q']) ?>" placeholder="Nome ou descricao">
        </div>
        <div class="col-md-6 col-lg-2">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="">Todos</option>
                <?php foreach (project_status_options() as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-4">
            <label class="form-label">Projeto analisado</label>
            <select name="project_id" class="form-select" onchange="this.form.item_id.value = ''; this.form.submit();">
                <option value="0">Selecione...</option>
                <?php foreach ($projectRows as $projectOption): ?>
                    <option value="<?= (int) $projectOption['id'] ?>" <?= $selectedProjectId === (int) $projectOption['id'] ? 'selected' : '' ?>>
                        <?= e($projectOption['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-3">
            <label class="form-label">Item dentro do projeto</label>
            <select name="item_id" class="form-select">
                <option value="0">Todos/primeiro item</option>
                <?php foreach ($itemOptions as $itemOption): ?>
                    <option value="<?= (int) $itemOption['procurement_item_id'] ?>" <?= $selectedItemId === (int) $itemOption['procurement_item_id'] ? 'selected' : '' ?>>
                        <?= e(($itemOption['tracking_code'] ?? '-') . ' - ' . ($itemOption['item_name'] ?? '-')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 d-flex justify-content-end gap-2 flex-wrap">
            <a href="/project_bi.php" class="btn btn-outline-secondary">
                <i class="bi bi-x-lg"></i>Limpar
            </a>
            <button class="btn btn-primary">
                <i class="bi bi-funnel"></i>Aplicar filtros
            </button>
        </div>
    </div>
</form>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card card-body dashboard-kpi">
            <div class="dashboard-kpi-icon text-bg-primary"><i class="bi bi-folder2-open"></i></div>
            <div>
                <div class="text-muted small">Projetos filtrados</div>
                <div class="h3 mb-0"><?= count($projectRows) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card card-body dashboard-kpi">
            <div class="dashboard-kpi-icon text-bg-success"><i class="bi bi-currency-dollar"></i></div>
            <div>
                <div class="text-muted small">Valor estimado filtrado</div>
                <div class="h4 mb-0"><?= e(project_bi_money((float) $totalEstimatedValue)) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card card-body dashboard-kpi">
            <div class="dashboard-kpi-icon text-bg-info"><i class="bi bi-receipt"></i></div>
            <div>
                <div class="text-muted small">Orcamentos vinculados</div>
                <div class="h3 mb-0"><?= (int) $totalQuotes ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card card-body dashboard-kpi">
            <div class="dashboard-kpi-icon text-bg-danger"><i class="bi bi-activity"></i></div>
            <div>
                <div class="text-muted small">Outliers do item</div>
                <div class="h3 mb-0"><?= (int) $outlierCount ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header fw-semibold">Projetos por valor estimado</div>
            <div class="card-body"><div class="project-bi-chart"><canvas id="projectValueChart"></canvas></div></div>
        </div>
    </div>
    <div class="col-xl-3">
        <div class="card h-100">
            <div class="card-header fw-semibold">Projetos por status</div>
            <div class="card-body"><div class="project-bi-chart"><canvas id="statusChart"></canvas></div></div>
        </div>
    </div>
    <div class="col-xl-3">
        <div class="card h-100">
            <div class="card-header fw-semibold">Fornecedores mais presentes</div>
            <div class="card-body"><div class="project-bi-chart"><canvas id="supplierParticipationChart"></canvas></div></div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                    <div class="fw-semibold">Fornecedores do item selecionado</div>
                    <div class="text-muted small"><?= $selectedItem ? e($selectedItem['item_name']) : 'Nenhum item selecionado' ?></div>
                </div>
                <?php if ($selectedProject): ?>
                    <span class="badge <?= e(project_status_badge_class($selectedProject['status'] ?? null)) ?>"><?= e(project_status_label($selectedProject['status'] ?? null)) ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body"><div class="project-bi-chart project-bi-chart-wide"><canvas id="itemSupplierChart"></canvas></div></div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header fw-semibold">Estatistica do item</div>
            <div class="card-body project-bi-stat-grid">
                <div><span>Fornecedores</span><strong><?= (int) ($priceStats['count'] ?? 0) ?></strong></div>
                <div><span>Media</span><strong><?= e(project_bi_money($priceStats['average'])) ?></strong></div>
                <div><span>Mediana</span><strong><?= e(project_bi_money($priceStats['median'])) ?></strong></div>
                <div><span>Moda</span><strong><?= e(project_bi_money($priceStats['mode'])) ?></strong></div>
                <div><span>Menor</span><strong><?= e(project_bi_money($priceStats['min'])) ?></strong></div>
                <div><span>Maior</span><strong><?= e(project_bi_money($priceStats['max'])) ?></strong></div>
                <div><span>Desvio padrao</span><strong><?= e(project_bi_money($priceStats['stddev'])) ?></strong></div>
                <div><span>Coef. variacao</span><strong><?= e(project_bi_percent_text($priceStats['coefficient_variation'])) ?></strong></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header fw-semibold">Achados administrativos</div>
            <div class="card-body">
                <?php foreach ($insights as $insight): ?>
                    <div class="project-bi-insight project-bi-insight-<?= e($insight['type']) ?>">
                        <i class="bi <?= e($insight['icon']) ?>"></i>
                        <div>
                            <strong><?= e($insight['title']) ?></strong>
                            <span><?= e($insight['text']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header fw-semibold">Comparativo de fornecedores do item</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Fornecedor</th>
                            <th>Cotacoes</th>
                            <th>Media</th>
                            <th>Menor</th>
                            <th>Maior</th>
                            <th>Status analitico</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$itemSupplierPrices): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Nenhum preco de fornecedor para o item selecionado.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($itemSupplierPrices as $row): ?>
                            <tr>
                                <td>
                                    <strong><?= e($row['supplier_name']) ?></strong>
                                    <?php if (!empty($row['supplier_document'])): ?>
                                        <div class="small text-muted"><?= e(format_brazil_document($row['supplier_document'])) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int) $row['quote_count'] ?></td>
                                <td class="fw-semibold"><?= e(project_bi_money((float) $row['average_unit_price'])) ?></td>
                                <td><?= e(project_bi_money((float) $row['min_unit_price'])) ?></td>
                                <td><?= e(project_bi_money((float) $row['max_unit_price'])) ?></td>
                                <td>
                                    <?php if (!empty($row['is_outlier'])): ?>
                                        <span class="badge text-bg-danger">Outlier</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-success">Dentro da faixa</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header fw-semibold">Projetos filtrados</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 project-bi-project-table">
            <thead class="table-light">
                <tr>
                    <th>Projeto</th>
                    <th>Status</th>
                    <th>Demandas</th>
                    <th>Itens</th>
                    <th>Fornecedores</th>
                    <th>Orcamentos</th>
                    <th>Valor estimado</th>
                    <th class="text-end">Acao</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$projectRows): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Nenhum projeto encontrado pelos filtros.</td></tr>
                <?php endif; ?>
                <?php foreach ($projectRows as $row): ?>
                    <tr>
                        <td><strong><?= e($row['name']) ?></strong></td>
                        <td><span class="badge <?= e(project_status_badge_class($row['status'] ?? null)) ?>"><?= e(project_status_label($row['status'] ?? null)) ?></span></td>
                        <td><?= (int) $row['demand_count'] ?></td>
                        <td><?= (int) $row['item_count'] ?></td>
                        <td><?= (int) $row['supplier_count'] ?></td>
                        <td><?= (int) $row['quote_count'] ?></td>
                        <td class="fw-semibold"><?= e(project_bi_money((float) $row['total_estimated_value'])) ?></td>
                        <td class="text-end"><a href="/project_show.php?id=<?= (int) $row['id'] ?>" class="btn btn-sm btn-outline-primary">Abrir</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (!window.Chart) {
        return;
    }

    const moneyFormatter = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
    const projectValue = <?= project_bi_chart_json($projectValueChart) ?>;
    const status = <?= project_bi_chart_json($statusChart) ?>;
    const supplierParticipation = <?= project_bi_chart_json($supplierParticipationChart) ?>;
    const itemSupplier = <?= project_bi_chart_json($itemSupplierChart) ?>;

    function barChart(id, labels, values, label, color, currency) {
        const canvas = document.getElementById(id);

        if (!canvas) {
            return;
        }

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{ label: label, data: values, backgroundColor: color, borderRadius: 6 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { callback: value => currency ? moneyFormatter.format(value) : value } } }
            }
        });
    }

    barChart('projectValueChart', projectValue.labels, projectValue.values, 'Valor estimado', '#0d6efd', true);
    barChart('supplierParticipationChart', supplierParticipation.labels, supplierParticipation.values, 'Cotacoes', '#198754', false);
    barChart('itemSupplierChart', itemSupplier.labels, itemSupplier.values, 'Preco medio', itemSupplier.colors, true);

    const statusCanvas = document.getElementById('statusChart');

    if (statusCanvas) {
        new Chart(statusCanvas, {
            type: 'doughnut',
            data: {
                labels: status.labels,
                datasets: [{ data: status.values, backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#6c757d', '#20c997'] }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
        });
    }
});
</script>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
