<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$id = (int) ($_GET['id'] ?? $_POST['project_id'] ?? 0);
$project = find_project($id);

if (!$project) {
    http_response_code(404);
    exit('Projeto nao encontrado.');
}

if (!project_is_direct_purchase($project)) {
    redirect('/project_show.php?id=' . (int) $project['id'] . '&project_error=' . urlencode('O DOD esta disponivel apenas para projetos de Compra Direta.'));
}

$errors = [];
$projectLocked = project_is_locked($project);
$dod = get_direct_purchase_dod($id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($projectLocked) {
        $errors[] = project_locked_edit_message($project);
    } elseif (empty($dod['schema_available'])) {
        $errors[] = 'Atualize o schema do banco antes de salvar o DOD da Compra Direta.';
    } else {
        $footer = is_array($_POST['footer'] ?? null) ? $_POST['footer'] : [];
        $footer['additional_fields'] = direct_purchase_dod_additional_fields_from_text((string) ($_POST['footer_additional_fields'] ?? ''));

        try {
            save_direct_purchase_dod($id, [
                'header' => is_array($_POST['header'] ?? null) ? $_POST['header'] : [],
                'footer' => $footer,
                'sections' => is_array($_POST['sections'] ?? null) ? $_POST['sections'] : [],
            ]);

            redirect('/direct_purchase_dod.php?id=' . (int) $project['id'] . '&saved=1');
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
    }

    $dod = [
        'schema_available' => $dod['schema_available'] ?? false,
        'exists' => $dod['exists'] ?? false,
        'header' => direct_purchase_dod_normalize_header($_POST['header'] ?? [], $project),
        'footer' => direct_purchase_dod_normalize_footer($footer ?? []),
        'sections' => direct_purchase_dod_normalize_sections($_POST['sections'] ?? []),
    ];
}

$header = $dod['header'];
$footer = $dod['footer'];
$sections = $dod['sections'];
$footerAdditionalFields = direct_purchase_dod_additional_fields_text($footer['additional_fields'] ?? []);
$demands = get_project_demands($id);
$consolidatedItems = get_project_consolidated_items($id);
$aiPrompt = direct_purchase_dod_ai_prompt_text($project, $demands, $consolidatedItems, $dod);

require __DIR__ . '/../app/views/header.php';
?>

<div class="d-flex justify-content-between align-items-start gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
            <span class="badge <?= e(project_process_type_badge_class($project['process_type'] ?? null)) ?>">
                <?= e(project_process_type_label($project['process_type'] ?? null)) ?>
            </span>
            <span class="badge text-bg-light text-dark border">
                <?= e(direct_purchase_award_criterion_label($project['direct_purchase_award_criterion'] ?? null)) ?>
            </span>
        </div>
        <h1 class="h3 mb-1">DOD da Compra Direta</h1>
        <p class="text-muted mb-0"><?= e($project['name']) ?></p>
    </div>

    <div class="d-flex gap-2 flex-wrap justify-content-end">
        <a href="/direct_purchase_dod_export.php?id=<?= (int) $project['id'] ?>" target="_blank" class="btn btn-outline-danger">
            <i class="bi bi-filetype-pdf"></i>Visualizar/PDF
        </a>
        <a href="/direct_purchase_dod_export.php?id=<?= (int) $project['id'] ?>&format=word" class="btn btn-outline-primary">
            <i class="bi bi-file-earmark-word"></i>Word
        </a>
        <a href="/project_show.php?id=<?= (int) $project['id'] ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>Voltar
        </a>
    </div>
</div>

<?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success">DOD salvo com sucesso.</div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (empty($dod['schema_available'])): ?>
    <div class="alert alert-warning">
        A tabela do DOD ainda nao existe. Rode o <code>database/schema.sql</code> antes de salvar configuracoes.
    </div>
<?php endif; ?>

<?php if ($projectLocked): ?>
    <div class="alert alert-warning">
        <?= e(project_locked_edit_message($project)) ?>
    </div>
<?php endif; ?>

<form method="post" class="vstack gap-4" id="directPurchaseDodForm">
    <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">

    <div class="card card-body shadow-sm">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
            <div>
                <h2 class="h5 mb-1">Cabecalho</h2>
                <p class="text-muted mb-0">Dados principais do documento e identificacao do processo.</p>
            </div>
            <span class="badge text-bg-light text-dark border">DOD</span>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Nome da entidade</label>
                <input type="text" name="header[entity_name]" class="form-control" value="<?= e($header['entity_name'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Local</label>
                <input type="text" name="header[place]" class="form-control" value="<?= e($header['place'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Data</label>
                <input type="date" name="header[issue_date]" class="form-control" value="<?= e($header['issue_date'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Titulo do documento</label>
                <input type="text" name="header[title]" class="form-control" value="<?= e($header['title'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Numero do oficio/processo</label>
                <input type="text" name="header[document_number]" class="form-control" value="<?= e($header['document_number'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Destinatario</label>
                <input type="text" name="header[recipient]" class="form-control" value="<?= e($header['recipient'] ?? '') ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Assunto</label>
                <input type="text" name="header[subject]" class="form-control" value="<?= e($header['subject'] ?? '') ?>">
            </div>
        </div>
    </div>

    <div class="card card-body shadow-sm">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
            <div>
                <h2 class="h5 mb-1">Topicos do DOD</h2>
                <p class="text-muted mb-0">Habilite, reordene, renumere e edite os textos que serao exibidos.</p>
            </div>
            <button type="button" class="btn btn-outline-primary" data-add-section>
                <i class="bi bi-plus-lg"></i>Novo topico
            </button>
        </div>

        <div class="vstack gap-3" data-section-list data-next-index="<?= count($sections) ?>">
            <?php foreach ($sections as $sectionIndex => $section): ?>
                <div class="border rounded p-3" data-section-row>
                    <input type="hidden" name="sections[<?= (int) $sectionIndex ?>][id]" value="<?= e($section['id'] ?? '') ?>">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-2 col-lg-1">
                            <label class="form-label">Ordem</label>
                            <input type="number" min="1" name="sections[<?= (int) $sectionIndex ?>][sort_order]" class="form-control" value="<?= e((string) ($section['sort_order'] ?? ($sectionIndex + 1))) ?>">
                        </div>
                        <div class="col-md-2 col-lg-1">
                            <label class="form-label">Numero</label>
                            <input type="text" name="sections[<?= (int) $sectionIndex ?>][number]" class="form-control" value="<?= e($section['number'] ?? '') ?>">
                        </div>
                        <div class="col-md-8 col-lg-6">
                            <label class="form-label">Titulo</label>
                            <input type="text" name="sections[<?= (int) $sectionIndex ?>][title]" class="form-control" value="<?= e($section['title'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 col-lg-2">
                            <input type="hidden" name="sections[<?= (int) $sectionIndex ?>][enabled]" value="0">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="sections[<?= (int) $sectionIndex ?>][enabled]" value="1" <?= !empty($section['enabled']) ? 'checked' : '' ?>>
                                <label class="form-check-label">Exibir</label>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-2">
                            <input type="hidden" name="sections[<?= (int) $sectionIndex ?>][required]" value="0">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="sections[<?= (int) $sectionIndex ?>][required]" value="1" <?= !empty($section['required']) ? 'checked' : '' ?>>
                                <label class="form-check-label">Obrigatorio</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Orientacao/modelo</label>
                            <textarea name="sections[<?= (int) $sectionIndex ?>][guidance]" rows="2" class="form-control"><?= e($section['guidance'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Texto do topico</label>
                            <textarea name="sections[<?= (int) $sectionIndex ?>][content]" rows="5" class="form-control"><?= e($section['content'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card card-body shadow-sm">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
            <div>
                <h2 class="h5 mb-1">Rodape e assinaturas</h2>
                <p class="text-muted mb-0">Configure emissao, responsaveis e campos adicionais.</p>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Local da emissao</label>
                <input type="text" name="footer[issue_place]" class="form-control" value="<?= e($footer['issue_place'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Data da emissao</label>
                <input type="date" name="footer[issue_date]" class="form-control" value="<?= e($footer['issue_date'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Campos adicionais</label>
                <textarea name="footer_additional_fields" rows="1" class="form-control" placeholder="Um por linha: Rotulo: valor"><?= e($footerAdditionalFields) ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Assinatura do requisitante</label>
                <input type="text" name="footer[requester_name]" class="form-control" value="<?= e($footer['requester_name'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Cargo do requisitante</label>
                <input type="text" name="footer[requester_role]" class="form-control" value="<?= e($footer['requester_role'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Assinatura da autoridade competente</label>
                <input type="text" name="footer[authority_name]" class="form-control" value="<?= e($footer['authority_name'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Cargo da autoridade competente</label>
                <input type="text" name="footer[authority_role]" class="form-control" value="<?= e($footer['authority_role'] ?? '') ?>">
            </div>
        </div>
    </div>

    <div class="card card-body shadow-sm">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
            <div>
                <h2 class="h5 mb-1">Prompt para IA</h2>
                <p class="text-muted mb-0">Use como apoio para gerar os textos dos topicos habilitados.</p>
            </div>
            <button type="button" class="btn btn-outline-secondary" data-copy-prompt>
                <i class="bi bi-clipboard"></i>Copiar
            </button>
        </div>
        <textarea class="form-control font-monospace" rows="12" readonly data-ai-prompt><?= e($aiPrompt) ?></textarea>
    </div>

    <div class="d-flex justify-content-end gap-2 flex-wrap">
        <a href="/project_show.php?id=<?= (int) $project['id'] ?>" class="btn btn-outline-secondary">Cancelar</a>
        <button class="btn btn-primary" <?= $projectLocked || empty($dod['schema_available']) ? 'disabled' : '' ?>>
            <i class="bi bi-save"></i>Salvar DOD
        </button>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const list = document.querySelector('[data-section-list]');
        const addButton = document.querySelector('[data-add-section]');
        const prompt = document.querySelector('[data-ai-prompt]');
        const copyButton = document.querySelector('[data-copy-prompt]');

        if (addButton && list) {
            addButton.addEventListener('click', function() {
                const index = Number(list.dataset.nextIndex || '0');
                list.dataset.nextIndex = String(index + 1);
                const order = index + 1;

                const wrapper = document.createElement('div');
                wrapper.className = 'border rounded p-3';
                wrapper.dataset.sectionRow = '1';
                wrapper.innerHTML = `
                    <input type="hidden" name="sections[${index}][id]" value="topico_personalizado_${order}">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-2 col-lg-1">
                            <label class="form-label">Ordem</label>
                            <input type="number" min="1" name="sections[${index}][sort_order]" class="form-control" value="${order}">
                        </div>
                        <div class="col-md-2 col-lg-1">
                            <label class="form-label">Numero</label>
                            <input type="text" name="sections[${index}][number]" class="form-control" value="${order}">
                        </div>
                        <div class="col-md-8 col-lg-6">
                            <label class="form-label">Titulo</label>
                            <input type="text" name="sections[${index}][title]" class="form-control" value="Novo topico">
                        </div>
                        <div class="col-md-6 col-lg-2">
                            <input type="hidden" name="sections[${index}][enabled]" value="0">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="sections[${index}][enabled]" value="1" checked>
                                <label class="form-check-label">Exibir</label>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-2">
                            <input type="hidden" name="sections[${index}][required]" value="0">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="sections[${index}][required]" value="1">
                                <label class="form-check-label">Obrigatorio</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Orientacao/modelo</label>
                            <textarea name="sections[${index}][guidance]" rows="2" class="form-control"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Texto do topico</label>
                            <textarea name="sections[${index}][content]" rows="5" class="form-control"></textarea>
                        </div>
                    </div>`;
                list.appendChild(wrapper);
            });
        }

        if (copyButton && prompt && navigator.clipboard) {
            copyButton.addEventListener('click', async function() {
                await navigator.clipboard.writeText(prompt.value);
                copyButton.innerHTML = '<i class="bi bi-check2"></i>Copiado';
                setTimeout(function() {
                    copyButton.innerHTML = '<i class="bi bi-clipboard"></i>Copiar';
                }, 1600);
            });
        }
    });
</script>

<?php require __DIR__ . '/../app/views/footer.php'; ?>