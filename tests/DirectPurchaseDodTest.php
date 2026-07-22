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
        'specification' => json_encode([
            'descricao_minima' => 'Notebook corporativo.',
            'caracteristicas_minimas' => ['Memoria minima de 16 GB', 'SSD de 512 GB'],
            'criterios_aceitacao' => ['Equipamento sem avarias'],
            'documentacao_exigida' => ['Manual tecnico'],
            'certificados' => ['Certificacao aplicavel'],
            'observacoes' => ['Equipamento novo e de primeiro uso'],
        ], JSON_UNESCAPED_UNICODE),
        'warranty' => '12 meses',
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

$quantityHtml = direct_purchase_dod_quantity_methodology_html(
    $items,
    '<p>Metodologia definida pelo usuario.</p>',
    '4'
);
dod_test_contains($quantityHtml, '<h3>4.1. Estimativa de quantidade</h3>', 'Topico 4.1 deve identificar a estimativa automatica.');
dod_test_contains($quantityHtml, 'dod-quantity-table', 'Estimativa de quantidade deve ser exibida em tabela propria.');
dod_test_contains($quantityHtml, 'Tipo de unidade', 'Tabela deve identificar o tipo de unidade.');
dod_test_contains($quantityHtml, 'Notebook', 'Tabela deve exibir a descricao do item.');
dod_test_contains($quantityHtml, '<h3>4.2. Metodologia</h3>', 'Topico 4.2 deve identificar a metodologia editavel.');
dod_test_contains($quantityHtml, 'Metodologia definida pelo usuario.', 'Metodologia personalizada deve ser preservada.');

$requirementSettings = direct_purchase_dod_normalize_requirement_settings([
    'delivery_days' => 9,
    'delivery_day_type' => 'calendar',
    'delivery_trigger' => 'contract_signature',
    'receipt_days' => 6,
    'receipt_day_type' => 'business',
    'support_text' => 'Suporte personalizado durante a garantia.',
], true);
$requirementsHtml = direct_purchase_dod_requirements_html($items, $requirementSettings, '', '5');
dod_test_contains($requirementsHtml, '5.1. Requisitos técnicos mínimos', 'Topico 5.1 deve listar os requisitos tecnicos.');
dod_test_contains($requirementsHtml, '5.1.1. Do item: Notebook', 'Cada item deve gerar um sub-subtopico proprio.');
dod_test_contains($requirementsHtml, 'Memoria minima de 16 GB', 'Requisitos devem usar as caracteristicas cadastradas no item.');
dod_test_contains($requirementsHtml, 'Documentação exigida', 'Requisitos devem incluir a documentacao cadastrada.');
dod_test_contains($requirementsHtml, 'Certificados mínimos', 'DOD deve incluir certificados tecnicos cadastrados no item.');
dod_test_contains($requirementsHtml, '9 (nove) dias corridos', 'Prazo de entrega deve aplicar numero por extenso e tipo de dia.');
dod_test_contains($requirementsHtml, 'assinatura do contrato', 'Prazo de entrega deve aplicar o marco inicial selecionado.');
dod_test_contains($requirementsHtml, '6 (seis) dias úteis', 'Recebimento deve aplicar prazo editavel por extenso.');
dod_test_contains($requirementsHtml, 'Suporte personalizado durante a garantia.', 'Suporte tecnico personalizado deve ser preservado.');

$defaultRequirementSettings = direct_purchase_dod_normalize_requirement_settings([]);
dod_test_assert_true($defaultRequirementSettings['delivery_days'] === 7, 'Prazo de entrega padrao deve ser de sete dias.');
dod_test_assert_true($defaultRequirementSettings['receipt_days'] === 5, 'Prazo de recebimento padrao deve ser de cinco dias.');

$invalidRequirementSettingsFailed = false;
try {
    direct_purchase_dod_normalize_requirement_settings(['delivery_days' => 0], true);
} catch (InvalidArgumentException) {
    $invalidRequirementSettingsFailed = true;
}
dod_test_assert_true($invalidRequirementSettingsFailed, 'Prazo fora do intervalo permitido deve ser rejeitado.');

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

