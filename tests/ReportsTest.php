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

echo "ReportsTest: OK\n";