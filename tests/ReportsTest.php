<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

function reports_test_assert_close(float $expected, ?float $actual, string $message): void
{
    if ($actual === null || abs($expected - $actual) > 0.0001) {
        throw new RuntimeException($message . ' Esperado: ' . $expected . ' Obtido: ' . var_export($actual, true));
    }
}

function reports_test_assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Esperado: ' . var_export($expected, true) . ' Obtido: ' . var_export($actual, true));
    }
}

function reports_test_assert_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($message . ' Trecho ausente: ' . $needle);
    }
}

function reports_test_assert_not_contains(string $needle, string $haystack, string $message): void
{
    if (str_contains($haystack, $needle)) {
        throw new RuntimeException($message . ' Trecho inesperado: ' . $needle);
    }
}

$groups = build_licitation_annex_ii_groups_from_rows([
    [
        'procurement_item_id' => 10,
        'licitation_number' => 1,
        'item_name' => 'Servico tecnico',
        'annex_quantity' => 2,
        'suppliers' => [
            ['key' => 'supplier:1', 'name' => 'Fornecedor A', 'unit_price' => 100.00, 'proposal_date' => '2026-07-01'],
            ['key' => 'supplier:2', 'name' => 'Fornecedor B', 'unit_price' => 120.00, 'proposal_date' => '2026-07-02'],
        ],
    ],
]);

$item = $groups['groups'][0]['items'][0];
reports_test_assert_close(110.00, $item['estimated_unit_price'], 'Relatorio/anexo deve calcular media unitario estimada.');
reports_test_assert_close(220.00, $item['estimated_total'], 'Relatorio/anexo deve calcular total estimado.');
reports_test_assert_same(['2026-07-01'], $groups['groups'][0]['suppliers'][0]['proposal_dates'], 'Data da proposta deve ser preservada para relatorios.');

$specificationItem = [
    'specification' => [
        'descricao_minima' => "Descri\u{00E7}\u{00E3}o completa do item.",
        'caracteristicas_minimas' => ['Caracteristica A', 'Caracteristica B'],
        'criterios_aceitacao' => ['Criterio A'],
        'certificados' => ['Certificado A'],
        'observacoes' => ["Pre\u{00C3}\u{00A7}os conforme proposta."],
    ],
    'warranty' => '12 meses',
    'minimum_validity_text' => "O produto deverá possuir prazo de validade remanescente mínimo de 12 (doze) meses, contado da data da entrega.",
];
$specificationHtml = licitation_annex_specification_html($specificationItem);
$specificationText = licitation_annex_specification_text($specificationItem);

foreach ([
    "Descri\u{00E7}\u{00E3}o m\u{00ED}nima",
    "Caracter\u{00ED}sticas m\u{00ED}nimas",
    "Crit\u{00E9}rios de aceita\u{00E7}\u{00E3}o",
    "Observa\u{00E7}\u{00F5}es",
    'Garantia',
    "Validade m\u{00ED}nima",
] as $sectionLabel) {
    reports_test_assert_contains(
        '<strong class="annex-spec-title">' . $sectionLabel . '</strong>',
        $specificationHtml,
        'Anexo deve identificar cada topico da especificacao em negrito.'
    );
}

reports_test_assert_contains('<ul>', $specificationHtml, 'Anexo deve renderizar campos multivalorados como lista.');
reports_test_assert_contains('<li>Caracteristica A</li>', $specificationHtml, 'Anexo deve listar caracteristicas minimas.');
reports_test_assert_not_contains("Certificados (m\u{00ED}nimos)", $specificationHtml, 'Anexo nao deve exibir o topico de certificados.');
reports_test_assert_not_contains('Certificado A', $specificationHtml, 'Anexo nao deve exibir certificados cadastrados.');
reports_test_assert_not_contains('Certificado A', $specificationText, 'Payload textual do anexo nao deve incluir certificados.');
reports_test_assert_contains("Pre\u{00E7}os conforme proposta.", $specificationHtml, 'Anexo deve reparar mojibake legado.');
reports_test_assert_not_contains("\u{00C3}", $specificationHtml, 'Anexo nao deve manter marcador de mojibake.');
reports_test_assert_contains('12 meses', $specificationHtml, 'Anexo deve exibir garantia cadastrada.');
reports_test_assert_contains('Garantia:', $specificationText, 'Payload textual deve incluir garantia.');
reports_test_assert_contains("Validade m\u{00ED}nima:", $specificationText, 'Payload textual deve incluir validade quando aplicável.');