$structuredSections = direct_purchase_dod_normalize_sections([
    [
        'id' => 'quantidades',
        'title' => 'Estimativa de Quantidades e Metodologia',
        'methodology' => '<p>Metodologia persistida.</p>',
    ],
    [
        'id' => 'requisitos',
        'title' => direct_purchase_dod_text('Requisitos da Contrata\u{00E7}\u{00E3}o'),
        'requirements' => ['delivery_days' => 12, 'receipt_days' => 8],
    ],
]);
dod_test_assert_true($structuredSections[0]['auto_generated'] === true, 'Topico 4 deve permanecer automatico e editavel.');
dod_test_contains($structuredSections[0]['methodology'], 'Metodologia persistida.', 'Metodologia deve ser persistida separadamente.');
dod_test_assert_true($structuredSections[1]['auto_generated'] === true, 'Topico 5 deve ser hibrido e gerar requisitos automaticamente.');
dod_test_assert_true($structuredSections[1]['requirements']['delivery_days'] === 12, 'Prazo de entrega personalizado deve ser persistido.');

$headingFour = sanitize_rich_text_html('<h4>5.1.1. Do item</h4>');
dod_test_contains($headingFour, '<h4>', 'Saneamento deve preservar sub-subtopicos de quarto nivel.');

$legacyFooter = direct_purchase_dod_normalize_footer(['show_page_numbers' => true]);
dod_test_assert_true(!array_key_exists('show_page_numbers', $legacyFooter), 'Rodape deve descartar configuracao antiga de numeracao.');

$defaultHeader = direct_purchase_dod_normalize_header([]);
$defaultFooter = direct_purchase_dod_normalize_footer([]);
dod_test_assert_true($defaultHeader['repeat_on_every_page'] === true, 'Cabecalho deve repetir em todas as paginas por padrao.');
dod_test_assert_true($defaultFooter['repeat_on_every_page'] === true, 'Rodape deve repetir em todas as paginas por padrao.');

$nonRepeatingHeader = direct_purchase_dod_normalize_header(['repeat_on_every_page' => '0']);
$nonRepeatingFooter = direct_purchase_dod_normalize_footer(['repeat_on_every_page' => '0']);
dod_test_assert_true($nonRepeatingHeader['repeat_on_every_page'] === false, 'Cabecalho deve aceitar exibicao somente na primeira pagina.');
dod_test_assert_true($nonRepeatingFooter['repeat_on_every_page'] === false, 'Rodape deve aceitar exibicao somente ao final do documento.');

$customHeaderValues = [
    'entity_name' => 'Entidade personalizada',
    'state_name' => 'Estado personalizado',
    'place' => 'Municipio personalizado',
    'logo_left_path' => '/assets/logo-esquerda.png',
    'logo_center_path' => '/assets/logo-central.png',
    'logo_right_path' => '/assets/logo-direita.png',
];
$customHeader = direct_purchase_dod_normalize_header($customHeaderValues);
foreach ($customHeaderValues as $key => $expectedValue) {
    dod_test_assert_true($customHeader[$key] === $expectedValue, 'Cabecalho deve preservar o valor personalizado de ' . $key . '.');
}

$editorDefaults = rich_text_editor_default_settings();
dod_test_assert_true($editorDefaults['default_text_align'] === 'justify', 'Editor deve usar alinhamento justificado por padrao.');
dod_test_assert_true($editorDefaults['font_family'] === 'arial', 'Editor deve usar Arial por padrao.');
dod_test_assert_true($editorDefaults['show_page_numbers'] === true, 'DOD deve numerar as paginas por padrao.');
dod_test_assert_true((float) $editorDefaults['page_margin_top_mm'] >= 50, 'Margem superior deve reservar espaco para o cabecalho.');
dod_test_assert_true((float) $editorDefaults['page_margin_bottom_mm'] >= 25, 'Margem inferior deve reservar espaco para o rodape.');

