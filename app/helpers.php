<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header("Location: {$path}");
    exit;
}

function old(array $source, string $key, mixed $default = ''): mixed
{
    return $source[$key] ?? $default;
}

function validate_json(string $json): bool
{
    json_decode($json, true);
    return json_last_error() === JSON_ERROR_NONE;
}

function pretty_json(mixed $value): string
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        return $value;
    }

    return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

function standard_item_observations(): array
{
    return [
        'A imagem do produto, quando utilizada no processo administrativo, será meramente ilustrativa, sem vinculação obrigatória de marca ou fabricante.',
        'Serão aceitos produtos equivalentes ou superiores desde que atendam integralmente às especificações mínimas exigidas.',
        'Não serão aceitos produtos remanufaturados, recondicionados, usados ou de procedência duvidosa.',
        'Todos os equipamentos deverão ser novos, de primeiro uso e entregues em embalagem original do fabricante.',
        'O fornecedor deverá assegurar assistência técnica e suporte durante o período de garantia.',
    ];
}

function default_item_specification(): array
{
    return [
        'marca_referencia' => '',
        'modelo_referencia' => '',
        'descricao_minima' => '',
        'caracteristicas_minimas' => [],
        'criterios_aceitacao' => [],
        'documentacao_exigida' => [],
        'certificados' => [],
        'observacoes' => standard_item_observations(),
    ];
}

function default_item_specification_json(): string
{
    return pretty_json(default_item_specification());
}

function item_specification_key_order(): array
{
    return [
        'marca_referencia',
        'modelo_referencia',
        'descricao_minima',
        'caracteristicas_minimas',
        'criterios_aceitacao',
        'documentacao_exigida',
        'certificados',
        'observacoes',
    ];
}

function normalize_item_specification_array(array $decoded): array
{
    $normalized = array_merge(default_item_specification(), $decoded);

    foreach ([
        'caracteristicas_minimas',
        'criterios_aceitacao',
        'documentacao_exigida',
        'certificados',
        'observacoes',
    ] as $key) {
        if (!isset($normalized[$key]) || !is_array($normalized[$key])) {
            $normalized[$key] = [];
        }
    }

    foreach (standard_item_observations() as $observation) {
        if (!in_array($observation, $normalized['observacoes'], true)) {
            $normalized['observacoes'][] = $observation;
        }
    }

    $ordered = [];
    $knownKeys = item_specification_key_order();

    foreach ($knownKeys as $key) {
        if ($key !== 'observacoes') {
            $ordered[$key] = $normalized[$key];
        }
    }

    foreach ($normalized as $key => $value) {
        if (!in_array($key, $knownKeys, true)) {
            $ordered[$key] = $value;
        }
    }

    $ordered['observacoes'] = $normalized['observacoes'];

    return $ordered;
}

function normalize_item_specification_json(string $json): string
{
    $decoded = json_decode($json, true);

    if (!is_array($decoded)) {
        $decoded = [];
    }

    return pretty_json(normalize_item_specification_array($decoded));
}

function format_item_specification_json(mixed $value): string
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $value;
        }

        return pretty_json(normalize_item_specification_array(is_array($decoded) ? $decoded : []));
    }

    return pretty_json(normalize_item_specification_array(is_array($value) ? $value : []));
}

function environmental_impacts_to_array(mixed $value): array
{
    if (is_array($value)) {
        if (array_is_list($value)) {
            return array_values(array_filter(array_map('strval', $value)));
        }

        return array_values(array_filter(array_map('strval', $value['itens'] ?? [])));
    }

    $value = trim((string) $value);

    if ($value === '') {
        return [];
    }

    $decoded = json_decode($value, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        if (array_is_list($decoded)) {
            return array_values(array_filter(array_map('strval', $decoded)));
        }

        return array_values(array_filter(array_map('strval', $decoded['itens'] ?? [])));
    }

    $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];
    $items = [];

    foreach ($lines as $line) {
        $line = trim((string) $line);

        if ($line !== '') {
            $items[] = ltrim($line, "- \t");
        }
    }

    return $items ?: [$value];
}

function normalize_environmental_impacts_json(mixed $value): string
{
    return json_encode(
        environmental_impacts_to_array($value),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    );
}

function environmental_impacts_as_text(?string $value): string
{
    return implode(PHP_EOL, environmental_impacts_to_array($value));
}

function render_environmental_impacts_list(?string $value): string
{
    $items = environmental_impacts_to_array($value);

    if (!$items) {
        return '<span class="text-muted">-</span>';
    }

    $html = '<ul class="mb-0 ps-3">';

    foreach ($items as $item) {
        $html .= '<li>' . e($item) . '</li>';
    }

    return $html . '</ul>';
}

function municipal_logo_public_path(): ?string
{
    $path = (string) (defined('MUNICIPAL_LOGO_PATH') ? MUNICIPAL_LOGO_PATH : '');

    if ($path === '') {
        return null;
    }

    $filePath = dirname(__DIR__) . '/public' . $path;

    return is_file($filePath) ? $path : null;
}

function render_municipal_logo(string $class = 'report-logo'): string
{
    $path = municipal_logo_public_path();

    if (!$path) {
        return '';
    }

    return '<img src="' . e($path) . '" class="' . e($class) . '" alt="Brasao do municipio" style="width:80px;height:auto;margin-bottom:8px;">';
}

function upload_item_image(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Erro ao enviar a imagem.');
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    $mime = mime_content_type($file['tmp_name']);

    if (!isset($allowedTypes[$mime])) {
        throw new RuntimeException('Formato de imagem inválido. Use JPG, PNG ou WEBP.');
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('A imagem deve ter no máximo 2 MB.');
    }

    $extension = $allowedTypes[$mime];
    $filename = 'item_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;

    $uploadDir = __DIR__ . '/../public/uploads/items';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $destination = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Não foi possível salvar a imagem.');
    }

    return '/uploads/items/' . $filename;
}

function upload_item_images(array $files): array
{
    if (
        empty($files['name']) ||
        !is_array($files['name'])
    ) {
        return [];
    }

    $uploadedPaths = [];

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    $uploadDir = __DIR__ . '/../public/uploads/items';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    foreach ($files['name'] as $index => $originalName) {
        if (($files['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($files['error'][$index] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Erro ao enviar uma das imagens.');
        }

        $tmpName = $files['tmp_name'][$index];

        $mime = mime_content_type($tmpName);

        if (!isset($allowedTypes[$mime])) {
            throw new RuntimeException('Formato inválido. Use JPG, PNG ou WEBP.');
        }

        if ($files['size'][$index] > 2 * 1024 * 1024) {
            throw new RuntimeException('Cada imagem deve ter no máximo 2 MB.');
        }

        $extension = $allowedTypes[$mime];

        $filename = 'item_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;

        $destination = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($tmpName, $destination)) {
            throw new RuntimeException('Não foi possível salvar uma das imagens.');
        }

        $uploadedPaths[] = '/uploads/items/' . $filename;
    }

    return $uploadedPaths;
}
