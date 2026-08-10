<?php

declare(strict_types=1);

function catalog_data_export_formats(): array
{
    return [
        'json' => [
            'label' => 'JSON',
            'icon' => 'bi-filetype-json',
            'description' => 'Formato estruturado para backup, integração e reimportação no sistema.',
        ],
        'pdf' => [
            'label' => 'PDF',
            'icon' => 'bi-filetype-pdf',
            'description' => 'Relatório administrativo pronto para imprimir ou salvar como PDF.',
        ],
        'csv' => [
            'label' => 'CSV',
            'icon' => 'bi-filetype-csv',
            'description' => 'Uma tabela por arquivo; escopos compostos são reunidos em um ZIP.',
        ],
        'xlsx' => [
            'label' => 'XLSX',
            'icon' => 'bi-file-earmark-spreadsheet',
            'description' => 'Planilha Excel com uma aba para cada tabela do escopo.',
        ],
    ];
}

function catalog_data_export_tables(array $payload): array
{
    $data = $payload['data'] ?? [];

    if (!is_array($data)) {
        throw new InvalidArgumentException('Os dados da exportação são inválidos.');
    }

    $definitions = function_exists('catalog_json_table_definitions')
        ? catalog_json_table_definitions()
        : [];
    $labelOverrides = [
        'rich_text_editor_settings' => 'Configurações do editor e documentos',
        'demand_approval_events' => 'Histórico de aprovação das demandas',
        'demand_supplier_quotes' => 'Orçamentos de fornecedores',
        'demand_supplier_quote_attachments' => 'Documentos dos orçamentos',
        'demand_supplier_quote_items' => 'Valores dos orçamentos',
        'demand_price_references' => 'Referências históricas de preços',
        'project_licitation_items' => 'Numeração de licitação',
        'project_annex_versions' => 'Versões dos anexos',
        'project_lot_denominations' => 'Denominações de lotes',
        'project_lot_assignments' => 'Vínculos de lotes',
    ];
    $tables = [];

    foreach ($data as $tableName => $rows) {
        if (!is_string($tableName) || !is_array($rows)) {
            continue;
        }

        $definition = $definitions[$tableName] ?? [];
        $columns = array_values(array_filter(
            (array) ($definition['columns'] ?? []),
            static fn (mixed $column): bool => is_string($column) && $column !== ''
        ));

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            foreach (array_keys($row) as $column) {
                if (is_string($column) && !in_array($column, $columns, true)) {
                    $columns[] = $column;
                }
            }
        }

        $tables[] = [
            'name' => $tableName,
            'label' => $labelOverrides[$tableName] ?? (string) ($definition['label'] ?? $tableName),
            'columns' => $columns,
            'rows' => array_values(array_filter($rows, 'is_array')),
        ];
    }

    return $tables;
}

