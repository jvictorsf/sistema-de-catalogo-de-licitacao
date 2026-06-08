<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$items = search_items();

?>

<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Catálogo de Itens para Licitação</title>

    <style>
        @page {
            size: A4 portrait;
            margin: 14mm;
        }

        body {
            background: #ffffff;
            color: #1f2937;
            font-family: Arial, sans-serif;
            font-size: 10px;
            line-height: 1.35;
            margin: 0;
        }

        h1,
        h2,
        h3,
        h4 {
            color: #111827;
        }

        .print-actions {
            margin-bottom: 16px;
            text-align: right;
        }

        .print-actions button {
            background: #111827;
            border: 0;
            color: #ffffff;
            cursor: pointer;
            font-size: 12px;
            padding: 8px 12px;
        }

        @media print {
            .print-actions {
                display: none;
            }
        }

        .header {
            border-bottom: 3px solid #111827;
            margin-bottom: 14px;
            padding-bottom: 10px;
            text-align: center;
        }

        .header h1 {
            font-size: 16px;
            margin: 0;
            text-transform: uppercase;
        }

        .header h2 {
            font-size: 12px;
            font-weight: normal;
            margin: 3px 0 0;
        }

        .document-title {
            font-size: 14px;
            margin: 0 0 10px;
            text-align: center;
            text-transform: uppercase;
        }

        .summary-table,
        .meta-table,
        .spec-table {
            border-collapse: collapse;
            width: 100%;
        }

        .summary-table {
            margin-bottom: 14px;
        }

        .summary-table th,
        .summary-table td,
        .meta-table th,
        .meta-table td,
        .spec-table th,
        .spec-table td {
            border: 1px solid #d1d5db;
            padding: 5px 6px;
            vertical-align: top;
        }

        .summary-table th,
        .meta-table th,
        .spec-table th {
            background: #f3f4f6;
            font-weight: bold;
            text-align: left;
        }

        .item {
            border: 1px solid #9ca3af;
            border-left: 4px solid #111827;
            margin-bottom: 12px;
            padding: 10px;
            page-break-inside: avoid;
        }

        .item-title {
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .item-code {
            background: #111827;
            color: #ffffff;
            display: inline-block;
            font-size: 10px;
            margin-right: 5px;
            padding: 2px 5px;
        }

        h3 {
            border-bottom: 1px solid #d1d5db;
            font-size: 11px;
            margin: 10px 0 6px;
            padding-bottom: 3px;
            text-transform: uppercase;
        }

        .spec-section {
            margin-bottom: 8px;
        }

        .spec-section h4 {
            font-size: 10px;
            margin: 0 0 4px;
        }

        .spec-list {
            margin: 4px 0 0 16px;
            padding: 0;
        }

        .spec-list li {
            margin-bottom: 3px;
        }

        .text-muted {
            color: #6b7280;
        }
    </style>
</head>

<body>

<div class="print-actions">
    <button onclick="window.print()">Imprimir / Salvar PDF</button>
</div>

<div class="header">
    <?= render_municipal_logo('document-logo') ?>
    <h1>Prefeitura Municipal de Espírito Santo do Turvo</h1>
    <h2>Departamento de Tecnologia da Informação</h2>
    <h2>Catálogo Institucional de Itens para Licitação</h2>
</div>

<h1 class="document-title">Catálogo de Itens</h1>

<table class="summary-table">
    <tr>
        <th>Total de itens</th>
        <td><?= count($items) ?></td>
        <th>Emitido em</th>
        <td><?= e(date('d/m/Y H:i')) ?></td>
    </tr>
</table>

<?php foreach ($items as $item): ?>
    <div class="item">
        <div class="item-title">
            <span class="item-code"><?= e($item['tracking_code']) ?></span>
            <?= e($item['name']) ?>
        </div>

        <table class="meta-table">
            <tr>
                <th>Categoria</th>
                <td><?= e($item['category_name'] ?? '-') ?></td>
                <th>Subcategoria</th>
                <td><?= e($item['subcategory_name'] ?? '-') ?></td>
            </tr>
            <tr>
                <th>Unidade</th>
                <td><?= e($item['unit_type_abbreviation'] ?: ($item['unit_type_name'] ?? '-')) ?></td>
                <th>Nível</th>
                <td><?= e($item['level']) ?></td>
            </tr>
            <tr>
                <th>Status</th>
                <td colspan="3"><?= e(item_status_label($item['status'] ?? null)) ?></td>
            </tr>
        </table>

        <h3>Especificação técnica</h3>
        <?= render_item_specification_html($item['specification']) ?>

        <h3>Justificativa</h3>
        <p><?= nl2br(e($item['justification'])) ?></p>

        <h3>Garantia</h3>
        <p><?= nl2br(e($item['warranty'])) ?></p>

        <h3>Possíveis impactos ambientais</h3>
        <?= render_environmental_impacts_list($item['environmental_impacts']) ?>
    </div>
<?php endforeach; ?>

</body>
</html>
