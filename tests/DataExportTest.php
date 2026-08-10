<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/repository.php';
require_once __DIR__ . '/../app/data_exports.php';
require_once __DIR__ . '/../app/system_tools.php';

function data_export_assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function data_export_assert_contains(string $needle, string $haystack, string $message): void
{
    data_export_assert_true(str_contains($haystack, $needle), $message . ' Ausente: ' . $needle);
}

$payload = [
    'system' => 'Sistema de teste',
    'scope' => 'all',
    'exported_at' => '2026-08-10T10:30:00-03:00',
    'format_version' => 2,
    'data' => [
        'categories' => [
            [
                'id' => 1,
                'parent_id' => null,
                'name' => 'Informática; manutenção',
                'created_at' => '2026-08-10 10:00:00',
                'updated_at' => '2026-08-10 10:00:00',
            ],
        ],
        'suppliers' => [
            [
                'id' => 2,
                'name' => '=HYPERLINK("https://example.test")',
                'document' => '004033848000178',
                'secondary_cnaes' => [['code' => '6201-5/01', 'name' => 'Desenvolvimento']],
            ],
        ],
    ],
];

$tables = catalog_data_export_tables($payload);
data_export_assert_true(count($tables) === 2, 'Escopo composto deve preservar as duas tabelas.');
data_export_assert_true($tables[0]['label'] === 'Categorias', 'Tabela deve usar o rótulo administrativo.');

$csv = catalog_data_export_csv_contents($tables[1]);
data_export_assert_true(str_starts_with($csv, "\xEF\xBB\xBF"), 'CSV deve conter BOM UTF-8.');
data_export_assert_contains("'=HYPERLINK", $csv, 'CSV deve neutralizar fórmulas em texto.');
data_export_assert_contains('004033848000178', $csv, 'CSV deve preservar identificadores com zero à esquerda.');
data_export_assert_contains('6201-5/01', $csv, 'CSV deve serializar campos JSON de forma legível.');

$csvBundle = catalog_data_export_csv_bundle($payload);
data_export_assert_true($csvBundle['extension'] === 'csv.zip', 'Escopo composto deve gerar pacote ZIP de CSVs.');
$csvZip = new ZipArchive();
data_export_assert_true($csvZip->open($csvBundle['path']) === true, 'Pacote CSV deve ser um ZIP válido.');
data_export_assert_true($csvZip->locateName('01-categories.csv') !== false, 'ZIP deve conter o CSV da primeira tabela.');
data_export_assert_true($csvZip->locateName('02-suppliers.csv') !== false, 'ZIP deve conter o CSV da segunda tabela.');
data_export_assert_true($csvZip->locateName('_manifesto.json') !== false, 'ZIP deve conter manifesto dos arquivos.');
$csvZip->close();
@unlink($csvBundle['path']);

$singleTablePayload = $payload;
$singleTablePayload['scope'] = 'categories';
$singleTablePayload['data'] = ['categories' => $payload['data']['categories']];
$singleCsvBundle = catalog_data_export_csv_bundle($singleTablePayload);
data_export_assert_true($singleCsvBundle['extension'] === 'csv', 'Escopo de uma tabela deve gerar CSV direto.');
data_export_assert_true(str_starts_with((string) file_get_contents($singleCsvBundle['path']), "\xEF\xBB\xBF"), 'CSV direto deve permanecer em UTF-8.');
@unlink($singleCsvBundle['path']);

$xlsxPath = catalog_data_export_write_xlsx($payload);
$xlsx = new ZipArchive();
data_export_assert_true($xlsx->open($xlsxPath) === true, 'XLSX deve ser um pacote Open XML válido.');
data_export_assert_true($xlsx->locateName('xl/workbook.xml') !== false, 'XLSX deve conter o workbook.');
data_export_assert_true($xlsx->locateName('xl/worksheets/sheet1.xml') !== false, 'XLSX deve criar uma aba por tabela.');
data_export_assert_true($xlsx->locateName('xl/worksheets/sheet2.xml') !== false, 'XLSX deve criar a segunda aba.');
$workbookXml = (string) $xlsx->getFromName('xl/workbook.xml');
$supplierSheetXml = (string) $xlsx->getFromName('xl/worksheets/sheet2.xml');
data_export_assert_contains('Categorias', $workbookXml, 'Workbook deve nomear a aba de categorias.');
data_export_assert_contains('Fornecedores', $workbookXml, 'Workbook deve nomear a aba de fornecedores.');
data_export_assert_contains('t="inlineStr"', $supplierSheetXml, 'Textos devem ser gravados como inline string.');
data_export_assert_contains('=HYPERLINK', $supplierSheetXml, 'XLSX deve preservar texto semelhante a fórmula sem executá-lo.');
data_export_assert_true(!str_contains($supplierSheetXml, '<f>'), 'XLSX não deve criar fórmulas a partir dos dados.');

for ($entryIndex = 0; $entryIndex < $xlsx->numFiles; $entryIndex++) {
    $entryName = (string) $xlsx->getNameIndex($entryIndex);

    if (!str_ends_with($entryName, '.xml') && !str_ends_with($entryName, '.rels')) {
        continue;
    }

    $document = new DOMDocument();
    data_export_assert_true(
        @$document->loadXML((string) $xlsx->getFromIndex($entryIndex)),
        'Cada componente XML do XLSX deve ser válido: ' . $entryName
    );
}

$xlsx->close();
@unlink($xlsxPath);

$pdfHtml = catalog_data_export_pdf_html($payload);
data_export_assert_contains('Exportação de dados do sistema', $pdfHtml, 'Relatório PDF deve apresentar título administrativo.');
data_export_assert_contains('Imprimir / Salvar PDF', $pdfHtml, 'Relatório PDF deve oferecer comando de impressão.');
data_export_assert_contains('Informática; manutenção', $pdfHtml, 'Relatório PDF deve preservar caracteres UTF-8.');
data_export_assert_contains('@page { size: A4 landscape;', $pdfHtml, 'Relatório PDF deve configurar página A4 paisagem.');

$dataPageSource = (string) file_get_contents(__DIR__ . '/../public/data.php');
data_export_assert_true(
    array_keys(catalog_data_export_formats()) === ['json', 'pdf', 'csv', 'xlsx'],
    'Exportador deve registrar JSON, PDF, CSV e XLSX na ordem esperada.'
);
data_export_assert_contains('action="/export_data.php"', $dataPageSource, 'Tela de dados deve usar a nova rota de exportação.');
data_export_assert_contains('name="format"', $dataPageSource, 'Tela de dados deve enviar o formato selecionado.');
data_export_assert_true(in_array('zip', app_required_php_extensions(), true), 'Diagnóstico deve validar a extensão zip.');

echo "DataExportTest: OK\n";