function catalog_data_export_value(mixed $value): string
{
    if ($value === null) {
        return '';
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    if ($value instanceof DateTimeInterface) {
        return $value->format(DATE_ATOM);
    }

    if (is_array($value) || is_object($value)) {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? '' : $encoded;
    }

    $text = (string) $value;

    if (function_exists('mb_scrub')) {
        $text = mb_scrub($text, 'UTF-8');
    }

    return $text;
}

function catalog_data_export_xml(mixed $value): string
{
    $text = catalog_data_export_value($value);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text) ?? '';

    return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function catalog_data_export_temp_path(string $extension): string
{
    $base = tempnam(sys_get_temp_dir(), 'catalog-data-');

    if ($base === false) {
        throw new RuntimeException('Não foi possível criar o arquivo temporário da exportação.');
    }

    $path = $base . '.' . ltrim($extension, '.');

    if (!rename($base, $path)) {
        @unlink($base);
        throw new RuntimeException('Não foi possível preparar o arquivo temporário da exportação.');
    }

    return $path;
}

function catalog_data_export_write_csv_stream($stream, array $table): void
{
    if (!is_resource($stream)) {
        throw new InvalidArgumentException('Destino CSV inválido.');
    }

    fwrite($stream, "\xEF\xBB\xBF");
    fputcsv($stream, $table['columns'], ';', '"', '');

    foreach ($table['rows'] as $row) {
        $values = [];

        foreach ($table['columns'] as $column) {
            $original = $row[$column] ?? null;
            $value = catalog_data_export_value($original);

            if (is_string($original) && preg_match('/^[=+\-@]/', $value) === 1) {
                $value = "'" . $value;
            }

            $values[] = $value;
        }

        fputcsv($stream, $values, ';', '"', '');
    }
}

function catalog_data_export_csv_contents(array $table): string
{
    $stream = fopen('php://temp', 'w+b');

    if ($stream === false) {
        throw new RuntimeException('Não foi possível preparar o conteúdo CSV.');
    }

    catalog_data_export_write_csv_stream($stream, $table);
    rewind($stream);
    $contents = stream_get_contents($stream);
    fclose($stream);

    if ($contents === false) {
        throw new RuntimeException('Não foi possível ler o conteúdo CSV.');
    }

    return $contents;
}

function catalog_data_export_csv_bundle(array $payload): array
{
    $tables = catalog_data_export_tables($payload);

    if (count($tables) === 1) {
        $path = catalog_data_export_temp_path('csv');
        $stream = fopen($path, 'wb');

        if ($stream === false) {
            @unlink($path);
            throw new RuntimeException('Não foi possível criar o arquivo CSV.');
        }

        catalog_data_export_write_csv_stream($stream, $tables[0]);
        fclose($stream);

        return [
            'path' => $path,
            'extension' => 'csv',
            'content_type' => 'text/csv; charset=utf-8',
        ];
    }

    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('A extensão PHP zip é necessária para exportar escopos compostos em CSV.');
    }

    $path = catalog_data_export_temp_path('zip');
    $zip = new ZipArchive();

    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Não foi possível criar o pacote de arquivos CSV.');
    }

    foreach ($tables as $index => $table) {
        $entry = sprintf('%02d-%s.csv', $index + 1, $table['name']);
        $zip->addFromString($entry, catalog_data_export_csv_contents($table));
    }

    $manifest = [
        'system' => $payload['system'] ?? '',
        'scope' => $payload['scope'] ?? '',
        'exported_at' => $payload['exported_at'] ?? date(DATE_ATOM),
        'format' => 'csv-zip',
        'files' => array_map(
            static fn (array $table, int $index): array => [
                'file' => sprintf('%02d-%s.csv', $index + 1, $table['name']),
                'table' => $table['name'],
                'label' => $table['label'],
                'rows' => count($table['rows']),
            ],
            $tables,
            array_keys($tables)
        ),
    ];
    $zip->addFromString('_manifesto.json', (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $zip->close();

    return [
        'path' => $path,
        'extension' => 'csv.zip',
        'content_type' => 'application/zip',
    ];
}

function catalog_data_export_excel_column(int $number): string
{
    $name = '';

    while ($number > 0) {
        $number--;
        $name = chr(65 + ($number % 26)) . $name;
        $number = intdiv($number, 26);
    }

    return $name;
}

function catalog_data_export_sheet_name(string $label, array $used): string
{
    $label = preg_replace('/[\\\\\/\?\*\[\]:]+/u', ' ', trim($label)) ?? '';
    $label = preg_replace('/\s+/u', ' ', $label) ?? '';
    $label = $label !== '' ? $label : 'Dados';
    $base = function_exists('mb_substr') ? mb_substr($label, 0, 31) : substr($label, 0, 31);
    $candidate = $base;
    $suffix = 2;

    while (in_array(strtolower($candidate), array_map('strtolower', $used), true)) {
        $ending = ' ' . $suffix++;
        $limit = 31 - strlen($ending);
        $candidate = (function_exists('mb_substr') ? mb_substr($base, 0, $limit) : substr($base, 0, $limit)) . $ending;
    }

    return $candidate;
}

function catalog_data_export_write_xlsx(array $payload): string
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('A extensão PHP zip é necessária para gerar planilhas XLSX.');
    }

    $tables = catalog_data_export_tables($payload);

    if ($tables === []) {
        throw new RuntimeException('Não há tabelas para exportar.');
    }

    $path = catalog_data_export_temp_path('xlsx');
    $zip = new ZipArchive();

    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Não foi possível criar a planilha XLSX.');
    }

    $usedNames = [];
    $sheets = [];

    foreach ($tables as $index => $table) {
        if (count($table['rows']) > 1048575) {
            $zip->close();
            @unlink($path);
            throw new RuntimeException('A tabela ' . $table['label'] . ' excede o limite de linhas do XLSX.');
        }

        $sheetName = catalog_data_export_sheet_name($table['label'], $usedNames);
        $usedNames[] = $sheetName;
        $sheets[] = ['name' => $sheetName, 'id' => $index + 1];
        $lastColumn = catalog_data_export_excel_column(max(1, count($table['columns'])));
        $lastRow = max(1, count($table['rows']) + 1);
        $widths = [];

        foreach ($table['columns'] as $columnIndex => $column) {
            $widths[$columnIndex] = min(42, max(10, strlen($column) + 2));
        }

        foreach (array_slice($table['rows'], 0, 200) as $row) {
            foreach ($table['columns'] as $columnIndex => $column) {
                $length = function_exists('mb_strlen')
                    ? mb_strlen(catalog_data_export_value($row[$column] ?? null))
                    : strlen(catalog_data_export_value($row[$column] ?? null));
                $widths[$columnIndex] = min(42, max($widths[$columnIndex] ?? 10, $length + 2));
            }
        }

        $columnsXml = '';
        foreach ($widths as $columnIndex => $width) {
            $position = $columnIndex + 1;
            $columnsXml .= '<col min="' . $position . '" max="' . $position . '" width="' . $width . '" customWidth="1"/>';
        }

        $rowsXml = '<row r="1">';
        foreach ($table['columns'] as $columnIndex => $column) {
            $reference = catalog_data_export_excel_column($columnIndex + 1) . '1';
            $rowsXml .= '<c r="' . $reference . '" t="inlineStr" s="1"><is><t xml:space="preserve">'
                . catalog_data_export_xml($column)
                . '</t></is></c>';
        }
        $rowsXml .= '</row>';

        foreach ($table['rows'] as $rowIndex => $row) {
            $excelRow = $rowIndex + 2;
            $rowsXml .= '<row r="' . $excelRow . '">';

            foreach ($table['columns'] as $columnIndex => $column) {
                $reference = catalog_data_export_excel_column($columnIndex + 1) . $excelRow;
                $value = $row[$column] ?? null;

                if (is_int($value) || is_float($value)) {
                    $rowsXml .= '<c r="' . $reference . '" s="2"><v>' . catalog_data_export_xml($value) . '</v></c>';
                } elseif (is_bool($value)) {
                    $rowsXml .= '<c r="' . $reference . '" t="b" s="2"><v>' . ($value ? '1' : '0') . '</v></c>';
                } else {
                    $rowsXml .= '<c r="' . $reference . '" t="inlineStr" s="2"><is><t xml:space="preserve">'
                        . catalog_data_export_xml($value)
                        . '</t></is></c>';
                }
            }

            $rowsXml .= '</row>';
        }

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<dimension ref="A1:' . $lastColumn . $lastRow . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<cols>' . $columnsXml . '</cols>'
            . '<sheetData>' . $rowsXml . '</sheetData>'
            . '<autoFilter ref="A1:' . $lastColumn . $lastRow . '"/>'
            . '<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
            . '</worksheet>';
        $zip->addFromString('xl/worksheets/sheet' . ($index + 1) . '.xml', $sheetXml);
    }

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    $workbookSheets = '';
    $workbookRelationships = '';

    foreach ($sheets as $sheet) {
        $contentTypes .= '<Override PartName="/xl/worksheets/sheet' . $sheet['id'] . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $workbookSheets .= '<sheet name="' . catalog_data_export_xml($sheet['name']) . '" sheetId="' . $sheet['id'] . '" r:id="rId' . $sheet['id'] . '"/>';
        $workbookRelationships .= '<Relationship Id="rId' . $sheet['id'] . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $sheet['id'] . '.xml"/>';
    }

    $contentTypes .= '</Types>';
    $styleRelationshipId = count($sheets) + 1;
    $workbookRelationships .= '<Relationship Id="rId' . $styleRelationshipId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $workbookSheets . '</sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $workbookRelationships . '</Relationships>');
    $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="10"/><name val="Arial"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="10"/><name val="Arial"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF245A3F"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFD9DEE3"/></left><right style="thin"><color rgb="FFD9DEE3"/></right><top style="thin"><color rgb="FFD9DEE3"/></top><bottom style="thin"><color rgb="FFD9DEE3"/></bottom><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
    $zip->close();

    return $path;
}

