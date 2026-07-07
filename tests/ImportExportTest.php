<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/repository.php';

function import_test_assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$template = catalog_json_import_template('items');
import_test_assert_true(($template['scope'] ?? null) === 'items', 'Template de itens deve preservar escopo.');
import_test_assert_true(isset($template['data']['procurement_items'][0]['specification']), 'Template de itens deve incluir especificacao tecnica.');
import_test_assert_true(isset($template['data']['procurement_item_versions'][0]), 'Template de itens deve incluir versoes do item.');

$failed = false;
try {
    catalog_json_import_template('escopo_invalido');
} catch (InvalidArgumentException) {
    $failed = true;
}

import_test_assert_true($failed, 'Escopo invalido de importacao/exportacao deve gerar excecao.');

echo "ImportExportTest: OK\n";