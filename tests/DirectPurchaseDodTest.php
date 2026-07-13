<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/views/components/rich_text_editor.php';

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

function dod_test_not_contains(string $haystack, string $needle, string $message): void
{
    if (strpos($haystack, $needle) !== false) {
        throw new RuntimeException($message . ' Trecho indevido: ' . $needle);
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

$unsafeRichText = '<h2 style="text-align: center; color: red" onclick="alert(1)">Título</h2>'
    . '<p><strong>Conteúdo</strong> <a href="javascript:alert(1)">inseguro</a></p>'
    . '<script>alert(1)</script><table><tr><td colspan="2">Dado</td></tr></table>';
$safeRichText = sanitize_rich_text_html($unsafeRichText);
dod_test_not_contains($safeRichText, '<script', 'Saneamento deve remover scripts do conteudo rico.');
dod_test_not_contains($safeRichText, 'onclick', 'Saneamento deve remover eventos HTML.');
dod_test_not_contains($safeRichText, 'javascript:', 'Saneamento deve remover links inseguros.');
dod_test_contains($safeRichText, '<strong>Conteúdo</strong>', 'Saneamento deve preservar formatacao permitida.');

if (class_exists('DOMDocument')) {
    dod_test_contains($safeRichText, 'style="text-align: center;"', 'Saneamento deve preservar alinhamento permitido.');
    dod_test_contains($safeRichText, '<table>', 'Saneamento deve preservar tabelas.');
    dod_test_contains($safeRichText, 'colspan="2"', 'Saneamento deve preservar mescla valida de celulas.');
}

$richRendered = direct_purchase_dod_render_content('<p>Texto <u>sublinhado</u></p>');
dod_test_contains($richRendered, 'class="rich-text-content"', 'Relatorio deve identificar conteudo rico.');
dod_test_contains($richRendered, '<u>sublinhado</u>', 'Relatorio deve preservar sublinhado permitido.');

$normalizedSections = direct_purchase_dod_normalize_sections([[
    'id' => 'personalizado',
    'title' => 'Personalizado',
    'content' => '<p onclick="alert(1)">Texto seguro</p>',
]]);
dod_test_not_contains($normalizedSections[0]['content'], 'onclick', 'Normalizacao deve sanear HTML antes da persistencia.');

$legacyFooter = direct_purchase_dod_normalize_footer(['show_page_numbers' => true]);
dod_test_assert_true(!array_key_exists('show_page_numbers', $legacyFooter), 'Rodape deve descartar configuracao antiga de numeracao.');

$editorMarkup = render_rich_text_editor('sections[0][content]', '<p><strong>Existente</strong></p>', [
    'id' => 'editor-test',
    'max_length' => 1200,
    'aria_label' => 'Conteudo de teste',
]);
dod_test_contains($editorMarkup, 'name="sections[0][content]"', 'Componente deve integrar o campo ao formulario.');
dod_test_contains($editorMarkup, 'data-rich-editor', 'Componente deve sinalizar inicializacao do TipTap.');
dod_test_contains($editorMarkup, '<strong>Existente</strong>', 'Componente deve carregar conteudo HTML existente.');
dod_test_contains($editorMarkup, 'data-rich-max-length="1200"', 'Componente deve expor limite para validacao.');

$exportSource = (string) file_get_contents(__DIR__ . '/../public/direct_purchase_dod_export.php');
$formSource = (string) file_get_contents(__DIR__ . '/../public/direct_purchase_dod.php');
$editorSource = (string) file_get_contents(__DIR__ . '/../public/assets/rich-text-editor.js');
dod_test_not_contains($exportSource, 'showPageNumbers', 'Exportador nao deve gerar numeracao de paginas.');
dod_test_not_contains($exportSource, 'Página x de y', 'Pre-visualizacao nao deve exibir marcador de pagina.');
dod_test_not_contains($formSource, 'show_page_numbers', 'Formulario nao deve oferecer numeracao de paginas.');
dod_test_contains($exportSource, 'print-color-adjust: exact', 'Faixas devem preservar cores na impressao.');
dod_test_contains($exportSource, 'border-top-color: #ff0000', 'Faixa vermelha deve possuir borda imprimivel.');
dod_test_contains($exportSource, 'border-top-color: #0070c0', 'Faixa azul deve possuir borda imprimivel.');
dod_test_contains($exportSource, 'border-top-color: #ffff00', 'Faixa amarela deve possuir borda imprimivel.');
dod_test_contains($editorSource, '@tiptap/core@3', 'Editor deve usar a integracao TipTap.');
dod_test_contains($editorSource, 'toggleUnderline', 'Editor deve oferecer sublinhado.');
dod_test_contains($editorSource, 'insertTable', 'Editor deve oferecer tabelas.');
dod_test_contains($editorSource, 'setTextAlign', 'Editor deve oferecer alinhamento.');
dod_test_contains($editorSource, 'unsetAllMarks', 'Editor deve oferecer limpeza de formatacao.');

echo "DirectPurchaseDodTest: OK\n";
