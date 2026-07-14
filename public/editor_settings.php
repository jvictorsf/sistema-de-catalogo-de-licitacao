<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

auth_require_permission('system.manage_editor_settings');

$errors = [];
$settings = get_rich_text_editor_settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedSettings = is_array($_POST['settings'] ?? null) ? $_POST['settings'] : [];

    try {
        save_rich_text_editor_settings($postedSettings);
        redirect('/editor_settings.php?saved=1');
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
        $settings = array_merge($settings, $postedSettings);
    }
}

$previewSettings = rich_text_editor_normalize_settings($settings);
$fontOptions = rich_text_editor_font_options();
$alignmentOptions = rich_text_editor_alignment_options();
$fontCss = rich_text_editor_font_css($previewSettings);

require __DIR__ . '/../app/views/header.php';
?>

<style>
    .editor-settings-layout {
        display: grid;
        gap: 2rem;
        grid-template-columns: minmax(0, 1fr) minmax(280px, 420px);
    }

    .document-settings-preview {
        background: #fff;
        border: 1px solid #cbd5e1;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .1);
        color: #111827;
        font-family: <?= e($fontCss) ?>;
        font-size: <?= e(rich_text_editor_css_number($previewSettings['font_size_pt'])) ?>pt;
        line-height: <?= e(rich_text_editor_css_number($previewSettings['line_height'])) ?>;
        margin: 0 auto;
        max-width: 420px;
        min-height: 594px;
        padding: 42px 34px 36px;
        position: sticky;
        top: 1rem;
        width: 100%;
    }

    .document-settings-preview-header,
    .document-settings-preview-footer {
        color: #334155;
        font-size: 9pt;
        text-align: center;
    }

    .document-settings-preview-header {
        border-bottom: 3px solid #0070c0;
        margin-bottom: 26px;
        padding-bottom: 10px;
    }

    .document-settings-preview-body p {
        margin: 0 0 <?= e(rich_text_editor_css_number($previewSettings['paragraph_spacing_pt'])) ?>pt;
        text-align: <?= e($previewSettings['default_text_align']) ?>;
    }

    .document-settings-preview-footer {
        border-top: 3px solid #0070c0;
        bottom: 24px;
        left: 34px;
        padding-top: 8px;
        position: absolute;
        right: 34px;
    }

    @media (max-width: 991.98px) {
        .editor-settings-layout {
            grid-template-columns: 1fr;
        }

        .document-settings-preview {
            position: static;
        }
    }
</style>

<div class="page-header d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
    <div>
        <h1 class="h3 mb-1">Editor e documentos</h1>
        <p class="text-muted mb-0">Padrões globais do TipTap e da impressão institucional.</p>
    </div>
    <a href="/dashboard.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>Voltar
    </a>
</div>

<?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success">Configurações atualizadas.</div>
<?php endif; ?>

<?php if (empty($settings['schema_available'])): ?>
    <div class="alert alert-warning">
        Aplique <code>database/schema.sql</code> para habilitar a gravação destas configurações.
    </div>
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