function catalog_data_export_pdf_html(array $payload): string
{
    $tables = catalog_data_export_tables($payload);
    $scopes = function_exists('catalog_json_scopes') ? catalog_json_scopes() : [];
    $scope = (string) ($payload['scope'] ?? 'all');
    $scopeLabel = $scopes[$scope] ?? $scope;
    $exportedAt = (string) ($payload['exported_at'] ?? date(DATE_ATOM));
    $formattedAt = $exportedAt;

    try {
        $formattedAt = (new DateTimeImmutable($exportedAt))->format('d/m/Y H:i:s');
    } catch (Throwable) {
    }

    ob_start();
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Exportação de dados - <?= e($scopeLabel) ?></title>
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #1f2933; font: 9pt Arial, sans-serif; }
        .toolbar { position: sticky; top: 0; display: flex; justify-content: flex-end; padding: 10px; background: #fff; border-bottom: 1px solid #d9dee3; }
        .toolbar button { border: 0; border-radius: 4px; padding: 9px 14px; color: #fff; background: #245a3f; font-weight: 700; cursor: pointer; }
        .report-header { display: grid; grid-template-columns: 90px 1fr 90px; align-items: center; gap: 16px; padding-bottom: 12px; border-bottom: 3px solid #245a3f; }
        .report-logo { max-width: 72px; max-height: 72px; justify-self: center; }
        h1 { margin: 0 0 4px; font-size: 18pt; text-align: center; }
        .meta { color: #52616b; text-align: center; }
        .summary { display: flex; gap: 18px; margin: 12px 0 18px; }
        .summary span { padding-right: 18px; border-right: 1px solid #d9dee3; }
        .table-section { break-before: page; page-break-before: always; }
        .table-section:first-of-type { break-before: auto; page-break-before: auto; }
        h2 { margin: 0 0 8px; font-size: 13pt; }
        .row-count { color: #52616b; font-size: 8pt; }
        .table-wrap { width: 100%; overflow: visible; }
        table { width: 100%; border-collapse: collapse; table-layout: auto; font-size: 7pt; }
        thead { display: table-header-group; }
        tr { break-inside: avoid; page-break-inside: avoid; }
        th, td { max-width: 75mm; padding: 4px 5px; border: 1px solid #b8c2cc; vertical-align: top; overflow-wrap: anywhere; white-space: pre-wrap; }
        th { color: #fff; background: #245a3f !important; font-weight: 700; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        tbody tr:nth-child(even) td { background: #f5f7f8 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .empty { padding: 16px; border: 1px solid #d9dee3; color: #52616b; text-align: center; }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>
    <div class="toolbar"><button type="button" onclick="window.print()">Imprimir / Salvar PDF</button></div>
    <header class="report-header">
        <div><?= render_municipal_logo('report-logo') ?></div>
        <div>
            <h1>Exportação de dados do sistema</h1>
            <div class="meta"><?= e($scopeLabel) ?> | Gerado em <?= e($formattedAt) ?></div>
        </div>
        <div></div>
    </header>
    <div class="summary">
        <span><strong><?= count($tables) ?></strong> tabela(s)</span>
        <span><strong><?= array_sum(array_map(static fn (array $table): int => count($table['rows']), $tables)) ?></strong> registro(s)</span>
        <span>Formato de origem: JSON estruturado v<?= e((string) ($payload['format_version'] ?? 2)) ?></span>
    </div>

    <?php foreach ($tables as $table): ?>
        <section class="table-section">
            <h2><?= e($table['label']) ?> <span class="row-count">(<?= count($table['rows']) ?> registro(s))</span></h2>
            <?php if ($table['rows'] === []): ?>
                <div class="empty">Nenhum registro nesta tabela.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><?php foreach ($table['columns'] as $column): ?><th><?= e($column) ?></th><?php endforeach; ?></tr></thead>
                        <tbody>
                            <?php foreach ($table['rows'] as $row): ?>
                                <tr><?php foreach ($table['columns'] as $column): ?><td><?= e(catalog_data_export_value($row[$column] ?? null)) ?></td><?php endforeach; ?></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</body>
</html>
    <?php

    return (string) ob_get_clean();
}
