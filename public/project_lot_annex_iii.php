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

$title = 'Anexo III por lote - Quadro resumido por lote';
$annex = get_project_lot_licitation_annex_iii_groups($id);
$lots = $annex['lots'];
$globalTotal = (float) $annex['global_total'];
$annexVersion = register_project_annex_version($id, 'lot_annex_iii');
$annexVersionText = !empty($annexVersion['version_number']) ? 'v' . $annexVersion['version_number'] : 'sem versao';
$annexHashText = substr((string) ($annexVersion['content_hash'] ?? ''), 0, 12);

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
        @page { size: A4 landscape; margin: 10mm; }
        * { box-sizing: border-box; }
        body { color: #111827; font-family: Arial, sans-serif; font-size: <?= $isExcel ? '10pt' : '9px' ?>; line-height: 1.3; margin: 0; }
        .print-actions { margin-bottom: 14px; text-align: right; }
        .print-actions button { background: #0f766e; border: 0; border-radius: 4px; color: #fff; cursor: pointer; font-weight: 700; padding: 8px 12px; }
        .header { border-bottom: 2px solid #111827; margin-bottom: 12px; padding-bottom: 10px; text-align: center; }
        .header h1, .header h2, .header p { margin: 0; }
        .header h1 { font-size: 14px; text-transform: uppercase; }
        .header h2 { font-size: 12px; margin-top: 4px; text-transform: uppercase; }
        .header p { color: #4b5563; font-size: 9px; margin-top: 4px; }
        .lot-title { background: #dbeafe; border: 1px solid #6b7280; font-weight: 700; margin: 14px 0 0; padding: 6px; }
        .lot-justification { border: 1px solid #6b7280; border-top: 0; color: #374151; margin-bottom: 8px; padding: 6px; }
        table { border-collapse: collapse; margin-bottom: 12px; table-layout: fixed; width: 100%; }
        thead { display: table-header-group; }
        th, td { border: 1px solid #6b7280; padding: 5px; vertical-align: top; }
        th { background: #e5e7eb; font-size: 8px; text-align: left; text-transform: uppercase; }
        tbody tr:nth-child(even) { background: #f9fafb; }
        tfoot th { background: #111827; color: #fff; }
        .number, .money { text-align: right; white-space: nowrap; }
        .muted { color: #6b7280; }
        .wrap { white-space: normal; mso-data-placement: same-cell; }
        @media print { .print-actions { display: none; } }
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
    <h1>Prefeitura Municipal de Espirito Santo do Turvo</h1>
    <h2><?= e($title) ?></h2>
    <p>Projeto: <?= e($project['name']) ?> | Emissao: <?= date('d/m/Y') ?> | Versao: <?= e($annexVersionText) ?> | Hash: <?= e($annexHashText) ?></p>
</div>

<?php if (!$lots): ?>
    <table>
        <tr>
            <td class="muted">Nenhum item demandado no projeto.</td>
        </tr>
    </table>
<?php endif; ?>

<?php foreach ($lots as $lot): ?>
    <?php
        $lotNumberText = $lot['lot_number'] !== null ? (string) (int) $lot['lot_number'] : '-';
        $lotTitle = $lot['lot_number'] !== null
            ? 'Lote ' . (int) $lot['lot_number'] . ' - ' . (string) $lot['name']
            : (string) $lot['name'];
    ?>
    <div class="lot-title"><?= e($lotTitle) ?></div>
    <div class="lot-justification">
        <strong>Justificativa:</strong> <?= nl2br(e($lot['justification'] ?? '-')) ?>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 6%;">Lote</th>
                <th style="width: 8%;">Item</th>
                <th style="width: 34%;">Descricao</th>
                <th style="width: 14%;">Unidade</th>
                <th style="width: 10%;">Quantidade</th>
                <th style="width: 14%;">Valor unitario estimado</th>
                <th style="width: 14%;">Valor total estimado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lot['items'] as $item): ?>
                <?php
                    $quantity = (float) ($item['annex_quantity'] ?? 0);
                    $estimatedUnitPrice = $item['estimated_unit_price'];
                    $estimatedTotal = $item['estimated_total'];
                ?>
                <tr>
                    <td class="number"><?= e($lotNumberText) ?></td>
                    <td class="number"><?= (int) $item['sequence'] ?></td>
                    <td class="wrap"><?= e($item['item_name'] ?? '-') ?></td>
                    <td class="wrap"><?= e(licitation_annex_unit_text($item)) ?></td>
                    <td class="number" <?= $isExcel ? 'x:num="' . e((string) $quantity) . '"' : '' ?>>
                        <?= e(format_decimal_quantity($quantity)) ?>
                    </td>
                    <td class="money" <?= $isExcel && $estimatedUnitPrice !== null ? 'x:num="' . e((string) $estimatedUnitPrice) . '"' : '' ?>>
                        <?= $estimatedUnitPrice !== null ? 'R$ ' . number_format((float) $estimatedUnitPrice, 2, ',', '.') : '-' ?>
                    </td>
                    <td class="money" <?= $isExcel && $estimatedTotal !== null ? 'x:num="' . e((string) $estimatedTotal) . '"' : '' ?>>
                        <?= $estimatedTotal !== null ? 'R$ ' . number_format((float) $estimatedTotal, 2, ',', '.') : '-' ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="6" class="money">Subtotal estimado do lote <?= e($lotNumberText) ?></th>
                <th class="money" <?= $isExcel ? 'x:num="' . e((string) $lot['subtotal']) . '"' : '' ?>>
                    R$ <?= number_format((float) $lot['subtotal'], 2, ',', '.') ?>
                </th>
            </tr>
        </tfoot>
    </table>
<?php endforeach; ?>

<table>
    <tfoot>
        <tr>
            <th colspan="6" class="money">Valor global estimado</th>
            <th class="money" <?= $isExcel ? 'x:num="' . e((string) $globalTotal) . '"' : '' ?>>
                R$ <?= number_format($globalTotal, 2, ',', '.') ?>
            </th>
        </tr>
    </tfoot>
</table>

</body>
</html>
