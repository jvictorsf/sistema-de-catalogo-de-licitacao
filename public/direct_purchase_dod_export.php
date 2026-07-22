<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

function direct_purchase_dod_export_date(?string $value): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return date('d/m/Y');
    }

    $timestamp = strtotime($value);

    return $timestamp ? date('d/m/Y', $timestamp) : $value;
}

function direct_purchase_dod_export_public_asset(?string $path): string
{
    $path = trim((string) $path);

    if ($path === '') {
        return '';
    }

    if (!str_starts_with($path, '/')) {
        $path = '/' . $path;
    }

    $absolute = dirname(__DIR__) . '/public' . $path;

    return is_file($absolute) ? $path : '';
}

function direct_purchase_dod_export_logo(?string $path, string $class, string $alt): string
{
    $asset = direct_purchase_dod_export_public_asset($path);

    if ($asset === '') {
        return '<span class="logo-placeholder"></span>';
    }

    return '<img src="' . e($asset) . '" class="' . e($class) . '" alt="' . e($alt) . '">';
}

function direct_purchase_dod_export_header_html(array $header, array $additionalLogoPaths, string $entityName): string
{
    $leftAdditionalLogos = [];
    $rightAdditionalLogos = [];

    foreach (array_values($additionalLogoPaths) as $index => $additionalLogoPath) {
        if ($index % 2 === 0) {
            $leftAdditionalLogos[] = $additionalLogoPath;
        } else {
            $rightAdditionalLogos[] = $additionalLogoPath;
        }
    }

    ob_start();
    ?>
    <header class="official-header">
        <div class="logo-row">
            <div class="logo-slot logo-group logo-group-left">
                <?= direct_purchase_dod_export_logo($header['logo_left_path'] ?? '', 'logo-side', 'Município Agro') ?>
                <?php foreach ($leftAdditionalLogos as $extraLogoPath): ?>
                    <?= direct_purchase_dod_export_logo($extraLogoPath, 'logo-extra', 'Logo adicional') ?>
                <?php endforeach; ?>
            </div>
            <div class="logo-slot"><?= direct_purchase_dod_export_logo($header['logo_center_path'] ?? '', 'logo-center', 'Brasão do Município') ?></div>
            <div class="logo-slot logo-group logo-group-right">
                <?= direct_purchase_dod_export_logo($header['logo_right_path'] ?? '', 'logo-side', 'Município Verde Azul') ?>
                <?php foreach ($rightAdditionalLogos as $extraLogoPath): ?>
                    <?= direct_purchase_dod_export_logo($extraLogoPath, 'logo-extra', 'Logo adicional') ?>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="header-line header-entity"><?= e($entityName) ?></div>
        <?php if (!empty($header['state_name'])): ?>
            <div class="header-line header-state"><?= e($header['state_name']) ?></div>
        <?php endif; ?>
        <?php if (!empty($header['secretariat_name'])): ?>
            <div class="header-line header-secretariat"><?= e($header['secretariat_name']) ?></div>
        <?php endif; ?>
        <?php if (!empty($header['department_name'])): ?>
            <div class="header-line header-department"><?= e($header['department_name']) ?></div>
        <?php endif; ?>
        <div class="stripes" aria-hidden="true">
            <div class="stripe stripe-red"></div>
            <div class="stripe stripe-blue"></div>
            <div class="stripe stripe-yellow"></div>
        </div>
    </header>
    <?php

    return (string) ob_get_clean();
}

function direct_purchase_dod_export_word_field(string $code, string $fallback): string
{
    return '<span style="mso-element: field-begin"></span>'
        . '<span style="mso-field-code: &quot; ' . e($code) . ' &quot;"></span>'
        . '<span style="mso-element: field-separator"></span>'
        . '<span class="word-page-field">' . e($fallback) . '</span>'
        . '<span style="mso-element: field-end"></span>';
}

function direct_purchase_dod_export_word_page_number(): string
{
    return direct_purchase_dod_export_word_field('PAGE', '1')
        . ' de '
        . direct_purchase_dod_export_word_field('NUMPAGES', '1');
}