<form method="post" class="editor-settings-layout">
    <div>
        <fieldset class="mb-4">
            <legend class="h5 mb-3">Texto</legend>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="font_family">Fonte padrão</label>
                    <select id="font_family" name="settings[font_family]" class="form-select" required>
                        <?php foreach ($fontOptions as $value => $option): ?>
                            <option value="<?= e($value) ?>" <?= ($settings['font_family'] ?? '') === $value ? 'selected' : '' ?>>
                                <?= e($option['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="font_size_pt">Tamanho</label>
                    <div class="input-group">
                        <input id="font_size_pt" type="number" name="settings[font_size_pt]" class="form-control" min="8" max="24" step="0.5" value="<?= e((string) ($settings['font_size_pt'] ?? 12)) ?>" required>
                        <span class="input-group-text">pt</span>
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="line_height">Entrelinhas</label>
                    <input id="line_height" type="number" name="settings[line_height]" class="form-control" min="1" max="2.5" step="0.05" value="<?= e((string) ($settings['line_height'] ?? 1.5)) ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="default_text_align">Alinhamento padrão</label>
                    <select id="default_text_align" name="settings[default_text_align]" class="form-select" required>
                        <?php foreach ($alignmentOptions as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= ($settings['default_text_align'] ?? '') === $value ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="paragraph_spacing_pt">Após parágrafo</label>
                    <div class="input-group">
                        <input id="paragraph_spacing_pt" type="number" name="settings[paragraph_spacing_pt]" class="form-control" min="0" max="24" step="0.5" value="<?= e((string) ($settings['paragraph_spacing_pt'] ?? 6)) ?>" required>
                        <span class="input-group-text">pt</span>
                    </div>
                </div>

                <div class="col-md-3 d-flex align-items-end">
                    <div>
                        <input type="hidden" name="settings[force_text_alignment]" value="0">
                        <div class="form-check form-switch mb-2">
                            <input id="force_text_alignment" class="form-check-input" type="checkbox" name="settings[force_text_alignment]" value="1" <?= boolish($settings['force_text_alignment'] ?? true, true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="force_text_alignment">Aplicar a todo o documento</label>
                        </div>
                    </div>
                </div>
            </div>
        </fieldset>

        <hr>

        <fieldset class="my-4">
            <legend class="h5 mb-3">Página impressa</legend>
            <div class="row g-3">
                <?php foreach ([
                    'page_margin_top_mm' => ['Superior', 50, 80],
                    'page_margin_right_mm' => ['Direita', 10, 40],
                    'page_margin_bottom_mm' => ['Inferior', 25, 60],
                    'page_margin_left_mm' => ['Esquerda', 10, 40],
                ] as $field => [$label, $minimum, $maximum]): ?>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="<?= e($field) ?>"><?= e($label) ?></label>
                        <div class="input-group">
                            <input id="<?= e($field) ?>" type="number" name="settings[<?= e($field) ?>]" class="form-control" min="<?= (int) $minimum ?>" max="<?= (int) $maximum ?>" step="0.5" value="<?= e((string) ($settings[$field] ?? '')) ?>" required>
                            <span class="input-group-text">mm</span>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="col-12">
                    <input type="hidden" name="settings[show_page_numbers]" value="0">
                    <div class="form-check form-switch">
                        <input id="show_page_numbers" class="form-check-input" type="checkbox" name="settings[show_page_numbers]" value="1" <?= boolish($settings['show_page_numbers'] ?? true, true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="show_page_numbers">Exibir Página X de Y</label>
                    </div>
                </div>
            </div>
        </fieldset>

        <?php if (!empty($settings['updated_at'])): ?>
            <p class="small text-muted">
                Última alteração: <?= e((string) $settings['updated_at']) ?>
                <?php if (!empty($settings['updated_by_user_name'])): ?>
                    por <?= e((string) $settings['updated_by_user_name']) ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <div class="d-flex justify-content-end">
            <button class="btn btn-primary" <?= empty($settings['schema_available']) ? 'disabled' : '' ?>>
                <i class="bi bi-save"></i>Salvar padrões
            </button>
        </div>
    </div>

    <aside aria-label="Prévia do documento">
        <div class="document-settings-preview" data-settings-preview>
            <div class="document-settings-preview-header">
                <strong>PREFEITURA MUNICIPAL</strong><br>
                DOCUMENTO OFICIAL
            </div>
            <div class="document-settings-preview-body" data-settings-preview-body>
                <h2 class="h6 text-center mb-3">1. OBJETO DA CONTRATAÇÃO</h2>
                <p>Este parágrafo demonstra a fonte, o tamanho, o alinhamento, o espaçamento entre linhas e a distância entre parágrafos.</p>
                <p>Os mesmos padrões serão aplicados ao editor TipTap e aos documentos DOD gerados pelo sistema.</p>
            </div>
            <div class="document-settings-preview-footer" data-settings-preview-footer>
                Rua da unidade - CEP 00000-000<br>
                <span data-settings-preview-page>Página 1 de 1</span>
            </div>
        </div>
    </aside>
</form>

<script>
    (() => {
        const form = document.querySelector('.editor-settings-layout');
        const preview = document.querySelector('[data-settings-preview]');
        const body = document.querySelector('[data-settings-preview-body]');
        const footer = document.querySelector('[data-settings-preview-footer]');
        const pageNumber = document.querySelector('[data-settings-preview-page]');
        const fontOptions = <?= json_encode(array_map(static fn (array $option): string => $option['css'], $fontOptions), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

        if (!form || !preview || !body || !footer || !pageNumber) {
            return;
        }

        const numericValue = (id, fallback) => {
            const value = Number(document.getElementById(id)?.value);
            return Number.isFinite(value) ? value : fallback;
        };

        const updatePreview = () => {
            const fontFamily = document.getElementById('font_family')?.value || 'arial';
            const alignment = document.getElementById('default_text_align')?.value || 'justify';
            const top = numericValue('page_margin_top_mm', 50);
            const right = numericValue('page_margin_right_mm', 18);
            const bottom = numericValue('page_margin_bottom_mm', 32);
            const left = numericValue('page_margin_left_mm', 18);

            preview.style.fontFamily = fontOptions[fontFamily] || fontOptions.arial;
            preview.style.fontSize = `${numericValue('font_size_pt', 12)}pt`;
            preview.style.lineHeight = numericValue('line_height', 1.5);
            preview.style.padding = `${Math.max(32, top * .82)}px ${Math.max(24, right * 1.25)}px ${Math.max(40, bottom * 1.25)}px ${Math.max(24, left * 1.25)}px`;
            body.querySelectorAll('p').forEach((paragraph) => {
                paragraph.style.marginBottom = `${numericValue('paragraph_spacing_pt', 6)}pt`;
                paragraph.style.textAlign = alignment;
            });
            footer.style.left = `${Math.max(24, left * 1.25)}px`;
            footer.style.right = `${Math.max(24, right * 1.25)}px`;
            pageNumber.hidden = !document.getElementById('show_page_numbers')?.checked;
        };

        form.addEventListener('input', updatePreview);
        form.addEventListener('change', updatePreview);
        updatePreview();
    })();
</script>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
