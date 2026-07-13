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

$title = project_annex_types()['lot_annex_iv'];
$annex = get_project_lot_licitation_annex_ii_groups($id);
$lots = $annex['lots'];
$globalTotal = (float) $annex['global_total'];
$annexVersion = register_project_annex_version($id, 'lot_annex_iv');
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

if (!function_exists('lot_annex_iv_items_text')) {
    function lot_annex_iv_items_text(array $lot): string
    {
        $items = [];

        foreach ($lot['items'] ?? [] as $item) {
            $sequence = (int) ($item['sequence'] ?? 0);
            $name = trim((string) ($item['item_name'] ?? '-'));
            $key = ((int) ($item['procurement_item_id'] ?? 0)) . '|' . $sequence . '|' . $name;

            $items[$key] = [
                'sequence' => $sequence,
                'name' => $name !== '' ? $name : '-',
            ];
        }

        if (!$items) {
            foreach ($lot['supplier_groups'] ?? [] as $supplierGroup) {
                foreach ($supplierGroup['items'] ?? [] as $item) {
                    $sequence = (int) ($item['sequence'] ?? 0);
                    $name = trim((string) ($item['item_name'] ?? '-'));
                    $key = ((int) ($item['procurement_item_id'] ?? 0)) . '|' . $sequence . '|' . $name;

                    $items[$key] = [
                        'sequence' => $sequence,
                        'name' => $name !== '' ? $name : '-',
                    ];
                }
            }
        }

        usort($items, static function (array $left, array $right): int {
            $leftSequence = (int) ($left['sequence'] ?? 0);
            $rightSequence = (int) ($right['sequence'] ?? 0);

            if ($leftSequence > 0 || $rightSequence > 0) {
                return ($leftSequence ?: PHP_INT_MAX) <=> ($rightSequence ?: PHP_INT_MAX);
            }

            return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
        });

        $lines = [];

        foreach ($items as $item) {
            $sequence = (int) ($item['sequence'] ?? 0);
            $lines[] = ($sequence > 0 ? $sequence . ' - ' : '') . (string) ($item['name'] ?? '-');
        }

        return $lines ? implode("\n", $lines) : '-';
    }
}

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
        @page { size: A4 landscape; margin: 10mm; }
        * { box-sizing: border-box; }
        body { color: #111827; font-family: Arial, sans-serif; font-size: <?= $isExcel ? '10pt' : '9px' ?>; line-height: 1.3; margin: 0; }
        .print-actions { align-items: center; display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; margin-bottom: 14px; }
        .print-actions .issue-date-form { align-items: center; display: inline-flex; flex-wrap: wrap; gap: 8px; }
        .print-actions input { border: 1px solid #9ca3af; border-radius: 4px; padding: 7px 8px; }
        .print-actions button { background: #0f766e; border: 0; border-radius: 4px; color: #fff; cursor: pointer; font-weight: 700; padding: 8px 12px; }
        .header { border-bottom: 2px solid #111827; margin-bottom: 12px; padding-bottom: 10px; text-align: center; }
        .header h1, .header h2, .header p { margin: 0; }
        .header h1 { font-size: 14px; text-transform: uppercase; }
        .header h2 { font-size: 12px; margin-top: 4px; text-transform: uppercase; }
        .header p { color: #4b5563; font-size: 9px; margin-top: 4px; }
        table { border-collapse: collapse; margin-bottom: 12px; table-layout: fixed; width: 100%; }
        thead { display: table-header-group; }
        th, td { border: 1px solid #6b7280; padding: 6px; vertical-align: top; }
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
    <?= render_annex_print_actions($issueDate) ?>
<?php endif; ?>

<div class="header">
    <?= render_municipal_logo() ?>
    <h1>Prefeitura Municipal de Espirito Santo do Turvo</h1>
    <h2><?= e($title) ?></h2>
    <p>Projeto: <?= e($project['name']) ?> | Emissao: <?= e($issueDateText) ?> | Versao do documento: <?= e($annexVersionText) ?> | Hash: <?= e($annexHashText) ?></p>
</div>

<?php if (!$lots): ?>
    <table>
        <tr>
            <td class="muted">Nenhum item com estimativa de precos no projeto.</td>
        </tr>
    </table>
<?php endif; ?>

<?php if ($lots): ?>
    <table>
        <thead>
            <tr>
                <th style="width: 8%;">Lote</th>
                <th style="width: 24%;">Denominacao</th>
                <th style="width: 48%;">Itens integrantes</th>
                <th style="width: 20%;">Valor estimado do lote</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lots as $lot): ?>
                <?php
                    $subtotal = (float) ($lot['subtotal'] ?? 0);
                    $lotNumber = $lot['lot_number'] !== null ? (int) $lot['lot_number'] : null;
                ?>
                <tr>
                    <td class="number"><?= $lotNumber !== null ? $lotNumber : '-' ?></td>
                    <td class="wrap"><?= e($lot['name'] ?? '-') ?></td>
                    <td class="wrap"><?= nl2br(e(lot_annex_iv_items_text($lot))) ?></td>
                    <td class="money" <?= $isExcel ? 'x:num="' . e((string) $subtotal) . '"' : '' ?>>
                        R$ <?= number_format($subtotal, 2, ',', '.') ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="3" class="money">Valor global estimado</th>
                <th class="money" <?= $isExcel ? 'x:num="' . e((string) $globalTotal) . '"' : '' ?>>
                    R$ <?= number_format($globalTotal, 2, ',', '.') ?>
                </th>
            </tr>
        </tfoot>
    </table>
<?php endif; ?>

</body>
</html>
