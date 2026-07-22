<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$filters = project_bi_normalize_price_comparison_filters($_GET);
$comparison = project_bi_build_price_comparison(
    project_bi_annual_price_rows($filters),
    $filters
);
$summary = $comparison['summary'];
$dimensionLabel = project_bi_price_comparison_dimensions()[$filters['dimension']];
$fileName = sprintf(
    'comparativo-anual-precos-%d-%d.xls',
    $filters['year_from'],
    $filters['year_to']
);

send_download_headers('application/vnd.ms-excel; charset=utf-8', $fileName);

function annual_export_money(?float $value): string
{
    return $value !== null ? number_format($value, 2, ',', '.') : '';
}

function annual_export_percent(?float $value): string
{
    return $value !== null ? number_format($value * 100, 2, ',', '.') . '%' : '';
}

function annual_export_date(?string $value): string
{
    if (!$value) {
        return '';
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);

    return $date ? $date->format('d/m/Y') : $value;
}
?>
<!doctype html>
<html lang="pt-BR"
      xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
    <meta charset="utf-8">
    <title>Comparativo anual de preços</title>
    <style>
        body { color: #111827; font-family: Arial, sans-serif; font-size: 10pt; }
        h1 { color: #17365d; font-size: 18pt; margin: 0 0 8px; }
        h2 { color: #17365d; font-size: 13pt; margin: 22px 0 6px; }
        table { border-collapse: collapse; margin-bottom: 16px; width: 100%; }
        th, td { border: 1px solid #9ca3af; padding: 5px 7px; vertical-align: top; }
        th { background: #d9eaf7; color: #17365d; font-weight: bold; text-align: left; }
        .meta td:first-child { background: #eef3f8; font-weight: bold; width: 180px; }
        .number { mso-number-format: "0"; text-align: right; }
        .decimal { mso-number-format: "0.00"; text-align: right; }
        .money { mso-number-format: "\0022R$ \0022\#\,\#\#0.00"; text-align: right; }
        .date { mso-number-format: "dd/mm/yyyy"; }
        .outlier { background: #f8d7da; color: #842029; font-weight: bold; }
        .section { background: #17365d; color: #fff; font-size: 12pt; }
    </style>
</head>
<body>
    <h1>Comparativo anual de preços</h1>

    <table class="meta">
        <tr><td>Período</td><td><?= (int) $filters['year_from'] ?> a <?= (int) $filters['year_to'] ?></td></tr>
        <tr><td>Agrupamento</td><td><?= e($dimensionLabel) ?></td></tr>
        <tr><td>Preços analisados</td><td class="number" x:num="<?= (int) ($summary['count'] ?? 0) ?>"><?= (int) ($summary['count'] ?? 0) ?></td></tr>
        <tr><td>Média unitária</td><td class="money" x:num="<?= e((string) ($summary['average'] ?? 0)) ?>"><?= e(annual_export_money($summary['average'])) ?></td></tr>
        <tr><td>Mediana</td><td class="money" x:num="<?= e((string) ($summary['median'] ?? 0)) ?>"><?= e(annual_export_money($summary['median'])) ?></td></tr>
        <tr><td>Menor preço</td><td class="money" x:num="<?= e((string) ($summary['min'] ?? 0)) ?>"><?= e(annual_export_money($summary['min'])) ?></td></tr>
        <tr><td>Maior preço</td><td class="money" x:num="<?= e((string) ($summary['max'] ?? 0)) ?>"><?= e(annual_export_money($summary['max'])) ?></td></tr>
        <tr><td>Outliers</td><td class="number" x:num="<?= (int) ($summary['outlier_count'] ?? 0) ?>"><?= (int) ($summary['outlier_count'] ?? 0) ?></td></tr>
    </table>

    <h2>Resumo anual</h2>
    <table>
        <thead>
            <tr>
                <th>Ano</th>
                <th>Preços</th>
                <th>Média</th>
                <th>Mediana</th>
                <th>Moda</th>
                <th>Menor</th>
                <th>Maior</th>
                <th>Desvio padrão</th>
                <th>Coeficiente de variação</th>
                <th>Outliers</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($comparison['annual'] as $row): ?>
                <tr>
                    <td class="number" x:num="<?= (int) $row['year'] ?>"><?= (int) $row['year'] ?></td>
                    <td class="number" x:num="<?= (int) $row['count'] ?>"><?= (int) $row['count'] ?></td>
                    <td class="money" x:num="<?= e((string) ($row['average'] ?? 0)) ?>"><?= e(annual_export_money($row['average'])) ?></td>
                    <td class="money" x:num="<?= e((string) ($row['median'] ?? 0)) ?>"><?= e(annual_export_money($row['median'])) ?></td>
                    <td class="money" x:num="<?= e((string) ($row['mode'] ?? 0)) ?>"><?= e(annual_export_money($row['mode'])) ?></td>
                    <td class="money" x:num="<?= e((string) ($row['min'] ?? 0)) ?>"><?= e(annual_export_money($row['min'])) ?></td>
                    <td class="money" x:num="<?= e((string) ($row['max'] ?? 0)) ?>"><?= e(annual_export_money($row['max'])) ?></td>
                    <td class="money" x:num="<?= e((string) ($row['stddev'] ?? 0)) ?>"><?= e(annual_export_money($row['stddev'])) ?></td>
                    <td><?= e(annual_export_percent($row['coefficient_variation'])) ?></td>
                    <td class="number" x:num="<?= (int) $row['outlier_count'] ?>"><?= (int) $row['outlier_count'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Comparativo por <?= e(mb_strtolower($dimensionLabel)) ?></h2>
    <table>
        <thead>
            <tr>
                <th><?= e($dimensionLabel) ?></th>
                <th>Ano</th>
                <th>Preços</th>
                <th>Média</th>
                <th>Mediana</th>
                <th>Menor</th>
                <th>Maior</th>
                <th>Coeficiente de variação</th>
                <th>Outliers</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($comparison['groups'] as $row): ?>
                <tr>
                    <td><?= e($row['dimension_label']) ?></td>
                    <td class="number" x:num="<?= (int) $row['year'] ?>"><?= (int) $row['year'] ?></td>
                    <td class="number" x:num="<?= (int) $row['count'] ?>"><?= (int) $row['count'] ?></td>
                    <td class="money" x:num="<?= e((string) ($row['average'] ?? 0)) ?>"><?= e(annual_export_money($row['average'])) ?></td>
                    <td class="money" x:num="<?= e((string) ($row['median'] ?? 0)) ?>"><?= e(annual_export_money($row['median'])) ?></td>
                    <td class="money" x:num="<?= e((string) ($row['min'] ?? 0)) ?>"><?= e(annual_export_money($row['min'])) ?></td>
                    <td class="money" x:num="<?= e((string) ($row['max'] ?? 0)) ?>"><?= e(annual_export_money($row['max'])) ?></td>
                    <td><?= e(annual_export_percent($row['coefficient_variation'])) ?></td>
                    <td class="number" x:num="<?= (int) $row['outlier_count'] ?>"><?= (int) $row['outlier_count'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Série mensal</h2>
    <table>
        <thead>
            <tr>
                <th>Mês</th>
                <th>Preços</th>
                <th>Média</th>
                <th>Média móvel (3 meses)</th>
                <th>Menor</th>
                <th>Maior</th>
                <th>Outliers</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($comparison['monthly'] as $row): ?>
                <tr>
                    <td><?= e($row['label']) ?></td>
                    <td class="number" x:num="<?= (int) $row['count'] ?>"><?= (int) $row['count'] ?></td>
                    <td class="money" x:num="<?= e((string) ($row['average'] ?? 0)) ?>"><?= e(annual_export_money($row['average'])) ?></td>
                    <td class="money" x:num="<?= e((string) ($row['moving_average'] ?? 0)) ?>"><?= e(annual_export_money($row['moving_average'])) ?></td>
                    <td class="money" x:num="<?= e((string) ($row['min'] ?? 0)) ?>"><?= e(annual_export_money($row['min'])) ?></td>
                    <td class="money" x:num="<?= e((string) ($row['max'] ?? 0)) ?>"><?= e(annual_export_money($row['max'])) ?></td>
                    <td class="number" x:num="<?= (int) $row['outlier_count'] ?>"><?= (int) $row['outlier_count'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Observações de preço</h2>
    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Código</th>
                <th>Item</th>
                <th>Fornecedor</th>
                <th>CNPJ/CPF</th>
                <th>Categoria</th>
                <th>Secretaria</th>
                <th>Projeto</th>
                <th>Nº orçamento</th>
                <th>Preço unitário</th>
                <th>Resultado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($comparison['rows'] as $row): ?>
                <tr class="<?= !empty($row['is_outlier']) ? 'outlier' : '' ?>">
                    <td class="date"><?= e(annual_export_date($row['price_date'])) ?></td>
                    <td><?= e($row['tracking_code']) ?></td>
                    <td><?= e($row['item_name']) ?></td>
                    <td><?= e($row['supplier_name']) ?></td>
                    <td><?= e(format_brazil_document($row['supplier_document'] ?? '')) ?></td>
                    <td><?= e($row['category_name']) ?></td>
                    <td><?= e($row['secretariat_name']) ?></td>
                    <td><?= e($row['project_name']) ?></td>
                    <td><?= e($row['quote_number'] ?? '') ?></td>
                    <td class="money" x:num="<?= e((string) $row['unit_price']) ?>"><?= e(annual_export_money((float) $row['unit_price'])) ?></td>
                    <td><?= !empty($row['is_outlier']) ? 'Outlier' : 'Dentro da faixa' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
