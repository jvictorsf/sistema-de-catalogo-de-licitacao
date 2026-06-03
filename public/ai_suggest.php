<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/AiSuggestionService.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Payload inválido.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$itemName = trim((string) ($payload['name'] ?? ''));

if (mb_strlen($itemName) < 3) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Informe um nome de item com pelo menos 3 caracteres.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $suggestion = generate_ai_suggestion($itemName);

    echo json_encode([
        'success' => true,
        'data' => $suggestion,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $exception) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