$printLayoutMetrics = direct_purchase_dod_print_layout_metrics(
    [
        'state_name' => 'Estado de Sao Paulo',
        'secretariat_name' => 'Secretaria Municipal',
        'department_name' => 'Departamento Administrativo',
    ],
    [
        'address' => 'Rua de teste',
        'postal_code' => '00000-000',
        'phone' => '(00) 0000-0000',
        'branch' => '100',
        'cnpj' => '00.000.000/0001-00',
        'email' => 'teste@example.com',
    ],
    $editorDefaults
);
dod_test_assert_true($printLayoutMetrics['header_top_mm'] === 4.0, 'Cabecalho deve manter afastamento fisico constante da borda da folha.');
dod_test_assert_true($printLayoutMetrics['footer_bottom_mm'] === 9.0, 'Rodape deve deixar area exclusiva para a paginacao.');
dod_test_assert_true(
    $printLayoutMetrics['margin_top_mm'] >= $printLayoutMetrics['header_top_mm'] + $printLayoutMetrics['header_height_mm'] + $printLayoutMetrics['content_gap_mm'],
    'Margem superior deve conter posicao, altura do cabecalho e folga antes do conteudo.'
);
dod_test_assert_true(
    $printLayoutMetrics['margin_bottom_mm'] >= $printLayoutMetrics['footer_bottom_mm'] + $printLayoutMetrics['footer_height_mm'] + $printLayoutMetrics['content_gap_mm'],
    'Margem inferior deve conter paginacao, rodape e folga antes do conteudo.'
);
dod_test_assert_true(!array_key_exists('header_offset_mm', $printLayoutMetrics), 'Metrica nao deve expor offset ambiguo do cabecalho.');
dod_test_assert_true(!array_key_exists('footer_offset_mm', $printLayoutMetrics), 'Metrica nao deve expor offset ambiguo do rodape.');

$printLayoutWithoutPageNumbers = direct_purchase_dod_print_layout_metrics([], ['cnpj' => '00.000.000/0001-00'], array_merge($editorDefaults, ['show_page_numbers' => false]));
dod_test_assert_true($printLayoutWithoutPageNumbers['footer_bottom_mm'] === 4.0, 'Rodape sem paginacao deve usar apenas o afastamento padrao da borda.');

$threeHeaderLinesAndFourFooterLines = direct_purchase_dod_print_layout_metrics(
    [
        'state_name' => 'Estado de Sao Paulo',
        'secretariat_name' => 'Secretaria Municipal',
        'department_name' => '',
    ],
    [
        'address' => 'Rua de teste',
        'postal_code' => '00000-000',
        'phone' => '(00) 0000-0000',
        'branch' => '100',
        'cnpj' => '00.000.000/0001-00',
        'email' => 'teste@example.com',
    ],
    $editorDefaults
);
dod_test_assert_true($threeHeaderLinesAndFourFooterLines['header_height_mm'] === 48.0, 'Tres linhas institucionais devem reservar 48 mm para o cabecalho.');
dod_test_assert_true($threeHeaderLinesAndFourFooterLines['footer_height_mm'] === 28.0, 'Quatro linhas devem reservar 28 mm para o rodape.');
dod_test_assert_true($threeHeaderLinesAndFourFooterLines['margin_top_mm'] === 56.0, 'Exemplo deve reservar margem logica superior de 56 mm.');
dod_test_assert_true($threeHeaderLinesAndFourFooterLines['margin_bottom_mm'] === 41.0, 'Exemplo deve reservar margem logica inferior de 41 mm.');

$normalizedEditorSettings = rich_text_editor_normalize_settings([
    'default_text_align' => 'left',
    'force_text_alignment' => '0',
    'font_family' => 'times_new_roman',
    'font_size_pt' => '11,5',
    'line_height' => '1.25',
    'paragraph_spacing_pt' => '4',
    'page_margin_top_mm' => '52',
    'page_margin_right_mm' => '16',
    'page_margin_bottom_mm' => '30',
    'page_margin_left_mm' => '16',
    'show_page_numbers' => '0',
], true);
dod_test_assert_true($normalizedEditorSettings['font_size_pt'] === 11.5, 'Editor deve aceitar decimal com virgula.');
dod_test_assert_true($normalizedEditorSettings['force_text_alignment'] === false, 'Editor deve normalizar aplicacao forcada.');
dod_test_assert_true($normalizedEditorSettings['show_page_numbers'] === false, 'Editor deve permitir desabilitar numeracao.');