function direct_purchase_dod_export_footer_html(array $footer, bool $showPageNumbers = false, bool $wordFields = false): string
{
    ob_start();
    ?>
    <footer class="official-footer">
        <div class="stripes" aria-hidden="true">
            <div class="stripe stripe-red"></div>
            <div class="stripe stripe-blue"></div>
            <div class="stripe stripe-yellow"></div>
        </div>
        <?php if (!empty($footer['address']) || !empty($footer['postal_code'])): ?>
            <p>Rua <?= e($footer['address'] ?: '-') ?><?= !empty($footer['postal_code']) ? ' - CEP ' . e($footer['postal_code']) : '' ?></p>
        <?php endif; ?>
        <?php if (!empty($footer['phone']) || !empty($footer['branch'])): ?>
            <p>Telefone <?= e($footer['phone'] ?: '-') ?><?= !empty($footer['branch']) ? ' / Ramal ' . e($footer['branch']) : '' ?></p>
        <?php endif; ?>
        <?php if (!empty($footer['cnpj'])): ?>
            <p>CNPJ/MF <?= e($footer['cnpj']) ?></p>
        <?php endif; ?>
        <?php if (!empty($footer['email'])): ?>
            <p>E-mail: <?= e($footer['email']) ?></p>
        <?php endif; ?>
        <?php if ($showPageNumbers && $wordFields): ?>
            <p class="word-page-number">Página <?= direct_purchase_dod_export_word_page_number() ?></p>
        <?php endif; ?>
    </footer>
    <?php

    return (string) ob_get_clean();
}

$id = (int) ($_GET['id'] ?? 0);
$format = strtolower(trim((string) ($_GET['format'] ?? 'pdf')));
$project = find_project($id);

if (!$project) {
    http_response_code(404);
    exit('Projeto não encontrado.');
}

if (!project_is_direct_purchase($project)) {
    http_response_code(400);
    exit('O DOD está disponível apenas para projetos de Compra Direta.');
}

$dod = get_direct_purchase_dod($id);
$demands = get_project_demands($id);
$consolidatedItems = get_project_consolidated_items($id);
$budgetEvaluation = get_direct_purchase_budget_evaluation($id);
$header = direct_purchase_dod_normalize_header($dod['header'] ?? [], $project);
$footer = direct_purchase_dod_prefill_footer_from_demands(direct_purchase_dod_normalize_footer($dod['footer'] ?? []), $demands);
$sections = direct_purchase_dod_enabled_sections(direct_purchase_dod_apply_auto_content($project, $demands, $consolidatedItems, $dod, $budgetEvaluation));
$entityName = trim((string) ($header['entity_name'] ?? '')) ?: APP_NAME;
$title = trim((string) ($header['title'] ?? '')) ?: 'Documento de Oficialização de Demanda (DOD)';
$filename = 'dod-compra-direta-projeto-' . $id . '.doc';
$signatures = direct_purchase_dod_normalize_signatures($footer['signatures'] ?? [], $footer);
$additionalLogoPaths = direct_purchase_dod_normalize_logo_paths($header['additional_logo_paths'] ?? []);
$editorSettings = rich_text_editor_normalize_settings(get_rich_text_editor_settings());
$fontCss = rich_text_editor_font_css($editorSettings);
$fontSize = rich_text_editor_css_number($editorSettings['font_size_pt']);
$lineHeight = rich_text_editor_css_number($editorSettings['line_height']);
$paragraphSpacing = rich_text_editor_css_number($editorSettings['paragraph_spacing_pt']);
$printLayoutMetrics = direct_purchase_dod_print_layout_metrics($header, $footer, $editorSettings);
$marginTop = rich_text_editor_css_number($editorSettings['page_margin_top_mm']);
$marginRight = rich_text_editor_css_number($editorSettings['page_margin_right_mm']);
$marginBottom = rich_text_editor_css_number($editorSettings['page_margin_bottom_mm']);
$marginLeft = rich_text_editor_css_number($editorSettings['page_margin_left_mm']);
$printMarginTop = rich_text_editor_css_number($printLayoutMetrics['margin_top_mm']);
$printMarginBottom = rich_text_editor_css_number($printLayoutMetrics['margin_bottom_mm']);
$headerOffset = rich_text_editor_css_number($printLayoutMetrics['header_offset_mm']);
$footerOffset = rich_text_editor_css_number($printLayoutMetrics['footer_offset_mm']);
$headerHeight = rich_text_editor_css_number($printLayoutMetrics['header_height_mm']);
$footerHeight = rich_text_editor_css_number($printLayoutMetrics['footer_height_mm']);
$showPageNumbers = (bool) $editorSettings['show_page_numbers'];
$headerHtml = direct_purchase_dod_export_header_html($header, $additionalLogoPaths, $entityName);
$footerHtml = direct_purchase_dod_export_footer_html($footer);
$wordFooterHtml = direct_purchase_dod_export_footer_html($footer, $showPageNumbers, true);

