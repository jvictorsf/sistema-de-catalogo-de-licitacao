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

$title = "Anexo II por lote - Pesquisa e estimativa de pre\u{00E7}os por lote";
$annex = get_project_lot_licitation_annex_ii_groups($id);
$lots = $annex['lots'];
$globalTotal = (float) $annex['global_total'];
$annexVersion = register_project_annex_version($id, 'lot_annex_ii');
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

if (!function_exists('annex_proposal_date_text')) {
    function annex_proposal_date_text(array $supplier): string
    {
        $dates = $supplier['proposal_dates'] ?? [];
        $dates = is_array($dates) ? $dates : [];

        if (!$dates && !empty($supplier['proposal_date'])) {
            $dates[] = $supplier['proposal_date'];
        }

        $formatted = [];

        foreach ($dates as $date) {
            $timestamp = strtotime((string) $date);

            if ($timestamp !== false) {
                $formatted[] = date('d/m/Y', $timestamp);
            }
        }

        $formatted = array_values(array_unique($formatted));

        return $formatted ? implode(', ', $formatted) : '-';
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
    <title><?= e($title) ?> - <?= e($project['name']) ?></title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        * { box-sizing: border-box; }
        body { color: #111827; font-family: Arial, sans-serif; font-size: <?= $isExcel ? '10pt' : '8px' ?>; line-height: 1.3; margin: 0; }
        .print-actions { align-items: center; display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; margin-bottom: 14px; }
        .print-actions .issue-date-form { align-items: center; display: inline-flex; flex-wrap: wrap; gap: 8px; }
        .print-actions input { border: 1px solid #9ca3af; border-radius: 4px; padding: 7px 8px; }
        .print-actions button { background: #0f766e; border: 0; border-radius: 4px; color: #fff; cursor: pointer; font-weight: 700; padding: 8px 12px; }
        .header { border-bottom: 2px solid #111827; margin-bottom: 12px; padding-bottom: 10px; text-align: center; }
        .header h1, .header h2, .header p { margin: 0; }
        .header h1 { font-size: 14px; text-transform: uppercase; }
        .header h2 { font-size: 12px; margin-top: 4px; text-transform: uppercase; }
        .header p { color: #4b5563; font-size: 9px; margin-top: 4px; }
        .lot-title { background: #dbeafe; border: 1px solid #6b7280; font-weight: 700; margin: 14px 0 0; padding: 6px; }
        .lot-justification { border: 1px solid #6b7280; border-top: 0; color: #374151; margin-bottom: 8px; padding: 6px; }
        .supplier-title { background: #f3f4f6; border: 1px solid #6b7280; font-weight: 700; margin-top: 8px; padding: 5px; }
        table { border-collapse: collapse; margin-bottom: 10px; table-layout: fixed; width: 100%; }
        thead { display: table-header-group; }
        th, td { border: 1px solid #6b7280; padding: 4px; vertical-align: top; }
        th { background: #e5e7eb; font-size: 7px; text-align: left; text-transform: uppercase; }
        tbody tr:nth-child(even) { background: #f9fafb; }
        .number, .money { text-align: right; white-space: nowrap; }
        .muted { color: #6b7280; }
        .wrap { white-space: normal; mso-data-placement: same-cell; }
        .annex-item-name { display: block; font-weight: 700; margin-bottom: 6px; }
        .annex-spec-section + .annex-spec-section { margin-top: 6px; }
        .annex-spec-title { display: block; font-weight: 700; margin-bottom: 2px; }
        .annex-specification ul { margin: 2px 0 0; padding-left: 16px; }
        .annex-specification li + li { margin-top: 2px; }
        .annex-spec-empty { color: #6b7280; }
        .global-total { margin-top: 18px; width: 45%; }
        .global-total th { background: #111827; color: #fff; }
        @media print { .print-actions { display: none; } }
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

    <?php foreach ($lot['supplier_groups'] as $groupIndex => $supplierGroup): ?>
        <?php
            $suppliers = $supplierGroup['suppliers'] ?? [];
        ?>

        <div class="supplier-title">
            <?= $suppliers ? 'Fornecedores consultados' : 'Itens sem cota&ccedil;&atilde;o de fornecedor' ?>
        </div>

        <?php if ($suppliers): ?>
            <table>
                <thead>
                    <tr>
                        <th style="width: 6%;">Fornecedor</th>
                        <th style="width: 8%;">Data da proposta</th>
                        <th style="width: 11%;">CNPJ</th>
                        <th style="width: 18%;">Raz&atilde;o social</th>
                        <th style="width: 22%;">Endere&ccedil;o</th>
                        <th style="width: 12%;">Contato</th>
                        <th style="width: 13%;">E-mail</th>
                        <th style="width: 10%;">Telefone</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($suppliers as $supplierIndex => $supplier): ?>
                        <tr>
                            <td>Fornecedor <?= $supplierIndex + 1 ?></td>
                            <td><?= e(annex_proposal_date_text($supplier)) ?></td>
                            <td><?= e(!empty($supplier['document']) ? format_brazil_document($supplier['document']) : '-') ?></td>
                            <td><?= e($supplier['name'] ?? '-') ?></td>
                            <td><?= e(supplier_address_text($supplier)) ?></td>
                            <td><?= e(($supplier['contact_name'] ?? '') ?: '-') ?></td>
                            <td><?= e(($supplier['email'] ?? '') ?: '-') ?></td>
                            <td><?= e(!empty($supplier['phone']) ? format_brazil_phone($supplier['phone']) : '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th style="width: 5%;">Lote</th>
                    <th style="width: 10%;">Denomina&ccedil;&atilde;o</th>
                    <th style="width: 5%;">Item</th>
                    <th style="width: 24%;">Descri&ccedil;&atilde;o e especifica&ccedil;&atilde;o t&eacute;cnica</th>
                    <th style="width: 9%;">Unidade</th>
                    <th style="width: 7%;">Quantidade estimada</th>
                    <th style="width: 15%;">Mem&oacute;ria / justificativa do quantitativo</th>
                    <th style="width: 13%;">Valores dos fornecedores</th>
                    <th style="width: 6%;">Valor unit&aacute;rio estimado</th>
                    <th style="width: 6%;">Valor total estimado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($supplierGroup['items'] as $item): ?>
                    <?php
                        $quantity = (float) ($item['annex_quantity'] ?? 0);
                        $estimatedUnitPrice = $item['estimated_unit_price'];
                        $estimatedTotal = $item['estimated_total'];
                        $demandMemory = licitation_annex_demand_memory_text($item['demand_memory'] ?? []);
                    ?>
                    <tr>
                        <td class="number"><?= e($lotNumberText) ?></td>
                        <td class="wrap"><?= e($lot['name'] ?? '-') ?></td>
                        <td class="number"><?= (int) $item['sequence'] ?></td>
                        <td class="wrap">
                            <strong class="annex-item-name"><?= e($item['item_name'] ?? '-') ?></strong>
                            <?= licitation_annex_specification_html($item) ?>
                        </td>
                        <td class="wrap"><?= e(licitation_annex_unit_text($item)) ?></td>
                        <td class="number" <?= $isExcel ? 'x:num="' . e((string) $quantity) . '"' : '' ?>>
                            <?= e(format_decimal_quantity($quantity)) ?>
                        </td>
                        <td class="wrap"><?= nl2br(e($demandMemory)) ?></td>
                        <td class="wrap">
                            <?php if (!$suppliers): ?>
                                -
                            <?php endif; ?>

                            <?php foreach ($suppliers as $supplierIndex => $supplier): ?>
                                <?php
                                    $supplierKey = (string) $supplier['key'];
                                    $price = $item['supplier_prices'][$supplierKey] ?? null;
                                ?>
                                <div>
                                    <strong>F<?= $supplierIndex + 1 ?>:</strong>
                                    <?= $price !== null ? 'R$ ' . number_format((float) $price, 2, ',', '.') : '-' ?>
                                </div>
                            <?php endforeach; ?>
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
        </table>
    <?php endforeach; ?>

    <table>
        <tr>
            <th colspan="9" class="money">Subtotal estimado do lote <?= e($lotNumberText) ?></th>
            <th class="money" <?= $isExcel ? 'x:num="' . e((string) $lot['subtotal']) . '"' : '' ?>>
                R$ <?= number_format((float) $lot['subtotal'], 2, ',', '.') ?>
            </th>
        </tr>
    </table>
<?php endforeach; ?>

<table class="global-total">
    <tr>
        <th>Valor global estimado</th>
        <td class="money" <?= $isExcel ? 'x:num="' . e((string) $globalTotal) . '"' : '' ?>>
            R$ <?= number_format($globalTotal, 2, ',', '.') ?>
        </td>
    </tr>
</table>

</body>
</html>
