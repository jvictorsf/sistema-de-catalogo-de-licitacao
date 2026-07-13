</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
<script src="/assets/app.js"></script>
<?php if (function_exists('rich_text_editor_assets_requested') && rich_text_editor_assets_requested()): ?>
    <script type='module' src='/assets/rich-text-editor.js'></script>
<?php endif; ?>
</body>
</html>
