<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';

function dod_test_assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function dod_test_contains(string $haystack, string $needle, string $message): void
{
    if (strpos($haystack, $needle) === false) {
        throw new RuntimeException($message . ' Trecho esperado: ' . $needle);
    }
}

$moneyWords = direct_purchase_dod_money_in_words(1234.56);
dod_test_assert_true(
    $moneyWords === direct_purchase_dod_text('mil duzentos e trinta e quatro reais e cinquenta e seis centavos'),
    'Valor por extenso do DOD incorreto.'
);

$items = [
    [
        'tracking_code' => 'IT001',
        'item_name' => 'Notebook',
        'total_approved_quantity' => 2,
        'demand_count' => 1,
        'unit_type_abbreviation' => 'UN',
        'environmental_impacts' => json_encode(['Consumo de energia', 'Uso de embalagens'], JSON_UNESCAPED_UNICODE),
    ],
    [
        'tracking_code' => 'IT002',
        'item_name' => 'Monitor',
        'total_approved_quantity' => 3,
        'demand_count' => 2,
        'unit_type_abbreviation' => 'UN',
        'environmental_impacts' => json_encode(['consumo de energia', 'Descarte adequado'], JSON_UNESCAPED_UNICODE),
    ],
];

$quantityText = direct_purchase_dod_quantity_methodology_text($items);
dod_test_contains($quantityText, 'IT001 - Notebook: 2 UN', 'Estimativa de quantidade deve listar item consolidado.');
dod_test_contains($quantityText, '2 demandas', 'Estimativa de quantidade deve exibir quantidade de demandas origem.');

$impactText = direct_purchase_dod_environmental_impacts_text($items);
dod_test_assert_true(substr_count(mb_strtolower($impactText, 'UTF-8'), 'consumo de energia') === 1, 'Impactos iguais devem ser mesclados sem duplicidade.');
dod_test_contains($impactText, '- Uso de embalagens', 'Impacto ambiental deve ser renderizado em lista.');

$valueText = direct_purchase_dod_value_estimate_text(
    ['direct_purchase_award_criterion' => 'global_lowest'],
    ['global_winner' => ['supplier_name' => 'BAURUINFO COMERCIAL LTDA', 'supplier_document' => '04033848000178', 'total' => 373.0]]
);
dod_test_contains($valueText, 'R$ 373,00', 'Estimativa de valor deve exibir valor monetario formatado.');
dod_test_contains($valueText, 'BAURUINFO COMERCIAL LTDA', 'Estimativa de valor deve citar fornecedor vencedor.');
dod_test_contains($valueText, direct_purchase_dod_text('contrata\u{00E7}\u{00E3}o'), 'Estimativa de valor deve preservar acentuacao de contratacao.');
dod_test_contains($valueText, direct_purchase_dod_text('or\u{00E7}amento'), 'Estimativa de valor deve preservar acentuacao de orcamento.');
dod_test_contains($valueText, direct_purchase_dod_text('n\u{00BA} 04033848000178'), 'Estimativa de valor deve preservar simbolo de numero.');
dod_test_contains($valueText, direct_purchase_dod_text('Of\u{00ED}cio'), 'Estimativa de valor deve preservar acentuacao de Oficio.');
dod_test_assert_true(strpos($valueText, direct_purchase_dod_text('\u{00C3}')) === false, 'Estimativa de valor nao deve conter mojibake com A til.');

$heading = direct_purchase_dod_section_heading(['number' => '1', 'title' => direct_purchase_dod_text('Objeto da Contrata\u{00E7}\u{00E3}o')]);
dod_test_assert_true($heading === direct_purchase_dod_text('1. Objeto da Contrata\u{00E7}\u{00E3}o'), 'Numero do topico deve receber ponto apos o numero.');

$html = direct_purchase_dod_render_content("Texto **importante**\n- Primeiro item\n- Segundo item");
dod_test_contains($html, '<strong>importante</strong>', 'Renderizacao do DOD deve aplicar negrito.');
dod_test_contains($html, '<ul><li>Primeiro item</li><li>Segundo item</li></ul>', 'Renderizacao do DOD deve aplicar lista nao ordenada.');

echo "DirectPurchaseDodTest: OK\n";