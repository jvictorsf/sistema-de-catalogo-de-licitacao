<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$id = (int) ($_GET['id'] ?? 0);
$format = strtolower((string) ($_GET['format'] ?? 'pdf'));
$format = in_array($format, ['pdf', 'word', 'excel'], true) ? $format : 'pdf';

$project = find_project($id);

if (!$project) {
    http_response_code(404);
    exit('Projeto nao encontrado.');
}

$title = 'Anexo II - Planilha Comparativa de Preços e Valor Global Estimado';
$annex = get_project_licitation_annex_ii_groups($id);
$groups = $annex['groups'];
$globalTotal = (float) $annex['global_total'];

if ($format === 'word') {
    send_download_headers('application/msword; charset=utf-8', $title . '.doc');
} elseif ($format === 'excel') {
    send_download_headers('application/vnd.ms-excel; charset=utf-8', $title . '.xls');
} else {
    header('Content-Type: text/html; charset=utf-8');
}

$isExcel = $format === 'excel';

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
            margin-bottom: 14px;
            text-align: right;
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

        .group-title {
            background: #dbeafe;
            border: 1px solid #6b7280;
            font-weight: 700;
            margin: 14px 0 0;
            padding: 6px;
        }

        table {
            border-collapse: collapse;
            margin-bottom: 12px;
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

        .number,
        .money {
            text-align: right;
            white-space: nowrap;
        }

        .muted {
            color: #6b7280;
        }

        .wrap {
            white-space: normal;
            mso-data-placement: same-cell;
        }

        .global-total {
            margin-top: 18px;
            width: 45%;
        }

        .global-total th {
            background: #111827;
            color: #fff;
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
        <button onclick="window.print()">Imprimir / Salvar PDF</button>
    </div>
<?php endif; ?>

<div class="header">
    <?= render_municipal_logo() ?>
    <h1>Prefeitura Municipal de Espírito Santo do Turvo</h1>
    <h2><?= e($title) ?></h2>
    <p>Projeto: <?= e($project['name']) ?> | Emissão: <?= date('d/m/Y') ?></p>
</div>

<?php if (!$groups): ?>
    <table>
        <tr>
            <td class="muted">Nenhum item demandado no projeto.</td>
        </tr>
    </table>
<?php endif; ?>

<?php foreach ($groups as $groupIndex => $group): ?>
    <?php
        $suppliers = $group['suppliers'];
        $supplierCount = count($suppliers);
        $colspan = 5 + $supplierCount;
        $supplierNames = array_map(
            static fn (array $supplier): string => (string) $supplier['name'],
            $suppliers
        );
    ?>

    <div class="group-title">
        <?php if ($suppliers): ?>
            Grupo <?= $groupIndex + 1 ?> - Fornecedores cotantes: <?= e(implode(', ', $supplierNames)) ?>
        <?php else: ?>
            Grupo <?= $groupIndex + 1 ?> - Itens sem cotação de fornecedor
        <?php endif; ?>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">Item</th>
                <th style="width: 25%;">Descrição do item/produto</th>
                <th style="width: 12%;">Unidade</th>
                <?php foreach ($suppliers as $supplierIndex => $supplier): ?>
                    <th>
                        Fornecedor <?= $supplierIndex + 1 ?><br>
                        <span class="muted"><?= e($supplier['name']) ?></span>
                    </th>
                <?php endforeach; ?>
                <th style="width: 12%;">Valor unitário estimado</th>
                <th style="width: 12%;">Valor total</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($group['items'] as $item): ?>
                <?php
                    $quantity = (float) ($item['annex_quantity'] ?? 0);
                    $estimatedUnitPrice = $item['estimated_unit_price'];
                    $estimatedTotal = $item['estimated_total'];
                ?>
                <tr>
                    <td class="number"><?= (int) $item['sequence'] ?></td>
                    <td class="wrap"><?= e($item['item_name'] ?? '-') ?></td>
                    <td class="wrap">
                        <?= e(licitation_annex_unit_text($item)) ?><br>
                        <span class="muted">Qtd.: <?= e(format_decimal_quantity($quantity)) ?></span>
                    </td>
                    <?php foreach ($suppliers as $supplier): ?>
                        <?php $price = $item['supplier_prices'][(int) $supplier['id']] ?? null; ?>
                        <td class="money" <?= $isExcel && $price !== null ? 'x:num="' . e((string) $price) . '"' : '' ?>>
                            <?= $price !== null ? 'R$ ' . number_format((float) $price, 2, ',', '.') : '-' ?>
                        </td>
                    <?php endforeach; ?>
                    <td
                        class="money"
                        <?= $isExcel && $estimatedUnitPrice !== null ? 'x:num="' . e((string) $estimatedUnitPrice) . '"' : '' ?>>
                        <?= $estimatedUnitPrice !== null ? 'R$ ' . number_format((float) $estimatedUnitPrice, 2, ',', '.') : '-' ?>
                    </td>
                    <td
                        class="money"
                        <?= $isExcel && $estimatedTotal !== null ? 'x:num="' . e((string) $estimatedTotal) . '"' : '' ?>>
                        <?= $estimatedTotal !== null ? 'R$ ' . number_format((float) $estimatedTotal, 2, ',', '.') : '-' ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if ($group['items']): ?>
                <tr>
                    <th colspan="<?= $colspan - 1 ?>" class="money">Subtotal do grupo</th>
                    <th class="money" <?= $isExcel ? 'x:num="' . e((string) $group['subtotal']) . '"' : '' ?>>
                        R$ <?= number_format((float) $group['subtotal'], 2, ',', '.') ?>
                    </th>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
<?php endforeach; ?>

<table class="global-total">
    <tr>
        <th>Valor global estimado</th>
        <td class="money" <?= $isExcel ? 'x:num="' . e((string) $globalTotal) . '"' : '' ?>>
            R$ <?= number_format($globalTotal, 2, ',', '.') ?>
        </td>
    </tr>
</table>

</body>
</html>
