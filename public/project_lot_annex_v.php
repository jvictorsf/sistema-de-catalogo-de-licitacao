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

$title = project_annex_types()['lot_annex_v'];
$annex = get_project_lot_licitation_annex_iii_groups($id);
$lots = $annex['lots'] ?? [];
$annexVersion = register_project_annex_version($id, 'lot_annex_v');
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
$issueDate = annex_issue_date_value();
$issueDateText = annex_issue_date_text($issueDate);

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
    <title><?= e($title) ?></title>
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        * { box-sizing: border-box; }
        body { color: #111827; font-family: Arial, sans-serif; font-size: <?= $isExcel ? '11pt' : '9px' ?>; line-height: 1.35; margin: 0; }
        .print-actions { align-items: center; display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; margin-bottom: 14px; }
        .print-actions .issue-date-form { align-items: center; display: inline-flex; flex-wrap: wrap; gap: 8px; }
        .print-actions input { border: 1px solid #9ca3af; border-radius: 4px; padding: 7px 8px; }
        .print-actions button { background: #0f766e; border: 0; border-radius: 4px; color: #fff; cursor: pointer; font-weight: 700; padding: 8px 12px; }
        .header { border-bottom: 2px solid #111827; margin-bottom: 14px; padding-bottom: 10px; text-align: center; }
        .header h1, .header h2, .header p { margin: 0; }
        .header h1 { font-size: 14px; text-transform: uppercase; }
        .header h2 { font-size: 12px; margin-top: 5px; text-transform: uppercase; }
        .header p { color: #4b5563; font-size: 9px; margin-top: 5px; }
        .lot-section { margin-bottom: 16px; }
        .lot-title { background: #dbeafe; border: 1px solid #6b7280; font-size: 11px; font-weight: 700; margin: 0; padding: 7px; }
        table { border-collapse: collapse; margin: 0; table-layout: fixed; width: 100%; }
        thead { display: table-header-group; }
        tfoot { display: table-footer-group; }
        tr { break-inside: avoid; page-break-inside: avoid; }
        th, td { border: 1px solid #6b7280; padding: 7px; vertical-align: middle; }
        th { background: #e5e7eb; font-size: 8px; text-align: left; text-transform: uppercase; }
        tbody tr:nth-child(even) { background: #f9fafb; }
        tfoot th { background: #1f2937; color: #fff; }
        .item { overflow-wrap: anywhere; }
        .item-number { display: inline-block; font-weight: 700; min-width: 24px; }
        .number, .money { text-align: right; white-space: nowrap; }
        .muted { color: #6b7280; }
        @media print {
            .print-actions { display: none; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .lot-title { break-after: avoid; page-break-after: avoid; }
        }
    </style>
</head>
<body>

<?php if ($format === 'pdf'): ?>
    <?= render_annex_print_actions($issueDate) ?>
<?php endif; ?>

<div class="header">
    <?= render_municipal_logo() ?>
    <h1>Prefeitura Municipal de Esp&iacute;rito Santo do Turvo</h1>
    <h2><?= e($title) ?></h2>
    <p>Projeto: <?= e($project['name']) ?> | Emiss&atilde;o: <?= e($issueDateText) ?> | Vers&atilde;o do documento: <?= e($annexVersionText) ?> | Hash: <?= e($annexHashText) ?></p>
</div>

<?php if (!$lots): ?>
    <table>
        <tr>
            <td class="muted">Nenhum item com estimativa de pre&ccedil;os no projeto.</td>
        </tr>
    </table>
<?php endif; ?>

<?php foreach ($lots as $lot): ?>
    <?php
        $lotNumber = $lot['lot_number'] !== null ? (int) $lot['lot_number'] : null;
        $lotTitle = $lotNumber !== null
            ? 'Lote ' . $lotNumber . ' - ' . (string) ($lot['name'] ?? '-')
            : (string) ($lot['name'] ?? 'Itens sem denominacao');
        $lotSubtotal = (float) ($lot['subtotal'] ?? 0);
    ?>
    <section class="lot-section">
        <h3 class="lot-title"><?= e($lotTitle) ?></h3>
        <table>
            <thead>
                <tr>
                    <th style="width: 52%;">Item</th>
                    <th style="width: 16%;">Quantidade</th>
                    <th style="width: 16%;">Valor unit&aacute;rio</th>
                    <th style="width: 16%;">Valor total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lot['items'] ?? [] as $item): ?>
                    <?php
                        $sequence = (int) ($item['sequence'] ?? 0);
                        $quantity = (float) ($item['annex_quantity'] ?? 0);
                        $unitPrice = ($item['estimated_unit_price'] ?? null) !== null
                            ? (float) $item['estimated_unit_price']
                            : null;
                        $totalPrice = ($item['estimated_total'] ?? null) !== null
                            ? (float) $item['estimated_total']
                            : null;
                    ?>
                    <tr>
                        <td class="item">
                            <span class="item-number"><?= $sequence > 0 ? $sequence . '.' : '-' ?></span>
                            <?= e($item['item_name'] ?? '-') ?>
                        </td>
                        <td class="number" <?= $isExcel ? 'x:num="' . e((string) $quantity) . '"' : '' ?>>
                            <?= e(format_decimal_quantity($quantity)) ?>
                        </td>
                        <td class="money" <?= $isExcel && $unitPrice !== null ? 'x:num="' . e((string) $unitPrice) . '"' : '' ?>>
                            <?= $unitPrice !== null ? 'R$ ' . number_format($unitPrice, 2, ',', '.') : '-' ?>
                        </td>
                        <td class="money" <?= $isExcel && $totalPrice !== null ? 'x:num="' . e((string) $totalPrice) . '"' : '' ?>>
                            <?= $totalPrice !== null ? 'R$ ' . number_format($totalPrice, 2, ',', '.') : '-' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3" class="money">Valor total do lote</th>
                    <th class="money" <?= $isExcel ? 'x:num="' . e((string) $lotSubtotal) . '"' : '' ?>>
                        R$ <?= number_format($lotSubtotal, 2, ',', '.') ?>
                    </th>
                </tr>
            </tfoot>
        </table>
    </section>
<?php endforeach; ?>

</body>
</html>
