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

$title = 'Anexo I - Planilha de Itens, EspecificaÃ§Ãµes, Quantitativos e MemÃ³ria de CÃ¡lculo';
$items = get_project_licitation_annex_i_items($id);
$annexVersion = register_project_annex_version($id, 'annex_i');
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
    <title><?= e($title) ?> - <?= e($project['name']) ?></title>

    <style>
        @page {
            size: A4 landscape;
            margin: 10mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            color: #111827;
            font-family: Arial, sans-serif;
            font-size: <?= $isExcel ? '10pt' : '9px' ?>;
            line-height: 1.3;
            margin: 0;
        }

        .print-actions {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-end;
            margin-bottom: 14px;
        }

        .print-actions .issue-date-form {
            align-items: center;
            display: inline-flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .print-actions input {
            border: 1px solid #9ca3af;
            border-radius: 4px;
            padding: 7px 8px;
        }

        .print-actions button {
            background: #0f766e;
            border: 0;
            border-radius: 4px;
            color: #fff;
            cursor: pointer;
            font-weight: 700;
            padding: 8px 12px;
        }

        .header {
            border-bottom: 2px solid #111827;
            margin-bottom: 12px;
            padding-bottom: 10px;
            text-align: center;
        }

        .header h1,
        .header h2,
        .header p {
            margin: 0;
        }

        .header h1 {
            font-size: 14px;
            text-transform: uppercase;
        }

        .header h2 {
            font-size: 12px;
            margin-top: 4px;
            text-transform: uppercase;
        }

        .header p {
            color: #4b5563;
            font-size: 9px;
            margin-top: 4px;
        }

        table {
            border-collapse: collapse;
            table-layout: fixed;
            width: 100%;
        }

        thead {
            display: table-header-group;
        }

        th,
        td {
            border: 1px solid #6b7280;
            padding: 5px;
            vertical-align: top;
        }

        th {
            background: #e5e7eb;
            font-size: 8px;
            text-align: left;
            text-transform: uppercase;
        }

        tbody tr:nth-child(even) {
            background: #f9fafb;
        }

        .number {
            text-align: right;
            white-space: nowrap;
        }

        .muted {
            color: #6b7280;
        }

        .wrap {
            white-space: normal;
            mso-data-placement: same-cell;
        }

        @media print {
            .print-actions {
                display: none;
            }
        }
    </style>
</head>

<body>

<?php if ($format === 'pdf'): ?>
    <?= render_annex_print_actions($issueDate) ?>
<?php endif; ?>

<div class="header">
    <?= render_municipal_logo() ?>
    <h1>Prefeitura Municipal de EspÃ­rito Santo do Turvo</h1>
    <h2><?= e($title) ?></h2>
    <p>Projeto: <?= e($project['name']) ?> | Emissao: <?= e($issueDateText) ?> | Versao do documento: <?= e($annexVersionText) ?> | Hash: <?= e($annexHashText) ?></p>
</div>

<table>
    <thead>
        <tr>
            <th style="width: 5%;">Item</th>
            <th style="width: 17%;">Nome do item</th>
            <th style="width: 34%;">EspecificaÃ§Ã£o tÃ©cnica</th>
            <th style="width: 13%;">Unidade</th>
            <th style="width: 8%;">Quantidade</th>
            <th style="width: 23%;">Demandas / memÃ³ria de cÃ¡lculo</th>
        </tr>
    </thead>

    <tbody>
        <?php if (!$items): ?>
            <tr>
                <td colspan="6" class="muted">Nenhum item demandado no projeto.</td>
            </tr>
        <?php endif; ?>

        <?php foreach ($items as $item): ?>
            <?php
                $quantity = (float) ($item['annex_quantity'] ?? 0);
                $specification = licitation_annex_specification_text($item);
                $demandMemory = licitation_annex_demand_memory_text($item['demand_memory'] ?? []);
            ?>
            <tr>
                <td class="number"><?= (int) $item['sequence'] ?></td>
                <td class="wrap"><?= e($item['item_name'] ?? '-') ?></td>
                <td class="wrap"><?= nl2br(e($specification)) ?></td>
                <td class="wrap"><?= e(licitation_annex_unit_text($item)) ?></td>
                <td class="number" <?= $isExcel ? 'x:num="' . e((string) $quantity) . '"' : '' ?>>
                    <?= e(format_decimal_quantity($quantity)) ?>
                </td>
                <td class="wrap"><?= nl2br(e($demandMemory)) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>
