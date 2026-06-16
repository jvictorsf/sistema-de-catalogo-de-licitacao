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

function item_specification_array_from_value(mixed $value): array
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return normalize_item_specification_array([]);
        }

        return normalize_item_specification_array(is_array($decoded) ? $decoded : []);
    }

    return normalize_item_specification_array(is_array($value) ? $value : []);
}

function item_specification_label(string $key): string
{
    $labels = [
        'marca_referencia' => 'Marca de referencia',
        'modelo_referencia' => 'Modelo de referencia',
        'descricao_minima' => 'Descricao minima',
        'caracteristicas_minimas' => 'Caracteristicas minimas',
        'criterios_aceitacao' => 'Criterios de aceitacao',
        'documentacao_exigida' => 'Documentacao exigida',
        'certificados' => 'Certificados',
        'observacoes' => 'Observacoes',
    ];

    if (isset($labels[$key])) {
        return $labels[$key];
    }

    return ucwords(str_replace('_', ' ', $key));
}

function item_specification_value_is_empty(mixed $value): bool
{
    if ($value === null) {
        return true;
    }

    if (is_string($value)) {
        return trim($value) === '';
    }

    if (is_array($value)) {
        foreach ($value as $item) {
            if (!item_specification_value_is_empty($item)) {
                return false;
            }
        }

        return true;
    }

    return false;
}

function render_item_specification_value(mixed $value): string
{
    if (item_specification_value_is_empty($value)) {
        return '<span class="text-muted">Nao informado.</span>';
    }

    if (is_array($value)) {
        if (array_is_list($value)) {
            $html = '<ul class="spec-list">';

            foreach ($value as $item) {
                if (item_specification_value_is_empty($item)) {
                    continue;
                }

                $html .= '<li>' . render_item_specification_value($item) . '</li>';
            }

            return $html . '</ul>';
        }

        $html = '<table class="spec-table">';

        foreach ($value as $key => $item) {
            if (item_specification_value_is_empty($item)) {
                continue;
            }

            $html .= '<tr>';
            $html .= '<th>' . e(item_specification_label((string) $key)) . '</th>';
            $html .= '<td>' . render_item_specification_value($item) . '</td>';
            $html .= '</tr>';
        }

        return $html . '</table>';
    }

    return nl2br(e((string) $value));
}

function render_item_specification_section(string $title, mixed $value): string
{
    if (item_specification_value_is_empty($value)) {
        return '';
    }

    return '<div class="spec-section"><h4>' . e($title) . '</h4>' . render_item_specification_value($value) . '</div>';
}

