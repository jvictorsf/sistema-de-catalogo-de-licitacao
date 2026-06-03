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

        .item {
            page-break-inside: avoid;
            border: 1px solid #333;
            padding: 10px;
            margin-bottom: 12px;
        }

        .item-title {
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .meta {
            margin-bottom: 8px;
        }

        .badge {
            display: inline-block;
            border: 1px solid #333;
            background: #eee;
            padding: 2px 5px;
            margin: 2px;
        }

        pre {
            background: #f5f5f5;
            border: 1px solid #ccc;
            padding: 8px;
            white-space: pre-wrap;
            font-size: 9px;
        }

        h3 {
            font-size: 11px;
            margin-bottom: 4px;
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
    <h2>Catálogo Institucional de Itens para Licitação</h2>
</div>

<?php foreach ($items as $item): ?>
    <div class="item">
        <div class="item-title">
            <?= e($item['tracking_code']) ?> - <?= e($item['name']) ?>
        </div>

        <div class="meta">
            <span class="badge">Categoria: <?= e($item['category_name'] ?? '-') ?></span>
            <span class="badge">Subcategoria: <?= e($item['subcategory_name'] ?? '-') ?></span>
            <span class="badge">Unidade: <?= e($item['unit_type_abbreviation'] ?: ($item['unit_type_name'] ?? '-')) ?></span>
            <span class="badge">Nível: <?= e($item['level']) ?></span>
            <span class="badge">Status: <?= e($item['status']) ?></span>
        </div>

        <h3>Especificação técnica</h3>
        <pre><?= e(pretty_json($item['specification'])) ?></pre>

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
