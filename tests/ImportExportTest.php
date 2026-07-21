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
import_test_assert_true(($template['format_version'] ?? 0) === 2, 'Template estruturado de itens deve usar formato 2.');
import_test_assert_true(($template['data']['procurement_items'][0]['item_nature'] ?? null) === 'PERMANENTE', 'Template deve incluir natureza estruturada.');
import_test_assert_true(($template['data']['procurement_items'][0]['warranty_months'] ?? null) === 12, 'Template deve incluir garantia em meses.');
import_test_assert_true(array_key_exists('minimum_validity_months', $template['data']['procurement_items'][0]), 'Template deve incluir validade minima estruturada.');
import_test_assert_true(isset($template['data']['procurement_item_versions'][0]), 'Template de itens deve incluir versoes do item.');
import_test_assert_true(array_key_exists('change_summary', $template['data']['procurement_item_versions'][0]), 'Template deve incluir auditoria das versoes.');

$allTemplate = catalog_json_import_template('all');
import_test_assert_true(isset($allTemplate['data']['rich_text_editor_settings'][0]), 'Template geral deve incluir configuracoes do editor.');
import_test_assert_true(isset($allTemplate['data']['demand_approval_events'][0]), 'Template geral deve incluir historico de aprovacao das demandas.');
import_test_assert_true(
    array_key_exists('approval_status', $allTemplate['data']['demand_lists'][0]),
    'Template geral deve incluir a decisao da demanda.'
);
import_test_assert_true(
    array_key_exists('validation_status', $allTemplate['data']['demand_items'][0]),
    'Template geral deve incluir a validacao e o quantitativo aprovado dos itens.'
);
import_test_assert_true(
    is_array($allTemplate['data']['demand_approval_events'][0]['item_quantities'] ?? null),
    'Historico da aprovacao deve preservar o snapshot dos quantitativos em JSON.'
);
import_test_assert_true(
    ($allTemplate['data']['rich_text_editor_settings'][0]['default_text_align'] ?? null) === 'justify',
    'Template geral deve exportar padrao de alinhamento.'
);

$failed = false;
try {
    catalog_json_import_template('escopo_invalido');
} catch (InvalidArgumentException) {
    $failed = true;
}

import_test_assert_true($failed, 'Escopo invalido de importacao/exportacao deve gerar excecao.');

echo "ImportExportTest: OK\n";