$legacyObservationHtml = licitation_annex_specification_html([
    'specification' => ['observacoes' => [standard_product_item_observations()[0]]],
]);
reports_test_assert_contains("ser\u{00E1} meramente ilustrativa", $legacyObservationHtml, 'Anexo deve recuperar observacao padrao antiga.');
reports_test_assert_not_contains("\u{00C3}", implode(' ', standard_product_item_observations()), 'Novos itens devem usar observacoes padrao em UTF-8.');
$expectedLotAnnexTitles = [
    'lot_annex_i' => "Anexo I - Planilha de Itens, Especifica\u{00E7}\u{00F5}es, Quantitativos e Mem\u{00F3}ria de C\u{00E1}lculo por lote",
    'lot_annex_ii' => "Anexo II - Planilha de Pesquisa e Estimativa de Pre\u{00E7}os por lote",
    'lot_annex_iii' => 'Anexo III - Quadro de agrupamento dos lotes',
    'lot_annex_iv' => 'Anexo IV - Quadro resumido da estimativa por lote',
];
$annexTypes = project_annex_types();

reports_test_assert_same(
    "Anexo IV - Rela\u{00E7}\u{00E3}o simplificada de itens e quantidades",
    $annexTypes['annex_iv'] ?? null,
    'Nome do anexo simplificado deve seguir o padrao institucional.'
);

$simpleAnnexSource = file_get_contents(__DIR__ . '/../public/project_licitation_annex_iv.php') ?: '';
reports_test_assert_contains("register_project_annex_version(\$id, 'annex_iv')", $simpleAnnexSource, 'Anexo simplificado deve registrar versao e hash.');
reports_test_assert_contains('<th style="width: 10%;">Item</th>', $simpleAnnexSource, 'Anexo simplificado deve exibir o numero do item.');
reports_test_assert_contains('<th style="width: 72%;">Nome do item</th>', $simpleAnnexSource, 'Anexo simplificado deve exibir o nome do item.');
reports_test_assert_contains('<th style="width: 18%;">Quantidade</th>', $simpleAnnexSource, 'Anexo simplificado deve exibir a quantidade.');

$schemaSource = file_get_contents(__DIR__ . '/../database/schema.sql') ?: '';
reports_test_assert_contains("'annex_iv'", $schemaSource, 'Schema deve permitir o versionamento do anexo simplificado.');

$repositorySource = file_get_contents(__DIR__ . '/../app/repository.php') ?: '';
reports_test_assert_contains(
    'function get_project_item_price_estimates',
    $repositorySource,
    'Repositorio deve calcular as estimativas consolidadas do projeto em lote.'
);
reports_test_assert_contains(
    'apply_project_item_price_estimates($items, $itemPriceEstimates)',
    $repositorySource,
    'Consolidado exibido no projeto deve aplicar a mesma regra dos anexos.'
);
reports_test_assert_contains(
    'GROUP BY procurement_item_id, source_key',
    $repositorySource,
    'Consulta consolidada deve retornar somente uma media por item e fonte.'
);

foreach ($expectedLotAnnexTitles as $annexType => $expectedTitle) {
    reports_test_assert_same($expectedTitle, $annexTypes[$annexType] ?? null, 'Nome do anexo por lote deve seguir o padrao institucional.');
    $source = file_get_contents(__DIR__ . '/../public/project_' . $annexType . '.php') ?: '';
    reports_test_assert_contains('<title><?= e($title) ?></title>', $source, 'Nome sugerido ao salvar nao deve incluir o projeto.');
    reports_test_assert_not_contains(' - <?= e($project[' . "'name'" . ']) ?>', $source, 'Titulo HTML nao deve concatenar o nome do projeto.');
}

echo "ReportsTest: OK\n";
