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

$filename = 'demanda-' . $id . '.doc';

header('Content-Type: application/msword; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

?>

<html>
<head>
    <meta charset="utf-8">

    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
        }

        h1,
        h2 {
            text-align: center;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #111;
            padding-bottom: 12px;
            margin-bottom: 24px;
        }

        .header h1 {
            font-size: 16pt;
            margin: 0;
            text-transform: uppercase;
        }

        .header h2 {
            font-size: 12pt;
            margin: 4px 0 0;
            font-weight: normal;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }

        th,
        td {
            border: 1px solid #333;
            padding: 6px;
            vertical-align: top;
        }

        th {
            background: #eee;
        }

        .signature {
            margin-top: 70px;
            width: 50%;
            margin-left: auto;
            margin-right: auto;
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #000;
            padding-top: 6px;
        }
    </style>
</head>

<body>

<div class="header">
    <h1>Prefeitura Municipal de Espírito Santo do Turvo</h1>
    <h2>Departamento de Tecnologia da Informação</h2>
    <h2>Relatório Individual de Demanda</h2>
</div>

<h1><?= e($demand['name']) ?></h1>

<p><strong>Projeto:</strong> <?= e($project['name'] ?? '-') ?></p>
<p><strong>Unidade/Setor:</strong> <?= e($demand['requester_department']) ?></p>
<p><strong>Secretaria:</strong> <?= e($demand['secretariat_name'] ?? 'Sem secretaria vinculada') ?></p>
<p><strong>Responsável:</strong> <?= e($demand['responsible_name']) ?></p>
<p><strong>Observações:</strong> <?= nl2br(e($demand['notes'])) ?></p>

<table>
    <tr>
        <th>Qtd. solicitada</th>
        <th>Qtd. aprovada</th>
        <th>Valor total estimado</th>
    </tr>

    <tr>
        <td><?= e((string) ($summary['total_requested_quantity'] ?? 0)) ?></td>
        <td><?= e((string) ($summary['total_approved_quantity'] ?? 0)) ?></td>
        <td>R$ <?= number_format((float) ($summary['total_estimated_value'] ?? 0), 2, ',', '.') ?></td>
    </tr>
</table>

<table>
    <thead>
        <tr>
            <th>Código</th>
            <th>Item</th>
            <th>Un.</th>
            <th>Qtd. solicitada</th>
            <th>Qtd. aprovada</th>
            <th>Valor unit.</th>
            <th>Total estimado</th>
            <th>Observação</th>
        </tr>
    </thead>

    <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?= e($item['tracking_code']) ?></td>
                <td><?= e($item['item_name']) ?></td>
                <td><?= e($item['unit_type_abbreviation'] ?: ($item['unit_type_name'] ?? '-')) ?></td>
                <td><?= e((string) $item['quantity']) ?></td>
                <td><?= e((string) ($item['approved_quantity'] ?? $item['quantity'])) ?></td>
                <td>R$ <?= number_format((float) ($item['estimated_unit_price'] ?? 0), 2, ',', '.') ?></td>
                <td>R$ <?= number_format((float) ($item['estimated_total'] ?? 0), 2, ',', '.') ?></td>
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

</body>
</html>
