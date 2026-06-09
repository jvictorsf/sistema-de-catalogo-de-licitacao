<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$id = (int) ($_GET['id'] ?? 0);

$demand = find_demand_list($id);

if (!$demand) {
    http_response_code(404);
    exit('Demanda não encontrada.');
}

$project = find_project((int) $demand['project_id']);
$items = get_demand_items($id);
$summary = get_demand_financial_summary($id);
$budgetReport = get_demand_budget_report($id);
$budgetItemsByDemandItem = [];
$summaryValueLabel = !empty($summary['uses_supplier_average'])
    ? 'Valor médio geral'
    : 'Valor total de referência';

foreach ($budgetReport['items'] as $budgetItem) {
    $budgetItemsByDemandItem[(int) $budgetItem['id']] = $budgetItem;
}

?>

<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Demanda - <?= e($demand['name']) ?></title>

    <style>
        @page {
            size: A4 landscape;
            margin: 12mm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            color: #111;
        }

        .print-actions {
            text-align: right;
            margin-bottom: 16px;
        }

        @media print {
            .print-actions {
                display: none;
            }
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #111;
            padding-bottom: 8px;
            margin-bottom: 14px;
        }

        .header h1 {
            margin: 0;
            font-size: 16px;
            text-transform: uppercase;
        }

        .header h2 {
            margin: 3px 0 0;
            font-size: 12px;
            font-weight: normal;
        }

        .title {
            text-align: center;
            margin: 14px 0;
            font-size: 15px;
            font-weight: bold;
        }

        .summary {
            display: flex;
            gap: 8px;
            margin-bottom: 14px;
        }

        .box {
            flex: 1;
            border: 1px solid #333;
            padding: 8px;
            text-align: center;
        }

        .box strong {
            display: block;
            font-size: 10px;
            margin-bottom: 4px;
        }

        .box span {
            font-size: 14px;
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #eee;
        }

        th,
        td {
            border: 1px solid #333;
            padding: 5px;
            vertical-align: top;
        }

        .signature {
            width: 45%;
            margin: 60px auto 0;
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #000;
            padding-top: 6px;
        }

        .footer {
            margin-top: 24px;
            text-align: center;
            color: #555;
            font-size: 9px;
        }
    </style>
</head>

<body>

<div class="print-actions">
    <button onclick="window.print()">Imprimir / Salvar PDF</button>
</div>

<div class="header">
    <?= render_municipal_logo() ?>
    <h1>Prefeitura Municipal de Espírito Santo do Turvo</h1>
    <h2>Departamento de Tecnologia da Informação</h2>
    <h2>Relatório Individual de Demanda</h2>
</div>

<div class="title">
    <?= e($demand['name']) ?>
</div>

<p>
    <strong>Projeto:</strong>
    <?= e($project['name'] ?? '-') ?>
</p>

<p>
    <strong>Unidade/Setor:</strong>
    <?= e($demand['requester_department']) ?>
</p>

<p>
    <strong>Secretaria:</strong>
    <?= e($demand['secretariat_name'] ?? 'Sem secretaria vinculada') ?>
</p>

<p>
    <strong>Responsável:</strong>
    <?= e($demand['responsible_name']) ?>
</p>

<p>
    <strong>Observações:</strong>
    <?= nl2br(e($demand['notes'])) ?>
</p>

<div class="summary">
    <div class="box">
        <strong>Quantidade solicitada</strong>
        <span><?= e((string) ($summary['total_requested_quantity'] ?? 0)) ?></span>
    </div>

    <div class="box">
        <strong>Quantidade aprovada</strong>
        <span><?= e((string) ($summary['total_approved_quantity'] ?? 0)) ?></span>
    </div>

    <div class="box">
        <strong><?= e($summaryValueLabel) ?></strong>
        <span>R$ <?= number_format((float) ($summary['total_estimated_value'] ?? 0), 2, ',', '.') ?></span>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>Código</th>
            <th>Item</th>
            <th>Un.</th>
            <th>Qtd. solicitada</th>
            <th>Qtd. aprovada</th>
            <th>Valor unit. médio</th>
            <th>Total médio estimado</th>
            <th>Observação</th>
        </tr>
    </thead>

    <tbody>
        <?php foreach ($items as $item): ?>
            <?php
                $budgetItem = $budgetItemsByDemandItem[(int) $item['id']] ?? [];
                $averageUnitPrice = $budgetItem['average_unit_price'] ?? null;
                $averageTotal = $budgetItem['average_total'] ?? null;
            ?>
            <tr>
                <td><?= e($item['tracking_code']) ?></td>
                <td><?= e($item['item_name']) ?></td>
                <td>
                    <?= e($item['unit_type_abbreviation'] ?: ($item['unit_type_name'] ?? '-')) ?>
                    <?php if (format_package_content($item) !== '-'): ?>
                        <br><span class="text-muted">Conteudo: <?= e(format_package_content($item)) ?></span>
                    <?php endif; ?>
                </td>
                <td><?= e((string) $item['quantity']) ?></td>
                <td><?= e((string) ($item['approved_quantity'] ?? $item['quantity'])) ?></td>
                <td>
                    R$ <?= number_format((float) ($averageUnitPrice ?? $item['estimated_unit_price'] ?? 0), 2, ',', '.') ?>
                    <?= $averageUnitPrice === null ? '<br><small>referencia manual</small>' : '' ?>
                </td>
                <td>R$ <?= number_format((float) ($averageTotal ?? $item['estimated_total'] ?? 0), 2, ',', '.') ?></td>
                <td><?= e($item['notes']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="signature">
    <div class="signature-line">
        <?= e($demand['responsible_name'] ?: 'Responsável pela demanda') ?><br>
        <?= e($demand['requester_department'] ?: $demand['name']) ?>
    </div>
</div>

<div class="footer">
    Documento gerado pelo Sistema de Planejamento de Demandas para Contratações Públicas.
</div>

</body>
</html>