if ($format === 'word') {
    send_download_headers('application/msword; charset=utf-8', $filename);
} else {
    header('Content-Type: text/html; charset=utf-8');
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title><?= e($title) ?></title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        html { background: #e5e7eb; }
        body { font-family: <?= e($fontCss) ?>; font-size: <?= e($fontSize) ?>pt; line-height: <?= e($lineHeight) ?>; color: #111827; margin: 0; background: #e5e7eb; orphans: 3; widows: 3; }
        .toolbar { position: sticky; top: 0; z-index: 3; display: flex; justify-content: flex-end; gap: 8px; padding: 12px; background: #111827; }
        .toolbar a, .toolbar button { border: 0; border-radius: 6px; padding: 8px 12px; background: #fff; color: #111827; text-decoration: none; cursor: pointer; font-size: 14px; }
        .page { width: 210mm; max-width: calc(100% - 24px); min-height: 297mm; margin: 18px auto; padding: 14mm 18mm 16mm; background: #fff; box-shadow: 0 8px 30px rgba(15, 23, 42, .16); }
        .official-header { text-align: center; margin-bottom: 8mm; }
        .logo-row { display: grid; grid-template-columns: minmax(0, 1fr) 30mm minmax(0, 1fr); align-items: center; gap: 4mm; margin-bottom: 1.5mm; min-height: 23mm; }
        .logo-group { display: flex; align-items: center; gap: 2.5mm; min-width: 0; }
        .logo-group-left { justify-content: flex-end; }
        .logo-group-right { justify-content: flex-start; }
        .logo-side { max-width: 22mm; max-height: 14mm; object-fit: contain; }
        .logo-center { max-width: 29mm; max-height: 23mm; object-fit: contain; }
        .logo-placeholder { display: inline-block; width: 18mm; min-height: 1px; }
        .logo-extra { max-width: 16mm; max-height: 11mm; object-fit: contain; }
        .header-line { margin: .4mm 0; font-weight: 700; text-transform: uppercase; line-height: 1.1; }
        .header-entity { font-size: 11pt; }
        .header-state, .header-secretariat, .header-department { font-size: 9pt; }
        .stripes { width: 100%; margin-top: 2mm; }
        .stripe { height: 1mm; width: 100%; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .stripe-red { background: #ff0000; }
        .stripe-blue { background: #0070c0; }
        .stripe-yellow { background: #ffff00; }
        .document-title { text-align: center; font-size: 17px; text-transform: uppercase; margin: 20px 0 12px; }
        .meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 20px; margin: 18px 0 24px; font-size: 13px; }
        .meta div { border-bottom: 1px solid #d1d5db; padding-bottom: 5px; }
        .meta strong { display: block; color: #374151; font-size: 11px; text-transform: uppercase; margin-bottom: 2px; }
        .section { break-inside: auto; margin: 0 0 7mm; }
        .section h2 { break-after: avoid-page; font-size: 13pt; margin: 0 0 3mm; text-transform: uppercase; }
        .section p { margin: 0 0 <?= e($paragraphSpacing) ?>pt; line-height: <?= e($lineHeight) ?>; text-align: <?= e($editorSettings['default_text_align']) ?>; }
        .section ul, .section ol { margin: 0 0 10px 22px; padding: 0; }
        .section li { margin: 0 0 5px; line-height: <?= e($lineHeight) ?>; text-align: <?= e($editorSettings['default_text_align']) ?>; }
        .rich-text-content h1 { font-size: 17px; margin: 16px 0 8px; }
        .rich-text-content h2 { font-size: 15px; margin: 14px 0 8px; text-transform: none; }
        .rich-text-content h3 { break-after: avoid-page; font-size: 11.5pt; margin: 5mm 0 2mm; }
        .rich-text-content h4 { break-after: avoid-page; font-size: 10.5pt; margin: 4mm 0 2mm; }
        .rich-text-content blockquote { border-left: 3px solid #9ca3af; color: #374151; margin: 10px 0; padding-left: 10px; }
        .rich-text-content a { color: #075db8; text-decoration: underline; }
        .rich-text-content table { border-collapse: collapse; margin: 3mm 0 5mm; table-layout: fixed; width: 100%; }
        .rich-text-content thead { display: table-header-group; }
        .rich-text-content tr { break-inside: avoid; page-break-inside: avoid; }
        .rich-text-content th, .rich-text-content td { border: 1px solid #4b5563; overflow-wrap: anywhere; padding: 2mm; text-align: left; vertical-align: top; }
        .rich-text-content th { background: #e5e7eb; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .rich-text-content .dod-quantity-table th:first-child, .rich-text-content .dod-quantity-table td:first-child { width: 10%; }
        .rich-text-content .dod-quantity-table th:nth-child(3), .rich-text-content .dod-quantity-table td:nth-child(3) { width: 24%; }
        .rich-text-content .dod-quantity-table th:last-child, .rich-text-content .dod-quantity-table td:last-child { width: 16%; }
        .empty { color: #6b7280; font-style: italic; }
        .issue-place { margin-top: 28px; text-align: right; font-size: 13px; }
        .footer-meta { margin-top: 18px; font-size: 13px; }
        .signatures { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18mm 12mm; margin-top: 20mm; }
        .signature { text-align: center; min-height: 22mm; break-inside: avoid; page-break-inside: avoid; }
        .signature-line { border-top: 1px solid #111827; margin-bottom: 8px; }
        .signature strong { display: block; font-size: 13px; }
        .signature span, .signature small { display: block; color: #4b5563; font-size: 12px; }
        .official-footer { margin-top: 12mm; text-align: center; font-size: 8.5pt; line-height: 1.15; color: #111827; }
        .official-footer .stripes { margin-bottom: 1.5mm; }
        .official-footer p { margin: .5mm 0; }
        .print-running-header, .print-running-footer { display: none; }
        .word-header-container { mso-element: header; }
        .word-footer-container { mso-element: footer; }
        .word-section { page: Section1; }
        .word-header-container .official-header,
        .word-footer-container .official-footer { margin: 0; }
        .word-page-number { font-size: 9pt; text-align: left; }
        <?php if (!empty($editorSettings['force_text_alignment'])): ?>
        .document-content .section p,
        .document-content .section li,
        .document-content .rich-text-content blockquote,
        .document-content .rich-text-content td,
        .document-content .rich-text-content th {
            text-align: <?= e($editorSettings['default_text_align']) ?> !important;
        }
        <?php endif; ?>
        @media (max-width: 720px) {
            .page { max-width: calc(100% - 12px); margin: 6px auto; min-height: 0; padding: 18px; }
            .meta { grid-template-columns: 1fr; }
            .logo-row { grid-template-columns: 1fr 24mm 1fr; gap: 2mm; }
            .logo-extra { display: none; }
            .signatures { grid-template-columns: 1fr; }
        }
        @media print {
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            html, body { background: #fff; width: auto; }
            .toolbar { display: none; }
            .page { width: auto; max-width: none; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
            .document-screen-header, .document-screen-footer { display: none !important; }
            .print-running-header, .print-running-footer {
                background: #fff;
                display: block;
                left: 0;
                margin: 0;
                overflow: hidden;
                position: fixed;
                right: 0;
                z-index: 1;
            }
            .print-running-header {
                height: <?= e($headerHeight) ?>mm;
                top: -<?= e($headerOffset) ?>mm;
            }
            .print-running-footer {
                bottom: -<?= e($footerOffset) ?>mm;
                height: <?= e($footerHeight) ?>mm;
            }
            .print-running-header .official-header,
            .print-running-footer .official-footer { margin: 0; }
            .section h2, .section h3, .section h4, .signature { break-inside: avoid; page-break-inside: avoid; }
            .section h2, .section h3, .section h4 { break-after: avoid-page; page-break-after: avoid; }
            .stripe { background: transparent !important; border-top: 1mm solid transparent; height: 0; }
            .stripe-red { border-top-color: #ff0000 !important; }
            .stripe-blue { border-top-color: #0070c0 !important; }
            .stripe-yellow { border-top-color: #ffff00 !important; }
        }
        @page {
            size: A4;
            margin: <?= e($printMarginTop) ?>mm <?= e($marginRight) ?>mm <?= e($printMarginBottom) ?>mm <?= e($marginLeft) ?>mm;
            <?php if ($showPageNumbers): ?>
            @bottom-left {
                content: "Página " counter(page) " de " counter(pages);
                font-family: <?= e($fontCss) ?>;
                font-size: 9pt;
                padding-bottom: 1mm;
                vertical-align: bottom;
            }
            <?php endif; ?>
        }
        @page Section1 {
            size: 595.3pt 841.9pt;
            margin: <?= e($marginTop) ?>mm <?= e($marginRight) ?>mm <?= e($marginBottom) ?>mm <?= e($marginLeft) ?>mm;
            mso-header: dodWordHeader;
            mso-header-margin: 5mm;
            mso-footer: dodWordFooter;
            mso-footer-margin: 5mm;
        }
    </style>
</head>
<body>
<?php if ($format !== 'word'): ?>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Imprimir/PDF</button>
        <a href="/direct_purchase_dod.php?id=<?= (int) $project['id'] ?>">Editar DOD</a>
        <a href="/project_show.php?id=<?= (int) $project['id'] ?>">Voltar</a>
    </div>
<?php endif; ?>

<?php if ($format === 'word'): ?>
    <div id="dodWordHeader" class="word-header-container"><?= $headerHtml ?></div>
    <div id="dodWordFooter" class="word-footer-container"><?= $wordFooterHtml ?></div>
<?php endif; ?>

<?php if ($format !== 'word'): ?>
    <div class="print-running-header" aria-hidden="true"><?= $headerHtml ?></div>
    <div class="print-running-footer" aria-hidden="true"><?= $footerHtml ?></div>
<?php endif; ?>

<main class="page document-content <?= $format === 'word' ? 'word-section' : '' ?>">
    <?php if ($format !== 'word'): ?>
        <div class="document-screen-header"><?= $headerHtml ?></div>
    <?php endif; ?>

    <h1 class="document-title"><?= e($title) ?></h1>

    <section class="meta">
        <div><strong>Projeto</strong><?= e($project['name'] ?? '-') ?></div>
        <div><strong>Modalidade</strong><?= e(project_process_type_label($project['process_type'] ?? null)) ?></div>
        <div><strong>Local e data</strong><?= e(trim(implode(', ', array_filter([(string) ($header['place'] ?? ''), direct_purchase_dod_export_date($header['issue_date'] ?? null)])))) ?></div>
        <div><strong>Ofício/processo</strong><?= e($header['document_number'] ?: '-') ?></div>
        <div><strong>Destinatário</strong><?= e($header['recipient'] ?: '-') ?></div>
        <div><strong>Assunto</strong><?= e($header['subject'] ?: ($project['name'] ?? '-')) ?></div>
        <div><strong>Critério do orçamento</strong><?= e(direct_purchase_award_criterion_label($project['direct_purchase_award_criterion'] ?? null)) ?></div>
        <div><strong>Data de emissão</strong><?= e(direct_purchase_dod_export_date($footer['issue_date'] ?? $header['issue_date'] ?? null)) ?></div>
    </section>

    <?php foreach ($sections as $section): ?>
        <section class="section">
            <h2><?= e(direct_purchase_dod_section_heading($section)) ?></h2>
            <?= direct_purchase_dod_render_content((string) ($section['content'] ?? '')) ?>
        </section>
    <?php endforeach; ?>

    <section class="footer-meta">
        <?php foreach (($footer['additional_fields'] ?? []) as $field): ?>
            <?php if (is_array($field) && trim((string) ($field['label'] ?? '')) !== ''): ?>
                <p><strong><?= e($field['label']) ?>:</strong> <?= e($field['value'] ?? '') ?></p>
            <?php endif; ?>
        <?php endforeach; ?>
    </section>

    <?php if (!empty($footer['issue_place']) || !empty($footer['issue_date'])): ?>
        <p class="issue-place"><?= e(trim(implode(', ', array_filter([(string) ($footer['issue_place'] ?? ''), direct_purchase_dod_export_date($footer['issue_date'] ?? null)])))) ?></p>
    <?php endif; ?>

    <section class="signatures">
        <?php foreach ($signatures as $signature): ?>
            <div class="signature">
                <div class="signature-line"></div>
                <strong><?= e(($signature['name'] ?? '') !== '' ? $signature['name'] : ($signature['label'] ?? 'Assinatura')) ?></strong>
                <?php if (($signature['role'] ?? '') !== ''): ?>
                    <span><?= e($signature['role']) ?></span>
                <?php endif; ?>
                <?php if (($signature['label'] ?? '') !== ''): ?>
                    <small><?= e($signature['label']) ?></small>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </section>

    <?php if ($format !== 'word'): ?>
        <div class="document-screen-footer"><?= $footerHtml ?></div>
    <?php endif; ?>
</main>
</body>
</html>
