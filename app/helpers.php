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

function boolish(mixed $value, bool $default = false): bool
{
    if ($value === null) {
        return $default;
    }

    if (is_bool($value)) {
        return $value;
    }

    if (is_string($value)) {
        $normalized = strtolower(trim($value));

        if (in_array($normalized, ['1', 'true', 't', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'f', 'no', 'off', ''], true)) {
            return false;
        }
    }

    return (bool) $value;
}

function checked_attr(mixed $value, bool $default = false): string
{
    return boolish($value, $default) ? 'checked' : '';
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

function project_status_options(): array
{
    return [
        'draft' => 'Rascunho',
        'collecting' => 'Coletando demandas',
        'review' => 'Em revisao',
        'closed' => 'Fechado',
        'rectification' => 'Retificacao',
    ];
}

function project_status_options_for_form(?array $project = null): array
{
    $status = (string) ($project['status'] ?? 'draft');

    if (in_array($status, ['closed', 'rectification'], true)) {
        return [
            'closed' => 'Fechado',
            'rectification' => 'Retificacao',
        ];
    }

    return [
        'draft' => 'Rascunho',
        'collecting' => 'Coletando demandas',
        'review' => 'Em revisao',
        'closed' => 'Fechado',
    ];
}

function project_status_label(?string $status): string
{
    $labels = project_status_options();

    return $labels[$status ?? ''] ?? (string) $status;
}

function project_status_badge_class(?string $status): string
{
    $classes = [
        'draft' => 'text-bg-secondary',
        'collecting' => 'text-bg-info',
        'review' => 'text-bg-warning',
        'closed' => 'text-bg-success',
        'rectification' => 'text-bg-danger',
    ];

    return $classes[$status ?? ''] ?? 'text-bg-secondary';
}

function project_is_closed(mixed $project): bool
{
    $status = is_array($project) ? ($project['status'] ?? null) : $project;

    return $status === 'closed';
}

function project_is_rectification(mixed $project): bool
{
    $status = is_array($project) ? ($project['status'] ?? null) : $project;

    return $status === 'rectification';
}

function project_closed_edit_message(): string
{
    return 'Projeto fechado. Para corrigir ou alterar dados, mude o status do projeto para Retificacao.';
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

function licitation_annex_supplier_signature(array $suppliers): string
{
    $keys = [];

    foreach ($suppliers as $supplier) {
        $key = trim((string) ($supplier['key'] ?? ''));

        if ($key !== '') {
            $keys[] = $key;
        }
    }

    sort($keys, SORT_NATURAL);

    return $keys ? implode('|', $keys) : 'sem-cotacao';
}

function round_money_value(float $value): float
{
    return round($value, 2);
}

function price_outlier_flags(array $supplierPrices, float $threshold = 0.30): array
{
    $prices = array_values(array_filter(
        array_map(
            static fn (mixed $price): ?float => $price !== null ? (float) $price : null,
            $supplierPrices
        ),
        static fn (?float $price): bool => $price !== null
    ));

    if (count($prices) < 3) {
        return [];
    }

    sort($prices, SORT_NUMERIC);

    $middle = intdiv(count($prices), 2);
    $median = count($prices) % 2 === 0
        ? ($prices[$middle - 1] + $prices[$middle]) / 2
        : $prices[$middle];

    if ($median <= 0) {
        return [];
    }

    $flags = [];

    foreach ($supplierPrices as $supplierKey => $price) {
        if ($price === null) {
            continue;
        }

        $deviation = abs((float) $price - $median) / $median;

        if ($deviation > $threshold) {
            $flags[(string) $supplierKey] = 'Possível preço discrepante. Necessária análise e justificativa antes da exclusão.';
        }
    }

    return $flags;
}

function supplier_proposal_dates(array $supplier): array
{
    $dates = $supplier['proposal_dates'] ?? [];
    $dates = is_array($dates) ? $dates : [];
    $proposalDate = trim((string) ($supplier['proposal_date'] ?? ''));

    if ($proposalDate !== '' && !in_array($proposalDate, $dates, true)) {
        $dates[] = $proposalDate;
    }

    sort($dates);

    return $dates;
}

function build_licitation_annex_ii_groups_from_rows(array $rows): array
{
    $groups = [];
    $globalTotal = 0.0;

    foreach ($rows as $row) {
        $quantity = (float) ($row['annex_quantity'] ?? $row['quantity'] ?? 0);
        $suppliers = array_values(array_filter(
            $row['suppliers'] ?? [],
            static fn (array $supplier): bool => ($supplier['unit_price'] ?? null) !== null
        ));

        usort($suppliers, static function (array $left, array $right): int {
            return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''))
                ?: strcasecmp((string) ($left['key'] ?? ''), (string) ($right['key'] ?? ''));
        });

        $groupKey = licitation_annex_supplier_signature($suppliers);
        $itemKey = (int) ($row['procurement_item_id'] ?? 0) . '|' . $groupKey;

        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                'key' => $groupKey,
                'suppliers' => [],
                'items' => [],
                'min_sequence' => PHP_INT_MAX,
                'subtotal' => 0.0,
            ];
        }

        foreach ($suppliers as $supplier) {
            $supplierKey = (string) $supplier['key'];
            $supplier['proposal_dates'] = supplier_proposal_dates($supplier);

            if (!isset($groups[$groupKey]['suppliers'][$supplierKey])) {
                $groups[$groupKey]['suppliers'][$supplierKey] = $supplier;
                continue;
            }

            foreach ($supplier['proposal_dates'] as $proposalDate) {
                if (!in_array($proposalDate, $groups[$groupKey]['suppliers'][$supplierKey]['proposal_dates'], true)) {
                    $groups[$groupKey]['suppliers'][$supplierKey]['proposal_dates'][] = $proposalDate;
                }
            }

            sort($groups[$groupKey]['suppliers'][$supplierKey]['proposal_dates']);
        }

        if (!isset($groups[$groupKey]['items'][$itemKey])) {
            $groups[$groupKey]['items'][$itemKey] = array_merge($row, [
                'annex_quantity' => 0.0,
                'manual_price_values' => [],
                'supplier_prices' => [],
                'supplier_price_values' => [],
                'supplier_price_alerts' => [],
                'estimated_unit_price' => null,
                'estimated_total' => null,
                'demand_memory' => [],
            ]);
        }

        $groups[$groupKey]['items'][$itemKey]['annex_quantity'] += $quantity;

        if (!$suppliers && ($row['manual_unit_price'] ?? null) !== null) {
            $groups[$groupKey]['items'][$itemKey]['manual_price_values'][] = (float) $row['manual_unit_price'];
        }

        foreach ($row['demand_memory'] ?? [] as $memory) {
            $groups[$groupKey]['items'][$itemKey]['demand_memory'][] = $memory;
        }

        foreach ($suppliers as $supplier) {
            $supplierKey = (string) $supplier['key'];
            $unitPrice = (float) $supplier['unit_price'];

            $groups[$groupKey]['items'][$itemKey]['supplier_price_values'][$supplierKey][] = $unitPrice;
        }
    }

    $sequence = 1;

    foreach ($groups as $groupKey => $group) {
        $groups[$groupKey]['suppliers'] = array_values($group['suppliers']);
        $items = [];

        $rawItems = array_values($group['items']);

        usort($rawItems, static function (array $left, array $right): int {
            $leftSequence = (int) ($left['licitation_number'] ?? $left['sequence'] ?? 0);
            $rightSequence = (int) ($right['licitation_number'] ?? $right['sequence'] ?? 0);

            if ($leftSequence > 0 || $rightSequence > 0) {
                return ($leftSequence ?: PHP_INT_MAX) <=> ($rightSequence ?: PHP_INT_MAX);
            }

            return strcasecmp((string) ($left['item_name'] ?? ''), (string) ($right['item_name'] ?? ''));
        });

        foreach ($rawItems as $item) {
            $unitPrices = [];

            foreach ($groups[$groupKey]['suppliers'] as $supplier) {
                $supplierKey = (string) $supplier['key'];
                $priceValues = $item['supplier_price_values'][$supplierKey] ?? [];
                $unitPrice = $priceValues
                    ? round_money_value(array_sum($priceValues) / count($priceValues))
                    : null;

                $item['supplier_prices'][$supplierKey] = $unitPrice;

                if ($unitPrice !== null) {
                    $unitPrices[] = $unitPrice;
                }
            }

            $itemSequence = (int) ($item['licitation_number'] ?? $item['sequence'] ?? 0);
            $item['sequence'] = $itemSequence > 0 ? $itemSequence : $sequence++;
            $sequence = max($sequence, (int) $item['sequence'] + 1);
            $item['estimated_unit_price'] = $unitPrices
                ? round_money_value(array_sum($unitPrices) / count($unitPrices))
                : null;
            $item['supplier_price_alerts'] = price_outlier_flags($item['supplier_prices']);

            if ($item['estimated_unit_price'] === null && $item['manual_price_values']) {
                $item['estimated_unit_price'] = round_money_value(
                    array_sum($item['manual_price_values']) / count($item['manual_price_values'])
                );
            }

            $item['estimated_total'] = $item['estimated_unit_price'] !== null
                ? round_money_value($item['estimated_unit_price'] * (float) $item['annex_quantity'])
                : null;

            unset(
                $item['manual_price_values'],
                $item['supplier_price_values'],
                $item['suppliers']
            );

            if ($item['estimated_total'] !== null) {
                $groups[$groupKey]['subtotal'] += $item['estimated_total'];
                $globalTotal += $item['estimated_total'];
            }

            $groups[$groupKey]['min_sequence'] = min(
                $groups[$groupKey]['min_sequence'],
                (int) $item['sequence']
            );

            $items[] = $item;
        }

        $groups[$groupKey]['items'] = $items;
    }

    uasort($groups, static function (array $left, array $right): int {
        return ((int) ($left['min_sequence'] ?? PHP_INT_MAX))
            <=> ((int) ($right['min_sequence'] ?? PHP_INT_MAX));
    });

    foreach ($groups as $groupKey => $group) {
        unset($groups[$groupKey]['min_sequence']);
    }

    return [
        'groups' => array_values($groups),
        'global_total' => $globalTotal,
    ];
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
    $normalizedContentType = strtolower($contentType);

    header('Content-Type: ' . $contentType);
    header(
        'Content-Disposition: attachment; filename="'
        . addcslashes($fallback, '"\\')
        . '"; filename*=UTF-8\'\''
        . rawurlencode($filename)
    );
    header('Cache-Control: max-age=0');

    if (
        str_contains($normalizedContentType, 'application/msword')
        || str_contains($normalizedContentType, 'application/vnd.ms-excel')
    ) {
        echo "\xEF\xBB\xBF";
    }
}

