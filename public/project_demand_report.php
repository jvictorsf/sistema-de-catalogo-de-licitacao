<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

function project_demand_report_export_url(string $format, string $issueDate, string $filterKey): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/project_demand_report.php';
    $query = $_GET;
    $query['format'] = $format;
    $query['issue_date'] = $issueDate;
    $query['filter'] = $filterKey;

    return $path . '?' . http_build_query($query);
}

$id = (int) ($_GET['id'] ?? 0);
$groupBy = ($_GET['group'] ?? 'unit') === 'secretariat' ? 'secretariat' : 'unit';
$withPrices = boolish($_GET['prices'] ?? false);
$filterKey = trim((string) ($_GET['filter'] ?? ''));
$format = strtolower((string) ($_GET['format'] ?? 'pdf'));
$format = in_array($format, ['pdf', 'word', 'excel'], true) ? $format : 'pdf';

$project = find_project($id);

if (!$project) {
    http_response_code(404);
    exit('Projeto nao encontrado.');
}

$groupLabel = $groupBy === 'secretariat' ? 'secretaria' : 'unidade';
$title = $withPrices
    ? 'Relatorio de demanda com precos por ' . $groupLabel
    : 'Relatorio de demanda por ' . $groupLabel;
$filterOptions = get_project_demand_report_options($id, $groupBy);

if ($filterKey === '' && count($filterOptions) === 1) {
    $filterKey = (string) $filterOptions[0]['key'];
}

$selectedFilter = null;

foreach ($filterOptions as $filterOption) {
    if ((string) $filterOption['key'] === $filterKey) {
        $selectedFilter = $filterOption;
        break;
    }
}

$hasSelectedFilter = $selectedFilter !== null;
$report = $hasSelectedFilter
    ? get_project_demand_group_report($id, $groupBy, $filterKey)
    : [
        'group_by' => $groupBy,
        'groups' => [],
        'total_quantity' => 0,
        'global_total' => 0,
    ];
$displayTitle = $hasSelectedFilter
    ? $title . ' - ' . (string) $selectedFilter['name']
    : $title;

if ($format === 'word') {
    send_download_headers('application/msword; charset=utf-8', $displayTitle . '.doc');
} elseif ($format === 'excel') {
    send_download_headers('application/vnd.ms-excel; charset=utf-8', $displayTitle . '.xls');
} else {
    header('Content-Type: text/html; charset=utf-8');
}

$isExcel = $format === 'excel';
$issueDate = annex_issue_date_value();
$issueDateText = annex_issue_date_text($issueDate);
$columnCount = $withPrices ? 5 : 2;
$filterLabel = $groupBy === 'secretariat' ? 'Secretaria' : 'Unidade';

?>
<!doctype html>
<html
    lang="pt-BR"
    <?php if ($isExcel): ?>
        xmlns:o="urn:schemas-microsoft-com:office:office"
        xmlns:x="urn:schemas-microsoft-com:office:excel"
    <?php endif; ?>>
