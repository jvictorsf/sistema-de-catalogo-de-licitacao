<?php

declare(strict_types=1);

function toolkit_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$basePath = dirname(__DIR__);
$config = (string) file_get_contents($basePath . '/config/app.php');
$footer = (string) file_get_contents($basePath . '/app/views/footer.php');
$envExample = (string) file_get_contents($basePath . '/.env.example');

foreach ([
    'TOOLKIT_ENABLED',
    'TOOLKIT_SCRIPT_URL',
    'TOOLKIT_TITLE',
    'TOOLKIT_SUBTITLE',
    'TOOLKIT_ACCENT',
    'TOOLKIT_ACCENT_DARK',
    'TOOLKIT_POSITION',
    'TOOLKIT_SHORTCUT',
] as $variable) {
    toolkit_test_assert(str_contains($config, $variable), $variable . ' deve ser carregada pela configuracao.');
    toolkit_test_assert(str_contains($envExample, $variable . '='), $variable . ' deve constar no .env.example.');
}

toolkit_test_assert(str_contains($footer, 'ToolkitFlutuante.createToolkit'), 'Layout deve inicializar o toolkit flutuante.');
toolkit_test_assert(str_contains($footer, 'DOMContentLoaded'), 'Toolkit deve aguardar o carregamento do documento.');
toolkit_test_assert(str_contains($footer, 'JSON_HEX_TAG'), 'Configuracao JavaScript deve usar serializacao segura.');
toolkit_test_assert(str_contains($footer, "['left', 'right']"), 'Posicao deve ser limitada aos lados suportados.');
toolkit_test_assert(str_contains($footer, "'/^#[0-9a-f]{6}$/i'"), 'Cores devem ser validadas no formato hexadecimal completo.');

echo "ToolkitIntegrationTest: OK\n";
