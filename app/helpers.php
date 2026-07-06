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

function item_specification_kind(string $kind): string
{
    return $kind === 'service' ? 'service' : 'product';
}

function standard_product_item_observations(): array
{
    return [
        'A imagem do produto, quando utilizada no processo administrativo, será meramente ilustrativa, sem vinculação obrigatória de marca ou fabricante.',
        'Serão aceitos produtos equivalentes ou superiores desde que atendam integralmente às especificações mínimas exigidas.',
        'Não serão aceitos produtos remanufaturados, recondicionados, usados ou de procedência duvidosa.',
        'Todos os equipamentos deverão ser novos, de primeiro uso e entregues em embalagem original do fabricante.',
        'O fornecedor deverá assegurar assistência técnica e suporte durante o período de garantia.',
    ];
}

function standard_service_item_observations(): array
{
    return [
        'O serviço deverá ser executado conforme as condições, prazos e níveis mínimos de qualidade definidos no termo de referência.',
        'Serão aceitas soluções tecnicamente equivalentes ou superiores desde que atendam integralmente às especificações mínimas exigidas.',
        'A contratada deverá empregar profissionais qualificados e materiais, ferramentas e equipamentos adequados à execução do serviço, quando aplicável.',
        'A execução deverá observar as normas técnicas, de segurança, ambientais e demais legislações vigentes aplicáveis ao serviço.',
        'A contratada deverá prestar garantia, correção de falhas e suporte durante o período previsto para o serviço executado, quando aplicável.',
    ];
}

function standard_item_observations(string $kind = 'product'): array
{
    return item_specification_kind($kind) === 'service'
        ? standard_service_item_observations()
        : standard_product_item_observations();
}

function opposite_standard_item_observations(string $kind): array
{
    return item_specification_kind($kind) === 'service'
        ? standard_product_item_observations()
        : standard_service_item_observations();
}

function item_specification_kind_from_unit_type(?array $unitType): string
{
    if (!$unitType) {
        return 'product';
    }

    $name = strtolower(trim((string) ($unitType['name'] ?? '')));
    $abbreviation = strtolower(trim((string) ($unitType['abbreviation'] ?? '')));
    $description = strtolower(trim((string) ($unitType['description'] ?? '')));

    if (
        in_array($abbreviation, ['serv', 'svc', 'srv'], true) ||
        str_starts_with($name, 'servi') ||
        str_contains($description, 'servico') ||
        str_contains($description, 'serviço')
    ) {
        return 'service';
    }

    return 'product';
}

function default_item_specification(string $kind = 'product', bool $withStandardObservations = true): array
{
    return [
        'marca_referencia' => '',
        'modelo_referencia' => '',
        'descricao_minima' => '',
        'caracteristicas_minimas' => [],
        'criterios_aceitacao' => [],
        'documentacao_exigida' => [],
        'certificados' => [],
        'observacoes' => $withStandardObservations ? standard_item_observations($kind) : [],
    ];
}

function default_item_specification_json(string $kind = 'product'): string
{
    return pretty_json(default_item_specification($kind));
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

function normalize_item_specification_array(
    array $decoded,
    string $kind = 'product',
    bool $withStandardObservations = true
): array
{
    $kind = item_specification_kind($kind);
    $normalized = array_merge(default_item_specification($kind, false), $decoded);

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

    if ($withStandardObservations) {
        $oppositeObservations = opposite_standard_item_observations($kind);
        $normalized['observacoes'] = array_values(array_filter(
            $normalized['observacoes'],
            static fn (mixed $observation): bool => !is_string($observation)
                || !in_array($observation, $oppositeObservations, true)
        ));

        foreach (standard_item_observations($kind) as $observation) {
            if (!in_array($observation, $normalized['observacoes'], true)) {
                $normalized['observacoes'][] = $observation;
            }
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

function normalize_item_specification_json(string $json, string $kind = 'product'): string
{
    $decoded = json_decode($json, true);

    if (!is_array($decoded)) {
        $decoded = [];
    }

    return pretty_json(normalize_item_specification_array($decoded, $kind));
}

function format_item_specification_json(mixed $value, string $kind = 'product'): string
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $value;
        }

        return pretty_json(normalize_item_specification_array(is_array($decoded) ? $decoded : [], $kind, false));
    }

    return pretty_json(normalize_item_specification_array(is_array($value) ? $value : [], $kind, false));
}

function item_specification_array_from_value(mixed $value, string $kind = 'product'): array
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return normalize_item_specification_array([], $kind, false);
        }

        return normalize_item_specification_array(is_array($decoded) ? $decoded : [], $kind, false);
    }

    return normalize_item_specification_array(is_array($value) ? $value : [], $kind, false);
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

