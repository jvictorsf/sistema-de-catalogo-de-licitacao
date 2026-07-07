<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$id = (int) ($_GET['id'] ?? $_POST['project_id'] ?? 0);
$project = find_project($id);

if (!$project) {
    http_response_code(404);
    exit('Projeto não encontrado.');
}

if (!project_is_direct_purchase($project)) {
    redirect('/project_show.php?id=' . (int) $project['id'] . '&project_error=' . urlencode('O DOD está disponível apenas para projetos de Compra Direta.'));
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

$demands = get_project_demands($id);
$consolidatedItems = get_project_consolidated_items($id);
$budgetEvaluation = get_direct_purchase_budget_evaluation($id);
$header = $dod['header'];
$footer = $dod['footer'];
$sections = direct_purchase_dod_apply_auto_content($project, $demands, $consolidatedItems, $dod, $budgetEvaluation);
$footerAdditionalFields = direct_purchase_dod_additional_fields_text($footer['additional_fields'] ?? []);
$aiPrompt = direct_purchase_dod_ai_prompt_text($project, $demands, $consolidatedItems, ['sections' => $sections]);
$signatureRows = direct_purchase_dod_normalize_signatures($footer['signatures'] ?? [], $footer);

require __DIR__ . '/../app/views/header.php';
?>

<div class="d-flex justify-content-between align-items-start gap-3 mb-4 flex-wrap">
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
        A tabela do DOD ainda não existe. Rode o <code>database/schema.sql</code> antes de salvar configurações.
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
                <h2 class="h5 mb-1">Cabeçalho institucional</h2>
                <p class="text-muted mb-0">Dados exibidos no topo da folha e na identificação do documento.</p>
            </div>
            <span class="badge text-bg-light text-dark border">DOD</span>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Nome da entidade</label>
                <input type="text" name="header[entity_name]" class="form-control" value="<?= e($header['entity_name'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Estado</label>
                <input type="text" name="header[state_name]" class="form-control" value="<?= e($header['state_name'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Local</label>
                <input type="text" name="header[place]" class="form-control" value="<?= e($header['place'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Secretaria</label>
                <input type="text" name="header[secretariat_name]" class="form-control" value="<?= e($header['secretariat_name'] ?? '') ?>" placeholder="SECRETARIA MUNICIPAL DE ...">
            </div>
            <div class="col-md-6">
                <label class="form-label">Departamento</label>
                <input type="text" name="header[department_name]" class="form-control" value="<?= e($header['department_name'] ?? '') ?>" placeholder="DEPARTAMENTO DE ...">
            </div>
            <div class="col-md-4">
                <label class="form-label">Logo esquerda</label>
                <input type="text" name="header[logo_left_path]" class="form-control" value="<?= e($header['logo_left_path'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Brasão central</label>
                <input type="text" name="header[logo_center_path]" class="form-control" value="<?= e($header['logo_center_path'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Logo direita</label>
                <input type="text" name="header[logo_right_path]" class="form-control" value="<?= e($header['logo_right_path'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Data</label>
                <input type="date" name="header[issue_date]" class="form-control" value="<?= e($header['issue_date'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="col-md-5">
                <label class="form-label">Título do documento</label>
                <input type="text" name="header[title]" class="form-control" value="<?= e($header['title'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Número do ofício/processo</label>
                <input type="text" name="header[document_number]" class="form-control" value="<?= e($header['document_number'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Destinatário</label>
                <input type="text" name="header[recipient]" class="form-control" value="<?= e($header['recipient'] ?? '') ?>">
            </div>
            <div class="col-md-8">
                <label class="form-label">Assunto</label>
                <input type="text" name="header[subject]" class="form-control" value="<?= e($header['subject'] ?? '') ?>">
            </div>
        </div>
    </div>

    <div class="card card-body shadow-sm">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
            <div>
                <h2 class="h5 mb-1">Tópicos do DOD</h2>
                <p class="text-muted mb-0">Habilite, reordene e edite os textos. Os tópicos automáticos são atualizados pelas demandas e orçamentos.</p>
            </div>
            <button type="button" class="btn btn-outline-primary" data-add-section>
                <i class="bi bi-plus-lg"></i>Novo tópico
            </button>
        </div>

        <div class="vstack gap-3" data-section-list data-next-index="<?= count($sections) ?>">
            <?php foreach ($sections as $sectionIndex => $section): ?>
                <?php $isAutoSection = !empty($section['auto_generated']); ?>
                <div class="border rounded p-3 <?= $isAutoSection ? 'bg-light-subtle' : '' ?>" data-section-row>
                    <input type="hidden" name="sections[<?= (int) $sectionIndex ?>][id]" value="<?= e($section['id'] ?? '') ?>">
                    <input type="hidden" name="sections[<?= (int) $sectionIndex ?>][auto_generated]" value="<?= $isAutoSection ? '1' : '0' ?>">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-2 col-lg-1">
                            <label class="form-label">Ordem</label>
                            <input type="number" min="1" name="sections[<?= (int) $sectionIndex ?>][sort_order]" class="form-control" value="<?= e((string) ($section['sort_order'] ?? ($sectionIndex + 1))) ?>">
                        </div>
                        <div class="col-md-2 col-lg-1">
                            <label class="form-label">Número</label>
                            <input type="text" name="sections[<?= (int) $sectionIndex ?>][number]" class="form-control" value="<?= e(rtrim((string) ($section['number'] ?? ''), '.')) ?>">
                        </div>
                        <div class="col-md-8 col-lg-6">
                            <label class="form-label">Título</label>
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
                                <label class="form-check-label">Obrigatório</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                <label class="form-label mb-0">Orientação/modelo</label>
                                <?php if ($isAutoSection): ?>
                                    <span class="badge text-bg-info">Automático</span>
                                <?php endif; ?>
                            </div>
                            <textarea name="sections[<?= (int) $sectionIndex ?>][guidance]" rows="2" class="form-control"><?= e($section['guidance'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Texto do tópico</label>
                            <?php if ($isAutoSection): ?>
                                <div class="form-text mb-2">Este conteúdo é gerado na exportação a partir das demandas, orçamento geral e impactos dos itens.</div>
                            <?php endif; ?>
                            <textarea
                                name="sections[<?= (int) $sectionIndex ?>][content]"
                                rows="6"
                                class="form-control <?= $isAutoSection ? 'bg-light' : '' ?>"
                                <?= $isAutoSection ? 'readonly' : 'data-rich-editor' ?>><?= e($section['content'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card card-body shadow-sm">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
            <div>
                <h2 class="h5 mb-1">Rodapé e assinaturas</h2>
                <p class="text-muted mb-0">Configure emissão, contato institucional e quantas assinaturas forem necessárias.</p>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Local da emissão</label>
                <input type="text" name="footer[issue_place]" class="form-control" value="<?= e($footer['issue_place'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Data da emissão</label>
                <input type="date" name="footer[issue_date]" class="form-control" value="<?= e($footer['issue_date'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Campos adicionais</label>
                <textarea name="footer_additional_fields" rows="1" class="form-control" placeholder="Um por linha: Rótulo: valor"><?= e($footerAdditionalFields) ?></textarea>
            </div>
            <div class="col-md-5">
                <label class="form-label">Rua/endereço</label>
                <input type="text" name="footer[address]" class="form-control" value="<?= e($footer['address'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">CEP</label>
                <input type="text" name="footer[postal_code]" class="form-control" value="<?= e($footer['postal_code'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Telefone</label>
                <input type="text" name="footer[phone]" class="form-control" value="<?= e($footer['phone'] ?? '') ?>">
            </div>
            <div class="col-md-1">
                <label class="form-label">Ramal</label>
                <input type="text" name="footer[branch]" class="form-control" value="<?= e($footer['branch'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">CNPJ/MF</label>
                <input type="text" name="footer[cnpj]" class="form-control" value="<?= e($footer['cnpj'] ?? '') ?>">
            </div>
            <div class="col-md-8">
                <label class="form-label">E-mail</label>
                <input type="email" name="footer[email]" class="form-control" value="<?= e($footer['email'] ?? '') ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <input type="hidden" name="footer[show_page_numbers]" value="0">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="footer[show_page_numbers]" value="1" <?= !empty($footer['show_page_numbers']) ? 'checked' : '' ?>>
                    <label class="form-check-label">Exibir numeração de páginas</label>
                </div>
            </div>
        </div>

        <hr>

        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
            <h3 class="h6 mb-0">Assinaturas</h3>
            <button type="button" class="btn btn-sm btn-outline-primary" data-add-signature>
                <i class="bi bi-plus-lg"></i>Adicionar assinatura
            </button>
        </div>

        <div class="vstack gap-2" data-signature-list data-next-signature-index="<?= count($signatureRows) ?>">
            <?php foreach ($signatureRows as $signatureIndex => $signature): ?>
                <div class="row g-2 align-items-end border rounded p-2" data-signature-row>
                    <div class="col-md-3">
                        <label class="form-label">Tipo</label>
                        <input type="text" name="footer[signatures][<?= (int) $signatureIndex ?>][label]" class="form-control" value="<?= e($signature['label'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Nome</label>
                        <input type="text" name="footer[signatures][<?= (int) $signatureIndex ?>][name]" class="form-control" value="<?= e($signature['name'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Cargo/função</label>
                        <input type="text" name="footer[signatures][<?= (int) $signatureIndex ?>][role]" class="form-control" value="<?= e($signature['role'] ?? '') ?>">
                    </div>
                    <div class="col-md-1 d-grid">
                        <button type="button" class="btn btn-outline-danger" title="Remover assinatura" data-remove-signature>
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card card-body shadow-sm">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
            <div>
                <h2 class="h5 mb-1">Prompt para IA</h2>
                <p class="text-muted mb-0">Use como apoio para gerar os textos dos tópicos habilitados.</p>
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
        const signatureList = document.querySelector('[data-signature-list]');
        const addSignatureButton = document.querySelector('[data-add-signature]');

        function insertAroundSelection(textarea, before, after, placeholder) {
            const start = textarea.selectionStart || 0;
            const end = textarea.selectionEnd || 0;
            const selected = textarea.value.slice(start, end) || placeholder;
            const next = before + selected + after;
            textarea.setRangeText(next, start, end, 'select');
            textarea.focus();
        }

        function insertList(textarea, ordered) {
            const start = textarea.selectionStart || 0;
            const end = textarea.selectionEnd || 0;
            const selected = textarea.value.slice(start, end).trim();
            const lines = selected !== '' ? selected.split(/\r?\n/) : ['Item da lista'];
            const formatted = lines.map(function(line, index) {
                return (ordered ? (index + 1) + '. ' : '- ') + line.replace(/^\s*([-*]|\d+[.)])\s+/, '');
            }).join('\n');
            textarea.setRangeText(formatted, start, end, 'select');
            textarea.focus();
        }

        function enhanceRichEditors(scope) {
            (scope || document).querySelectorAll('[data-rich-editor]:not([data-rich-ready])').forEach(function(textarea) {
                textarea.dataset.richReady = '1';
                const toolbar = document.createElement('div');
                toolbar.className = 'btn-toolbar gap-1 mb-2';
                toolbar.innerHTML = `
                    <button type="button" class="btn btn-sm btn-outline-secondary" title="Negrito" data-editor-action="bold"><i class="bi bi-type-bold"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" title="Itálico" data-editor-action="italic"><i class="bi bi-type-italic"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" title="Lista" data-editor-action="list"><i class="bi bi-list-ul"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" title="Lista numerada" data-editor-action="ordered"><i class="bi bi-list-ol"></i></button>`;
                textarea.parentNode.insertBefore(toolbar, textarea);

                toolbar.addEventListener('click', function(event) {
                    const button = event.target.closest('[data-editor-action]');

                    if (!button) {
                        return;
                    }

                    const action = button.dataset.editorAction;

                    if (action === 'bold') {
                        insertAroundSelection(textarea, '**', '**', 'texto em negrito');
                    } else if (action === 'italic') {
                        insertAroundSelection(textarea, '*', '*', 'texto em itálico');
                    } else if (action === 'list') {
                        insertList(textarea, false);
                    } else if (action === 'ordered') {
                        insertList(textarea, true);
                    }
                });
            });
        }

        function bindSignatureRemove(scope) {
            (scope || document).querySelectorAll('[data-remove-signature]').forEach(function(button) {
                if (button.dataset.bound) {
                    return;
                }

                button.dataset.bound = '1';
                button.addEventListener('click', function() {
                    const rows = signatureList ? signatureList.querySelectorAll('[data-signature-row]') : [];

                    if (rows.length <= 1) {
                        return;
                    }

                    button.closest('[data-signature-row]').remove();
                });
            });
        }

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
                    <input type="hidden" name="sections[${index}][auto_generated]" value="0">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-2 col-lg-1">
                            <label class="form-label">Ordem</label>
                            <input type="number" min="1" name="sections[${index}][sort_order]" class="form-control" value="${order}">
                        </div>
                        <div class="col-md-2 col-lg-1">
                            <label class="form-label">Número</label>
                            <input type="text" name="sections[${index}][number]" class="form-control" value="${order}">
                        </div>
                        <div class="col-md-8 col-lg-6">
                            <label class="form-label">Título</label>
                            <input type="text" name="sections[${index}][title]" class="form-control" value="Novo tópico">
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
                                <label class="form-check-label">Obrigatório</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Orientação/modelo</label>
                            <textarea name="sections[${index}][guidance]" rows="2" class="form-control"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Texto do tópico</label>
                            <textarea name="sections[${index}][content]" rows="6" class="form-control" data-rich-editor></textarea>
                        </div>
                    </div>`;
                list.appendChild(wrapper);
                enhanceRichEditors(wrapper);
            });
        }

        if (addSignatureButton && signatureList) {
            addSignatureButton.addEventListener('click', function() {
                const index = Number(signatureList.dataset.nextSignatureIndex || '0');
                signatureList.dataset.nextSignatureIndex = String(index + 1);
                const wrapper = document.createElement('div');
                wrapper.className = 'row g-2 align-items-end border rounded p-2';
                wrapper.dataset.signatureRow = '1';
                wrapper.innerHTML = `
                    <div class="col-md-3">
                        <label class="form-label">Tipo</label>
                        <input type="text" name="footer[signatures][${index}][label]" class="form-control" value="Assinatura ${index + 1}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Nome</label>
                        <input type="text" name="footer[signatures][${index}][name]" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Cargo/função</label>
                        <input type="text" name="footer[signatures][${index}][role]" class="form-control">
                    </div>
                    <div class="col-md-1 d-grid">
                        <button type="button" class="btn btn-outline-danger" title="Remover assinatura" data-remove-signature>
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>`;
                signatureList.appendChild(wrapper);
                bindSignatureRemove(wrapper);
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

        enhanceRichEditors(document);
        bindSignatureRemove(document);
    });
</script>

<?php require __DIR__ . '/../app/views/footer.php'; ?>