<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';

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
];
$specificationHtml = licitation_annex_specification_html($specificationItem);
$specificationText = licitation_annex_specification_text($specificationItem);

foreach ([
    "Descri\u{00E7}\u{00E3}o m\u{00ED}nima",
    "Caracter\u{00ED}sticas m\u{00ED}nimas",
    "Crit\u{00E9}rios de aceita\u{00E7}\u{00E3}o",
    "Certificados (m\u{00ED}nimos)",
    "Observa\u{00E7}\u{00F5}es",
    'Garantia',
] as $sectionLabel) {
    reports_test_assert_contains(
        '<strong class="annex-spec-title">' . $sectionLabel . '</strong>',
        $specificationHtml,
        'Anexo deve identificar cada topico da especificacao em negrito.'
    );
}

reports_test_assert_contains('<ul>', $specificationHtml, 'Anexo deve renderizar campos multivalorados como lista.');
reports_test_assert_contains('<li>Caracteristica A</li>', $specificationHtml, 'Anexo deve listar caracteristicas minimas.');
reports_test_assert_contains("Pre\u{00E7}os conforme proposta.", $specificationHtml, 'Anexo deve reparar mojibake legado.');
reports_test_assert_not_contains("\u{00C3}", $specificationHtml, 'Anexo nao deve manter marcador de mojibake.');
reports_test_assert_contains('12 meses', $specificationHtml, 'Anexo deve exibir garantia cadastrada.');
reports_test_assert_contains('Garantia:', $specificationText, 'Payload textual deve incluir garantia.');

$legacyObservationHtml = licitation_annex_specification_html([
    'specification' => ['observacoes' => [standard_product_item_observations()[0]]],
]);
reports_test_assert_contains("ser\u{00E1} meramente ilustrativa", $legacyObservationHtml, 'Anexo deve recuperar observacao padrao antiga.');
reports_test_assert_not_contains("\u{00C3}", implode(' ', standard_product_item_observations()), 'Novos itens devem usar observacoes padrao em UTF-8.');
echo "ReportsTest: OK\n";