function project_process_type_options(): array
{
    return [
        'licitacao' => 'Licitacao',
        'compra_direta' => 'Compra Direta',
    ];
}

function normalize_project_process_type(mixed $value): string
{
    $value = trim((string) $value);

    return array_key_exists($value, project_process_type_options()) ? $value : 'licitacao';
}

function project_process_type_label(?string $type): string
{
    $labels = project_process_type_options();

    return $labels[$type ?? ''] ?? 'Licitacao';
}

function project_process_type_badge_class(?string $type): string
{
    return normalize_project_process_type($type) === 'compra_direta'
        ? 'text-bg-primary'
        : 'text-bg-success';
}

function project_is_direct_purchase(mixed $project): bool
{
    $type = is_array($project) ? ($project['process_type'] ?? null) : $project;

    return normalize_project_process_type($type) === 'compra_direta';
}

function direct_purchase_award_criterion_options(): array
{
    return [
        'global_lowest' => 'Menor valor global',
        'item_lowest' => 'Menor valor por item',
    ];
}

function normalize_direct_purchase_award_criterion(mixed $value): string
{
    $value = trim((string) $value);

    return array_key_exists($value, direct_purchase_award_criterion_options()) ? $value : 'global_lowest';
}

function direct_purchase_award_criterion_label(?string $criterion): string
{
    $labels = direct_purchase_award_criterion_options();

    return $labels[$criterion ?? ''] ?? $labels['global_lowest'];
}

function direct_purchase_dod_default_header(array $project = []): array
{
    return [
        'entity_name' => '',
        'place' => '',
        'issue_date' => date('Y-m-d'),
        'title' => 'Documento de Oficializacao de Demanda (DOD)',
        'document_number' => '',
        'recipient' => '',
        'subject' => (string) ($project['name'] ?? ''),
    ];
}

function direct_purchase_dod_default_footer(): array
{
    return [
        'issue_place' => '',
        'issue_date' => date('Y-m-d'),
        'requester_name' => '',
        'requester_role' => '',
        'authority_name' => '',
        'authority_role' => '',
        'additional_fields' => [],
    ];
}

function direct_purchase_dod_default_sections(): array
{
    $rows = [
        ['objeto', 'Objeto da Contratacao', 'Descrever de forma objetiva o servico, item ou conjunto de itens a contratar.'],
        ['necessidade', 'Descricao da Necessidade', 'Explicar o problema administrativo, operacional ou publico que justifica a demanda.'],
        ['justificativa', 'Justificativa da Contratacao do Objeto', 'Relacionar a contratacao ao interesse publico, continuidade do servico e finalidade institucional.'],
        ['quantidades', 'Estimativa de Quantidades e Metodologia', 'Indicar memoria de calculo, historico de consumo, demanda prevista ou criterio tecnico usado.'],
        ['requisitos', 'Requisitos da Contratacao', 'Listar requisitos minimos, padroes de qualidade, prazos, garantia, suporte e criterios de aceitacao.'],
        ['valor', 'Estimativa de Valor', 'Apresentar a forma de pesquisa de precos, fornecedores consultados e criterio de apuracao.'],
        ['conclusao', 'Conclusao da Contratacao', 'Concluir quanto a necessidade, oportunidade e adequacao da compra direta.'],
        ['providencias', 'Providencias a serem Tomadas pela Administracao', 'Registrar providencias internas, fiscais, autorizacoes, dotacao, prazos e encaminhamentos.'],
        ['correlatas', 'Contratacoes Correlatas e Interdependentes', 'Informar contratos relacionados, dependencias tecnicas ou declarar inexistencia quando nao houver.'],
        ['impactos_ambientais', 'Demonstracao de Possiveis Impactos Ambientais', 'Descrever impactos e medidas mitigadoras, sustentabilidade, descarte e uso racional de recursos.'],
    ];
    $sections = [];

    foreach ($rows as $index => [$id, $title, $guidance]) {
        $sections[] = [
            'id' => $id,
            'sort_order' => $index + 1,
            'number' => (string) ($index + 1),
            'title' => $title,
            'enabled' => true,
            'required' => true,
            'content' => '',
            'guidance' => $guidance,
        ];
    }

    return $sections;
}