$invalidEditorSettingsFailed = false;
try {
    rich_text_editor_normalize_settings(['font_family' => 'comic_sans'], true);
} catch (InvalidArgumentException) {
    $invalidEditorSettingsFailed = true;
}
dod_test_assert_true($invalidEditorSettingsFailed, 'Editor deve rejeitar fonte fora da lista permitida.');

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
$headerSource = (string) file_get_contents(__DIR__ . '/../app/views/header.php');
$settingsPageSource = (string) file_get_contents(__DIR__ . '/../public/editor_settings.php');
$schemaSource = (string) file_get_contents(__DIR__ . '/../database/schema.sql');
dod_test_contains($exportSource, 'counter(page)', 'Impressao do DOD deve exibir pagina atual.');
dod_test_contains($exportSource, 'counter(pages)', 'Impressao do DOD deve exibir total de paginas.');
dod_test_contains($exportSource, 'position: fixed', 'Cabecalho e rodape devem ser fixos na impressao multipagina.');
dod_test_contains($exportSource, 'mso-element: header', 'Word deve registrar cabecalho nativo da secao.');
dod_test_contains($exportSource, 'mso-element: footer', 'Word deve registrar rodape nativo da secao.');
dod_test_contains($exportSource, "direct_purchase_dod_export_word_field('PAGE', '1')", 'Word deve usar campo de pagina atual.');
dod_test_contains($exportSource, "direct_purchase_dod_export_word_field('NUMPAGES', '1')", 'Word deve usar campo de total de paginas.');
dod_test_contains($exportSource, 'mso-element: field-begin', 'Campo de pagina do Word deve possuir marcador inicial.');
dod_test_contains($exportSource, 'mso-element: field-end', 'Campo de pagina do Word deve possuir marcador final.');
dod_test_contains($exportSource, 'direct_purchase_dod_export_header_html', 'Exportador deve reutilizar uma composicao de cabecalho.');
dod_test_contains($exportSource, 'direct_purchase_dod_export_footer_html', 'Exportador deve reutilizar uma composicao de rodape.');
dod_test_not_contains($formSource, 'show_page_numbers', 'Formulario nao deve oferecer numeracao de paginas.');
dod_test_contains($exportSource, 'print-color-adjust: exact', 'Faixas devem preservar cores na impressao.');
dod_test_contains($exportSource, 'border-top-color: #ff0000', 'Faixa vermelha deve possuir borda imprimivel.');
dod_test_contains($exportSource, 'border-top-color: #0070c0', 'Faixa azul deve possuir borda imprimivel.');
dod_test_contains($exportSource, 'border-top-color: #ffff00', 'Faixa amarela deve possuir borda imprimivel.');
dod_test_contains($exportSource, 'width: 210mm', 'Pre-visualizacao do DOD deve usar largura fisica A4.');
dod_test_contains($exportSource, 'table-header-group', 'Cabecalho das tabelas deve se repetir em quebras de pagina.');
dod_test_contains($exportSource, 'page-break-inside: avoid', 'Linhas, titulos e assinaturas devem evitar cortes na impressao.');
dod_test_contains($exportSource, '$headerHeight', 'Impressao deve reservar altura controlada para o cabecalho.');
dod_test_contains($exportSource, 'print-running-header', 'PDF deve usar uma copia independente e repetivel do cabecalho.');
dod_test_contains($exportSource, 'print-running-footer', 'PDF deve usar uma copia independente e repetivel do rodape.');
dod_test_contains($exportSource, 'document-screen-header', 'Pre-visualizacao deve manter seu cabecalho no fluxo normal.');
dod_test_contains($exportSource, 'document-screen-footer', 'Pre-visualizacao deve manter seu rodape no fluxo normal.');
dod_test_contains($exportSource, '$printMarginTop', 'Margem de impressao deve considerar a altura real do cabecalho.');
dod_test_contains($exportSource, '$printMarginBottom', 'Margem de impressao deve considerar rodape e paginacao.');
dod_test_contains($formSource, 'name="header[repeat_on_every_page]"', 'Formulario deve permitir configurar a repeticao do cabecalho.');
dod_test_contains($formSource, 'name="footer[repeat_on_every_page]"', 'Formulario deve permitir configurar a repeticao do rodape.');
dod_test_contains($exportSource, '$repeatHeader', 'Exportacao deve aplicar a escolha de repeticao do cabecalho.');
dod_test_contains($exportSource, '$repeatFooter', 'Exportacao deve aplicar a escolha de repeticao do rodape.');
dod_test_contains($exportSource, 'direct_purchase_dod_export_word_pagination_html', 'Word deve manter a paginacao mesmo sem repetir o rodape institucional.');
dod_test_contains($exportSource, '$headerTop', 'Exportacao deve usar posicao fisica clara para o cabecalho.');
dod_test_contains($exportSource, '$footerBottom', 'Exportacao deve usar posicao fisica clara para o rodape.');
dod_test_contains($exportSource, 'print-header-spacer', 'Chromium deve reservar a altura repetida do cabecalho no fluxo paginado.');
dod_test_contains($exportSource, 'print-footer-spacer', 'Chromium deve reservar a altura repetida do rodape no fluxo paginado.');
dod_test_contains($exportSource, 'display: table-footer-group', 'Rodape deve possuir espaco repetido em todas as paginas.');
dod_test_not_contains($exportSource, '$headerOffset', 'Exportacao nao deve reutilizar margem como offset do cabecalho.');
dod_test_not_contains($exportSource, '$footerOffset', 'Exportacao nao deve reutilizar margem como offset do rodape.');
dod_test_not_contains($exportSource, 'top: -<?=', 'Cabecalho nao deve usar coordenada vertical negativa.');
dod_test_not_contains($exportSource, 'bottom: -<?=', 'Rodape nao deve usar coordenada vertical negativa.');
dod_test_contains($formSource, '[methodology]', 'Formulario deve permitir editar a metodologia do topico 4.2.');
dod_test_contains($formSource, '[requirements][delivery_days]', 'Formulario deve parametrizar o prazo de entrega.');
dod_test_contains($formSource, '[requirements][receipt_days]', 'Formulario deve parametrizar o recebimento.');
dod_test_contains($formSource, '[requirements][support_text]', 'Formulario deve permitir editar o suporte tecnico.');
dod_test_contains($editorSource, '@tiptap/core@3', 'Editor deve usar a integracao TipTap.');
dod_test_contains($editorSource, 'toggleUnderline', 'Editor deve oferecer sublinhado.');
dod_test_contains($editorSource, 'insertTable', 'Editor deve oferecer tabelas.');
dod_test_contains($editorSource, 'setTextAlign', 'Editor deve oferecer alinhamento.');
dod_test_contains($editorSource, 'unsetAllMarks', 'Editor deve oferecer limpeza de formatacao.');
dod_test_contains($editorSource, 'defaultAlignment: editorDefaults.default_text_align', 'TipTap deve receber alinhamento padrao administrativo.');
dod_test_contains($headerSource, 'rich-text-editor-defaults', 'Aplicacao deve expor os padroes globais ao TipTap.');
dod_test_contains($settingsPageSource, 'auth_require_permission', 'Pagina de configuracoes deve exigir permissao.');
dod_test_contains($settingsPageSource, 'data-settings-preview', 'Pagina de configuracoes deve oferecer previa dinamica.');
dod_test_contains($schemaSource, 'CREATE TABLE IF NOT EXISTS rich_text_editor_settings', 'Schema deve persistir configuracoes do editor.');

echo "DirectPurchaseDodTest: OK\n";
