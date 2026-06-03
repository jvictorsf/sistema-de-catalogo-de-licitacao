<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$items = search_items();

$filename = 'catalogo-itens-licitacao.doc';

header('Content-Type: application/msword; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

?>

<html>
<head>
    <meta charset="utf-8">

    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
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

        .item {
            page-break-inside: avoid;
            border: 1px solid #333;
            margin-bottom: 18px;
            padding: 10px;
        }

        .item-title {
            font-size: 13pt;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .badge {
            background: #eee;
            border: 1px solid #333;
            padding: 2px 5px;
            font-size: 9pt;
        }

        pre {
            background: #f5f5f5;
            padding: 8px;
            border: 1px solid #ccc;
            white-space: pre-wrap;
        }
    </style>
</head>

<body>

<div class="header">
    <h1>Prefeitura Municipal de Espírito Santo do Turvo</h1>
    <h2>Departamento de Tecnologia da Informação</h2>
    <h2>Catálogo de Itens para Licitação</h2>
</div>

<h1>Catálogo de Itens</h1>

<?php foreach ($items as $item): ?>
    <div class="item">
        <div class="item-title">
            <?= e($item['tracking_code']) ?> - <?= e($item['name']) ?>
        </div>

        <p>
            <span class="badge">Categoria: <?= e($item['category_name'] ?? '-') ?></span>
            <span class="badge">Subcategoria: <?= e($item['subcategory_name'] ?? '-') ?></span>
            <span class="badge">Unidade: <?= e($item['unit_type_abbreviation'] ?: ($item['unit_type_name'] ?? '-')) ?></span>
            <span class="badge">Nível: <?= e($item['level']) ?></span>
            <span class="badge">Status: <?= e($item['status']) ?></span>
        </p>

        <h3>Especificação técnica</h3>
        <pre><?= e(pretty_json($item['specification'])) ?></pre>

        <h3>Justificativa</h3>
        <p><?= nl2br(e($item['justification'])) ?></p>

        <h3>Garantia</h3>
        <p><?= nl2br(e($item['warranty'])) ?></p>

        <h3>Possíveis impactos ambientais</h3>
        <p><?= nl2br(e($item['environmental_impacts'])) ?></p>
    </div>
<?php endforeach; ?>

</body>
</html>