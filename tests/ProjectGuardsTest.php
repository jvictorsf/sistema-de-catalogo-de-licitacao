<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';

function project_test_assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Esperado: ' . var_export($expected, true) . ' Obtido: ' . var_export($actual, true));
    }
}

function project_test_assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

project_test_assert_true(project_is_locked(['status' => 'closed']), 'Projeto fechado deve bloquear alteracoes.');
project_test_assert_true(project_is_locked(['status' => 'canceled']), 'Projeto cancelado deve bloquear alteracoes.');
project_test_assert_true(!project_is_locked(['status' => 'rectification']), 'Projeto em retificacao deve permitir alteracoes.');
project_test_assert_same(['closed' => 'Fechado', 'rectification' => 'Retificacao', 'canceled' => 'Cancelado'], project_status_options_for_form(['status' => 'closed']), 'Projeto fechado deve permitir somente fechado, retificacao e cancelado.');
project_test_assert_same('Projeto fechado. Para corrigir ou alterar dados, mude o status do projeto para Retificacao.', project_locked_edit_message(['status' => 'closed']), 'Mensagem de projeto fechado deve orientar retificacao.');

echo "ProjectGuardsTest: OK\n";