function render_item_specification_html(mixed $value): string
{
    $specification = item_specification_array_from_value($value);
    $knownKeys = item_specification_key_order();
    $html = '<div class="spec-readable">';

    if (
        !item_specification_value_is_empty($specification['marca_referencia'] ?? null) ||
        !item_specification_value_is_empty($specification['modelo_referencia'] ?? null)
    ) {
        $html .= '<table class="spec-table spec-reference">';

        foreach (['marca_referencia', 'modelo_referencia'] as $key) {
            if (item_specification_value_is_empty($specification[$key] ?? null)) {
                continue;
            }

            $html .= '<tr>';
            $html .= '<th>' . e(item_specification_label($key)) . '</th>';
            $html .= '<td>' . render_item_specification_value($specification[$key]) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';
    }

    foreach ([
        'descricao_minima',
        'caracteristicas_minimas',
        'criterios_aceitacao',
        'documentacao_exigida',
        'certificados',
    ] as $key) {
        $html .= render_item_specification_section(
            item_specification_label($key),
            $specification[$key] ?? null
        );
    }

    foreach ($specification as $key => $item) {
        if (in_array($key, $knownKeys, true)) {
            continue;
        }

        $html .= render_item_specification_section(item_specification_label((string) $key), $item);
    }

    $html .= render_item_specification_section(
        item_specification_label('observacoes'),
        $specification['observacoes'] ?? []
    );

    return $html . '</div>';
}

function item_status_options(): array
{
    return [
        'draft' => 'Rascunho',
        'review' => 'Em revisao',
        'standardized' => 'Padronizado',
        'deprecated' => 'Descontinuado',
        'blocked' => 'Bloqueado',
    ];
}

function item_status_label(?string $status): string
{
    $labels = item_status_options();

    return $labels[$status ?? ''] ?? (string) $status;
}

function item_status_badge_class(?string $status): string
{
    $classes = [
        'draft' => 'text-bg-secondary',
        'review' => 'text-bg-warning',
        'standardized' => 'text-bg-success',
        'deprecated' => 'text-bg-dark',
        'blocked' => 'text-bg-danger',
    ];

    return $classes[$status ?? ''] ?? 'text-bg-secondary';
}

function format_decimal_quantity(mixed $value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    $formatted = number_format((float) $value, 2, ',', '.');

    return rtrim(rtrim($formatted, '0'), ',');
}

function format_package_content(array $source): string
{
    $quantity = $source['package_content_quantity'] ?? null;

    if ($quantity === null || $quantity === '') {
        return '-';
    }

    $unit = $source['package_content_unit_type_abbreviation']
        ?? $source['package_content_unit_type_name']
        ?? '';

    return trim(format_decimal_quantity($quantity) . ($unit ? ' ' . $unit : ''));
}

function render_package_content(array $source): string
{
    $content = format_package_content($source);

    if ($content === '-') {
        return '<span class="text-muted">-</span>';
    }

    return e($content);
}

function licitation_annex_unit_text(array $item): string
{
    $unit = trim((string) (
        ($item['unit_type_abbreviation'] ?? '')
        ?: ($item['unit_type_name'] ?? '')
    ));
    $content = format_package_content($item);

    if ($unit === '') {
        $unit = '-';
    }

    if ($content !== '-') {
        return $unit . ' - Conteudo: ' . $content;
    }

    return $unit;
}

function licitation_annex_specification_text(array $item, string $separator = "\n"): string
{
    $specification = item_specification_array_from_value($item['specification'] ?? []);
    $parts = [];
    $description = supplier_quote_request_value_text($specification['descricao_minima'] ?? null, $separator);

    if ($description !== '-') {
        $parts[] = $description;
    }

    $characteristics = $specification['caracteristicas_minimas'] ?? [];

    if (is_array($characteristics)) {
        foreach ($characteristics as $characteristic) {
            $text = supplier_quote_request_value_text($characteristic, $separator);

            if ($text !== '-') {
                $parts[] = $text;
            }
        }
    } else {
        $text = supplier_quote_request_value_text($characteristics, $separator);

        if ($text !== '-') {
            $parts[] = $text;
        }
    }

    $parts = array_values(array_unique($parts));

    return $parts ? implode($separator, $parts) : '-';
}

function licitation_annex_demand_memory_text(array $memory, string $separator = "\n"): string
{
    $parts = [];

    foreach ($memory as $row) {
        $labelParts = array_filter([
            trim((string) ($row['secretariat_name'] ?? '')),
            trim((string) ($row['demand_name'] ?? '')),
        ]);
        $label = $labelParts ? implode(' - ', $labelParts) : 'Demanda';
        $quantity = format_decimal_quantity($row['quantity'] ?? 0);

        $parts[] = $label . ': ' . ($quantity !== '' ? $quantity : '0');
    }

    return $parts ? implode($separator, $parts) : '-';
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

function ascii_filename_fallback(string $filename): string
{
    $fallback = strtr($filename, [
        'á' => 'a',
        'à' => 'a',
        'ã' => 'a',
        'â' => 'a',
        'ä' => 'a',
        'Á' => 'A',
        'À' => 'A',
        'Ã' => 'A',
        'Â' => 'A',
        'Ä' => 'A',
        'é' => 'e',
        'ê' => 'e',
        'É' => 'E',
        'Ê' => 'E',
        'í' => 'i',
        'Í' => 'I',
        'ó' => 'o',
        'õ' => 'o',
        'ô' => 'o',
        'Ó' => 'O',
        'Õ' => 'O',
        'Ô' => 'O',
        'ú' => 'u',
        'ü' => 'u',
        'Ú' => 'U',
        'Ü' => 'U',
        'ç' => 'c',
        'Ç' => 'C',
    ]);
    $fallback = preg_replace('/[^A-Za-z0-9 ._-]+/', '', $fallback) ?? '';
    $fallback = preg_replace('/\s+/', ' ', $fallback) ?? '';
    $fallback = trim($fallback);

    return $fallback !== '' ? $fallback : 'documento';
}

function send_download_headers(string $contentType, string $filename): void
{
    $fallback = ascii_filename_fallback($filename);

    header('Content-Type: ' . $contentType);
    header(
        'Content-Disposition: attachment; filename="'
        . addcslashes($fallback, '"\\')
        . '"; filename*=UTF-8\'\''
        . rawurlencode($filename)
    );
    header('Cache-Control: max-age=0');
}

function supplier_quote_request_value_text(mixed $value, string $separator = '; '): string
{
    if ($value === null) {
        return '-';
    }

    if (is_array($value)) {
        $parts = [];

        foreach ($value as $item) {
            $text = supplier_quote_request_value_text($item, $separator);

            if ($text !== '-') {
                $parts[] = $text;
            }
        }

        return $parts ? implode($separator, $parts) : '-';
    }

    $value = trim((string) $value);

    return $value !== '' ? $value : '-';
}

function supplier_quote_request_characteristics_text(array $specification, string $separator = '; '): string
{
    $characteristics = $specification['caracteristicas_minimas'] ?? [];

    if (is_array($characteristics) && $characteristics) {
        return supplier_quote_request_value_text($characteristics, $separator);
    }

    return supplier_quote_request_value_text($specification['descricao_minima'] ?? null, $separator);
}

function supplier_quote_request_characteristics_html(array $specification): string
{
    $characteristics = $specification['caracteristicas_minimas'] ?? [];

    if (is_array($characteristics) && $characteristics) {
        $html = '<ul class="characteristics">';

        foreach ($characteristics as $item) {
            $text = supplier_quote_request_value_text($item);

            if ($text !== '-') {
                $html .= '<li>' . e($text) . '</li>';
            }
        }

        return $html . '</ul>';
    }

    $description = supplier_quote_request_value_text($specification['descricao_minima'] ?? null);

    if ($description !== '-') {
        return nl2br(e($description));
    }

    return '<span class="muted">-</span>';
}

function supplier_quote_request_group_id(array $item): int
{
    return (int) ($item['category_id'] ?? 0);
}

function supplier_quote_request_group_name(array $item): string
{
    $name = trim((string) ($item['category_name'] ?? ''));

    return $name !== '' ? $name : 'Sem grupo';
}

function supplier_quote_request_groups_from_items(array $items): array
{
    $groups = [];

    foreach ($items as $item) {
        $groupId = supplier_quote_request_group_id($item);
        $groupKey = (string) $groupId;

        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                'id' => $groupId,
                'name' => supplier_quote_request_group_name($item),
                'items_count' => 0,
                'total_quantity' => 0.0,
            ];
        }

        $groups[$groupKey]['items_count']++;
        $groups[$groupKey]['total_quantity'] += (float) (
            $item['total_approved_quantity']
            ?? $item['total_quantity']
            ?? 0
        );
    }

    uasort($groups, static function (array $left, array $right): int {
        if ((int) $left['id'] === 0) {
            return 1;
        }

        if ((int) $right['id'] === 0) {
            return -1;
        }

        return strcasecmp((string) $left['name'], (string) $right['name']);
    });

    return $groups;
}

