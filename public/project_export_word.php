<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$id = (int) ($_GET['id'] ?? 0);

$project = find_project($id);

if (!$project) {
    http_response_code(404);
    exit('Projeto não encontrado.');
}

$consolidatedItems = get_project_consolidated_items($id);
$itemsByDemand = get_project_items_by_demand($id);
$financialSummary = get_project_financial_summary($id);
$secretariatSummary = get_project_secretariat_summary($id);
$signatures = get_project_signature_blocks($id);

$filename = 'relatorio-projeto-' . $id . '.doc';

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
            color: #111;
        }

        h1, h2, h3 {
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

        .section-title {
            background: #eee;
            padding: 8px;
            border: 1px solid #333;
            font-weight: bold;
            margin-top: 24px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }

        th, td {
            border: 1px solid #333;
            padding: 6px;
            vertical-align: top;
        }

        th {
            background: #eee;
        }

        .summary-table td {
            width: 33.33%;
            text-align: center;
            font-size: 13pt;
            font-weight: bold;
        }

        .summary-table small {
            display: block;
            font-size: 9pt;
            font-weight: normal;
        }

        .text-small {
            font-size: 9pt;
        }

        .signature {
            margin-top: 60px;
            width: 45%;
            display: inline-block;
            text-align: center;
            vertical-align: top;
            margin-right: 4%;
        }

        .signature-line {
            border-top: 1px solid #000;
            padding-top: 6px;
        }
    </style>
</head>

<body>

<div class="header">
    <?= render_municipal_logo() ?>
    <h1>Prefeitura Municipal de Espírito Santo do Turvo</h1>
    <h2>Departamento de Tecnologia da Informação</h2>
    <h2>Relatório de Demandas para Contratação</h2>
</div>

<h1><?= e($project['name']) ?></h1>

<p>
    <strong>Descrição:</strong>
    <?= nl2br(e($project['description'])) ?>
</p>

<div class="section-title">Resumo Geral</div>

<table class="summary-table">
    <tr>
        <td>
            <small>Quantidade solicitada</small>
            <?= e((string) ($financialSummary['total_requested_quantity'] ?? 0)) ?>
        </td>

        <td>
            <small>Quantidade aprovada</small>
            <?= e((string) ($financialSummary['total_approved_quantity'] ?? 0)) ?>
        </td>

        <td>
            <small>Valor total estimado</small>
            R$ <?= number_format((float) ($financialSummary['total_estimated_value'] ?? 0), 2, ',', '.') ?>
            <?php if (!empty($financialSummary['uses_supplier_average'])): ?>
                <br><small>Calculado por médias de orçamento</small>
            <?php endif; ?>
        </td>
    </tr>
</table>

<div class="section-title">Resumo por Secretaria</div>

<table>
    <thead>
        <tr>
            <th>Secretaria</th>
            <th>Demandas</th>
            <th>Qtd. solicitada</th>
            <th>Qtd. aprovada</th>
            <th>Total estimado</th>
        </tr>
    </thead>

    <tbody>
        <?php foreach ($secretariatSummary as $row): ?>
            <tr>
                <td><?= e($row['secretariat_name']) ?></td>
                <td><?= e((string) $row['demand_count']) ?></td>
                <td><?= e((string) ($row['total_requested_quantity'] ?? 0)) ?></td>
                <td><?= e((string) ($row['total_approved_quantity'] ?? 0)) ?></td>
                <td>R$ <?= number_format((float) ($row['total_estimated_value'] ?? 0), 2, ',', '.') ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="section-title">Consolidação Geral dos Itens</div>

<table>
    <thead>
        <tr>
            <th>Código</th>
            <th>Item</th>
            <th>Unidade</th>
            <th>Qtd. solicitada</th>
            <th>Qtd. aprovada</th>
            <th>Valor médio unit.</th>
            <th>Total estimado</th>
        </tr>
    </thead>

    <tbody>
        <?php foreach ($consolidatedItems as $item): ?>
            <tr>
                <td><?= e($item['tracking_code']) ?></td>
                <td><?= e($item['item_name']) ?></td>
                <td>
                    <?= e($item['unit_type_abbreviation'] ?: ($item['unit_type_name'] ?? '-')) ?>
                    <?php if (format_package_content($item) !== '-'): ?>
                        <br><span class="text-muted">Conteudo: <?= e(format_package_content($item)) ?></span>
                    <?php endif; ?>
                </td>
                <td><?= e((string) $item['total_quantity']) ?></td>
                <td><?= e((string) $item['total_approved_quantity']) ?></td>
                <td>R$ <?= number_format((float) $item['average_unit_price'], 2, ',', '.') ?></td>
                <td>
                    R$ <?= number_format((float) $item['estimated_total'], 2, ',', '.') ?>
                    <?php if (!empty($item['uses_supplier_average'])): ?>
                        <br><span class="text-small">média de orçamento</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="section-title">Justificativas e Impactos Ambientais</div>

<table>
    <thead>
        <tr>
            <th>Código</th>
            <th>Item</th>
            <th>Justificativa</th>
            <th>Impactos ambientais</th>
        </tr>
    </thead>

    <tbody>
        <?php foreach ($consolidatedItems as $item): ?>
            <tr>
                <td><?= e($item['tracking_code']) ?></td>
                <td><?= e($item['item_name']) ?></td>
                <td><?= nl2br(e($item['justification'])) ?></td>
                <td><?= render_environmental_impacts_list($item['environmental_impacts']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="section-title">Detalhamento por Demanda</div>

<table>
    <thead>
        <tr>
            <th>Demanda</th>
            <th>Secretaria</th>
            <th>Setor/Unidade</th>
            <th>Responsável</th>
            <th>Código</th>
            <th>Item</th>
            <th>Un.</th>
            <th>Qtd. solic.</th>
            <th>Qtd. aprov.</th>
            <th>Valor unit.</th>
            <th>Total</th>
        </tr>
    </thead>

    <tbody>
        <?php foreach ($itemsByDemand as $item): ?>
            <tr>
                <td><?= e($item['demand_name']) ?></td>
                <td><?= e($item['secretariat_name'] ?? '-') ?></td>
                <td><?= e($item['requester_department']) ?></td>
                <td><?= e($item['responsible_name']) ?></td>
                <td><?= e($item['tracking_code']) ?></td>
                <td><?= e($item['item_name']) ?></td>
                <td>
                    <?= e($item['unit_type_abbreviation'] ?: ($item['unit_type_name'] ?? '-')) ?>
                    <?php if (format_package_content($item) !== '-'): ?>
                        <br><span class="text-muted">Conteudo: <?= e(format_package_content($item)) ?></span>
                    <?php endif; ?>
                </td>
                <td><?= e((string) $item['quantity']) ?></td>
                <td><?= e((string) $item['approved_quantity']) ?></td>
                <td>
                    R$ <?= number_format((float) ($item['calculated_unit_price'] ?? $item['estimated_unit_price'] ?? 0), 2, ',', '.') ?>
                    <?php if (!empty($item['uses_supplier_average'])): ?>
                        <br><span class="text-small">média de orçamento</span>
                    <?php endif; ?>
                </td>
                <td>R$ <?= number_format((float) ($item['calculated_total'] ?? $item['estimated_total'] ?? 0), 2, ',', '.') ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="section-title">Assinaturas das Unidades Demandantes</div>

<?php foreach ($signatures as $signature): ?>
    <div class="signature">
        <div class="signature-line">
            <?= e($signature['responsible_name'] ?: 'Responsável pela demanda') ?><br>
            <span class="text-small">
                <?= e($signature['secretariat_name'] ?? 'Sem secretaria') ?><br>
                <?= e($signature['requester_department'] ?: $signature['name']) ?>
            </span>
        </div>
    </div>
<?php endforeach; ?>

</body>
</html>
