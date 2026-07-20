<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

function project_quote_request_lot_label(array $group): string
{
    if (!empty($group['is_unassigned'])) {
        return 'Sem denominacao';
    }

    return 'Lote ' . (int) ($group['lot_number'] ?? 0) . ' - ' . (string) ($group['name'] ?? 'Denominacao');
}

function project_quote_request_group_quantity(array $group): float
{
    return array_reduce(
        $group['items'] ?? [],
        static fn (float $total, array $item): float => $total + project_item_effective_quantity($item),
        0.0
    );
}

function project_quote_request_render_rows(array $items): void
{
    if (!$items) {
        ?>
        <tr>
            <td colspan="9" class="muted">Nenhum item demandado no projeto.</td>
        </tr>
        <?php
        return;
    }

    foreach ($items as $item) {
        $specification = item_specification_array_from_value($item['specification'] ?? []);
        $quantity = project_item_effective_quantity($item);
        $packageContentQuantity = $item['package_content_quantity'] ?? null;
        $packageContentUnit = $item['package_content_unit_type_name']
            ?? $item['package_content_unit_type_abbreviation']
            ?? null;
        ?>
        <tr>
            <td class="code"><?= e($item['tracking_code'] ?? '-') ?></td>
            <td><?= e($item['item_name'] ?? '-') ?></td>
            <td><?= e(supplier_quote_request_value_text($specification['marca_referencia'] ?? null)) ?></td>
            <td><?= e(supplier_quote_request_value_text($specification['modelo_referencia'] ?? null)) ?></td>
            <td><?= supplier_quote_request_characteristics_html($specification) ?></td>
            <td><?= e($item['unit_type_name'] ?? $item['unit_type_abbreviation'] ?? '-') ?></td>
            <td><?= e($packageContentQuantity !== null ? format_decimal_quantity($packageContentQuantity) : '-') ?></td>
            <td><?= e(supplier_quote_request_value_text($packageContentUnit)) ?></td>
            <td class="quantity"><?= e(format_decimal_quantity($quantity)) ?></td>
        </tr>
        <?php
    }
}

$id = (int) ($_GET['id'] ?? 0);
$format = strtolower((string) ($_GET['format'] ?? 'pdf'));
$format = in_array($format, ['pdf', 'word'], true) ? $format : 'pdf';
$groupByDenomination = ($_GET['group_by'] ?? '') === 'denomination';
$selectedLotId = (int) ($_GET['lot_id'] ?? 0);

$project = find_project($id);

if (!$project) {
    http_response_code(404);
    exit('Projeto nao encontrado.');
}

$allItems = get_project_consolidated_items($id);
$lotGroups = get_project_lot_groups($id, $allItems);
$sections = [];
$items = $allItems;
$contextLabel = 'Projeto completo';

if ($selectedLotId > 0) {
    foreach ($lotGroups as $group) {
        if ((int) ($group['lot_id'] ?? 0) === $selectedLotId) {
            $sections = [$group];
            $items = $group['items'] ?? [];
            $contextLabel = project_quote_request_lot_label($group);
            break;
        }
    }

    if (!$sections) {
        http_response_code(404);
        exit('Denominacao nao encontrada no projeto.');
    }
} elseif ($groupByDenomination) {
    $sections = $lotGroups;
    $contextLabel = 'Separado por denominacao';
} else {
    $sections = [[
        'name' => 'Itens do projeto',
        'items' => $allItems,
        'is_default' => true,
    ]];
}

if (!$sections) {
    $sections = [[
        'name' => 'Itens do projeto',
        'items' => $items,
        'is_default' => true,
    ]];
}

$totalQuantity = array_reduce(
    $items,
    static fn (float $total, array $item): float => $total + project_item_effective_quantity($item),
    0.0
);

$filenameSuffix = $selectedLotId > 0
    ? '-denominacao-' . $selectedLotId
    : ($groupByDenomination ? '-por-denominacao' : '');

if ($format === 'word') {
    send_download_headers('application/msword; charset=utf-8', 'solicitacao-orcamento-projeto-' . $id . $filenameSuffix . '.doc');
} else {
    header('Content-Type: text/html; charset=utf-8');
}

$wordQuery = $_GET;
$wordQuery['format'] = 'word';
$wordUrl = '/project_quote_request.php?' . http_build_query($wordQuery);

