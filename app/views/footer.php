</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
<script src="/assets/app.js"></script>
<?php if (function_exists('rich_text_editor_assets_requested') && rich_text_editor_assets_requested()): ?>
    <script type='module' src='/assets/rich-text-editor.js'></script>
<?php endif; ?>
<?php if (defined('TOOLKIT_ENABLED') && TOOLKIT_ENABLED): ?>
    <?php
    $toolkitScriptUrl = trim((string) TOOLKIT_SCRIPT_URL);
    $toolkitPosition = strtolower(trim((string) TOOLKIT_POSITION));
    $toolkitPosition = in_array($toolkitPosition, ['left', 'right'], true) ? $toolkitPosition : 'right';
    $toolkitAccent = trim((string) TOOLKIT_ACCENT);
    $toolkitAccentDark = trim((string) TOOLKIT_ACCENT_DARK);
    $toolkitAccent = preg_match('/^#[0-9a-f]{6}$/i', $toolkitAccent) ? $toolkitAccent : '#2f6f4f';
    $toolkitAccentDark = preg_match('/^#[0-9a-f]{6}$/i', $toolkitAccentDark) ? $toolkitAccentDark : '#245a3f';
    $toolkitConfig = json_encode([
        'title' => trim((string) TOOLKIT_TITLE),
        'subtitle' => trim((string) TOOLKIT_SUBTITLE),
        'accent' => $toolkitAccent,
        'accentDark' => $toolkitAccentDark,
        'position' => $toolkitPosition,
        'shortcut' => trim((string) TOOLKIT_SHORTCUT),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    ?>
    <?php if ($toolkitScriptUrl !== ''): ?>
        <script src="<?= e($toolkitScriptUrl) ?>"></script>
        <script>
            (function () {
                const toolkitConfig = <?= $toolkitConfig ?: '{}' ?>;

                function initializeToolkit() {
                    if (!window.ToolkitFlutuante || typeof window.ToolkitFlutuante.createToolkit !== 'function') {
                        return;
                    }

                    window.ToolkitFlutuante.createToolkit(toolkitConfig);
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initializeToolkit, {once: true});
                } else {
                    initializeToolkit();
                }
            })();
        </script>
    <?php endif; ?>
<?php endif; ?>
</body>
</html>
