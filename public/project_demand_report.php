<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$id = (int) ($_GET['id'] ?? 0);
$groupBy = ($_GET['group'] ?? 'unit') === 'secretariat' ? 'secretariat' : 'unit';
$withPrices = boolish($_GET['prices'] ?? false);
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
$report = get_project_demand_group_report($id, $groupBy);

if ($format === 'word') {
    send_download_headers('application/msword; charset=utf-8', $title . '.doc');
} elseif ($format === 'excel') {
    send_download_headers('application/vnd.ms-excel; charset=utf-8', $title . '.xls');
} else {
    header('Content-Type: text/html; charset=utf-8');
}

$isExcel = $format === 'excel';
$issueDate = annex_issue_date_value();
$issueDateText = annex_issue_date_text($issueDate);
$columnCount = $withPrices ? 5 : 2;

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
    <title><?= e($title) ?> - <?= e($project['name']) ?></title>

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

        .print-actions input {
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

        @media print {
            .print-actions {
                display: none;
            }
        }
    </style>
</head>

<body>

<?php if ($format === 'pdf'): ?>
    <?= render_annex_print_actions($issueDate) ?>
<?php endif; ?>

<div class="header">
    <?= render_municipal_logo() ?>
    <h1>Prefeitura Municipal de Espirito Santo do Turvo</h1>
    <h2><?= e($title) ?></h2>
    <p>Projeto: <?= e($project['name']) ?> | Emissao: <?= e($issueDateText) ?></p>
</div>

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

</body>
</html>