function direct_purchase_dod_normalize_header(mixed $header, array $project = []): array
{
    return array_merge(
        direct_purchase_dod_default_header($project),
        is_array($header) ? $header : []
    );
}

function direct_purchase_dod_normalize_footer(mixed $footer): array
{
    $footer = array_merge(
        direct_purchase_dod_default_footer(),
        is_array($footer) ? $footer : []
    );

    $footer['additional_fields'] = is_array($footer['additional_fields'] ?? null)
        ? array_values(array_filter($footer['additional_fields'], static fn (mixed $row): bool => is_array($row) && trim((string) ($row['label'] ?? '')) !== ''))
        : [];

    return $footer;
}

function direct_purchase_dod_section_id(string $title, int $index): string
{
    $base = strtolower(trim($title));
    $base = strtr($base, [
        ' ' => '_', '-' => '_', '/' => '_', '.' => '', ',' => '', ';' => '', ':' => '',
        'ç' => 'c', 'ã' => 'a', 'á' => 'a', 'à' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e',
        'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u',
    ]);
    $base = preg_replace('/[^a-z0-9_]+/', '', $base) ?: 'topico';

    return $base . '_' . $index;
}

function direct_purchase_dod_normalize_sections(mixed $sections): array
{
    $source = is_array($sections) && $sections ? $sections : direct_purchase_dod_default_sections();
    $normalized = [];

    foreach (array_values($source) as $index => $section) {
        if (!is_array($section)) {
            continue;
        }

        $title = trim((string) ($section['title'] ?? ''));

        if ($title === '') {
            continue;
        }

        $sortOrder = (int) ($section['sort_order'] ?? ($index + 1));
        $number = trim((string) ($section['number'] ?? ''));

        $normalized[] = [
            'id' => trim((string) ($section['id'] ?? '')) ?: direct_purchase_dod_section_id($title, $index + 1),
            'sort_order' => $sortOrder > 0 ? $sortOrder : ($index + 1),
            'number' => $number !== '' ? $number : (string) ($index + 1),
            'title' => $title,
            'enabled' => boolish($section['enabled'] ?? true, true),
            'required' => boolish($section['required'] ?? false, false),
            'content' => trim((string) ($section['content'] ?? '')),
            'guidance' => trim((string) ($section['guidance'] ?? '')),
        ];
    }

    usort($normalized, static fn (array $left, array $right): int => ((int) $left['sort_order'] <=> (int) $right['sort_order']) ?: strcasecmp($left['title'], $right['title']));

    return array_values($normalized);
}

function direct_purchase_dod_enabled_sections(array $sections): array
{
    return array_values(array_filter($sections, static fn (array $section): bool => !empty($section['enabled'])));
}

function direct_purchase_dod_additional_fields_from_text(string $text): array
{
    $fields = [];

    foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
        $line = trim($line);

        if ($line === '') {
            continue;
        }

        [$label, $value] = array_pad(array_map('trim', explode(':', $line, 2)), 2, '');
        $fields[] = [
            'label' => $label,
            'value' => $value,
        ];
    }

    return $fields;
}

