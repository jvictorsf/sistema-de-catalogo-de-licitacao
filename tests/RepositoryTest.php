<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/repository.php';

function repo_test_assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Esperado: ' . var_export($expected, true) . ' Obtido: ' . var_export($actual, true));
    }
}

function repo_test_assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

repo_test_assert_same('true', pg_bool('yes'), 'pg_bool deve aceitar yes como true.');
repo_test_assert_same('false', pg_bool('off'), 'pg_bool deve aceitar off como false.');
repo_test_assert_same('abcdef123456', normalize_document_hash_input('AB-CD EF 12:34:56'), 'Hash deve ser normalizado removendo caracteres nao hexadecimais.');
$payload = ['type' => 'annex_test', 'items' => [['id' => 1, 'total' => 10.0]]];
$hash = project_annex_hash($payload);
repo_test_assert_true(strlen($hash) === 64, 'Hash de anexo deve ser SHA-256 hexadecimal.');
repo_test_assert_same($hash, project_annex_hash($payload), 'Hash de anexo deve ser deterministico para o mesmo payload.');
repo_test_assert_same('{"a":1}', normalize_catalog_import_value(['a' => 1], true), 'Importacao JSON deve serializar arrays.');
repo_test_assert_same('true', normalize_catalog_import_value(true, false), 'Importacao nao JSON deve converter boolean true para PostgreSQL.');

echo "RepositoryTest: OK\n";