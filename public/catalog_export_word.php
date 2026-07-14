<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$items = search_items();
$filename = 'catalogo-itens-licitacao.doc';

send_download_headers('application/msword; charset=utf-8', $filename);

?>

<html>
<head>
    <meta charset="utf-8">

    <style>
        body {
            color: #1f2937;
            font-family: Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.35;
        }

        h1,
        h2,
        h3,
        h4 {
            color: #111827;
        }

        .header {
            border-bottom: 3px solid #111827;
            margin-bottom: 16pt;
            padding-bottom: 12pt;
            text-align: center;
        }

        .header h1 {
            font-size: 16pt;
            margin: 0;
            text-transform: uppercase;
        }

        .header h2 {
            font-size: 12pt;
            font-weight: normal;
            margin: 4pt 0 0;
        }

        .document-title {
            font-size: 14pt;
            margin: 0 0 10pt;
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
            margin-bottom: 16pt;
        }

        .summary-table th,
        .summary-table td,
        .meta-table th,
        .meta-table td,
        .spec-table th,
        .spec-table td {
            border: 1px solid #d1d5db;
            padding: 5pt 6pt;
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
            margin-bottom: 18pt;
            padding: 12pt;
            page-break-inside: avoid;
        }

        .item-title {
            font-size: 13pt;
            font-weight: bold;
            margin-bottom: 8pt;
        }

        .item-code {
            background: #111827;
            color: #ffffff;
            font-size: 9pt;
            padding: 2pt 5pt;
        }

        h3 {
            border-bottom: 1px solid #d1d5db;
            font-size: 11pt;
            margin: 12pt 0 6pt;
            padding-bottom: 3pt;
            text-transform: uppercase;
        }

        .spec-section {
            margin-bottom: 9pt;
        }

        .spec-section h4 {
            font-size: 10pt;
            margin: 0 0 4pt;
        }

        .spec-list {
            margin: 4pt 0 0 18pt;
            padding: 0;
        }

        .spec-list li {
            margin-bottom: 3pt;
        }

        .text-muted {
            color: #6b7280;
        }
    </style>
</head>

<body>

<div class="header">
    <?= render_municipal_logo('document-logo') ?>
    <h1>Prefeitura Municipal de Espírito Santo do Turvo</h1>
    <h2>Departamento de Tecnologia da Informação</h2>
    <h2>Catálogo de Itens para Licitação</h2>
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
                <td>
                    <?= e($item['unit_type_abbreviation'] ?: ($item['unit_type_name'] ?? '-')) ?>
                    <?php if (format_package_content($item) !== '-'): ?>
                        <br><span class="text-muted">Conteudo: <?= e(format_package_content($item)) ?></span>
                    <?php endif; ?>
                </td>
                <th>Nível</th>
                <td><?= e($item['level']) ?></td>
            </tr>
            <tr>
                <th>Status</th>
                <td><?= e(item_status_label($item['status'] ?? null)) ?></td>
                <th>Classificação</th>
                <td><?= e(item_supply_classification_label($item)) ?></td>
            </tr>
        </table>

        <h3>Especificação técnica</h3>
        <?= render_item_specification_html($item['specification']) ?>

        <h3>Justificativa</h3>
        <p><?= nl2br(e($item['justification'])) ?></p>

        <h3>Condições de fornecimento</h3>
        <p><?= nl2br(e($item['warranty'])) ?></p>
        <?php if (!empty($item['minimum_validity_text'])): ?>
            <p><?= nl2br(e($item['minimum_validity_text'])) ?></p>
        <?php endif; ?>

        <h3>Possíveis impactos ambientais</h3>
        <?= render_environmental_impacts_list($item['environmental_impacts']) ?>
    </div>
<?php endforeach; ?>

</body>
</html>
