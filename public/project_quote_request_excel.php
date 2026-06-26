<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

function project_quote_request_excel_lot_label(array $group): string
{
    if (!empty($group['is_unassigned'])) {
        return 'Sem denominacao';
    }

    return 'Lote ' . (int) ($group['lot_number'] ?? 0) . ' - ' . (string) ($group['name'] ?? 'Denominacao');
}

$id = (int) ($_GET['id'] ?? 0);

$project = find_project($id);

if (!$project) {
    http_response_code(404);
    exit('Projeto nao encontrado.');
}

$allItems = get_project_consolidated_items($id);
$groups = supplier_quote_request_groups_from_items($allItems);
$lotGroups = get_project_lot_groups($id, $allItems);
$groupFilterApplied = array_key_exists('group_id', $_GET);
$lotFilterApplied = array_key_exists('lot_id', $_GET);
$selectedGroup = null;
$selectedLot = null;
$selectedContextName = null;

if ($lotFilterApplied) {
    $lotId = max(0, (int) ($_GET['lot_id'] ?? 0));

    foreach ($lotGroups as $lotGroup) {
        if ((int) ($lotGroup['lot_id'] ?? 0) === $lotId) {
            $selectedLot = $lotGroup;
            break;
        }
    }

    if (!$selectedLot) {
        http_response_code(404);
        exit('Denominacao nao encontrada no projeto.');
    }

    $items = $selectedLot['items'] ?? [];
    $selectedContextName = project_quote_request_excel_lot_label($selectedLot);
} elseif ($groupFilterApplied) {
    $groupId = max(0, (int) ($_GET['group_id'] ?? 0));
    $groupKey = (string) $groupId;

    if (!isset($groups[$groupKey])) {
        http_response_code(404);
        exit('Grupo nao encontrado no projeto.');
    }

    $selectedGroup = $groups[$groupKey];
    $items = supplier_quote_request_filter_items_by_group($allItems, $groupId);
    $selectedContextName = (string) $selectedGroup['name'];
} else {
    $items = $allItems;
}
$totalQuantity = array_reduce(
    $items,
    static fn (float $total, array $item): float => $total + (float) ($item['total_approved_quantity'] ?? 0),
    0.0
);

$filename = 'solicitacao-orcamento-projeto-' . $id
    . ($selectedLot ? '-denominacao-' . (int) $selectedLot['lot_id'] : '')
    . ($selectedGroup ? '-grupo-' . (int) $selectedGroup['id'] : '')
    . '.xls';

send_download_headers('application/vnd.ms-excel; charset=utf-8', $filename);

?>
<!doctype html>
<html
    lang="pt-BR"
    xmlns:o="urn:schemas-microsoft-com:office:office"
    xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #808080;
            padding: 6px;
            vertical-align: top;
        }

        th {
            background: #d9eaf7;
            font-weight: bold;
            text-align: center;
        }

        .title {
            background: #1f4e78;
            color: #ffffff;
            font-size: 14pt;
            font-weight: bold;
            text-align: center;
        }

        .meta {
            background: #f2f2f2;
            font-weight: bold;
        }

        .section {
            background: #ddebf7;
            font-weight: bold;
            text-align: center;
        }

        .fill {
            background: #fff2cc;
        }

        .text {
            mso-number-format: "\@";
        }

        .number {
            mso-number-format: "0.00";
        }

        .money {
            mso-number-format: "R\$ #,##0.00";
        }

        .wrap {
            white-space: normal;
            mso-data-placement: same-cell;
        }
    </style>
</head>

<body>
<table>
    <tr>
        <td colspan="16" class="title">
            Solicitacao formal de orcamento - <?= $selectedContextName ? 'Filtro' : 'Projeto' ?>
        </td>
    </tr>

    <tr>
        <td colspan="8" class="meta">Projeto: <?= e($project['name']) ?></td>
        <td colspan="4" class="meta">Data de emissao: <?= date('d/m/Y') ?></td>
        <td colspan="4" class="meta">Quantidade total: <?= e(format_decimal_quantity($totalQuantity)) ?></td>
    </tr>

    <?php if ($selectedContextName): ?>
        <tr>
            <td colspan="16" class="meta">Escopo: <?= e($selectedContextName) ?></td>
        </tr>
    <?php endif; ?>

    <tr>
        <td colspan="16" class="section">
            Preencha as colunas amarelas e devolva esta planilha ao setor solicitante.
        </td>
    </tr>

    <tr>
        <th>Codigo</th>
        <th>Item</th>
        <th>Marca ref.</th>
        <th>Modelo ref.</th>
        <th>Caracteristicas minimas</th>
        <th>Tipo de unidade</th>
        <th>Conteudo</th>
        <th>Un. conteudo</th>
        <th>Quantidade</th>
        <th>Marca ofertada</th>
        <th>Modelo ofertado</th>
        <th>Valor unitario</th>
        <th>Valor total</th>
        <th>Prazo de entrega</th>
        <th>Validade da proposta</th>
        <th>Observacoes do fornecedor</th>
    </tr>

    <?php if (!$items): ?>
        <tr>
            <td colspan="16">Nenhum item demandado no projeto.</td>
        </tr>
    <?php endif; ?>

    <?php $excelRow = $selectedContextName ? 6 : 5; ?>
    <?php foreach ($items as $item): ?>
        <?php
            $specification = item_specification_array_from_value($item['specification'] ?? []);
            $quantity = (float) ($item['total_approved_quantity'] ?? $item['total_quantity'] ?? 0);
            $packageContentQuantity = $item['package_content_quantity'] ?? null;
            $packageContentUnit = $item['package_content_unit_type_name']
                ?? $item['package_content_unit_type_abbreviation']
                ?? null;
            $totalFormula = '=I' . $excelRow . '*L' . $excelRow;
        ?>
        <tr>
            <td class="text"><?= e($item['tracking_code'] ?? '-') ?></td>
            <td class="wrap"><?= e($item['item_name'] ?? '-') ?></td>
            <td class="wrap"><?= e(supplier_quote_request_value_text($specification['marca_referencia'] ?? null)) ?></td>
            <td class="wrap"><?= e(supplier_quote_request_value_text($specification['modelo_referencia'] ?? null)) ?></td>
            <td class="wrap"><?= nl2br(e(supplier_quote_request_characteristics_text($specification, "\n"))) ?></td>
            <td><?= e($item['unit_type_name'] ?? $item['unit_type_abbreviation'] ?? '-') ?></td>
            <?php if ($packageContentQuantity !== null): ?>
                <td class="number" x:num="<?= e((string) $packageContentQuantity) ?>">
                    <?= e(format_decimal_quantity($packageContentQuantity)) ?>
                </td>
            <?php else: ?>
                <td>-</td>
            <?php endif; ?>
            <td><?= e(supplier_quote_request_value_text($packageContentUnit)) ?></td>
            <td class="number" x:num="<?= e((string) $quantity) ?>"><?= e(format_decimal_quantity($quantity)) ?></td>
            <td class="fill"></td>
            <td class="fill"></td>
            <td class="money fill"></td>
            <td class="money fill" x:fmla="<?= e($totalFormula) ?>"></td>
            <td class="fill"></td>
            <td class="fill"></td>
            <td class="fill"></td>
        </tr>
        <?php $excelRow++; ?>
    <?php endforeach; ?>
</table>
</body>
</html>
