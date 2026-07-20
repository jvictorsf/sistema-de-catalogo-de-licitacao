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

$title = project_annex_types()['annex_iv'];
$items = get_project_licitation_annex_i_items($id);
$annexVersion = register_project_annex_version($id, 'annex_iv');
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
        @page {
            size: A4 portrait;
            margin: 14mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            color: #111827;
            font-family: Arial, sans-serif;
            font-size: <?= $isExcel ? '11pt' : '10px' ?>;
            line-height: 1.35;
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
            margin-bottom: 14px;
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
            margin-top: 5px;
            text-transform: uppercase;
        }

        .header p {
            color: #4b5563;
            font-size: 9px;
            margin-top: 5px;
        }

        table {
            border-collapse: collapse;
            table-layout: fixed;
            width: 100%;
        }

        thead {
            display: table-header-group;
        }

        tr {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        th,
        td {
            border: 1px solid #6b7280;
            padding: 7px;
            vertical-align: middle;
        }

        th {
            background: #e5e7eb;
            font-size: 9px;
            text-align: left;
            text-transform: uppercase;
        }

        tbody tr:nth-child(even) {
            background: #f9fafb;
        }

        .number {
            text-align: center;
            white-space: nowrap;
        }

        .quantity {
            text-align: right;
            white-space: nowrap;
        }

        .muted {
            color: #6b7280;
        }

        .wrap {
            overflow-wrap: anywhere;
            white-space: normal;
            mso-data-placement: same-cell;
        }

        @media print {
            .print-actions {
                display: none;
            }

            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
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
    <h1>Prefeitura Municipal de Esp&iacute;rito Santo do Turvo</h1>
    <h2><?= e($title) ?></h2>
    <p>
        Projeto: <?= e($project['name']) ?>
        | Emiss&atilde;o: <?= e($issueDateText) ?>
        | Vers&atilde;o do documento: <?= e($annexVersionText) ?>
        | Hash: <?= e($annexHashText) ?>
    </p>
</div>

<table>
    <thead>
        <tr>
            <th style="width: 10%;">Item</th>
            <th style="width: 72%;">Nome do item</th>
            <th style="width: 18%;">Quantidade</th>
        </tr>
    </thead>

    <tbody>
        <?php if (!$items): ?>
            <tr>
                <td colspan="3" class="muted">Nenhum item demandado no projeto.</td>
            </tr>
        <?php endif; ?>

        <?php foreach ($items as $item): ?>
            <?php $quantity = (float) ($item['annex_quantity'] ?? 0); ?>
            <tr>
                <td class="number"><?= (int) ($item['sequence'] ?? 0) ?></td>
                <td class="wrap"><?= e($item['item_name'] ?? '-') ?></td>
                <td
                    class="quantity"
                    <?= $isExcel ? 'x:num="' . e((string) $quantity) . '"' : '' ?>>
                    <?= e(format_decimal_quantity($quantity)) ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>