function direct_purchase_dod_additional_fields_text(array $fields): string
{
    $lines = [];

    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }

        $label = trim((string) ($field['label'] ?? ''));
        $value = trim((string) ($field['value'] ?? ''));

        if ($label !== '') {
            $lines[] = $label . ($value !== '' ? ': ' . $value : '');
        }
    }

    return implode(PHP_EOL, $lines);
}

function direct_purchase_dod_ai_prompt_text(array $project, array $demands, array $items, array $dod): string
{
    $lines = [
        'Voce e uma IA de apoio administrativo para gerar um Documento de Oficializacao de Demanda (DOD) de Compra Direta.',
        'Elabore texto objetivo, formal e revisavel, observando a Lei n. 14.133/2021, normas internas da Administracao e boas praticas de redacao administrativa.',
        'Use somente os topicos habilitados abaixo. Nao invente dados ausentes; quando faltar informacao, sinalize como ponto a complementar.',
        '',
        'Projeto: ' . (string) ($project['name'] ?? ''),
        'Modalidade: ' . project_process_type_label($project['process_type'] ?? null),
        'Criterio do orcamento: ' . direct_purchase_award_criterion_label($project['direct_purchase_award_criterion'] ?? null),
        'Descricao: ' . trim((string) ($project['description'] ?? '')),
        '',
        'Demandas:',
    ];

    foreach ($demands as $demand) {
        $lines[] = '- ' . trim(implode(' | ', array_filter([
            (string) ($demand['name'] ?? ''),
            (string) ($demand['secretariat_name'] ?? ''),
            (string) ($demand['requester_department'] ?? $demand['requester_unit_name'] ?? ''),
            (string) ($demand['responsible_name'] ?? ''),
            (string) ($demand['quote_collector_name'] ?? ''),
        ])));
    }

    $lines[] = '';
    $lines[] = 'Itens consolidados:';

    foreach ($items as $item) {
        $lines[] = '- ' . trim(implode(' | ', array_filter([
            (string) ($item['tracking_code'] ?? ''),
            (string) ($item['item_name'] ?? ''),
            'Qtd: ' . format_decimal_quantity($item['total_approved_quantity'] ?? $item['total_quantity'] ?? 0),
            'Unidade: ' . licitation_annex_unit_text($item),
        ])));
    }

    $lines[] = '';
    $lines[] = 'Topicos habilitados do DOD:';

    foreach (direct_purchase_dod_enabled_sections($dod['sections'] ?? []) as $section) {
        $lines[] = ($section['number'] ?? '') . '. ' . ($section['title'] ?? '');

        if (trim((string) ($section['guidance'] ?? '')) !== '') {
            $lines[] = 'Orientacao: ' . trim((string) $section['guidance']);
        }
    }

    $lines[] = '';
    $lines[] = 'Retorne o conteudo dividido exatamente pelos topicos habilitados.';

    return implode(PHP_EOL, $lines);
}
function project_status_options(): array
{
    return [
        'draft' => 'Rascunho',
        'collecting' => 'Coletando demandas',
        'review' => 'Em revisao',
        'closed' => 'Fechado',
        'rectification' => 'Retificacao',
        'canceled' => 'Cancelado',
        'reopened' => 'Reaberto',
    ];
}

function project_status_options_for_form(?array $project = null): array
{
    $status = (string) ($project['status'] ?? 'draft');

    if ($status === 'closed') {
        return [
            'closed' => 'Fechado',
            'rectification' => 'Retificacao',
            'canceled' => 'Cancelado',
        ];
    }

    if ($status === 'canceled') {
        return [
            'canceled' => 'Cancelado',
            'reopened' => 'Reaberto',
        ];
    }

    if ($status === 'rectification') {
        return [
            'rectification' => 'Retificacao',
            'closed' => 'Fechado',
            'canceled' => 'Cancelado',
        ];
    }

    if ($status === 'reopened') {
        return [
            'reopened' => 'Reaberto',
            'closed' => 'Fechado',
            'canceled' => 'Cancelado',
        ];
    }

    return [
        'draft' => 'Rascunho',
        'collecting' => 'Coletando demandas',
        'review' => 'Em revisao',
        'closed' => 'Fechado',
        'canceled' => 'Cancelado',
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
        'canceled' => 'text-bg-dark',
        'reopened' => 'text-bg-primary',
    ];

    return $classes[$status ?? ''] ?? 'text-bg-secondary';
}

