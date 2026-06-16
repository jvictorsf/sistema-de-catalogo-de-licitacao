<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';

function assert_close(float $expected, ?float $actual, string $message): void
{
    if ($actual === null || abs($expected - $actual) > 0.0001) {
        throw new RuntimeException($message . ' Esperado: ' . $expected . ' Obtido: ' . var_export($actual, true));
    }
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function supplier_row(string $key, string $name, float $unitPrice): array
{
    return [
        'key' => $key,
        'name' => $name,
        'unit_price' => $unitPrice,
    ];
}

$weighted = build_licitation_annex_ii_groups_from_rows([
    [
        'procurement_item_id' => 1,
        'item_name' => 'Computador',
        'annex_quantity' => 2,
        'suppliers' => [
            supplier_row('supplier:1', 'Fornecedor A', 10),
            supplier_row('supplier:2', 'Fornecedor B', 14),
        ],
        'demand_memory' => [['demand_name' => 'Demanda 1', 'quantity' => 2]],
    ],
    [
        'procurement_item_id' => 1,
        'item_name' => 'Computador',
        'annex_quantity' => 3,
        'suppliers' => [
            supplier_row('supplier:1', 'Fornecedor A', 20),
            supplier_row('supplier:2', 'Fornecedor B', 22),
        ],
        'demand_memory' => [['demand_name' => 'Demanda 2', 'quantity' => 3]],
    ],
]);

assert_true(count($weighted['groups']) === 1, 'Itens com a mesma combinacao de fornecedores devem ficar no mesmo grupo.');

$weightedItem = $weighted['groups'][0]['items'][0];
assert_close(5.0, (float) $weightedItem['annex_quantity'], 'Quantidade consolidada incorreta.');
assert_close(16.0, $weightedItem['supplier_prices']['supplier:1'], 'Preco ponderado do fornecedor A incorreto.');
assert_close(18.8, $weightedItem['supplier_prices']['supplier:2'], 'Preco ponderado do fornecedor B incorreto.');
assert_close(17.4, $weightedItem['estimated_unit_price'], 'Valor unitario estimado ponderado incorreto.');
assert_close(87.0, $weightedItem['estimated_total'], 'Total estimado ponderado incorreto.');
assert_close(87.0, $weighted['global_total'], 'Valor global estimado ponderado incorreto.');

$split = build_licitation_annex_ii_groups_from_rows([
    [
        'procurement_item_id' => 1,
        'item_name' => 'Notebook',
        'annex_quantity' => 10,
        'suppliers' => [
            supplier_row('supplier:1', 'Fornecedor A', 100),
            supplier_row('supplier:2', 'Fornecedor B', 120),
        ],
    ],
    [
        'procurement_item_id' => 1,
        'item_name' => 'Notebook',
        'annex_quantity' => 1,
        'suppliers' => [
            supplier_row('supplier:1', 'Fornecedor A', 100),
            supplier_row('supplier:3', 'Fornecedor C', 140),
        ],
    ],
]);

assert_true(count($split['groups']) === 2, 'Combinações diferentes de fornecedores devem gerar grupos separados.');
assert_close(1220.0, $split['global_total'], 'Valor global estimado com grupos separados incorreto.');

$withoutQuote = build_licitation_annex_ii_groups_from_rows([
    [
        'procurement_item_id' => 2,
        'item_name' => 'Impressora',
        'annex_quantity' => 4,
        'manual_unit_price' => 75,
        'suppliers' => [],
    ],
]);

assert_true(count($withoutQuote['groups']) === 1, 'Item sem cotacao deve gerar grupo proprio.');
assert_true($withoutQuote['groups'][0]['key'] === 'sem-cotacao', 'Grupo sem cotacao deve ser identificado.');
assert_close(75.0, $withoutQuote['groups'][0]['items'][0]['estimated_unit_price'], 'Item sem cotacao deve usar estimativa manual.');
assert_close(300.0, $withoutQuote['groups'][0]['items'][0]['estimated_total'], 'Item sem cotacao deve compor total estimado manual.');
assert_close(300.0, $withoutQuote['global_total'], 'Item sem cotacao deve compor valor global pela estimativa manual.');

echo "CalculationsTest: OK\n";