?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Solicitacao de Orcamento - <?= e($project['name']) ?></title>

    <style>
        @page { size: A4 landscape; margin: 12mm; }
        * { box-sizing: border-box; }
        body { color: #111827; font-family: Arial, sans-serif; font-size: 10px; line-height: 1.35; margin: 0; }
        .print-actions { display: flex; gap: 8px; justify-content: flex-end; margin-bottom: 14px; }
        .print-actions button { background: #0f766e; border: 0; border-radius: 4px; color: #fff; cursor: pointer; font-weight: 700; padding: 8px 12px; }
        .header { border-bottom: 2px solid #111827; margin-bottom: 14px; padding-bottom: 10px; text-align: center; }
        .header h1, .header h2, .header p { margin: 0; }
        .header h1 { font-size: 15px; text-transform: uppercase; }
        .header h2 { font-size: 13px; margin-top: 4px; text-transform: uppercase; }
        .header p { color: #4b5563; font-size: 10px; margin-top: 4px; }
        .intro { border: 1px solid #d1d5db; margin-bottom: 12px; padding: 8px 10px; }
        .intro-grid { display: grid; gap: 6px 18px; grid-template-columns: 1.4fr 1fr 1fr 1fr; }
        .intro strong { color: #374151; display: block; font-size: 9px; text-transform: uppercase; }
        .section-title { background: #dbeafe; border: 1px solid #9ca3af; font-weight: 700; margin: 14px 0 0; padding: 7px; }
        .section-title small { color: #374151; font-weight: 400; }
        table { border-collapse: collapse; margin-bottom: 12px; table-layout: fixed; width: 100%; }
        thead { display: table-header-group; }
        th, td { border: 1px solid #9ca3af; padding: 5px; vertical-align: top; }
        th { background: #e5e7eb; color: #111827; font-size: 9px; text-align: left; text-transform: uppercase; }
        tbody tr:nth-child(even) { background: #f9fafb; }
        .code { font-weight: 700; white-space: nowrap; }
        .quantity { font-weight: 700; text-align: right; white-space: nowrap; }
        .muted { color: #6b7280; }
        .characteristics { margin: 0; padding-left: 14px; }
        .characteristics li { margin-bottom: 3px; }
        .footer { color: #6b7280; font-size: 9px; margin-top: 12px; text-align: center; }
        @media print { .print-actions { display: none; } }
    </style>
</head>

<body>

<?php if ($format === 'pdf'): ?>
    <div class="print-actions">
        <button onclick="window.print()">Imprimir / Salvar PDF</button>
        <button onclick="window.location.href='<?= e($wordUrl) ?>'">Exportar Word</button>
    </div>
<?php endif; ?>

<div class="header">
    <?= render_municipal_logo() ?>
    <h1>Prefeitura Municipal de Espirito Santo do Turvo</h1>
    <h2>Solicitacao formal de orcamento</h2>
    <p>Orcamento geral do projeto, consolidando os itens demandados.</p>
</div>

<div class="intro">
    <div class="intro-grid">
        <div><strong>Projeto</strong><?= e($project['name']) ?></div>
        <div><strong>Escopo</strong><?= e($contextLabel) ?></div>
        <div><strong>Data de emissao</strong><?= date('d/m/Y') ?></div>
        <div><strong>Quantidade total</strong><?= e(format_decimal_quantity($totalQuantity)) ?></div>
    </div>
</div>

<?php foreach ($sections as $section): ?>
    <?php if (empty($section['is_default'])): ?>
        <div class="section-title">
            <?= e(project_quote_request_lot_label($section)) ?>
            <small>
                - <?= count($section['items'] ?? []) ?> item(ns)
                - Quantidade: <?= e(format_decimal_quantity(project_quote_request_group_quantity($section))) ?>
            </small>
        </div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th style="width: 7%;">Codigo</th>
                <th style="width: 16%;">Item</th>
                <th style="width: 10%;">Marca ref.</th>
                <th style="width: 10%;">Modelo ref.</th>
                <th style="width: 28%;">Caracteristicas minimas</th>
                <th style="width: 9%;">Tipo de unidade</th>
                <th style="width: 7%;">Conteudo</th>
                <th style="width: 7%;">Un. conteudo</th>
                <th style="width: 7%;">Quantidade</th>
            </tr>
        </thead>
        <tbody>
            <?php project_quote_request_render_rows($section['items'] ?? []); ?>
        </tbody>
    </table>
<?php endforeach; ?>

<div class="footer">
    Favor observar integralmente as especificacoes minimas informadas para cada item.
</div>

</body>
</html>