function supplier_quote_request_items_by_group(array $items): array
{
    $groups = supplier_quote_request_groups_from_items($items);

    foreach ($groups as $groupKey => $group) {
        $groups[$groupKey]['items'] = [];
    }

    foreach ($items as $item) {
        $groupKey = (string) supplier_quote_request_group_id($item);

        if (!isset($groups[$groupKey])) {
            continue;
        }

        $groups[$groupKey]['items'][] = $item;
    }

    return $groups;
}

function supplier_quote_request_filter_items_by_group(array $items, int $groupId): array
{
    return array_values(array_filter(
        $items,
        static fn (array $item): bool => supplier_quote_request_group_id($item) === $groupId
    ));
}

function only_digits(?string $value): string
{
    return preg_replace('/\D+/', '', (string) $value) ?? '';
}

function format_brazil_document(?string $value): string
{
    $digits = only_digits($value);

    if (strlen($digits) === 14) {
        return sprintf(
            '%s.%s.%s/%s-%s',
            substr($digits, 0, 2),
            substr($digits, 2, 3),
            substr($digits, 5, 3),
            substr($digits, 8, 4),
            substr($digits, 12, 2)
        );
    }

    if (strlen($digits) === 11) {
        return sprintf(
            '%s.%s.%s-%s',
            substr($digits, 0, 3),
            substr($digits, 3, 3),
            substr($digits, 6, 3),
            substr($digits, 9, 2)
        );
    }

    return trim((string) $value);
}