function project_status_is_locked(?string $status): bool
{
    return in_array($status, ['closed', 'canceled'], true);
}

function project_is_closed(mixed $project): bool
{
    $status = is_array($project) ? ($project['status'] ?? null) : $project;

    return $status === 'closed';
}

function project_is_canceled(mixed $project): bool
{
    $status = is_array($project) ? ($project['status'] ?? null) : $project;

    return $status === 'canceled';
}

function project_is_reopened(mixed $project): bool
{
    $status = is_array($project) ? ($project['status'] ?? null) : $project;

    return $status === 'reopened';
}

function project_is_locked(mixed $project): bool
{
    $status = is_array($project) ? ($project['status'] ?? null) : $project;

    return project_status_is_locked($status);
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

function project_canceled_edit_message(): string
{
    return 'Projeto cancelado. Ele fica disponivel apenas para consulta ou copia; para continuar, altere o status para Reaberto.';
}

function project_locked_edit_message(mixed $project): string
{
    return project_is_canceled($project)
        ? project_canceled_edit_message()
        : project_closed_edit_message();
}

function project_reopen_mode_options(): array
{
    return [
        'continuity' => 'Continuidade',
        'correction' => 'Correcao com prazo',
    ];
}

function project_reopen_mode_label(?string $mode): string
{
    $labels = project_reopen_mode_options();

    return $labels[$mode ?? ''] ?? (string) $mode;
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

function supplier_lookup_uppercase_text(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    return function_exists('mb_strtoupper')
        ? mb_strtoupper($value, 'UTF-8')
        : strtoupper($value);
}

function supplier_lookup_cnae_from_values(mixed $code, mixed $description): ?array
{
    $code = trim((string) $code);
    $description = trim((string) $description);

    if ($code === '' && $description === '') {
        return null;
    }

    return [
        'code' => $code,
        'name' => $description,
        'description' => $description,
    ];
}

function supplier_lookup_secondary_cnaes_from_data(array $data): array
{
    $rows = $data['cnaes_secundarios'] ?? $data['secondary_cnaes'] ?? [];

    if (!is_array($rows)) {
        return [];
    }

    $items = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $cnae = supplier_lookup_cnae_from_values(
            $row['codigo'] ?? $row['code'] ?? '',
            $row['descricao'] ?? $row['description'] ?? $row['nome'] ?? $row['name'] ?? ''
        );

        if ($cnae !== null) {
            $items[] = $cnae;
        }
    }

    return $items;
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

    return supplier_lookup_uppercase_text(implode(', ', array_values(array_filter([
        $streetLine,
        lookup_response_field($data, ['numero', 'number']),
        lookup_response_field($data, ['complemento', 'complement']),
        lookup_response_field($data, ['bairro', 'neighborhood']),
    ]))));
}

function supplier_lookup_city_from_data(array $data): string
{
    return supplier_lookup_uppercase_text(lookup_response_field($data, ['municipio', 'city', 'localidade']));
}

function supplier_lookup_state_from_data(array $data): string
{
    return supplier_lookup_uppercase_text(lookup_response_field($data, ['uf', 'state']));
}

function supplier_lookup_money_text(mixed $value): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    $normalized = preg_replace('/[^0-9,.-]/', '', $value) ?? '';

    if (str_contains($normalized, ',')) {
        $normalized = str_replace('.', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);
    }

    if ($normalized === '' || !is_numeric($normalized)) {
        return '';
    }

    return number_format((float) $normalized, 2, ',', '.');
}

