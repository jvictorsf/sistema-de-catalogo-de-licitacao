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
$showPageNumbers = !empty($footer['show_page_numbers']);
$signatures = direct_purchase_dod_normalize_signatures($footer['signatures'] ?? [], $footer);
$additionalLogoPaths = direct_purchase_dod_normalize_logo_paths($header['additional_logo_paths'] ?? []);

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
        body { font-family: Arial, Helvetica, sans-serif; color: #111827; margin: 0; background: #f3f4f6; }
        .toolbar { position: sticky; top: 0; z-index: 3; display: flex; justify-content: flex-end; gap: 8px; padding: 12px; background: #111827; }
        .toolbar a, .toolbar button { border: 0; border-radius: 6px; padding: 8px 12px; background: #fff; color: #111827; text-decoration: none; cursor: pointer; font-size: 14px; }
        .page { width: min(100%, 900px); margin: 24px auto; padding: 34px 44px 28px; background: #fff; box-shadow: 0 12px 40px rgba(15, 23, 42, .14); }
        .official-header { text-align: center; margin-bottom: 22px; }
        .logo-row { display: grid; grid-template-columns: 1fr 120px 1fr; align-items: center; gap: 18px; margin-bottom: 8px; }
        .logo-side { max-width: 86px; max-height: 58px; object-fit: contain; }
        .logo-center { max-width: 112px; max-height: 96px; object-fit: contain; }
        .logo-slot:first-child { text-align: right; }
        .logo-slot:last-child { text-align: left; }
        .logo-placeholder { display: inline-block; width: 82px; min-height: 1px; }
        .extra-logo-row { display: flex; justify-content: center; align-items: center; gap: 16px; margin: 4px 0 8px; }
        .logo-extra { max-width: 78px; max-height: 50px; object-fit: contain; }
        .header-line { margin: 1px 0; font-weight: 700; text-transform: uppercase; line-height: 1.25; }
        .header-entity { font-size: 16px; }
        .header-state, .header-secretariat, .header-department { font-size: 13px; }
        .stripes { width: 100%; margin-top: 10px; }
        .stripe { height: 3px; width: 100%; }
        .stripe-red { background: #ff0000; }
        .stripe-blue { background: #0070c0; }
        .stripe-yellow { background: #ffff00; }
        .document-title { text-align: center; font-size: 17px; text-transform: uppercase; margin: 20px 0 12px; }
        .meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 20px; margin: 18px 0 24px; font-size: 13px; }
        .meta div { border-bottom: 1px solid #d1d5db; padding-bottom: 5px; }
        .meta strong { display: block; color: #374151; font-size: 11px; text-transform: uppercase; margin-bottom: 2px; }
        .section { break-inside: avoid; margin: 0 0 22px; }
        .section h2 { font-size: 15px; margin: 0 0 8px; text-transform: uppercase; }
        .section p { margin: 0 0 8px; line-height: 1.55; text-align: justify; }
        .section ul, .section ol { margin: 0 0 10px 22px; padding: 0; }
        .section li { margin: 0 0 5px; line-height: 1.55; text-align: justify; }
        .empty { color: #6b7280; font-style: italic; }
        .issue-place { margin-top: 28px; text-align: right; font-size: 13px; }
        .footer-meta { margin-top: 18px; font-size: 13px; }
        .signatures { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 52px 34px; margin-top: 64px; }
        .signature { text-align: center; min-height: 74px; break-inside: avoid; }
        .signature-line { border-top: 1px solid #111827; margin-bottom: 8px; }
        .signature strong { display: block; font-size: 13px; }
        .signature span, .signature small { display: block; color: #4b5563; font-size: 12px; }
        .official-footer { margin-top: 42px; text-align: center; font-size: 12px; color: #111827; }
        .official-footer .stripes { margin-bottom: 8px; }
        .official-footer p { margin: 2px 0; }
        .page-number-note { margin-top: 8px; text-align: left; color: #4b5563; font-size: 11px; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .page { width: auto; margin: 0; padding: 0; box-shadow: none; }
            .section, .signature { break-inside: avoid; }
        }
        @page { margin: 18mm; }
        <?php if ($showPageNumbers): ?>
        @page { @bottom-left { content: "Página " counter(page) " de " counter(pages); font-size: 10px; color: #4b5563; } }
        <?php endif; ?>
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

<main class="page">
    <header class="official-header">
        <div class="logo-row">
            <div class="logo-slot"><?= direct_purchase_dod_export_logo($header['logo_left_path'] ?? '', 'logo-side', 'Município Agro') ?></div>
            <div class="logo-slot"><?= direct_purchase_dod_export_logo($header['logo_center_path'] ?? '', 'logo-center', 'Brasão do Município') ?></div>
            <div class="logo-slot"><?= direct_purchase_dod_export_logo($header['logo_right_path'] ?? '', 'logo-side', 'Município Verde Azul') ?></div>
        </div>
        <?php if ($additionalLogoPaths): ?>
            <div class="extra-logo-row">
                <?php foreach ($additionalLogoPaths as $extraLogoPath): ?>
                    <?= direct_purchase_dod_export_logo($extraLogoPath, 'logo-extra', 'Logo adicional') ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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
        <?php if ($showPageNumbers): ?>
            <div class="page-number-note">Página x de y</div>
        <?php endif; ?>
    </footer>
</main>
</body>
</html>