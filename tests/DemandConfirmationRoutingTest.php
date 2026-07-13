<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';

function demand_confirmation_routing_assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Esperado: ' . var_export($expected, true) . ' Obtido: ' . var_export($actual, true));
    }
}

function demand_confirmation_routing_assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$originalGet = $_GET;
$originalPost = $_POST;
$originalRequestUri = $_SERVER['REQUEST_URI'] ?? null;

$token = 'abc123';
$_GET = [
    'public_action' => 'demand_confirmation_sign',
    'token' => $token,
];
$_POST = [];
$_SERVER['REQUEST_URI'] = '/?public_action=demand_confirmation_sign&token=' . $token;

demand_confirmation_routing_assert_same(
    '/?public_action=demand_confirmation_sign&token=abc123',
    demand_confirmation_token_url($token),
    'Link de assinatura deve usar o gateway publico do index.'
);
demand_confirmation_routing_assert_true(auth_is_public_page('index.php'), 'Gateway de assinatura com token deve ser publico.');

$_GET = ['public_action' => 'demand_confirmation_sign'];
demand_confirmation_routing_assert_true(!auth_is_public_page('index.php'), 'Gateway sem token nao deve ser publico.');

$_GET = $originalGet;
$_POST = $originalPost;

if ($originalRequestUri === null) {
    unset($_SERVER['REQUEST_URI']);
} else {
    $_SERVER['REQUEST_URI'] = $originalRequestUri;
}

echo "DemandConfirmationRoutingTest: OK\n";