<head>
    <meta charset="utf-8">
    <title><?= e($displayTitle) ?> - <?= e($project['name']) ?></title>

    <style>
        @page {
            size: A4 landscape;
            margin: 10mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            color: #111827;
            font-family: Arial, sans-serif;
            font-size: <?= $isExcel ? '10pt' : '9px' ?>;
            line-height: 1.3;
            margin: 0;
        }

        .print-actions {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-end;
            margin-bottom: 14px;
        }

        .print-actions .issue-date-form {
            align-items: center;
            display: inline-flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .print-actions input,
        .print-actions select {
            border: 1px solid #9ca3af;
            border-radius: 4px;
            padding: 7px 8px;
        }

        .print-actions button {
            background: #0f766e;
            border: 0;
            border-radius: 4px;
            color: #fff;
            cursor: pointer;
            font-weight: 700;
            padding: 8px 12px;
        }

        .header {
            border-bottom: 2px solid #111827;
            margin-bottom: 12px;
            padding-bottom: 10px;
            text-align: center;
        }

        .header h1,
        .header h2,
        .header p {
            margin: 0;
        }

        .header h1 {
            font-size: 14px;
            text-transform: uppercase;
        }

        .header h2 {
            font-size: 12px;
            margin-top: 4px;
            text-transform: uppercase;
        }

        .header p {
            color: #4b5563;
            font-size: 9px;
            margin-top: 4px;
        }

        table {
            border-collapse: collapse;
            table-layout: fixed;
            width: 100%;
        }

        thead {
            display: table-header-group;
        }

        th,
        td {
            border: 1px solid #6b7280;
            padding: 5px;
            vertical-align: top;
        }

        th {
            background: #e5e7eb;
            font-size: 8px;
            text-align: left;
            text-transform: uppercase;
        }

        tbody tr:nth-child(even) {
            background: #f9fafb;
        }

        .group-row th {
            background: #111827;
            color: #fff;
            font-size: 9px;
        }

        .subtotal-row td {
            background: #f3f4f6;
            font-weight: 700;
        }

        tfoot th {
            background: #111827;
            color: #fff;
        }

        .number,
        .money {
            text-align: right;
            white-space: nowrap;
        }

        .wrap {
            white-space: normal;
            mso-data-placement: same-cell;
        }

        .muted {
            color: #6b7280;
        }

        .selector-empty {
            border: 1px dashed #9ca3af;
            color: #4b5563;
            font-size: 12px;
            margin-top: 18px;
            padding: 18px;
            text-align: center;
        }

        @media print {
            .print-actions {
                display: none;
            }
        }
    </style>
</head>

<body>

<?php if ($format === 'pdf'): ?>
    <div class="print-actions">
        <form method="get" class="issue-date-form">
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <input type="hidden" name="group" value="<?= e($groupBy) ?>">
            <input type="hidden" name="prices" value="<?= $withPrices ? '1' : '0' ?>">
            <input type="hidden" name="format" value="pdf">

            <label>
                <?= e($filterLabel) ?>
                <select name="filter" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($filterOptions as $filterOption): ?>
                        <option value="<?= e($filterOption['key']) ?>" <?= (string) $filterOption['key'] === $filterKey ? 'selected' : '' ?>>
                            <?= e($filterOption['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Data de emissao
                <input type="date" name="issue_date" value="<?= e($issueDate) ?>">
            </label>

            <button type="submit">Atualizar relatorio</button>
        </form>

        <?php if ($hasSelectedFilter): ?>
            <button type="button" onclick="window.print()">Imprimir / Salvar PDF</button>
            <button type="button" onclick="window.location.href='<?= e(project_demand_report_export_url('word', $issueDate, $filterKey)) ?>'">Exportar Word</button>
            <button type="button" onclick="window.location.href='<?= e(project_demand_report_export_url('excel', $issueDate, $filterKey)) ?>'">Exportar Excel</button>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="header">
    <?= render_municipal_logo() ?>
    <h1>Prefeitura Municipal de Espirito Santo do Turvo</h1>
    <h2><?= e($displayTitle) ?></h2>
    <p>Projeto: <?= e($project['name']) ?> | Emissao: <?= e($issueDateText) ?></p>
</div>

<?php if (!$hasSelectedFilter): ?>
    <div class="selector-empty">
        <?= $filterOptions ? e('Selecione uma opcao de ' . mb_strtolower($filterLabel) . ' para gerar o relatorio.') : e('Nenhuma opcao de ' . mb_strtolower($filterLabel) . ' possui itens demandados neste projeto.') ?>
    </div>
<?php else: ?>
<table>
    <thead>
        <tr>
            <?php if ($withPrices): ?>
                <th style="width: 34%;">Item</th>
                <th style="width: 18%;">Unidade</th>
                <th style="width: 12%;">Quantidade</th>
                <th style="width: 18%;">Valor unitario estimado</th>
                <th style="width: 18%;">Valor total estimado</th>
            <?php else: ?>
                <th>Item</th>
                <th style="width: 18%;">Quantidade</th>
            <?php endif; ?>
        </tr>
    </thead>

    <tbody>
        <?php if (!$report['groups']): ?>
            <tr>
                <td colspan="<?= $columnCount ?>" class="muted">Nenhum item demandado no projeto.</td>
            </tr>
        <?php endif; ?>

        <?php foreach ($report['groups'] as $group): ?>
            <tr class="group-row">
                <th colspan="<?= $columnCount ?>">
                    <?= e($groupBy === 'secretariat' ? 'Secretaria: ' : 'Unidade: ') ?><?= e($group['name']) ?>
                </th>
            </tr>

            <?php foreach ($group['items'] as $item): ?>
                <?php
                    $quantity = (float) ($item['quantity'] ?? 0);
                    $unitPrice = $item['estimated_unit_price'];
                    $total = $item['estimated_total'];
                ?>
                <tr>
                    <td class="wrap">
                        <?php if (!empty($item['sequence'])): ?>
                            <?= (int) $item['sequence'] ?> -
                        <?php endif; ?>
                        <?= e($item['item_name'] ?? '-') ?>
                    </td>

                    <?php if ($withPrices): ?>
                        <td class="wrap"><?= e($item['unit'] ?? '-') ?></td>
                    <?php endif; ?>

                    <td class="number" <?= $isExcel ? 'x:num="' . e((string) $quantity) . '"' : '' ?>>
                        <?= e(format_decimal_quantity($quantity)) ?>
                    </td>

                    <?php if ($withPrices): ?>
                        <td
                            class="money"
                            <?= $isExcel && $unitPrice !== null ? 'x:num="' . e((string) $unitPrice) . '"' : '' ?>>
                            <?= $unitPrice !== null ? 'R$ ' . number_format((float) $unitPrice, 2, ',', '.') : '-' ?>
                        </td>
                        <td
                            class="money"
                            <?= $isExcel ? 'x:num="' . e((string) $total) . '"' : '' ?>>
                            R$ <?= number_format((float) $total, 2, ',', '.') ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>

            <?php if ($withPrices): ?>
                <tr class="subtotal-row">
                    <td colspan="4" class="money">Subtotal</td>
                    <td class="money" <?= $isExcel ? 'x:num="' . e((string) $group['total']) . '"' : '' ?>>
                        R$ <?= number_format((float) $group['total'], 2, ',', '.') ?>
                    </td>
                </tr>
            <?php endif; ?>
        <?php endforeach; ?>
    </tbody>

    <?php if ($withPrices): ?>
        <tfoot>
            <tr>
                <th colspan="4" class="money">Valor global estimado</th>
                <th class="money" <?= $isExcel ? 'x:num="' . e((string) $report['global_total']) . '"' : '' ?>>
                    R$ <?= number_format((float) $report['global_total'], 2, ',', '.') ?>
                </th>
            </tr>
        </tfoot>
    <?php endif; ?>
</table>
<?php endif; ?>

</body>
</html>
