<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/prompts.php';

function generate_ai_suggestion(string $itemName): array
{
    $apiKey = getenv('OPENAI_API_KEY');

    if (!$apiKey) {
        throw new RuntimeException('A variável de ambiente OPENAI_API_KEY não foi configurada.');
    }

    $prompt = procurement_item_ai_prompt($itemName);

    $payload = [
        'model' => OPENAI_MODEL,
        'input' => [
            [
                'role' => 'system',
                'content' => [
                    [
                        'type' => 'input_text',
                        'text' => $prompt['system'],
                    ],
                ],
            ],
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'input_text',
                        'text' => $prompt['user'],
                    ],
                ],
            ],
        ],
        'text' => [
            'format' => [
                'type' => 'json_schema',
                'name' => 'procurement_item_suggestion',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'category' => ['type' => 'string'],
                        'subcategory' => ['type' => 'string'],
                        'level' => [
                            'type' => 'string',
                            'enum' => ['A', 'B', 'C'],
                        ],
                        'specification' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => [
                                'tipo' => ['type' => 'string'],
                                'caracteristicas_minimas' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string'],
                                ],
                                'requisitos_de_compatibilidade' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string'],
                                ],
                                'requisitos_de_desempenho' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string'],
                                ],
                                'observacoes' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string'],
                                ],
                            ],
                            'required' => [
                                'tipo',
                                'caracteristicas_minimas',
                                'requisitos_de_compatibilidade',
                                'requisitos_de_desempenho',
                                'observacoes',
                            ],
                        ],
                        'justification' => ['type' => 'string'],
                        'warranty' => ['type' => 'string'],
                        'environmental_impacts' => ['type' => 'string'],
                        'warnings' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                    'required' => [
                        'category',
                        'subcategory',
                        'level',
                        'specification',
                        'justification',
                        'warranty',
                        'environmental_impacts',
                        'warnings',
                    ],
                ],
            ],
        ],
    ];

    $ch = curl_init('https://api.openai.com/v1/responses');

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 60,
    ]);

    $rawResponse = curl_exec($ch);

    if ($rawResponse === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Erro ao chamar a API de IA: ' . $error);
    }

    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $response = json_decode($rawResponse, true);

    if ($statusCode < 200 || $statusCode >= 300) {
        $message = $response['error']['message'] ?? 'Erro desconhecido na API de IA.';
        throw new RuntimeException($message);
    }

    $outputText = $response['output'][0]['content'][0]['text'] ?? null;

    if (!$outputText) {
        throw new RuntimeException('A API de IA não retornou texto estruturado.');
    }

    $suggestion = json_decode($outputText, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('A IA retornou um JSON inválido.');
    }

    return $suggestion;
}