function lookup_cnpj_brasilapi(string $cnpj): array
{
    $digits = only_digits($cnpj);

    if (strlen($digits) !== 14) {
        throw new RuntimeException('Informe um CNPJ com 14 digitos.');
    }

    $url = 'https://brasilapi.com.br/api/cnpj/v1/' . $digits;
    $response = null;
    $httpCode = 0;

    if (function_exists('curl_init')) {
        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_USERAGENT => (defined('APP_NAME') ? APP_NAME : 'catalogo-licitacao') . '/1.0',
        ]);

        $response = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($response === false) {
            throw new RuntimeException('Nao foi possivel consultar o CNPJ: ' . $error);
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'timeout' => 8,
                'header' => "User-Agent: catalogo-licitacao/1.0\r\n",
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
            $httpCode = (int) $matches[1];
        }

        if ($response === false) {
            throw new RuntimeException('Nao foi possivel consultar o CNPJ.');
        }
    }

    if ($httpCode >= 400) {
        throw new RuntimeException($httpCode === 404
            ? 'CNPJ nao encontrado na BrasilAPI.'
            : 'A consulta de CNPJ retornou erro HTTP ' . $httpCode . '.');
    }

    $data = json_decode((string) $response, true);

    if (!is_array($data)) {
        throw new RuntimeException('A consulta de CNPJ retornou uma resposta invalida.');
    }

    $phones = array_values(array_filter([
        $data['ddd_telefone_1'] ?? '',
        $data['ddd_telefone_2'] ?? '',
    ]));

    $address = implode(', ', array_values(array_filter([
        $data['logradouro'] ?? '',
        $data['numero'] ?? '',
        $data['complemento'] ?? '',
        $data['bairro'] ?? '',
        $data['municipio'] ?? '',
        $data['uf'] ?? '',
        $data['cep'] ?? '',
    ])));

    $tradeName = trim((string) ($data['nome_fantasia'] ?? ''));

    return [
        'name' => trim((string) ($data['razao_social'] ?? $tradeName)),
        'document' => format_brazil_document((string) ($data['cnpj'] ?? $digits)),
        'email' => trim((string) ($data['email'] ?? '')),
        'phone' => implode(' / ', $phones),
        'address' => $address,
        'notes' => $tradeName !== '' ? 'Nome fantasia: ' . $tradeName : '',
    ];
}

function upload_supplier_quote_file(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Erro ao enviar o orçamento.');
    }

    $allowedTypes = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    $mime = mime_content_type($file['tmp_name']);

    if (!isset($allowedTypes[$mime])) {
        throw new RuntimeException('Formato inválido. Use PDF, DOC, DOCX, JPG, PNG ou WEBP.');
    }

    if ($file['size'] > 10 * 1024 * 1024) {
        throw new RuntimeException('O orçamento deve ter no máximo 10 MB.');
    }

    $extension = $allowedTypes[$mime];
    $filename = 'orcamento_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
    $uploadDir = __DIR__ . '/../public/uploads/supplier_quotes';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $destination = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Não foi possível salvar o orçamento.');
    }

    return '/uploads/supplier_quotes/' . $filename;
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
