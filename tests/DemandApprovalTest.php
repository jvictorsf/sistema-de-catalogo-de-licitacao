<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/repository.php';

function demand_approval_test_assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Esperado: ' . var_export($expected, true) . ' Obtido: ' . var_export($actual, true));
    }
}

function demand_approval_test_assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function demand_approval_test_throws(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException) {
        return;
    }

    throw new RuntimeException($message);
}

$items = [[
    'id' => 10,
    'procurement_item_id' => 20,
    'item_name' => 'Notebook',
    'quantity' => 4,
]];

$approved = prepare_demand_approval_decision([
    'approval_status' => 'APPROVED',
    'approved_quantities' => [10 => '4'],
], $items);
demand_approval_test_assert_same('APPROVED', $approved['approval_status'], 'Demanda deve aceitar aprovacao integral.');
demand_approval_test_assert_same('APPROVED', $approved['items'][0]['validation_status'], 'Item sem ajuste deve ser aprovado.');
demand_approval_test_assert_same(4.0, $approved['items'][0]['approved_quantity'], 'Quantidade integral deve ser preservada.');

$reserved = prepare_demand_approval_decision([
    'approval_status' => 'APPROVED_WITH_RESERVATIONS',
    'approval_notes' => 'Quantidade reduzida apos analise tecnica.',
    'approved_quantities' => [10 => '3,5'],
], $items);
demand_approval_test_assert_same('APPROVED_WITH_ADJUSTMENT', $reserved['items'][0]['validation_status'], 'Ajuste quantitativo deve ficar registrado no item.');
demand_approval_test_assert_same(3.5, $reserved['items'][0]['approved_quantity'], 'Quantidade aprovada com virgula deve ser normalizada.');
demand_approval_test_assert_true($reserved['has_quantitative_reservation'], 'Decisao deve sinalizar ressalva quantitativa.');

$rejected = prepare_demand_approval_decision([
    'approval_status' => 'REJECTED',
    'approval_notes' => 'Necessidade nao comprovada.',
], $items);
demand_approval_test_assert_same(0.0, $rejected['items'][0]['approved_quantity'], 'Negativa deve zerar o quantitativo aprovado.');
demand_approval_test_assert_same('REJECTED', $rejected['items'][0]['validation_status'], 'Negativa deve rejeitar cada item.');

demand_approval_test_throws(
    static fn (): array => prepare_demand_approval_decision([
        'approval_status' => 'APPROVED',
        'approved_quantities' => [10 => 3],
    ], $items),
    'Aprovacao integral nao deve permitir quantidade diferente.'
);
demand_approval_test_throws(
    static fn (): array => prepare_demand_approval_decision([
        'approval_status' => 'APPROVED_WITH_RESERVATIONS',
        'approved_quantities' => [10 => 3],
    ], $items),
    'Ressalva deve exigir justificativa.'
);
demand_approval_test_throws(
    static fn (): array => prepare_demand_approval_decision([
        'approval_status' => 'APPROVED',
        'approved_quantities' => [10 => 'quantidade invalida'],
    ], $items),
    'Quantidade nao numerica deve ser recusada.'
);

$annexStatuses = build_project_annex_statuses_from_latest([
    [
        'annex_type' => 'annex_i',
        'version_number' => 2,
        'content_hash' => str_repeat('a', 64),
        'status' => 'valid',
        'item_count' => 4,
        'total_value' => null,
        'generated_at' => '2026-07-21 10:00:00',
    ],
    [
        'annex_type' => 'annex_ii',
        'version_number' => 1,
        'content_hash' => str_repeat('b', 64),
        'status' => 'invalid',
        'item_count' => 4,
        'total_value' => 100,
        'generated_at' => '2026-07-21 09:00:00',
    ],
]);
demand_approval_test_assert_same('valid', $annexStatuses['annex_i']['status'], 'Anexo armazenado como valido deve permanecer valido.');
demand_approval_test_assert_same(null, $annexStatuses['annex_i']['total_value'], 'Anexo sem valor nao deve gerar aviso por acesso nulo.');
demand_approval_test_assert_same('stale', $annexStatuses['annex_ii']['status'], 'Anexo invalidado deve ficar desatualizado.');
demand_approval_test_assert_same('pending', $annexStatuses['annex_iii']['status'], 'Anexo nunca gerado deve ficar pendente.');

$repositorySource = file_get_contents(__DIR__ . '/../app/repository.php') ?: '';
$projectShowSource = file_get_contents(__DIR__ . '/../public/project_show.php') ?: '';
$schemaSource = file_get_contents(__DIR__ . '/../database/schema.sql') ?: '';

preg_match('/function get_project_annex_statuses\(.*?(?=\nfunction get_demand_supplier_quotes)/s', $repositorySource, $annexFunction);
$annexFunctionSource = $annexFunction[0] ?? '';
demand_approval_test_assert_true(str_contains($annexFunctionSource, 'DISTINCT ON (annex_type)'), 'Status dos anexos deve usar uma unica consulta das ultimas versoes.');
demand_approval_test_assert_true(!str_contains($annexFunctionSource, 'project_annex_payload('), 'Abertura do projeto nao deve recalcular payload e hash de todos os anexos.');

preg_match('/function get_project_demand_budget_items\(.*?(?=\nfunction get_project_consolidated_items)/s', $repositorySource, $budgetFunction);
$budgetFunctionSource = $budgetFunction[0] ?? '';
demand_approval_test_assert_true(str_contains($budgetFunctionSource, 'WITH project_items AS'), 'Orcamentos do projeto devem ser agregados em consulta de conjunto.');
demand_approval_test_assert_true(!str_contains($budgetFunctionSource, 'get_demand_budget_report('), 'Resumo do projeto nao deve consultar cada demanda individualmente.');
demand_approval_test_assert_true(str_contains($projectShowSource, 'get_project_financial_summary($id, $consolidatedItems)'), 'Project show deve reutilizar o consolidado ja carregado.');
demand_approval_test_assert_true(str_contains($schemaSource, 'CREATE TABLE IF NOT EXISTS demand_approval_events'), 'Schema deve manter historico imutavel das decisoes.');
demand_approval_test_assert_true(is_file(__DIR__ . '/../public/demand_approval.php'), 'Tela administrativa de aprovacao deve existir.');
$approvalSaveSource = file_get_contents(__DIR__ . '/../public/demand_approval_save.php') ?: '';
demand_approval_test_assert_true(str_contains($approvalSaveSource, "../app/config.php"), 'Gravacao da decisao deve passar pelo bootstrap de autenticacao.');

echo "DemandApprovalTest: OK\n";