function annex_issue_date_value(): string
{
    $value = trim((string) ($_GET['issue_date'] ?? ''));

    if ($value !== '') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if ($date && $date->format('Y-m-d') === $value) {
            return $value;
        }

        $date = DateTimeImmutable::createFromFormat('!d/m/Y', $value);

        if ($date && $date->format('d/m/Y') === $value) {
            return $date->format('Y-m-d');
        }
    }

    return date('Y-m-d');
}

function annex_issue_date_text(string $issueDate): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $issueDate);

    return $date ? $date->format('d/m/Y') : date('d/m/Y');
}

function annex_export_url(string $format, string $issueDate): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $query = $_GET;
    $query['format'] = $format;
    $query['issue_date'] = $issueDate;

    return $path . '?' . http_build_query($query);
}

function render_annex_print_actions(string $issueDate): string
{
    $hiddenFields = '';

    foreach ($_GET as $key => $value) {
        if (in_array((string) $key, ['format', 'issue_date'], true)) {
            continue;
        }

        if (is_array($value)) {
            continue;
        }

        $hiddenFields .= '<input type="hidden" name="' . e((string) $key) . '" value="' . e((string) $value) . '">';
    }

    return '
    <div class="print-actions">
        <form method="get" class="issue-date-form">
            ' . $hiddenFields . '
            <input type="hidden" name="format" value="pdf">
            <label>Data de emissao <input type="date" name="issue_date" value="' . e($issueDate) . '"></label>
            <button type="submit">Atualizar data</button>
        </form>
        <button type="button" onclick="window.print()">Imprimir / Salvar PDF</button>
        <button type="button" onclick="window.location.href=\'' . e(annex_export_url('word', $issueDate)) . '\'">Exportar Word</button>
        <button type="button" onclick="window.location.href=\'' . e(annex_export_url('excel', $issueDate)) . '\'">Exportar Excel</button>
    </div>';
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

function format_brazil_phone(?string $value): string
{
    $digits = only_digits($value);

    if (strlen($digits) === 11) {
        return sprintf(
            '(%s) %s-%s',
            substr($digits, 0, 2),
            substr($digits, 2, 5),
            substr($digits, 7, 4)
        );
    }

    if (strlen($digits) === 10) {
        return sprintf(
            '(%s) %s-%s',
            substr($digits, 0, 2),
            substr($digits, 2, 4),
            substr($digits, 6, 4)
        );
    }

    return trim((string) $value);
}

function format_brazil_postal_code(?string $value): string
{
    $digits = only_digits($value);

    if (strlen($digits) === 8) {
        return substr($digits, 0, 5) . '-' . substr($digits, 5, 3);
    }

    return trim((string) $value);
}

function supplier_address_text(array $supplier): string
{
    $address = trim((string) ($supplier['address'] ?? ''));
    $city = trim((string) ($supplier['city'] ?? ''));
    $state = trim((string) ($supplier['state'] ?? ''));
    $postalCode = format_brazil_postal_code($supplier['postal_code'] ?? '');
    $cityState = trim($city . ($state !== '' ? ' - ' . $state : ''));
    $parts = array_values(array_filter([
        $address,
        $cityState,
        $postalCode,
    ]));

    return $parts ? implode(', ', $parts) : '-';
}

function lookup_response_field(array $data, array $keys): string
{
    foreach ($keys as $key) {
        $value = trim((string) ($data[$key] ?? ''));

        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

function supplier_lookup_address_from_data(array $data): string
{
    $streetType = lookup_response_field($data, [
        'descricao_tipo_de_logradouro',
        'tipo_logradouro',
        'street_type',
    ]);
    $street = lookup_response_field($data, ['logradouro', 'street']);
    $streetLine = trim($streetType . ($streetType !== '' && $street !== '' ? ' ' : '') . $street);

    if ($streetLine === '') {
        $streetLine = lookup_response_field($data, ['address', 'endereco']);
    }

    return implode(', ', array_values(array_filter([
        $streetLine,
        lookup_response_field($data, ['numero', 'number']),
        lookup_response_field($data, ['complemento', 'complement']),
        lookup_response_field($data, ['bairro', 'neighborhood']),
    ])));
}

function supplier_lookup_city_from_data(array $data): string
{
    return lookup_response_field($data, ['municipio', 'city', 'localidade']);
}

function supplier_lookup_state_from_data(array $data): string
{
    return lookup_response_field($data, ['uf', 'state']);
}

function fetch_public_api_json(string $url, string $errorMessage, int $timeout = 8): array
{
    $response = null;
    $httpCode = 0;

    if (function_exists('curl_init')) {
        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => (defined('APP_NAME') ? APP_NAME : 'catalogo-licitacao') . '/1.0',
        ]);

        $response = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($response === false) {
            throw new RuntimeException($errorMessage . ': ' . $error);
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'header' => "User-Agent: catalogo-licitacao/1.0\r\n",
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
            $httpCode = (int) $matches[1];
        }

        if ($response === false) {
            throw new RuntimeException($errorMessage . '.');
        }
    }

    if ($httpCode >= 400) {
        throw new RuntimeException($httpCode === 404
            ? $errorMessage . ': registro nao encontrado.'
            : $errorMessage . ': erro HTTP ' . $httpCode . '.');
    }

    $data = json_decode((string) $response, true);

    if (!is_array($data)) {
        throw new RuntimeException($errorMessage . ': resposta invalida.');
    }

    return $data;
}

function lookup_cnpj_brasilapi(string $cnpj): array
{
    $digits = only_digits($cnpj);

    if (strlen($digits) !== 14) {
        throw new RuntimeException('Informe um CNPJ com 14 digitos.');
    }

    $data = fetch_public_api_json(
        'https://brasilapi.com.br/api/cnpj/v1/' . $digits,
        'Nao foi possivel consultar o CNPJ'
    );

    $phones = array_values(array_filter([
        $data['ddd_telefone_1'] ?? '',
        $data['ddd_telefone_2'] ?? '',
    ]));

    $tradeName = trim((string) ($data['nome_fantasia'] ?? ''));

    return [
        'name' => trim((string) ($data['razao_social'] ?? $tradeName)),
        'trade_name' => $tradeName,
        'document' => format_brazil_document((string) ($data['cnpj'] ?? $digits)),
        'email' => trim((string) ($data['email'] ?? '')),
        'phone' => format_brazil_phone($phones[0] ?? ''),
        'address' => supplier_lookup_address_from_data($data),
        'city' => supplier_lookup_city_from_data($data),
        'state' => supplier_lookup_state_from_data($data),
        'postal_code' => format_brazil_postal_code((string) ($data['cep'] ?? '')),
    ];
}

function lookup_cep_brasilapi(string $postalCode): array
{
    $digits = only_digits($postalCode);

    if (strlen($digits) !== 8) {
        throw new RuntimeException('Informe um CEP com 8 digitos.');
    }

    $data = fetch_public_api_json(
        'https://brasilapi.com.br/api/cep/v1/' . $digits,
        'Nao foi possivel consultar o CEP'
    );

    return [
        'address' => supplier_lookup_address_from_data($data),
        'city' => supplier_lookup_city_from_data($data),
        'state' => supplier_lookup_state_from_data($data),
        'postal_code' => format_brazil_postal_code((string) ($data['cep'] ?? $digits)),
    ];
}

function supplier_quote_storage_dir(): string
{
    return (defined('APP_STORAGE_PATH') ? APP_STORAGE_PATH : dirname(__DIR__) . '/storage')
        . '/uploads/supplier_quotes';
}

function ensure_writable_upload_dir(string $uploadDir, string $label): void
{
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        if (function_exists('app_log')) {
            app_log('error', 'Falha ao criar pasta de upload: ' . $label, [
                'path' => $uploadDir,
            ]);
        }

        throw new RuntimeException('Não foi possível preparar a pasta de uploads.');
    }

    if (!is_writable($uploadDir)) {
        if (function_exists('app_log')) {
            app_log('error', 'Pasta de upload sem permissao de escrita: ' . $label, [
                'path' => $uploadDir,
                'owner' => function_exists('posix_geteuid') ? posix_geteuid() : null,
            ]);
        }

        throw new RuntimeException('Não foi possível salvar o orçamento. A pasta de uploads não tem permissão de escrita.');
    }
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
    $uploadDir = supplier_quote_storage_dir();
    ensure_writable_upload_dir($uploadDir, 'orcamentos de fornecedores');

    $destination = $uploadDir . '/' . $filename;

    if (!@move_uploaded_file($file['tmp_name'], $destination)) {
        if (function_exists('app_log')) {
            app_log('error', 'Falha ao mover arquivo de orçamento enviado.', [
                'destination' => $destination,
                'tmp_name' => $file['tmp_name'] ?? null,
                'upload_dir_writable' => is_writable($uploadDir),
                'last_error' => error_get_last()['message'] ?? null,
            ]);
        }

        throw new RuntimeException('Não foi possível salvar o orçamento. Verifique as permissões da pasta de uploads.');
    }

    @chmod($destination, 0664);

    return '/supplier_quote_file.php?file=' . rawurlencode($filename);
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
