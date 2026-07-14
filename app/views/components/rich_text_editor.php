<?php

declare(strict_types=1);

function request_rich_text_editor_assets(): void
{
    $GLOBALS['rich_text_editor_assets_requested'] = true;
}

function rich_text_editor_assets_requested(): bool
{
    return !empty($GLOBALS['rich_text_editor_assets_requested']);
}

function rich_text_editor_resolved_settings(): array
{
    static $resolvedSettings = null;

    if (is_array($resolvedSettings)) {
        return $resolvedSettings;
    }

    $settings = function_exists('get_rich_text_editor_settings')
        ? get_rich_text_editor_settings()
        : (function_exists('rich_text_editor_default_settings') ? rich_text_editor_default_settings() : []);

    $resolvedSettings = function_exists('rich_text_editor_normalize_settings')
        ? rich_text_editor_normalize_settings($settings)
        : $settings;

    return $resolvedSettings;
}

function render_rich_text_editor(string $name, string $value = '', array $options = []): string
{
    request_rich_text_editor_assets();

    static $editorIndex = 0;
    $editorIndex++;

    $id = trim((string) ($options['id'] ?? 'rich-text-editor-' . $editorIndex));
    $rows = max(4, min(18, (int) ($options['rows'] ?? 7)));
    $maxLength = max(1, min(250000, (int) ($options['max_length'] ?? 50000)));
    $required = !empty($options['required']);
    $readonly = !empty($options['readonly']);
    $disabled = !empty($options['disabled']);
    $ariaLabel = trim((string) ($options['aria_label'] ?? 'Editor de texto rico'));
    $class = trim('form-control ' . (string) ($options['class'] ?? ''));

    if (function_exists('rich_text_contains_html') && rich_text_contains_html($value)) {
        $initialHtml = sanitize_rich_text_html($value);
    } elseif (function_exists('direct_purchase_dod_render_content') && trim($value) !== '') {
        $initialHtml = direct_purchase_dod_render_content($value);
    } else {
        $initialHtml = '<p></p>';
    }

    ob_start();
    ?>
    <div
        class="rich-text-editor-component"
        data-rich-text-component
        data-rich-required="<?= $required ? '1' : '0' ?>"
        data-rich-max-length="<?= $maxLength ?>">
        <textarea
            id="<?= e($id) ?>"
            name="<?= e($name) ?>"
            rows="<?= $rows ?>"
            class="<?= e($class) ?>"
            data-rich-editor
            data-rich-editor-label="<?= e($ariaLabel) ?>"
            data-rich-max-length="<?= $maxLength ?>"
            <?= $required ? 'required' : '' ?>
            <?= $readonly ? 'readonly' : '' ?>
            <?= $disabled ? 'disabled' : '' ?>><?= e($value) ?></textarea>
        <template data-rich-editor-initial><?= $initialHtml ?></template>
        <div class="invalid-feedback" data-rich-editor-error></div>
        <div class="rich-text-editor-meta">
            <span class="visually-hidden" data-rich-editor-status aria-live="polite">Carregando editor...</span>
            <span data-rich-editor-count aria-live="polite"></span>
        </div>
    </div>
    <?php

    return (string) ob_get_clean();
}
