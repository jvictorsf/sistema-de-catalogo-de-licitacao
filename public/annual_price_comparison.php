<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

function annual_price_chart_json(mixed $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function annual_price_money(?float $value): string
{
    return $value !== null ? 'R$ ' . number_format($value, 2, ',', '.') : '-';
}

function annual_price_percent(?float $value): string
{
    return $value !== null ? number_format($value * 100, 1, ',', '.') . '%' : '-';
}

function annual_price_date(?string $value): string
{
    if (!$value) {
        return '-';
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);

    return $date ? $date->format('d/m/Y') : $value;
}

$filters = project_bi_normalize_price_comparison_filters($_GET);
$options = project_bi_price_comparison_options();
$sourceRows = project_bi_annual_price_rows($filters);
$comparison = project_bi_build_price_comparison($sourceRows, $filters);
$summary = $comparison['summary'];
$dimensionLabel = project_bi_price_comparison_dimensions()[$filters['dimension']];
$minimumYear = min($options['minimum_year'], $filters['year_from']);
$maximumYear = max($options['maximum_year'], $filters['year_to'], (int) date('Y'));
$yearOptions = range($maximumYear, $minimumYear);
$exportQuery = http_build_query($filters);
$outlierRows = array_values(array_filter(
    $comparison['rows'],
    static fn (array $row): bool => !empty($row['is_outlier'])
));
$visibleOutliers = array_slice($outlierRows, 0, 100);
$visibleGroups = array_slice($comparison['groups'], 0, 250);
$trendChart = [
    'labels' => array_column($comparison['monthly'], 'label'),
    'average' => array_column($comparison['monthly'], 'average'),
    'moving_average' => array_column($comparison['monthly'], 'moving_average'),
    'min' => array_column($comparison['monthly'], 'min'),
    'max' => array_column($comparison['monthly'], 'max'),
    'outlier_average' => array_column($comparison['monthly'], 'outlier_average'),
];
$annualChart = [
    'labels' => array_map('strval', array_column($comparison['annual'], 'year')),
    'average' => array_column($comparison['annual'], 'average'),
    'min' => array_column($comparison['annual'], 'min'),
    'max' => array_column($comparison['annual'], 'max'),
];
$groupChart = [
    'labels' => array_map('strval', $comparison['years']),
    'series' => $comparison['group_series'],
];

require __DIR__ . '/../app/views/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-start mb-4">
    <div class="page-title">
        <h1 class="h3 mb-1">Comparativo anual de preços</h1>
        <p class="text-muted mb-0">Evolução histórica de preços por item, fornecedor, categoria e secretaria.</p>
    </div>

    <div class="page-actions d-flex gap-2 flex-wrap justify-content-end">
        <a href="/annual_price_comparison_export.php?<?= e($exportQuery) ?>" class="btn btn-success">
            <i class="bi bi-file-earmark-spreadsheet"></i>Exportar Excel
        </a>
        <a href="/project_bi.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>Gestão de projetos
        </a>
    </div>
</div>

<form method="get" class="card card-body mb-4 project-bi-filter">
    <div class="row g-3 align-items-end">
        <div class="col-sm-6 col-lg-2">
            <label for="year_from" class="form-label">Ano inicial</label>
            <select name="year_from" id="year_from" class="form-select">
                <?php foreach ($yearOptions as $year): ?>
                    <option value="<?= $year ?>" <?= $filters['year_from'] === $year ? 'selected' : '' ?>><?= $year ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-sm-6 col-lg-2">
            <label for="year_to" class="form-label">Ano final</label>
            <select name="year_to" id="year_to" class="form-select">
                <?php foreach ($yearOptions as $year): ?>
                    <option value="<?= $year ?>" <?= $filters['year_to'] === $year ? 'selected' : '' ?>><?= $year ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6 col-lg-3">
            <label for="dimension" class="form-label">Agrupar resultados por</label>
            <select name="dimension" id="dimension" class="form-select">
                <?php foreach (project_bi_price_comparison_dimensions() as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $filters['dimension'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6 col-lg-5">
            <label for="item_id" class="form-label">Item</label>
            <select name="item_id" id="item_id" class="form-select">
                <option value="0">Todos os itens</option>
                <?php foreach ($options['items'] as $item): ?>
                    <option value="<?= (int) $item['id'] ?>" <?= $filters['item_id'] === (int) $item['id'] ? 'selected' : '' ?>>
                        <?= e(trim((string) $item['tracking_code']) . ' - ' . (string) $item['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6 col-xl-4">
            <label for="supplier_id" class="form-label">Fornecedor</label>
            <select name="supplier_id" id="supplier_id" class="form-select">
                <option value="0">Todos os fornecedores</option>
                <?php foreach ($options['suppliers'] as $supplier): ?>
                    <option value="<?= (int) $supplier['id'] ?>" <?= $filters['supplier_id'] === (int) $supplier['id'] ? 'selected' : '' ?>>
                        <?= e((string) $supplier['name'] . (!empty($supplier['document']) ? ' - ' . format_brazil_document($supplier['document']) : '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6 col-xl-4">
            <label for="category_id" class="form-label">Categoria ou subcategoria</label>
            <select name="category_id" id="category_id" class="form-select">
                <option value="0">Todas as categorias</option>
                <?php foreach ($options['categories'] as $category): ?>
                    <?php $categoryLabel = !empty($category['parent_name']) ? $category['parent_name'] . ' / ' . $category['name'] : $category['name']; ?>
                    <option value="<?= (int) $category['id'] ?>" <?= $filters['category_id'] === (int) $category['id'] ? 'selected' : '' ?>>
                        <?= e($categoryLabel) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6 col-xl-4">
            <label for="secretariat_id" class="form-label">Secretaria</label>
            <select name="secretariat_id" id="secretariat_id" class="form-select">
                <option value="0">Todas as secretarias</option>
                <?php foreach ($options['secretariats'] as $secretariat): ?>
                    <option value="<?= (int) $secretariat['id'] ?>" <?= $filters['secretariat_id'] === (int) $secretariat['id'] ? 'selected' : '' ?>>
                        <?= e($secretariat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12 d-flex justify-content-end gap-2 flex-wrap">
            <a href="/annual_price_comparison.php" class="btn btn-outline-secondary">
                <i class="bi bi-x-lg"></i>Limpar
            </a>
            <button class="btn btn-primary">
                <i class="bi bi-funnel"></i>Aplicar filtros
            </button>
        </div>
    </div>
</form>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl">
        <div class="card card-body dashboard-kpi h-100">
            <div class="dashboard-kpi-icon text-bg-primary"><i class="bi bi-receipt"></i></div>
            <div>
                <div class="text-muted small">Preços analisados</div>
                <div class="h3 mb-0"><?= (int) ($summary['count'] ?? 0) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="card card-body dashboard-kpi h-100">
            <div class="dashboard-kpi-icon text-bg-success"><i class="bi bi-calculator"></i></div>
            <div>
                <div class="text-muted small">Média unitária</div>
                <div class="h5 mb-0"><?= e(annual_price_money($summary['average'])) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="card card-body dashboard-kpi h-100">
            <div class="dashboard-kpi-icon text-bg-info"><i class="bi bi-arrow-down"></i></div>
            <div>
                <div class="text-muted small">Menor preço</div>
                <div class="h5 mb-0"><?= e(annual_price_money($summary['min'])) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="card card-body dashboard-kpi h-100">
            <div class="dashboard-kpi-icon text-bg-warning"><i class="bi bi-arrow-up"></i></div>
            <div>
                <div class="text-muted small">Maior preço</div>
                <div class="h5 mb-0"><?= e(annual_price_money($summary['max'])) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="card card-body dashboard-kpi h-100">
            <div class="dashboard-kpi-icon text-bg-danger"><i class="bi bi-exclamation-diamond"></i></div>
            <div>
                <div class="text-muted small">Outliers</div>
                <div class="h3 mb-0"><?= (int) ($summary['outlier_count'] ?? 0) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header fw-semibold">Tendência mensal e média móvel</div>
            <div class="card-body">
                <div class="project-bi-chart project-bi-chart-wide"><canvas id="trendChart"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header fw-semibold">Indicadores estatísticos</div>
            <div class="card-body project-bi-stat-grid">
                <div><span>Mediana</span><strong><?= e(annual_price_money($summary['median'])) ?></strong></div>
                <div><span>Moda</span><strong><?= e(annual_price_money($summary['mode'])) ?></strong></div>
                <div><span>Desvio padrão</span><strong><?= e(annual_price_money($summary['stddev'])) ?></strong></div>
                <div><span>Coef. variação</span><strong><?= e(annual_price_percent($summary['coefficient_variation'])) ?></strong></div>
                <div><span>Itens</span><strong><?= (int) ($summary['item_count'] ?? 0) ?></strong></div>
                <div><span>Fornecedores</span><strong><?= (int) ($summary['supplier_count'] ?? 0) ?></strong></div>
                <div><span>Categorias</span><strong><?= (int) ($summary['category_count'] ?? 0) ?></strong></div>
                <div><span>Secretarias</span><strong><?= (int) ($summary['secretariat_count'] ?? 0) ?></strong></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header fw-semibold">Resumo por ano</div>
            <div class="card-body">
                <div class="project-bi-chart"><canvas id="annualChart"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <span class="fw-semibold">Evolução por <?= e(mb_strtolower($dimensionLabel)) ?></span>
                <span class="badge text-bg-secondary">6 maiores séries</span>
            </div>
            <div class="card-body">
                <div class="project-bi-chart"><canvas id="groupChart"></canvas></div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
        <span class="fw-semibold">Comparativo anual por <?= e(mb_strtolower($dimensionLabel)) ?></span>
        <span class="text-muted small"><?= count($visibleGroups) ?> de <?= count($comparison['groups']) ?> resultados</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 price-comparison-table">
            <thead class="table-light">
                <tr>
                    <th><?= e($dimensionLabel) ?></th>
                    <th>Ano</th>
                    <th>Preços</th>
                    <th>Média</th>
                    <th>Mediana</th>
                    <th>Menor</th>
                    <th>Maior</th>
                    <th>Variação</th>
                    <th>Outliers</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$visibleGroups): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">Nenhum preço encontrado para os filtros informados.</td></tr>
                <?php endif; ?>
                <?php foreach ($visibleGroups as $group): ?>
                    <tr>
                        <td><strong><?= e($group['dimension_label']) ?></strong></td>
                        <td><?= (int) $group['year'] ?></td>
                        <td><?= (int) $group['count'] ?></td>
                        <td class="fw-semibold"><?= e(annual_price_money($group['average'])) ?></td>
                        <td><?= e(annual_price_money($group['median'])) ?></td>
                        <td><?= e(annual_price_money($group['min'])) ?></td>
                        <td><?= e(annual_price_money($group['max'])) ?></td>
                        <td><?= e(annual_price_percent($group['coefficient_variation'])) ?></td>
                        <td>
                            <?php if ((int) $group['outlier_count'] > 0): ?>
                                <span class="badge text-bg-danger"><?= (int) $group['outlier_count'] ?></span>
                            <?php else: ?>
                                <span class="badge text-bg-success">0</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
        <span class="fw-semibold">Preços discrepantes</span>
        <span class="text-muted small">Critério IQR por item e ano</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 price-comparison-table">
            <thead class="table-light">
                <tr>
                    <th>Data</th>
                    <th>Item</th>
                    <th>Fornecedor</th>
                    <th>Categoria</th>
                    <th>Secretaria</th>
                    <th>Projeto</th>
                    <th>Preço unitário</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$visibleOutliers): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Nenhum outlier identificado no período.</td></tr>
                <?php endif; ?>
                <?php foreach ($visibleOutliers as $row): ?>
                    <tr>
                        <td><?= e(annual_price_date($row['price_date'])) ?></td>
                        <td>
                            <strong><?= e($row['item_name']) ?></strong>
                            <?php if (!empty($row['tracking_code'])): ?><div class="small text-muted"><?= e($row['tracking_code']) ?></div><?php endif; ?>
                        </td>
                        <td><?= e($row['supplier_name']) ?></td>
                        <td><?= e($row['category_name']) ?></td>
                        <td><?= e($row['secretariat_name']) ?></td>
                        <td><a href="/project_show.php?id=<?= (int) $row['project_id'] ?>"><?= e($row['project_name']) ?></a></td>
                        <td class="fw-semibold text-danger"><?= e(annual_price_money((float) $row['unit_price'])) ?></td>
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

    const money = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
    const trend = <?= annual_price_chart_json($trendChart) ?>;
    const annual = <?= annual_price_chart_json($annualChart) ?>;
    const grouped = <?= annual_price_chart_json($groupChart) ?>;
    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position: 'bottom' },
            tooltip: { callbacks: { label: context => context.dataset.label + ': ' + money.format(context.parsed.y) } }
        },
        scales: {
            y: { beginAtZero: true, ticks: { callback: value => money.format(value) } }
        }
    };

    const trendCanvas = document.getElementById('trendChart');

    if (trendCanvas) {
        new Chart(trendCanvas, {
            type: 'line',
            data: {
                labels: trend.labels,
                datasets: [
                    { label: 'Média', data: trend.average, borderColor: '#0d6efd', backgroundColor: '#0d6efd', tension: .2, spanGaps: true },
                    { label: 'Média móvel (3 meses)', data: trend.moving_average, borderColor: '#fd7e14', backgroundColor: '#fd7e14', borderWidth: 3, tension: .25, spanGaps: true },
                    { label: 'Menor', data: trend.min, borderColor: '#198754', backgroundColor: '#198754', borderDash: [6, 5], pointRadius: 2, spanGaps: true },
                    { label: 'Maior', data: trend.max, borderColor: '#6f42c1', backgroundColor: '#6f42c1', borderDash: [6, 5], pointRadius: 2, spanGaps: true },
                    { label: 'Outlier', data: trend.outlier_average, borderColor: '#dc3545', backgroundColor: '#dc3545', showLine: false, pointRadius: 6, spanGaps: false }
                ]
            },
            options: commonOptions
        });
    }

    const annualCanvas = document.getElementById('annualChart');

    if (annualCanvas) {
        new Chart(annualCanvas, {
            type: 'bar',
            data: {
                labels: annual.labels,
                datasets: [
                    { label: 'Média', data: annual.average, backgroundColor: '#0d6efd' },
                    { label: 'Menor', data: annual.min, backgroundColor: '#198754' },
                    { label: 'Maior', data: annual.max, backgroundColor: '#6f42c1' }
                ]
            },
            options: commonOptions
        });
    }

    const palette = ['#0d6efd', '#198754', '#fd7e14', '#6f42c1', '#0dcaf0', '#dc3545'];
    const groupCanvas = document.getElementById('groupChart');

    if (groupCanvas) {
        new Chart(groupCanvas, {
            type: 'line',
            data: {
                labels: grouped.labels,
                datasets: grouped.series.map((series, index) => ({
                    label: series.label,
                    data: series.values,
                    borderColor: palette[index % palette.length],
                    backgroundColor: palette[index % palette.length],
                    tension: .2,
                    spanGaps: true
                }))
            },
            options: commonOptions
        });
    }
});
</script>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
