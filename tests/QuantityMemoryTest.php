<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';

function quantity_memory_assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function quantity_memory_assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Esperado: ' . var_export($expected, true) . ' Obtido: ' . var_export($actual, true));
    }
}

function quantity_memory_assert_close(float $expected, mixed $actual, string $message): void
{
    if (!is_numeric($actual) || abs($expected - (float) $actual) > 0.0001) {
        throw new RuntimeException($message . ' Esperado: ' . $expected . ' Obtido: ' . var_export($actual, true));
    }
}

$exampleInput = [
    'project_id' => 1,
    'procurement_item_id' => 3,
    'calculation_method' => 'HYBRID',
    'planning_period_months' => 12,
    'include_approved_demands' => true,
    'calculation_data' => [
        'historical_projection' => [
            'quantity' => 3,
            'description' => 'Previsao baseada nas substituicoes realizadas nos ultimos 12 meses.',
            'source_reference' => 'Relatorio de chamados do DTI n. 04/2026',
        ],
        'asset_replacement' => [
            'obsolete' => 0,
            'irreparable' => 0,
            'incompatible' => 0,
            'new_positions' => 2,
            'description' => 'Criacao de dois novos postos administrativos.',
        ],
        'planned_projects' => [
            'quantity' => 0,
            'description' => null,
            'source_reference' => null,
        ],
        'technical_project' => [
            'quantity' => 0,
            'description' => null,
            'source_reference' => null,
        ],
        'installed_base' => [
            'quantity' => 0,
            'annual_failure_rate_percent' => 0,
            'projected_quantity' => 0,
            'description' => null,
        ],
        'technical_reserve' => [
            'type' => 'FIXED',
            'value' => 2,
            'calculated_quantity' => 2,
            'justification' => 'Atendimento emergencial durante acionamentos de garantia.',
        ],
        'technical_loss' => [
            'type' => 'NONE',
            'value' => 0,
            'calculated_quantity' => 0,
            'justification' => null,
        ],
        'other_additions' => [
            'quantity' => 0,
            'justification' => null,
        ],
        'deductions' => [
            'stock_available' => 3,
            'framework_agreement_balance' => 1,
            'contract_balance' => 0,
            'reusable_quantity' => 2,
            'purchases_in_progress' => 0,
            'other_quantity' => 0,
            'other_justification' => null,
        ],
    ],
    'supporting_references' => [
        [
            'type' => 'DEMAND_REPORT',
            'description' => 'Relatorio consolidado das demandas',
            'reference' => 'Projeto 003A/2026',
        ],
        [
            'type' => 'INVENTORY_REPORT',
            'description' => 'Inventario dos equipamentos disponiveis',
            'reference' => 'Relatorio de inventario n. 04/2026',
        ],
    ],
    'rounding_rule' => 'NONE',
    'requested_quantity_snapshot' => 44,
    'approved_quantity_snapshot' => 42,
    'additions_total' => 7,
    'deductions_total' => 6,
    'calculated_quantity' => 43,
    'final_quantity' => 43,
    'manual_adjustment_justification' => null,
    'status' => 'VALIDATED',
    'needs_review' => false,
];

$result = calculate_project_item_quantity_memory($exampleInput, 42, 44);

quantity_memory_assert_same(42, $result['approved_quantity_snapshot'] ?? null, 'A memoria deve preservar a quantidade aprovada.');
quantity_memory_assert_same(44, $result['requested_quantity_snapshot'] ?? null, 'A memoria deve preservar a quantidade solicitada.');
quantity_memory_assert_close(3.0, $result['calculation_data']['historical_projection']['quantity'] ?? null, 'A projecao historica deve ser preservada.');
quantity_memory_assert_close(2.0, $result['calculation_data']['asset_replacement']['new_positions'] ?? null, 'Os novos postos devem ser preservados.');
quantity_memory_assert_close(2.0, $result['calculation_data']['technical_reserve']['calculated_quantity'] ?? null, 'A reserva tecnica deve ser preservada.');
quantity_memory_assert_close(3.0, $result['calculation_data']['deductions']['stock_available'] ?? null, 'O estoque disponivel deve ser preservado.');
quantity_memory_assert_close(1.0, $result['calculation_data']['deductions']['framework_agreement_balance'] ?? null, 'O saldo de ata deve ser preservado.');
quantity_memory_assert_close(2.0, $result['calculation_data']['deductions']['reusable_quantity'] ?? null, 'Os bens reaproveitaveis devem ser preservados.');
quantity_memory_assert_same(7, $result['additions_total'] ?? null, 'O total de acrescimos deve ser calculado corretamente.');
quantity_memory_assert_same(6, $result['deductions_total'] ?? null, 'O total de deducoes deve ser calculado corretamente.');
quantity_memory_assert_same(43, $result['calculated_quantity'] ?? null, 'A quantidade calculada deve bater com o exemplo.');
quantity_memory_assert_same(43, $result['final_quantity'] ?? null, 'A quantidade final deve bater com o exemplo.');
quantity_memory_assert_same('VALIDATED', $result['status'] ?? null, 'A memoria validada deve permanecer validada.');
quantity_memory_assert_true(($result['needs_review'] ?? true) === false, 'O exemplo nao deve exigir revisao.');
quantity_memory_assert_true(count($result['supporting_references'] ?? []) === 2, 'As referencias de apoio devem ser preservadas.');
quantity_memory_assert_true(str_contains(project_item_quantity_memory_formula($result), '42 + 3 + 2 + 2 - 3 - 1 - 2 = 43'), 'A formula textual deve refletir a conta final.');
quantity_memory_assert_true(str_contains(project_item_quantity_memory_text($result), 'Demandas aprovadas: 42'), 'O texto da memoria deve explicar a base aprovada.');
quantity_memory_assert_true(str_contains(project_item_quantity_memory_text($result), 'Quantidade final estimada: 43 unidades'), 'O texto da memoria deve mostrar a quantidade final.');
quantity_memory_assert_same(43, project_item_effective_quantity($result), 'A quantidade efetiva deve usar o valor final da memoria.');
quantity_memory_assert_same('HYBRID', $result['calculation_method'] ?? null, 'A memoria deve preservar o metodo de calculo.');
quantity_memory_assert_same(12, $result['planning_period_months'] ?? null, 'A memoria deve preservar o periodo de planejamento.');
quantity_memory_assert_same(true, $result['include_approved_demands'] ?? null, 'A memoria deve preservar o indicador de demandas aprovadas.');
quantity_memory_assert_same('NONE', $result['calculation_data']['technical_loss']['type'] ?? null, 'Perdas tecnicas nao devem aparecer no exemplo.');
quantity_memory_assert_same(0, $result['calculation_data']['technical_loss']['calculated_quantity'] ?? null, 'Perdas tecnicas devem permanecer zeradas no exemplo.');
quantity_memory_assert_same(0, $result['calculation_data']['other_additions']['quantity'] ?? null, 'Outros acrescimos devem permanecer zerados no exemplo.');
quantity_memory_assert_same('Atendimento emergencial durante acionamentos de garantia.', $result['calculation_data']['technical_reserve']['justification'] ?? null, 'A justificativa da reserva tecnica deve ser preservada.');
quantity_memory_assert_same('Projeto 003A/2026', $result['supporting_references'][0]['reference'] ?? null, 'A primeira referencia de apoio deve ser preservada.');
quantity_memory_assert_same('INVENTORY_REPORT', $result['supporting_references'][1]['type'] ?? null, 'A segunda referencia de apoio deve ser preservada.');

echo "QuantityMemoryTest: OK\n";
