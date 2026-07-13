<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';
require_once __DIR__ . '/../app/demand_confirmations.php';
require_once __DIR__ . '/../app/auth.php';

function demand_signature_assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Esperado: ' . var_export($expected, true) . ' Obtido: ' . var_export($actual, true));
    }
}

function demand_signature_assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

demand_signature_assert_same('parallel', demand_signature_flow_mode('invalid'), 'Modo desconhecido deve usar paralelo.');
demand_signature_assert_same('pending', demand_confirmation_initial_request_status('parallel', 3), 'Fluxo paralelo deve liberar todos os assinantes.');
demand_signature_assert_same('pending', demand_confirmation_initial_request_status('sequential', 1), 'Primeira etapa sequencial deve ser liberada.');
demand_signature_assert_same('waiting', demand_confirmation_initial_request_status('sequential', 2), 'Etapas seguintes devem aguardar.');
demand_signature_assert_same('expired', demand_confirmation_effective_status([
    'status' => 'waiting',
    'expires_at' => '2000-01-01 00:00:00',
]), 'Etapa aguardando tambem deve expirar.');

$baseRequest = [
    'id' => 10,
    'flow_id' => 4,
    'signer_order' => 2,
    'demand_list_id' => 8,
    'statement_text' => 'Confirmo a demanda.',
];
$signer = ['name' => 'Maria', 'document' => '123', 'role' => 'Gestora', 'email' => '', 'phone' => ''];
$attachmentA = [['original_name' => 'doc-a.pdf', 'mime_type' => 'application/pdf', 'file_size' => 100, 'file_hash' => str_repeat('a', 64)]];
$attachmentB = [['original_name' => 'doc-b.pdf', 'mime_type' => 'application/pdf', 'file_size' => 100, 'file_hash' => str_repeat('b', 64)]];
$hashA = project_annex_hash(demand_confirmation_signature_hash_payload($baseRequest, $signer, ['items' => []], str_repeat('c', 64), $attachmentA, '2026-07-13 10:00:00'));
$hashB = project_annex_hash(demand_confirmation_signature_hash_payload($baseRequest, $signer, ['items' => []], str_repeat('c', 64), $attachmentB, '2026-07-13 10:00:00'));
demand_signature_assert_true($hashA !== $hashB, 'Alterar uma evidencia deve alterar o hash individual.');

$schema = file_get_contents(__DIR__ . '/../database/schema.sql') ?: '';
demand_signature_assert_true(str_contains($schema, 'CREATE TABLE IF NOT EXISTS demand_signature_flows'), 'Schema deve criar fluxos de assinatura.');
demand_signature_assert_true(str_contains($schema, 'CREATE TABLE IF NOT EXISTS demand_confirmation_attachments'), 'Schema deve criar anexos de comprovacao.');
demand_signature_assert_true(str_contains($schema, 'signer_order INTEGER NOT NULL DEFAULT 1'), 'Schema deve persistir a ordem do assinante.');
demand_signature_assert_same('confirmations.manage', auth_route_required_permission('signature_pending.php'), 'Painel deve exigir permissao de confirmacoes.');
demand_signature_assert_same('confirmations.manage', auth_route_required_permission('demand_confirmation_file.php'), 'Evidencias privadas devem exigir permissao de confirmacoes.');
$repository = file_get_contents(__DIR__ . '/../app/repository.php') ?: '';
demand_signature_assert_true(str_contains($repository, "'demand_confirmation' AS record_type"), 'Validador de hash deve consultar assinaturas individuais.');

$login = file_get_contents(__DIR__ . '/../public/login.php') ?: '';
demand_signature_assert_true(str_contains($login, 'MUNICIPAL_LOGO_PATH'), 'Login deve usar o brasao municipal configurado.');

echo "DemandSignatureFlowTest: OK\n";