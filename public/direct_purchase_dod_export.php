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

function direct_purchase_dod_export_paragraphs(string $text): array
{
    $paragraphs = array_values(array_filter(
        array_map('trim', preg_split('/\r\n|\r|\n/', $text) ?: []),
        static fn (string $line): bool => $line !== ''
    ));

    return $paragraphs ?: ['A preencher.'];
}

$id = (int) ($_GET['id'] ?? 0);
$format = strtolower(trim((string) ($_GET['format'] ?? 'pdf')));
$project = find_project($id);

if (!$project) {
    http_response_code(404);
    exit('Projeto nao encontrado.');
}

if (!project_is_direct_purchase($project)) {
    http_response_code(400);
    exit('O DOD esta disponivel apenas para projetos de Compra Direta.');
}

$dod = get_direct_purchase_dod($id);
$header = $dod['header'];
$footer = $dod['footer'];
$sections = direct_purchase_dod_enabled_sections($dod['sections'] ?? []);
$entityName = trim((string) ($header['entity_name'] ?? '')) ?: APP_NAME;
$title = trim((string) ($header['title'] ?? '')) ?: 'Documento de Oficializacao de Demanda (DOD)';
$filename = 'dod-compra-direta-projeto-' . $id . '.doc';

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
        .toolbar { position: sticky; top: 0; display: flex; justify-content: flex-end; gap: 8px; padding: 12px; background: #111827; }
        .toolbar a, .toolbar button { border: 0; border-radius: 6px; padding: 8px 12px; background: #fff; color: #111827; text-decoration: none; cursor: pointer; font-size: 14px; }
        .page { width: min(100%, 900px); margin: 24px auto; padding: 44px; background: #fff; box-shadow: 0 12px 40px rgba(15, 23, 42, .14); }
        .doc-header { display: grid; grid-template-columns: 92px 1fr; gap: 20px; align-items: center; border-bottom: 2px solid #111827; padding-bottom: 18px; margin-bottom: 22px; }
        .doc-header img { max-width: 82px; max-height: 82px; object-fit: contain; }
        .doc-header h1 { margin: 4px 0 0; font-size: 20px; text-transform: uppercase; }
        .entity { font-size: 16px; font-weight: 700; text-transform: uppercase; }
        .meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 20px; margin: 18px 0 24px; font-size: 13px; }
        .meta div { border-bottom: 1px solid #d1d5db; padding-bottom: 5px; }
        .meta strong { display: block; color: #374151; font-size: 11px; text-transform: uppercase; margin-bottom: 2px; }
        .section { break-inside: avoid; margin: 0 0 22px; }
        .section h2 { font-size: 16px; margin: 0 0 8px; text-transform: uppercase; }
        .section p { margin: 0 0 8px; line-height: 1.55; text-align: justify; }
        .empty { color: #6b7280; font-style: italic; }
        .footer-meta { margin-top: 26px; font-size: 13px; }
        .signatures { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 48px; margin-top: 64px; }
        .signature { text-align: center; border-top: 1px solid #111827; padding-top: 8px; min-height: 64px; }
        .signature strong { display: block; }
        .signature span { color: #4b5563; font-size: 13px; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .page { width: auto; margin: 0; padding: 0; box-shadow: none; }
        }
        @page { margin: 18mm; }
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
    <header class="doc-header">
        <div><?= render_municipal_logo() ?></div>
        <div>
            <div class="entity"><?= e($entityName) ?></div>
            <h1><?= e($title) ?></h1>
        </div>
    </header>

    <section class="meta">
        <div><strong>Projeto</strong><?= e($project['name'] ?? '-') ?></div>
        <div><strong>Modalidade</strong><?= e(project_process_type_label($project['process_type'] ?? null)) ?></div>
        <div><strong>Local e data</strong><?= e(trim(implode(', ', array_filter([(string) ($header['place'] ?? ''), direct_purchase_dod_export_date($header['issue_date'] ?? null)])))) ?></div>
        <div><strong>Oficio/processo</strong><?= e($header['document_number'] ?: '-') ?></div>
        <div><strong>Destinatario</strong><?= e($header['recipient'] ?: '-') ?></div>
        <div><strong>Assunto</strong><?= e($header['subject'] ?: ($project['name'] ?? '-')) ?></div>
        <div><strong>Criterio do orcamento</strong><?= e(direct_purchase_award_criterion_label($project['direct_purchase_award_criterion'] ?? null)) ?></div>
        <div><strong>Data de emissao</strong><?= e(direct_purchase_dod_export_date($footer['issue_date'] ?? $header['issue_date'] ?? null)) ?></div>
    </section>

    <?php foreach ($sections as $section): ?>
        <?php
            $heading = trim(implode(' ', array_filter([
                (string) ($section['number'] ?? ''),
                (string) ($section['title'] ?? ''),
            ])));
            $paragraphs = direct_purchase_dod_export_paragraphs((string) ($section['content'] ?? ''));
            $isEmpty = count($paragraphs) === 1 && $paragraphs[0] === 'A preencher.';
        ?>
        <section class="section">
            <h2><?= e($heading) ?></h2>
            <?php foreach ($paragraphs as $paragraph): ?>
                <p class="<?= $isEmpty ? 'empty' : '' ?>"><?= e($paragraph) ?></p>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>

    <section class="footer-meta">
        <?php if (!empty($footer['issue_place']) || !empty($footer['issue_date'])): ?>
            <p><?= e(trim(implode(', ', array_filter([(string) ($footer['issue_place'] ?? ''), direct_purchase_dod_export_date($footer['issue_date'] ?? null)])))) ?></p>
        <?php endif; ?>

        <?php foreach (($footer['additional_fields'] ?? []) as $field): ?>
            <?php if (is_array($field) && trim((string) ($field['label'] ?? '')) !== ''): ?>
                <p><strong><?= e($field['label']) ?>:</strong> <?= e($field['value'] ?? '') ?></p>
            <?php endif; ?>
        <?php endforeach; ?>
    </section>

    <section class="signatures">
        <div class="signature">
            <strong><?= e($footer['requester_name'] ?: 'Requisitante') ?></strong>
            <span><?= e($footer['requester_role'] ?: 'Cargo do requisitante') ?></span>
        </div>
        <div class="signature">
            <strong><?= e($footer['authority_name'] ?: 'Autoridade competente') ?></strong>
            <span><?= e($footer['authority_role'] ?: 'Cargo da autoridade competente') ?></span>
        </div>
    </section>
</main>
</body>
</html>