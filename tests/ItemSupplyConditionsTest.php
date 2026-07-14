<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';

function supply_test_assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Esperado: ' . var_export($expected, true) . ' Obtido: ' . var_export($actual, true));
    }
}

function supply_test_assert_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($message . ' Trecho ausente: ' . $needle);
    }
}

function supply_test_assert_throws(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException) {
        return;
    }

    throw new RuntimeException($message);
}

$permanent = prepare_item_supply_conditions([
    'item_classification' => 'permanent',
    'warranty_months' => '',
]);
supply_test_assert_same('PERMANENTE', $permanent['item_nature'], 'Material permanente deve persistir a natureza interna.');
supply_test_assert_same(12, $permanent['warranty_months'], 'Material permanente deve assumir garantia padrão de 12 meses.');
supply_test_assert_same(false, $permanent['minimum_validity_required'], 'Material permanente não deve exigir validade por padrão.');
supply_test_assert_contains('12 (doze) meses', $permanent['warranty'], 'Garantia gerada deve conter número e valor por extenso.');

supply_test_assert_throws(
    static fn (): array => prepare_item_supply_conditions([
        'item_classification' => 'permanent',
        'warranty_months' => '11',
    ]),
    'Material permanente não pode aceitar garantia inferior a 12 meses.'
);
supply_test_assert_throws(
    static fn (): array => prepare_item_supply_conditions([
        'item_classification' => 'permanent',
        'warranty_months' => '12,5',
    ]),
    'Garantia decimal não pode ser substituída silenciosamente pelo padrão.'
);
supply_test_assert_throws(
    static fn (): array => prepare_item_supply_conditions([
        'item_classification' => 'permanent',
        'warranty_months' => '12 a 24',
    ]),
    'Faixa textual de garantia não deve ser aceita.'
);

$nonperishable = prepare_item_supply_conditions([
    'item_classification' => 'consumption_nonperishable',
    'warranty_months' => '',
]);
supply_test_assert_same('CONSUMO', $nonperishable['item_nature'], 'Material de consumo deve persistir natureza de consumo.');
supply_test_assert_same(false, $nonperishable['is_perishable'], 'Consumo não perecível deve persistir false.');
supply_test_assert_same(3, $nonperishable['warranty_months'], 'Consumo deve assumir garantia padrão de 3 meses.');
supply_test_assert_same('', $nonperishable['minimum_validity_text'], 'Validade opcional desativada não deve gerar cláusula.');

$optionalValidity = prepare_item_supply_conditions([
    'item_classification' => 'consumption_nonperishable',
    'warranty_months' => '6',
    'minimum_validity_required' => true,
    'minimum_validity_months' => '12',
]);
supply_test_assert_same(12, $optionalValidity['minimum_validity_months'], 'Consumo não perecível deve aceitar validade opcional.');
supply_test_assert_contains('12 (doze) meses', $optionalValidity['minimum_validity_text'], 'Validade gerada deve conter prazo por extenso.');

$perishableDefault = prepare_item_supply_conditions([
    'item_classification' => 'consumption_perishable',
    'warranty_months' => '3',
]);
supply_test_assert_same(12, $perishableDefault['minimum_validity_months'], 'Consumo perecível deve assumir validade padrão de 12 meses.');
$perishable = prepare_item_supply_conditions([
    'item_classification' => 'consumption_perishable',
    'warranty_months' => '3',
    'minimum_validity_months' => '12',
]);
supply_test_assert_same(true, $perishable['is_perishable'], 'Consumo perecível deve persistir true.');
supply_test_assert_same(true, $perishable['minimum_validity_required'], 'Consumo perecível deve sempre exigir validade.');
supply_test_assert_throws(
    static fn (): array => prepare_item_supply_conditions([
        'item_classification' => 'consumption_perishable',
        'warranty_months' => '3',
        'minimum_validity_months' => '6.5',
    ]),
    'Validade decimal não deve ser aceita.'
);

supply_test_assert_throws(
    static fn (): array => prepare_item_supply_conditions([
        'item_classification' => 'permanent',
        'warranty_months' => '12',
        'minimum_validity_required' => true,
        'minimum_validity_months' => '6',
        'validity_exception_justification' => 'Exigência técnica específica.',
    ]),
    'Validade de permanente deve ser restrita ao administrador.'
);
$permanentException = prepare_item_supply_conditions([
    'item_classification' => 'permanent',
    'warranty_months' => '12',
    'minimum_validity_required' => true,
    'minimum_validity_months' => '6',
    'validity_exception_justification' => 'Exigência técnica específica.',
], false, true);
supply_test_assert_same('Exigência técnica específica.', $permanentException['validity_exception_justification'], 'Exceção administrativa deve preservar justificativa.');

$service = prepare_item_supply_conditions(['warranty_months' => '3'], true);
supply_test_assert_same('SERVICO', $service['item_nature'], 'Serviço deve usar classificação própria.');
supply_test_assert_contains('Garantia mínima dos serviços', $service['warranty'], 'Serviço deve gerar cláusula adequada ao objeto.');
supply_test_assert_same(null, $service['minimum_validity_months'], 'Serviço não deve possuir validade.');

supply_test_assert_same(false, item_supply_conditions_migrated(['warranty' => '12 meses']), 'Texto legado isolado não deve ser tratado como migrado.');
supply_test_assert_same(true, item_supply_conditions_migrated($permanent), 'Condições estruturadas devem ser reconhecidas como migradas.');

$formSource = file_get_contents(__DIR__ . '/../public/item_form.php') ?: '';
$scriptSource = file_get_contents(__DIR__ . '/../public/assets/item-supply-conditions.js') ?: '';
$schemaSource = file_get_contents(__DIR__ . '/../database/schema.sql') ?: '';
supply_test_assert_contains('name="item_classification"', $formSource, 'Formulário deve coletar classificação estruturada.');
supply_test_assert_contains('name="warranty_months"', $formSource, 'Formulário deve coletar garantia somente em meses.');
supply_test_assert_contains('data-warranty-preview', $formSource, 'Formulário deve exibir prévia da garantia.');
supply_test_assert_contains('validityMonths.required = validityActive', $scriptSource, 'Componente deve controlar validade obrigatória no frontend.');
supply_test_assert_contains('supply_conditions_migrated_at IS NULL', $schemaSource, 'Schema deve manter itens legados válidos até a edição.');
supply_test_assert_contains('ck_procurement_items_supply_conditions', $schemaSource, 'Schema deve validar condições estruturadas no banco.');

echo "ItemSupplyConditionsTest: OK\n";