function supplier_lookup_date_value(mixed $value): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }

    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $matches)) {
        return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
    }

    if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', only_digits($value), $matches)) {
        return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
    }

    return '';
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
        'email' => lookup_response_field($data, ['email', 'correio_eletronico', 'email_principal']),
        'phone' => format_brazil_phone($phones[0] ?? ''),
        'address' => supplier_lookup_address_from_data($data),
        'city' => supplier_lookup_city_from_data($data),
        'state' => supplier_lookup_state_from_data($data),
        'postal_code' => format_brazil_postal_code((string) ($data['cep'] ?? '')),
        'state_registration' => lookup_response_field($data, ['inscricao_estadual', 'state_registration']),
        'municipal_registration' => lookup_response_field($data, ['inscricao_municipal', 'municipal_registration']),
        'company_size' => lookup_response_field($data, ['descricao_porte', 'porte', 'company_size']),
        'share_capital' => supplier_lookup_money_text(lookup_response_field($data, ['capital_social', 'share_capital'])),
        'special_status' => lookup_response_field($data, ['situacao_especial', 'special_status']),
        'special_status_date' => supplier_lookup_date_value(lookup_response_field($data, ['data_situacao_especial', 'special_status_date'])),
        'main_cnae' => supplier_lookup_cnae_from_values($data['cnae_fiscal'] ?? $data['cnae_principal'] ?? '', $data['cnae_fiscal_descricao'] ?? $data['descricao_cnae_fiscal'] ?? ''),
        'secondary_cnaes' => supplier_lookup_secondary_cnaes_from_data($data),
        'website_url' => lookup_response_field($data, ['site', 'website', 'url']),
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

function get_supplier_quote_document_links(array $quote): array
{
    $attachments = is_array($quote['attachments'] ?? null) ? $quote['attachments'] : [];

    if (!$attachments && trim((string) ($quote['attachment_path'] ?? '')) !== '') {
        $attachments[] = [
            'quote_number' => $quote['quote_number'] ?? '',
            'quote_date' => $quote['quote_date'] ?? '',
            'validity_date' => $quote['validity_date'] ?? '',
            'attachment_path' => $quote['attachment_path'],
            'notes' => '',
        ];
    }

    $links = [];

    foreach ($attachments as $attachment) {
        if (!is_array($attachment)) {
            continue;
        }

        $path = trim((string) ($attachment['attachment_path'] ?? ''));

        if ($path === '') {
            continue;
        }

        $links[] = [
            'path' => $path,
            'quote_number' => trim((string) ($attachment['quote_number'] ?? '')),
            'quote_date' => trim((string) ($attachment['quote_date'] ?? '')),
            'validity_date' => trim((string) ($attachment['validity_date'] ?? '')),
        ];
    }

    return $links;
}

function render_supplier_quote_document_buttons(array $quote): string
{
    $links = get_supplier_quote_document_links($quote);

    if (!$links) {
        return '<span class="text-muted">-</span>';
    }

    $html = '<div class="d-flex flex-wrap gap-1">';

    foreach ($links as $index => $link) {
        $label = $link['quote_number'] !== ''
            ? 'Orcamento ' . e($link['quote_number'])
            : 'Anexo ' . ($index + 1);
        $html .= '<a href="' . e($link['path']) . '" target="_blank" class="btn btn-sm btn-outline-secondary">'
            . '<i class="bi bi-paperclip"></i>' . $label . '</a>';
    }

    return $html . '</div>';
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

function normalize_uploaded_file_list(array $files): array
{
    if (!isset($files['name'])) {
        return [];
    }

    if (!is_array($files['name'])) {
        return [$files];
    }

    $normalized = [];

    foreach (array_keys($files['name']) as $index) {
        $normalized[$index] = [
            'name' => $files['name'][$index] ?? '',
            'type' => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$index] ?? 0,
        ];
    }

    return $normalized;
}

function upload_supplier_quote_files(array $files): array
{
    $paths = [];

    foreach (normalize_uploaded_file_list($files) as $file) {
        $path = upload_supplier_quote_file($file);

        if ($path !== null) {
            $paths[] = $path;
        }
    }

    return $paths;
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
