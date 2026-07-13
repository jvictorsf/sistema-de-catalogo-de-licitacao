<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/repository.php';

function project_bi_test_assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function project_bi_test_assert_close(float $expected, ?float $actual, string $message): void
{
    if ($actual === null || abs($expected - $actual) > 0.0001) {
        throw new RuntimeException($message . ' Esperado: ' . $expected . ' Obtido: ' . var_export($actual, true));
    }
}

$stats = project_bi_price_statistics([100, 110, 120, 500]);
project_bi_test_assert_close(207.5, $stats['average'], 'Media do BI deve considerar todas as fontes.');
project_bi_test_assert_close(115.0, $stats['median'], 'Mediana do BI incorreta.');
project_bi_test_assert_true(project_bi_is_outlier(500, $stats), 'Valor muito discrepante deve ser marcado como outlier.');
project_bi_test_assert_true(!project_bi_is_outlier(110, $stats), 'Valor central nao deve ser marcado como outlier.');

$stable = project_bi_price_statistics([100, 101, 102]);
project_bi_test_assert_true(!project_bi_is_outlier(102, $stable), 'Valores proximos nao devem gerar outlier.');
project_bi_test_assert_true(($stable['coefficient_variation'] ?? 1) < 0.25, 'Coeficiente de variacao baixo deve indicar estabilidade.');

$historyRow = static function (array $overrides): array {
    return array_merge([
        'quote_item_id' => 1,
        'price_date' => '2025-01-10',
        'unit_price' => 100.0,
        'item_id' => 1,
        'tracking_code' => 'ITEM-001',
        'item_name' => 'Notebook',
        'supplier_id' => 1,
        'supplier_name' => 'Fornecedor A',
        'supplier_document' => '00000000000100',
        'category_id' => 10,
        'category_name' => 'Inform?tica',
        'secretariat_id' => 20,
        'secretariat_name' => 'Secretaria de Administra??o',
        'project_id' => 30,
        'project_name' => 'Projeto 2025',
        'quote_number' => 'ORC-001',
        'observation_key' => 'observation-a',
    ], $overrides);
};

$historyRows = [
    $historyRow([]),
    $historyRow([
        'quote_item_id' => 2,
        'secretariat_id' => 21,
        'secretariat_name' => 'Secretaria de Sa?de',
    ]),
    $historyRow([
        'quote_item_id' => 3,
        'price_date' => '2025-02-10',
        'unit_price' => 110.0,
        'supplier_id' => 2,
        'supplier_name' => 'Fornecedor B',
        'observation_key' => 'observation-b',
    ]),
    $historyRow([
        'quote_item_id' => 4,
        'price_date' => '2025-03-10',
        'unit_price' => 120.0,
        'supplier_id' => 3,
        'supplier_name' => 'Fornecedor C',
        'observation_key' => 'observation-c',
    ]),
    $historyRow([
        'quote_item_id' => 5,
        'price_date' => '2025-04-10',
        'unit_price' => 500.0,
        'supplier_id' => 4,
        'supplier_name' => 'Fornecedor D',
        'observation_key' => 'observation-d',
    ]),
    $historyRow([
        'quote_item_id' => 6,
        'price_date' => '2026-01-10',
        'unit_price' => 130.0,
        'observation_key' => 'observation-e',
        'project_id' => 31,
        'project_name' => 'Projeto 2026',
    ]),
    $historyRow([
        'quote_item_id' => 7,
        'price_date' => '2026-01-15',
        'unit_price' => 50.0,
        'item_id' => 2,
        'tracking_code' => 'ITEM-002',
        'item_name' => 'Mouse',
        'observation_key' => 'observation-f',
        'project_id' => 31,
        'project_name' => 'Projeto 2026',
    ]),
];

$comparison = project_bi_build_price_comparison($historyRows, [
    'year_from' => 2025,
    'year_to' => 2026,
    'dimension' => 'item',
]);

project_bi_test_assert_true(
    (int) $comparison['summary']['count'] === 6,
    'Comparativo deve eliminar copias da mesma observacao ao agrupar por item.'
);
project_bi_test_assert_true(
    (int) $comparison['summary']['outlier_count'] === 1,
    'Comparativo deve identificar o valor discrepante dentro do mesmo item e ano.'
);
project_bi_test_assert_true(
    count($comparison['groups']) === 3,
    'Comparativo deve gerar uma linha para cada combinacao de item e ano.'
);

$march = null;

foreach ($comparison['monthly'] as $month) {
    if ($month['month_key'] === '2025-03') {
        $march = $month;
        break;
    }
}

project_bi_test_assert_true($march !== null, 'Serie mensal deve conter marco de 2025.');
project_bi_test_assert_close(
    110.0,
    $march['moving_average'],
    'Media movel de tres meses deve considerar janeiro, fevereiro e marco.'
);

$secretariatComparison = project_bi_build_price_comparison($historyRows, [
    'year_from' => 2025,
    'year_to' => 2026,
    'dimension' => 'secretariat',
]);

project_bi_test_assert_true(
    (int) $secretariatComparison['summary']['count'] === 7,
    'Mesmo orcamento deve ser considerado uma vez em cada secretaria atendida.'
);
project_bi_test_assert_true(
    (int) $secretariatComparison['summary']['outlier_count'] === 1,
    'Duplicacao administrativa entre secretarias nao deve alterar a faixa estatistica do item.'
);



$normalizedFilters = project_bi_normalize_price_comparison_filters([
    'year_from' => 2026,
    'year_to' => 2024,
    'dimension' => 'invalid',
]);

project_bi_test_assert_true(
    $normalizedFilters['year_from'] === 2024
        && $normalizedFilters['year_to'] === 2026
        && $normalizedFilters['dimension'] === 'item',
    'Filtros devem normalizar periodo invertido e dimensao invalida.'
);


echo "ProjectBiTest: OK\n";