<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function app_url(string $path = ''): string
{
    $base = defined('APP_URL') ? trim((string) APP_URL) : '';

    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $base = $scheme . '://' . $host;
    }

    return rtrim($base, '/') . '/' . ltrim($path, '/');
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
        "A imagem do produto, quando utilizada no processo administrativo, ser\u{00E1} meramente ilustrativa, sem vincula\u{00E7}\u{00E3}o obrigat\u{00F3}ria de marca ou fabricante.",
        "Ser\u{00E3}o aceitos produtos equivalentes ou superiores desde que atendam integralmente \u{00E0}s especifica\u{00E7}\u{00F5}es m\u{00ED}nimas exigidas.",
        "N\u{00E3}o ser\u{00E3}o aceitos produtos remanufaturados, recondicionados, usados ou de proced\u{00EA}ncia duvidosa.",
        "Todos os equipamentos dever\u{00E3}o ser novos, de primeiro uso e entregues em embalagem original do fabricante.",
        "O fornecedor dever\u{00E1} assegurar assist\u{00EA}ncia t\u{00E9}cnica e suporte durante o per\u{00ED}odo de garantia.",
    ];
}

function standard_service_item_observations(): array
{
    return [
        "O servi\u{00E7}o dever\u{00E1} ser executado conforme as condi\u{00E7}\u{00F5}es, prazos e n\u{00ED}veis m\u{00ED}nimos de qualidade definidos no termo de refer\u{00EA}ncia.",
        "Ser\u{00E3}o aceitas solu\u{00E7}\u{00F5}es tecnicamente equivalentes ou superiores desde que atendam integralmente \u{00E0}s especifica\u{00E7}\u{00F5}es m\u{00ED}nimas exigidas.",
        "A contratada dever\u{00E1} empregar profissionais qualificados e materiais, ferramentas e equipamentos adequados \u{00E0} execu\u{00E7}\u{00E3}o do servi\u{00E7}o, quando aplic\u{00E1}vel.",
        "A execu\u{00E7}\u{00E3}o dever\u{00E1} observar as normas t\u{00E9}cnicas, de seguran\u{00E7}a, ambientais e demais legisla\u{00E7}\u{00F5}es vigentes aplic\u{00E1}veis ao servi\u{00E7}o.",
        "A contratada dever\u{00E1} prestar garantia, corre\u{00E7}\u{00E3}o de falhas e suporte durante o per\u{00ED}odo previsto para o servi\u{00E7}o executado, quando aplic\u{00E1}vel.",
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
        str_contains($description, 'serviÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â§o')
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

function direct_purchase_dod_text(string $text): string
{
    return preg_replace_callback(
        '/\\\\u\\{([0-9A-Fa-f]+)\\}/',
        static fn (array $match): string => html_entity_decode('&#x' . $match[1] . ';', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        $text
    ) ?? $text;
}

function direct_purchase_dod_seems_mojibake(string $text): bool
{
    return str_contains($text, direct_purchase_dod_text('\u{00C3}'))
        || str_contains($text, direct_purchase_dod_text('\u{00C2}'))
        || str_contains($text, direct_purchase_dod_text('\u{00E2}'));
}

function direct_purchase_dod_default_place(): string
{
    $place = trim((string) (defined('DOD_ENTITY_CITY') ? DOD_ENTITY_CITY : ''));

    return $place !== '' ? $place : direct_purchase_dod_text('Esp\u{00ED}rito Santo do Turvo - SP');
}

function direct_purchase_dod_env_text(string $constantName, string $fallback): string
{
    $value = defined($constantName) ? trim((string) constant($constantName)) : '';

    return $value !== '' ? $value : direct_purchase_dod_text($fallback);
}

function direct_purchase_dod_default_header(array $project = []): array
{
    return [
        'entity_name' => direct_purchase_dod_env_text('DOD_ENTITY_NAME', 'PREFEITURA MUNICIPAL DE ESP\u{00CD}RITO SANTO DO TURVO'),
        'state_name' => direct_purchase_dod_env_text('DOD_ENTITY_STATE', 'ESTADO DE S\u{00C3}O PAULO'),
        'secretariat_name' => '',
        'department_name' => '',
        'place' => direct_purchase_dod_default_place(),
        'issue_date' => date('Y-m-d'),
        'title' => direct_purchase_dod_text('Documento de Oficializa\u{00E7}\u{00E3}o de Demanda (DOD)'),
        'document_number' => '',
        'recipient' => '',
        'subject' => (string) ($project['name'] ?? ''),
        'logo_left_path' => direct_purchase_dod_env_text('DOD_LOGO_LEFT_PATH', '/assets/municipio-agro.png'),
        'logo_center_path' => municipal_logo_public_path() ?: '/assets/brasao-municipio.png',
        'logo_right_path' => direct_purchase_dod_env_text('DOD_LOGO_RIGHT_PATH', '/assets/municipio-verde-azul.png'),
        'additional_logo_paths' => [],
    ];
}

function direct_purchase_dod_default_footer(): array
{
    return [
        'issue_place' => direct_purchase_dod_default_place(),
        'issue_date' => date('Y-m-d'),
        'requester_name' => '',
        'requester_role' => '',
        'authority_name' => '',
        'authority_role' => '',
        'address' => '',
        'postal_code' => '',
        'phone' => '',
        'branch' => '',
        'cnpj' => direct_purchase_dod_env_text('DOD_ENTITY_CNPJ', '57.264.509/0001-69'),
        'email' => '',
        'signatures' => [
            ['label' => 'Requisitante', 'name' => '', 'role' => '', 'collaborator_id' => null],
            ['label' => 'Autoridade competente', 'name' => '', 'role' => '', 'collaborator_id' => null],
        ],
        'additional_fields' => [],
    ];
}

function direct_purchase_dod_normalize_logo_paths(mixed $paths): array
{
    if (is_string($paths)) {
        $paths = preg_split('/\r\n|\r|\n|,/', $paths) ?: [];
    }

    if (!is_array($paths)) {
        return [];
    }

    $normalized = [];

    foreach ($paths as $path) {
        $path = trim((string) $path);

        if ($path !== '' && !in_array($path, $normalized, true)) {
            $normalized[] = $path;
        }
    }

    return $normalized;
}

function direct_purchase_dod_logo_paths_from_text(string $text): array
{
    return direct_purchase_dod_normalize_logo_paths($text);
}

function direct_purchase_dod_logo_paths_text(array $paths): string
{
    return implode(PHP_EOL, direct_purchase_dod_normalize_logo_paths($paths));
}
function direct_purchase_dod_default_sections(): array
{
    $rows = [
        ['objeto', direct_purchase_dod_text('Objeto da Contrata\u{00E7}\u{00E3}o'), direct_purchase_dod_text('Descrever de forma objetiva o servi\u{00E7}o, item ou conjunto de itens a contratar.'), false],
        ['necessidade', direct_purchase_dod_text('Descri\u{00E7}\u{00E3}o da Necessidade'), direct_purchase_dod_text('Explicar o problema administrativo, operacional ou p\u{00FA}blico que justifica a demanda.'), false],
        ['justificativa', direct_purchase_dod_text('Justificativa da Contrata\u{00E7}\u{00E3}o do Objeto'), direct_purchase_dod_text('Relacionar a contrata\u{00E7}\u{00E3}o ao interesse p\u{00FA}blico, continuidade do servi\u{00E7}o e finalidade institucional.'), false],
        ['quantidades', 'Estimativa de Quantidades e Metodologia', direct_purchase_dod_text('Gerado automaticamente a partir dos itens e quantidades cadastrados nas demandas do projeto.'), true],
        ['requisitos', direct_purchase_dod_text('Requisitos da Contrata\u{00E7}\u{00E3}o'), direct_purchase_dod_text('Listar requisitos m\u{00ED}nimos, padr\u{00F5}es de qualidade, prazos, garantia, suporte e crit\u{00E9}rios de aceita\u{00E7}\u{00E3}o.'), false],
        ['valor', 'Estimativa de Valor', direct_purchase_dod_text('Gerado automaticamente a partir do Or\u{00E7}amento Geral da compra direta e do crit\u{00E9}rio de julgamento configurado.'), true],
        ['conclusao', direct_purchase_dod_text('Conclus\u{00E3}o da Contrata\u{00E7}\u{00E3}o'), direct_purchase_dod_text('Concluir quanto \u{00E0} necessidade, oportunidade e adequa\u{00E7}\u{00E3}o da compra direta.'), false],
        ['providencias', direct_purchase_dod_text('Provid\u{00EA}ncias a serem Tomadas pela Administra\u{00E7}\u{00E3}o'), direct_purchase_dod_text('Registrar provid\u{00EA}ncias internas, fiscais, autoriza\u{00E7}\u{00F5}es, dota\u{00E7}\u{00E3}o, prazos e encaminhamentos.'), false],
        ['correlatas', direct_purchase_dod_text('Contrata\u{00E7}\u{00F5}es Correlatas e Interdependentes'), direct_purchase_dod_text('Informar contratos relacionados, depend\u{00EA}ncias t\u{00E9}cnicas ou declarar inexist\u{00EA}ncia quando n\u{00E3}o houver.'), false],
        ['impactos_ambientais', direct_purchase_dod_text('Demonstra\u{00E7}\u{00E3}o de Poss\u{00ED}veis Impactos Ambientais'), direct_purchase_dod_text('Gerado automaticamente a partir dos impactos ambientais registrados nos itens demandados, sem duplicidades.'), true],
    ];
    $sections = [];

    foreach ($rows as $index => [$id, $title, $guidance, $autoGenerated]) {
        $sections[] = [
            'id' => $id,
            'sort_order' => $index + 1,
            'number' => (string) ($index + 1),
            'title' => $title,
            'enabled' => true,
            'required' => true,
            'auto_generated' => $autoGenerated,
            'content' => '',
            'guidance' => $guidance,
        ];
    }

    return $sections;
}

function direct_purchase_dod_normalize_header(mixed $header, array $project = []): array
{
    $defaults = direct_purchase_dod_default_header($project);
    $header = array_merge(
        $defaults,
        is_array($header) ? $header : []
    );

    foreach (['secretariat_name', 'department_name', 'title', 'document_number', 'recipient', 'subject'] as $key) {
        $header[$key] = trim((string) ($header[$key] ?? ''));
    }

    foreach (['entity_name', 'state_name', 'place', 'logo_left_path', 'logo_center_path', 'logo_right_path'] as $key) {
        $header[$key] = trim((string) ($defaults[$key] ?? ''));
    }

    $header['additional_logo_paths'] = direct_purchase_dod_normalize_logo_paths($header['additional_logo_paths'] ?? []);
    $header['issue_date'] = trim((string) ($header['issue_date'] ?? date('Y-m-d'))) ?: date('Y-m-d');

    return $header;
}
function direct_purchase_dod_normalize_signatures(mixed $signatures, array $footer = []): array
{
    $source = is_array($signatures) ? array_values($signatures) : [];

    if (!$source) {
        $source = [
            [
                'label' => 'Requisitante',
                'name' => (string) ($footer['requester_name'] ?? ''),
                'role' => (string) ($footer['requester_role'] ?? ''),
                'collaborator_id' => null,
            ],
            [
                'label' => 'Autoridade competente',
                'name' => (string) ($footer['authority_name'] ?? ''),
                'role' => (string) ($footer['authority_role'] ?? ''),
                'collaborator_id' => null,
            ],
        ];
    }

    $normalized = [];

    foreach ($source as $index => $signature) {
        if (!is_array($signature)) {
            continue;
        }

        $label = trim((string) ($signature['label'] ?? ''));
        $name = trim((string) ($signature['name'] ?? ''));
        $role = trim((string) ($signature['role'] ?? ''));
        $collaboratorId = (int) ($signature['collaborator_id'] ?? 0) ?: null;

        if ($label === '' && $name === '' && $role === '' && !$collaboratorId) {
            continue;
        }

        $normalized[] = [
            'label' => $label !== '' ? $label : 'Assinatura ' . ($index + 1),
            'name' => $name,
            'role' => $role,
            'collaborator_id' => $collaboratorId,
        ];
    }

    return $normalized ?: direct_purchase_dod_default_footer()['signatures'];
}

function direct_purchase_dod_normalize_footer(mixed $footer): array
{
    $footer = array_merge(
        direct_purchase_dod_default_footer(),
        is_array($footer) ? $footer : []
    );

    foreach (['issue_place', 'requester_name', 'requester_role', 'authority_name', 'authority_role', 'address', 'postal_code', 'phone', 'branch', 'cnpj', 'email'] as $key) {
        $footer[$key] = trim((string) ($footer[$key] ?? ''));
    }

    $footer['issue_date'] = trim((string) ($footer['issue_date'] ?? date('Y-m-d'))) ?: date('Y-m-d');
    unset($footer['show_page_numbers']);
    $footer['signatures'] = direct_purchase_dod_normalize_signatures($footer['signatures'] ?? [], $footer);

    $footer['requester_name'] = $footer['requester_name'] ?: (string) ($footer['signatures'][0]['name'] ?? '');
    $footer['requester_role'] = $footer['requester_role'] ?: (string) ($footer['signatures'][0]['role'] ?? '');
    $footer['authority_name'] = $footer['authority_name'] ?: (string) ($footer['signatures'][1]['name'] ?? '');
    $footer['authority_role'] = $footer['authority_role'] ?: (string) ($footer['signatures'][1]['role'] ?? '');

    $footer['additional_fields'] = is_array($footer['additional_fields'] ?? null)
        ? array_values(array_filter($footer['additional_fields'], static fn (mixed $row): bool => is_array($row) && trim((string) ($row['label'] ?? '')) !== ''))
        : [];

    return $footer;
}

function direct_purchase_dod_prefill_footer_from_demands(array $footer, array $demands): array
{
    $mapping = [
        'address' => 'requester_unit_address',
        'postal_code' => 'requester_unit_postal_code',
        'phone' => 'requester_unit_phone',
        'branch' => 'requester_unit_branch',
        'email' => 'requester_unit_email',
    ];

    foreach ($mapping as $footerKey => $demandKey) {
        if (trim((string) ($footer[$footerKey] ?? '')) !== '') {
            continue;
        }

        foreach ($demands as $demand) {
            $value = trim((string) ($demand[$demandKey] ?? ''));

            if ($value !== '') {
                $footer[$footerKey] = $value;
                break;
            }
        }
    }

    $footer['issue_place'] = trim((string) ($footer['issue_place'] ?? '')) ?: direct_purchase_dod_default_place();
    $footer['cnpj'] = trim((string) ($footer['cnpj'] ?? '')) ?: direct_purchase_dod_env_text('DOD_ENTITY_CNPJ', '57.264.509/0001-69');

    return $footer;
}

function direct_purchase_dod_section_id(string $title, int $index): string
{
    $base = function_exists('mb_strtolower') ? mb_strtolower(trim($title), 'UTF-8') : strtolower(trim($title));

    if (function_exists('iconv')) {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base);
        if ($ascii !== false) {
            $base = $ascii;
        }
    }

    $base = strtr($base, [' ' => '_', '-' => '_', '/' => '_', '.' => '', ',' => '', ';' => '', ':' => '']);
    $base = preg_replace('/[^a-z0-9_]+/', '', $base) ?: 'topico';

    return $base . '_' . $index;
}

function direct_purchase_dod_auto_section_ids(): array
{
    return ['quantidades', 'valor', 'impactos_ambientais'];
}

function direct_purchase_dod_normalize_sections(mixed $sections): array
{
    $source = is_array($sections) && $sections ? $sections : direct_purchase_dod_default_sections();
    $defaultsById = [];

    foreach (direct_purchase_dod_default_sections() as $defaultSection) {
        $defaultsById[(string) $defaultSection['id']] = $defaultSection;
    }

    $normalized = [];

    foreach (array_values($source) as $index => $section) {
        if (!is_array($section)) {
            continue;
        }

        $id = trim((string) ($section['id'] ?? ''));
        $default = $id !== '' ? ($defaultsById[$id] ?? []) : [];
        $rawTitle = trim((string) ($section['title'] ?? ''));
        $title = direct_purchase_dod_seems_mojibake($rawTitle) || $rawTitle === ''
            ? trim((string) ($default['title'] ?? ''))
            : $rawTitle;

        if ($title === '') {
            continue;
        }

        $rawGuidance = trim((string) ($section['guidance'] ?? ''));
        $guidance = direct_purchase_dod_seems_mojibake($rawGuidance)
            ? trim((string) ($default['guidance'] ?? ''))
            : $rawGuidance;
        $sortOrder = (int) ($section['sort_order'] ?? ($default['sort_order'] ?? ($index + 1)));
        $number = trim((string) ($section['number'] ?? ($default['number'] ?? '')));
        $autoGenerated = in_array($id, direct_purchase_dod_auto_section_ids(), true)
            || boolish($section['auto_generated'] ?? ($default['auto_generated'] ?? false), false);

        $content = normalize_rich_text_content((string) ($section['content'] ?? ''));

        $normalized[] = [
            'id' => $id !== '' ? $id : direct_purchase_dod_section_id($title, $index + 1),
            'sort_order' => $sortOrder > 0 ? $sortOrder : ($index + 1),
            'number' => $number !== '' ? rtrim($number, '.') : (string) ($index + 1),
            'title' => $title,
            'enabled' => boolish($section['enabled'] ?? ($default['enabled'] ?? true), true),
            'required' => boolish($section['required'] ?? ($default['required'] ?? false), false),
            'auto_generated' => $autoGenerated,
            'content' => $content,
            'guidance' => $guidance !== '' ? $guidance : trim((string) ($default['guidance'] ?? '')),
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

function direct_purchase_dod_section_number_text(mixed $number): string
{
    $number = trim((string) $number);

    return $number !== '' ? rtrim($number, '.') . '.' : '';
}

function direct_purchase_dod_section_heading(array $section): string
{
    return trim(implode(' ', array_filter([
        direct_purchase_dod_section_number_text($section['number'] ?? ''),
        (string) ($section['title'] ?? ''),
    ])));
}

function direct_purchase_dod_money_text(?float $value): string
{
    return $value !== null ? 'R$ ' . number_format($value, 2, ',', '.') : 'R$ 0,00';
}

function direct_purchase_dod_int_to_words_pt_br(int $number): string
{
    $number = max(0, $number);
    $units = [
        0 => 'zero', 1 => 'um', 2 => 'dois', 3 => direct_purchase_dod_text('tr\u{00EA}s'), 4 => 'quatro', 5 => 'cinco',
        6 => 'seis', 7 => 'sete', 8 => 'oito', 9 => 'nove', 10 => 'dez', 11 => 'onze',
        12 => 'doze', 13 => 'treze', 14 => 'quatorze', 15 => 'quinze', 16 => 'dezesseis',
        17 => 'dezessete', 18 => 'dezoito', 19 => 'dezenove',
    ];
    $tens = [2 => 'vinte', 3 => 'trinta', 4 => 'quarenta', 5 => 'cinquenta', 6 => 'sessenta', 7 => 'setenta', 8 => 'oitenta', 9 => 'noventa'];
    $hundreds = [1 => 'cento', 2 => 'duzentos', 3 => 'trezentos', 4 => 'quatrocentos', 5 => 'quinhentos', 6 => 'seiscentos', 7 => 'setecentos', 8 => 'oitocentos', 9 => 'novecentos'];

    if ($number < 20) {
        return $units[$number];
    }

    if ($number < 100) {
        $ten = intdiv($number, 10);
        $rest = $number % 10;

        return $tens[$ten] . ($rest > 0 ? ' e ' . $units[$rest] : '');
    }

    if ($number === 100) {
        return 'cem';
    }

    if ($number < 1000) {
        $hundred = intdiv($number, 100);
        $rest = $number % 100;

        return $hundreds[$hundred] . ($rest > 0 ? ' e ' . direct_purchase_dod_int_to_words_pt_br($rest) : '');
    }

    if ($number < 1000000) {
        $thousands = intdiv($number, 1000);
        $rest = $number % 1000;
        $text = $thousands === 1 ? 'mil' : direct_purchase_dod_int_to_words_pt_br($thousands) . ' mil';

        return $text . ($rest > 0 ? ($rest < 100 ? ' e ' : ' ') . direct_purchase_dod_int_to_words_pt_br($rest) : '');
    }

    if ($number < 1000000000) {
        $millions = intdiv($number, 1000000);
        $rest = $number % 1000000;
        $text = $millions === 1 ? direct_purchase_dod_text('um milh\u{00E3}o') : direct_purchase_dod_int_to_words_pt_br($millions) . direct_purchase_dod_text(' milh\u{00F5}es');

        return $text . ($rest > 0 ? ($rest < 100 ? ' e ' : ' ') . direct_purchase_dod_int_to_words_pt_br($rest) : '');
    }

    $billions = intdiv($number, 1000000000);
    $rest = $number % 1000000000;
    $text = $billions === 1 ? direct_purchase_dod_text('um bilh\u{00E3}o') : direct_purchase_dod_int_to_words_pt_br($billions) . direct_purchase_dod_text(' bilh\u{00F5}es');

    return $text . ($rest > 0 ? ' ' . direct_purchase_dod_int_to_words_pt_br($rest) : '');
}

function direct_purchase_dod_money_in_words(?float $value): string
{
    $value = round(max(0.0, (float) $value), 2);
    $reais = (int) floor($value);
    $centavos = (int) round(($value - $reais) * 100);

    if ($centavos === 100) {
        $reais++;
        $centavos = 0;
    }

    $parts = [];
    $parts[] = direct_purchase_dod_int_to_words_pt_br($reais) . ' ' . ($reais === 1 ? 'real' : 'reais');

    if ($centavos > 0) {
        $parts[] = direct_purchase_dod_int_to_words_pt_br($centavos) . ' ' . ($centavos === 1 ? 'centavo' : 'centavos');
    }

    return implode(' e ', $parts);
}

function direct_purchase_dod_quantity_methodology_text(array $items): string
{
    if (!$items) {
        return direct_purchase_dod_text('N\u{00E3}o h\u{00E1} itens cadastrados nas demandas do projeto para compor a estimativa de quantidades.');
    }

    $lines = [
        direct_purchase_dod_text('A estimativa de quantidades foi consolidada automaticamente a partir das demandas registradas no projeto, considerando as quantidades aprovadas em cada unidade administrativa.'),
        '',
    ];

    foreach ($items as $item) {
        $quantity = format_decimal_quantity($item['total_approved_quantity'] ?? $item['total_quantity'] ?? 0);
        $unit = licitation_annex_unit_text($item);
        $demandCount = (int) ($item['demand_count'] ?? 0);
        $demandText = $demandCount === 1 ? '1 demanda' : $demandCount . ' demandas';
        $code = trim((string) ($item['tracking_code'] ?? ''));
        $name = trim((string) ($item['item_name'] ?? 'Item'));
        $label = $code !== '' ? $code . ' - ' . $name : $name;

        $lines[] = '- ' . $label . ': ' . ($quantity !== '' ? $quantity : '0') . ' ' . $unit . ', consolidado a partir de ' . $demandText . '.';
    }

    $lines[] = '';
    $lines[] = direct_purchase_dod_text('A metodologia adotada preserva a mem\u{00F3}ria de c\u{00E1}lculo do sistema, permitindo rastrear a origem das quantidades nas demandas vinculadas ao projeto.');

    return implode(PHP_EOL, $lines);
}

function direct_purchase_dod_value_estimate_text(array $project, array $budgetEvaluation): string
{
    $criterion = normalize_direct_purchase_award_criterion($project['direct_purchase_award_criterion'] ?? 'global_lowest');
    $criterionText = direct_purchase_award_criterion_label($criterion);

    if ($criterion === 'item_lowest') {
        $itemWinners = is_array($budgetEvaluation['item_winners'] ?? null) ? $budgetEvaluation['item_winners'] : [];
        $total = 0.0;
        $suppliers = [];

        foreach ($itemWinners as $winner) {
            $total += (float) ($winner['total'] ?? 0);
            $supplierName = trim((string) ($winner['supplier_name'] ?? ''));
            $supplierDocument = trim((string) ($winner['supplier_document'] ?? ''));

            if ($supplierName !== '') {
                $suppliers[$supplierName . '|' . $supplierDocument] = $supplierName . ($supplierDocument !== '' ? direct_purchase_dod_text(', inscrita no CNPJ sob n\u{00BA} ') . $supplierDocument : '');
            }
        }

        if ($total <= 0) {
            return direct_purchase_dod_text('A estimativa de valor ser\u{00E1} gerada automaticamente ap\u{00F3}s o lan\u{00E7}amento dos or\u{00E7}amentos dos fornecedores no Or\u{00E7}amento Geral da compra direta.');
        }

        $supplierText = $suppliers ? implode('; ', array_values($suppliers)) : 'fornecedores consultados';

        return implode(PHP_EOL . PHP_EOL, [
            direct_purchase_dod_text('O valor estimado da contrata\u{00E7}\u{00E3}o \u{00E9} de ') . direct_purchase_dod_money_text($total) . ' (' . direct_purchase_dod_money_in_words($total) . direct_purchase_dod_text('), conforme composi\u{00E7}\u{00E3}o dos menores valores unit\u{00E1}rios apresentados pelos fornecedores consultados.'),
            direct_purchase_dod_text('O crit\u{00E9}rio configurado para a apura\u{00E7}\u{00E3}o \u{00E9} ') . $criterionText . direct_purchase_dod_text(', raz\u{00E3}o pela qual a estimativa considera a empresa de menor valor para cada item do objeto pretendido. Fornecedores considerados: ') . $supplierText . '.',
            direct_purchase_dod_text('Os dados dos fornecedores consultados, respectivos valores apresentados e demais informa\u{00E7}\u{00F5}es da pesquisa de pre\u{00E7}os constam no Anexo I - Or\u{00E7}amento Geral, que acompanha este Of\u{00ED}cio.'),
        ]);
    }

    $winner = is_array($budgetEvaluation['global_winner'] ?? null) ? $budgetEvaluation['global_winner'] : null;

    if (!$winner || (float) ($winner['total'] ?? 0) <= 0) {
        return direct_purchase_dod_text('A estimativa de valor ser\u{00E1} gerada automaticamente ap\u{00F3}s o lan\u{00E7}amento dos or\u{00E7}amentos dos fornecedores no Or\u{00E7}amento Geral da compra direta.');
    }

    $total = (float) $winner['total'];
    $supplierName = trim((string) ($winner['supplier_name'] ?? 'Fornecedor')) ?: 'Fornecedor';
    $supplierDocument = trim((string) ($winner['supplier_document'] ?? ''));
    $documentText = $supplierDocument !== '' ? direct_purchase_dod_text(', inscrita no CNPJ sob n\u{00BA} ') . $supplierDocument : '';

    return implode(PHP_EOL . PHP_EOL, [
        direct_purchase_dod_text('O valor estimado da contrata\u{00E7}\u{00E3}o \u{00E9} de ') . direct_purchase_dod_money_text($total) . ' (' . direct_purchase_dod_money_in_words($total) . direct_purchase_dod_text('), conforme menor or\u{00E7}amento apresentado pela empresa ') . $supplierName . $documentText . '.',
        direct_purchase_dod_text('A empresa ') . $supplierName . direct_purchase_dod_text(' foi a fornecedora que apresentou o menor valor global para a execu\u{00E7}\u{00E3}o do objeto pretendido, conforme levantamento de pre\u{00E7}os realizado.'),
        direct_purchase_dod_text('Os dados dos fornecedores consultados, respectivos valores apresentados e demais informa\u{00E7}\u{00F5}es da pesquisa de pre\u{00E7}os constam no Anexo I - Or\u{00E7}amento Geral, que acompanha este Of\u{00ED}cio.'),
    ]);
}

function direct_purchase_dod_environmental_impacts_text(array $items): string
{
    $impacts = [];

    foreach ($items as $item) {
        foreach (environmental_impacts_to_array($item['environmental_impacts'] ?? '') as $impact) {
            $impact = trim((string) $impact);

            if ($impact === '') {
                continue;
            }

            $key = function_exists('mb_strtolower') ? mb_strtolower($impact, 'UTF-8') : strtolower($impact);
            $key = preg_replace('/\s+/', ' ', $key) ?? $key;
            $impacts[$key] = $impact;
        }
    }

    if (!$impacts) {
        return direct_purchase_dod_text('N\u{00E3}o foram registrados impactos ambientais espec\u{00ED}ficos nos itens demandados. Caso identificada obriga\u{00E7}\u{00E3}o ambiental na instru\u{00E7}\u{00E3}o processual, a informa\u{00E7}\u{00E3}o dever\u{00E1} ser complementada pela unidade respons\u{00E1}vel.');
    }

    $lines = [
        direct_purchase_dod_text('Foram identificados os seguintes poss\u{00ED}veis impactos ambientais a partir dos itens cadastrados nas demandas, com consolida\u{00E7}\u{00E3}o autom\u{00E1}tica e sem duplicidade de ocorr\u{00EA}ncias:'),
        '',
    ];

    foreach (array_values($impacts) as $impact) {
        $lines[] = '- ' . $impact;
    }

    return implode(PHP_EOL, $lines);
}

function direct_purchase_dod_auto_content_for_section(string $sectionId, array $project, array $demands, array $items, array $budgetEvaluation = []): ?string
{
    return match ($sectionId) {
        'quantidades' => direct_purchase_dod_quantity_methodology_text($items),
        'valor' => direct_purchase_dod_value_estimate_text($project, $budgetEvaluation),
        'impactos_ambientais' => direct_purchase_dod_environmental_impacts_text($items),
        default => null,
    };
}

function direct_purchase_dod_apply_auto_content(array $project, array $demands, array $items, array $dod, array $budgetEvaluation = []): array
{
    $sections = direct_purchase_dod_normalize_sections($dod['sections'] ?? []);

    foreach ($sections as $index => $section) {
        $content = direct_purchase_dod_auto_content_for_section((string) ($section['id'] ?? ''), $project, $demands, $items, $budgetEvaluation);

        if ($content !== null) {
            $sections[$index]['content'] = $content;
            $sections[$index]['auto_generated'] = true;
        }
    }

    return $sections;
}

function direct_purchase_dod_render_inline_markdown(string $text): string
{
    $html = e($text);
    $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html) ?? $html;
    $html = preg_replace('/__(.+?)__/s', '<strong>$1</strong>', $html) ?? $html;
    $html = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/s', '<em>$1</em>', $html) ?? $html;

    return $html;
}

function rich_text_contains_html(string $content): bool
{
    return preg_match('/<(?:p|h[1-3]|strong|b|em|i|u|ul|ol|li|blockquote|br|hr|a|table|thead|tbody|tfoot|tr|th|td)\b/i', $content) === 1;
}

function rich_text_plain_length(string $content): int
{
    $plain = trim(html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    return function_exists('mb_strlen') ? mb_strlen($plain, 'UTF-8') : strlen($plain);
}

function sanitize_rich_text_html(string $html): string
{
    $html = trim($html);

    if ($html === '') {
        return '';
    }

    if (!class_exists('DOMDocument')) {
        $plain = preg_replace('/<(script|style|iframe|object|embed|svg)\b[^>]*>.*?<\/\1>/is', '', $html) ?? $html;
        $plain = trim(html_entity_decode(strip_tags($plain), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $plain !== '' ? '<p>' . nl2br(e($plain), false) . '</p>' : '';
    }

    $allowedTags = [
        'p', 'h1', 'h2', 'h3', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li',
        'blockquote', 'br', 'hr', 'a', 'table', 'thead', 'tbody', 'tfoot', 'tr',
        'th', 'td', 'colgroup', 'col',
    ];
    $blockedTags = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'form', 'input', 'button'];
    $document = new DOMDocument('1.0', 'UTF-8');
    $previousLibxmlState = libxml_use_internal_errors(true);
    $document->loadHTML(
        '<?xml encoding="UTF-8"><div id="rich-text-root">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previousLibxmlState);
    $root = $document->getElementById('rich-text-root');

    if (!$root) {
        return '';
    }

    $sanitizeNode = static function ($node) use (&$sanitizeNode, $allowedTags, $blockedTags): void {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $tagName = strtolower($child->nodeName);

            if (in_array($tagName, $blockedTags, true)) {
                $node->removeChild($child);
                continue;
            }

            $sanitizeNode($child);

            if (!in_array($tagName, $allowedTags, true)) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            $attributes = [];
            foreach (iterator_to_array($child->attributes ?? []) as $attribute) {
                $attributes[$attribute->nodeName] = $attribute->nodeValue;
            }

            while ($child->attributes && $child->attributes->length > 0) {
                $child->removeAttributeNode($child->attributes->item(0));
            }

            if ($tagName === 'a') {
                $href = trim((string) ($attributes['href'] ?? ''));
                $isSafeHref = $href !== ''
                    && !preg_match('/^(?:javascript|data|vbscript):/i', $href)
                    && (preg_match('#^(?:https?://|mailto:|/|\#)#i', $href) || !str_contains($href, ':'));

                if ($isSafeHref) {
                    $child->setAttribute('href', $href);
                    if (preg_match('#^https?://#i', $href)) {
                        $child->setAttribute('target', '_blank');
                        $child->setAttribute('rel', 'noopener noreferrer');
                    }
                }
            }

            if (in_array($tagName, ['p', 'h1', 'h2', 'h3', 'th', 'td'], true)) {
                $style = (string) ($attributes['style'] ?? '');
                if (preg_match('/(?:^|;)\s*text-align\s*:\s*(left|center|right|justify)\s*(?:;|$)/i', $style, $matches)) {
                    $child->setAttribute('style', 'text-align: ' . strtolower($matches[1]) . ';');
                }
            }

            if (in_array($tagName, ['th', 'td'], true)) {
                foreach (['colspan', 'rowspan'] as $spanAttribute) {
                    $span = (int) ($attributes[$spanAttribute] ?? 0);
                    if ($span >= 1 && $span <= 100) {
                        $child->setAttribute($spanAttribute, (string) $span);
                    }
                }
            }
        }
    };

    $sanitizeNode($root);
    $sanitized = '';

    foreach (iterator_to_array($root->childNodes) as $child) {
        $sanitized .= $document->saveHTML($child);
    }

    return trim($sanitized);
}

function normalize_rich_text_content(string $content, int $maxLength = 50000): string
{
    $content = trim($content);

    if ($content === '') {
        return '';
    }

    $normalized = rich_text_contains_html($content) ? sanitize_rich_text_html($content) : $content;

    if (rich_text_plain_length($normalized) > $maxLength) {
        throw new InvalidArgumentException('O texto do tópico ultrapassa o limite de ' . number_format($maxLength, 0, ',', '.') . ' caracteres.');
    }

    return $normalized;
}

function direct_purchase_dod_render_content(string $text): string
{
    if (rich_text_contains_html($text)) {
        $html = sanitize_rich_text_html($text);

        return rich_text_plain_length($html) > 0
            ? '<div class="rich-text-content">' . $html . '</div>'
            : '<p class="empty">A preencher.</p>';
    }

    $lines = preg_split('/\r\n|\r|\n/', trim($text)) ?: [];
    $html = '';
    $listType = null;

    $closeList = static function () use (&$html, &$listType): void {
        if ($listType !== null) {
            $html .= '</' . $listType . '>';
            $listType = null;
        }
    };

    foreach ($lines as $line) {
        $line = rtrim((string) $line);

        if (trim($line) === '') {
            $closeList();
            continue;
        }

        if (preg_match('/^\s*[-*]\s+(.+)$/', $line, $matches)) {
            if ($listType !== 'ul') {
                $closeList();
                $html .= '<ul>';
                $listType = 'ul';
            }

            $html .= '<li>' . direct_purchase_dod_render_inline_markdown(trim($matches[1])) . '</li>';
            continue;
        }

        if (preg_match('/^\s*\d+[\.)]\s+(.+)$/', $line, $matches)) {
            if ($listType !== 'ol') {
                $closeList();
                $html .= '<ol>';
                $listType = 'ol';
            }

            $html .= '<li>' . direct_purchase_dod_render_inline_markdown(trim($matches[1])) . '</li>';
            continue;
        }

        $closeList();
        $html .= '<p>' . direct_purchase_dod_render_inline_markdown(trim($line)) . '</p>';
    }

    $closeList();

    return $html !== '' ? $html : '<p class="empty">A preencher.</p>';
}

function direct_purchase_dod_ai_prompt_text(array $project, array $demands, array $items, array $dod): string
{
    $lines = [
        direct_purchase_dod_text('Voc\u{00EA} \u{00E9} uma IA de apoio administrativo para gerar um Documento de Oficializa\u{00E7}\u{00E3}o de Demanda (DOD) de Compra Direta.'),
        direct_purchase_dod_text('Elabore texto objetivo, formal e revis\u{00E1}vel, observando a Lei n\u{00BA} 14.133/2021, normas internas da Administra\u{00E7}\u{00E3}o e boas pr\u{00E1}ticas de reda\u{00E7}\u{00E3}o administrativa.'),
        direct_purchase_dod_text('Use somente os t\u{00F3}picos habilitados abaixo. N\u{00E3}o invente dados ausentes; quando faltar informa\u{00E7}\u{00E3}o, sinalize como ponto a complementar.'),
        direct_purchase_dod_text('Os t\u{00F3}picos marcados como autom\u{00E1}ticos devem ser preservados, pois o sistema os gera com base nas demandas, or\u{00E7}amentos e impactos dos itens.'),
        '',
        'Projeto: ' . (string) ($project['name'] ?? ''),
        'Modalidade: ' . project_process_type_label($project['process_type'] ?? null),
        direct_purchase_dod_text('Crit\u{00E9}rio do or\u{00E7}amento: ') . direct_purchase_award_criterion_label($project['direct_purchase_award_criterion'] ?? null),
        direct_purchase_dod_text('Descri\u{00E7}\u{00E3}o: ') . trim((string) ($project['description'] ?? '')),
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
    $lines[] = direct_purchase_dod_text('T\u{00F3}picos habilitados do DOD:');

    foreach (direct_purchase_dod_enabled_sections($dod['sections'] ?? []) as $section) {
        $lines[] = direct_purchase_dod_section_heading($section) . (!empty($section['auto_generated']) ? direct_purchase_dod_text(' [autom\u{00E1}tico]') : '');

        if (trim((string) ($section['guidance'] ?? '')) !== '') {
            $lines[] = direct_purchase_dod_text('Orienta\u{00E7}\u{00E3}o: ') . trim((string) $section['guidance']);
        }
    }

    $lines[] = '';
    $lines[] = direct_purchase_dod_text('Retorne o conte\u{00FA}do dividido exatamente pelos t\u{00F3}picos habilitados, sem alterar os t\u{00F3}picos autom\u{00E1}ticos.');

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

function licitation_annex_repair_text(string $value): string
{
    if (!function_exists('iconv') || !function_exists('mb_check_encoding')) {
        return $value;
    }

    $markers = [
        "\u{00C3}",
        "\u{00C2}",
        "\u{00E2}",
        "\u{0192}",
        "\u{00C6}",
    ];

    for ($attempt = 0; $attempt < 8; $attempt++) {
        $score = 0;

        foreach ($markers as $marker) {
            $score += substr_count($value, $marker);
        }

        if ($score === 0) {
            break;
        }

        $candidate = @iconv('UTF-8', 'Windows-1252//IGNORE', $value);

        if ($candidate === false || !mb_check_encoding($candidate, 'UTF-8')) {
            break;
        }

        $candidateScore = 0;

        foreach ($markers as $marker) {
            $candidateScore += substr_count($candidate, $marker);
        }

        if ($candidateScore >= $score) {
            break;
        }

        $value = $candidate;
    }

    return $value;
}

function licitation_annex_specification_values(mixed $value): array
{
    $values = [];

    if (is_array($value)) {
        foreach ($value as $entry) {
            $values = array_merge($values, licitation_annex_specification_values($entry));
        }
    } elseif (is_scalar($value)) {
        $text = trim(licitation_annex_repair_text((string) $value));

        if ($text !== '') {
            $lines = preg_split('/\R+/u', $text) ?: [];

            foreach ($lines as $line) {
                $line = trim($line);

                if ($line !== '') {
                    $values[] = $line;
                }
            }
        }
    }

    return array_values(array_unique($values));
}

function licitation_annex_specification_sections(array $item): array
{
    $specification = item_specification_array_from_value($item['specification'] ?? []);
    $warranty = $item['warranty'] ?? null;

    if (($warranty === null || trim((string) $warranty) === '') && array_key_exists('garantia', $specification)) {
        $warranty = $specification['garantia'];
    }

    return [
        [
            'label' => "Descri\u{00E7}\u{00E3}o m\u{00ED}nima",
            'values' => licitation_annex_specification_values($specification['descricao_minima'] ?? null),
            'list' => false,
        ],
        [
            'label' => "Caracter\u{00ED}sticas m\u{00ED}nimas",
            'values' => licitation_annex_specification_values($specification['caracteristicas_minimas'] ?? null),
            'list' => true,
        ],
        [
            'label' => "Crit\u{00E9}rios de aceita\u{00E7}\u{00E3}o",
            'values' => licitation_annex_specification_values($specification['criterios_aceitacao'] ?? null),
            'list' => true,
        ],
        [
            'label' => "Certificados (m\u{00ED}nimos)",
            'values' => licitation_annex_specification_values($specification['certificados'] ?? null),
            'list' => true,
        ],
        [
            'label' => "Observa\u{00E7}\u{00F5}es",
            'values' => licitation_annex_specification_values($specification['observacoes'] ?? null),
            'list' => true,
        ],
        [
            'label' => 'Garantia',
            'values' => licitation_annex_specification_values($warranty),
            'list' => false,
        ],
    ];
}

function licitation_annex_specification_text(array $item, string $separator = "\n"): string
{
    $parts = [];

    foreach (licitation_annex_specification_sections($item) as $section) {
        $values = $section['values'];
        $parts[] = $section['label'] . ':';

        if (!$values) {
            $parts[] = "N\u{00E3}o informado.";
            continue;
        }

        foreach ($values as $value) {
            $parts[] = !empty($section['list']) ? '- ' . $value : $value;
        }
    }

    return implode($separator, $parts);
}

function licitation_annex_specification_html(array $item): string
{
    $html = '<div class="annex-specification">';

    foreach (licitation_annex_specification_sections($item) as $section) {
        $values = $section['values'];
        $html .= '<div class="annex-spec-section">';
        $html .= '<strong class="annex-spec-title">' . e($section['label']) . '</strong>';

        if (!$values) {
            $html .= '<span class="annex-spec-empty">' . e("N\u{00E3}o informado.") . '</span>';
        } elseif (!empty($section['list'])) {
            $html .= '<ul>';

            foreach ($values as $value) {
                $html .= '<li>' . e($value) . '</li>';
            }

            $html .= '</ul>';
        } else {
            $html .= '<div>' . implode('<br>', array_map('e', $values)) . '</div>';
        }

        $html .= '</div>';
    }

    return $html . '</div>';
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
            $flags[(string) $supplierKey] = 'Poss' . "\u{00ED}" . 'vel pre' . "\u{00E7}" . 'o discrepante. Necess' . "\u{00E1}" . 'ria an' . "\u{00E1}" . 'lise e justificativa antes da exclus' . "\u{00E3}" . 'o.';
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
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡' => 'a',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â ' => 'a',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â£' => 'a',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢' => 'a',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¤' => 'a',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â' => 'A',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬' => 'A',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢' => 'A',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡' => 'A',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¾' => 'A',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©' => 'e',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Âª' => 'e',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â°' => 'E',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â ' => 'E',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â­' => 'i',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â' => 'I',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³' => 'o',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Âµ' => 'o',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â´' => 'o',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã¢â‚¬Å“' => 'O',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢' => 'O',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â' => 'O',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Âº' => 'u',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¼' => 'u',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡' => 'U',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œ' => 'U',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â§' => 'c',
        'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡' => 'C',
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

        throw new RuntimeException('NÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â£o foi possÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â­vel preparar a pasta de uploads.');
    }

    if (!is_writable($uploadDir)) {
        if (function_exists('app_log')) {
            app_log('error', 'Pasta de upload sem permissao de escrita: ' . $label, [
                'path' => $uploadDir,
                'owner' => function_exists('posix_geteuid') ? posix_geteuid() : null,
            ]);
        }

        throw new RuntimeException('NÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â£o foi possÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â­vel salvar o orÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â§amento. A pasta de uploads nÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â£o tem permissÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â£o de escrita.');
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
        throw new RuntimeException('Erro ao enviar o orÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â§amento.');
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
        throw new RuntimeException('Formato invÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡lido. Use PDF, DOC, DOCX, JPG, PNG ou WEBP.');
    }

    if ($file['size'] > 10 * 1024 * 1024) {
        throw new RuntimeException('O orÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â§amento deve ter no mÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ximo 10 MB.');
    }

    $extension = $allowedTypes[$mime];
    $filename = 'orcamento_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
    $uploadDir = supplier_quote_storage_dir();
    ensure_writable_upload_dir($uploadDir, 'orcamentos de fornecedores');

    $destination = $uploadDir . '/' . $filename;

    if (!@move_uploaded_file($file['tmp_name'], $destination)) {
        if (function_exists('app_log')) {
            app_log('error', 'Falha ao mover arquivo de orÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â§amento enviado.', [
                'destination' => $destination,
                'tmp_name' => $file['tmp_name'] ?? null,
                'upload_dir_writable' => is_writable($uploadDir),
                'last_error' => error_get_last()['message'] ?? null,
            ]);
        }

        throw new RuntimeException('NÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â£o foi possÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â­vel salvar o orÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â§amento. Verifique as permissÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Âµes da pasta de uploads.');
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
function demand_confirmation_storage_dir(): string
{
    return (defined('APP_STORAGE_PATH') ? APP_STORAGE_PATH : dirname(__DIR__) . '/storage')
        . '/uploads/demand_confirmations';
}

function demand_confirmation_file_path(string $filename): string
{
    return demand_confirmation_storage_dir() . '/' . basename($filename);
}

function demand_confirmation_file_url(int $requestId, string $kind, int $attachmentId = 0): string
{
    $url = '/demand_confirmation_file.php?id=' . $requestId . '&kind=' . rawurlencode($kind);

    return $attachmentId > 0 ? $url . '&attachment_id=' . $attachmentId : $url;
}
function demand_confirmation_default_statement(): string
{
    return 'Declaro, para os fins administrativos cabiveis, que conferi os itens, quantidades e informacoes da demanda apresentada e confirmo que correspondem a necessidade do setor/unidade requisitante.';
}

function demand_confirmation_token_url(string $token): string
{
    return '/?public_action=demand_confirmation_sign&token=' . rawurlencode($token);
}

function demand_confirmation_status_label(?string $status): string
{
    return match ($status) {
        'signed' => 'Assinada',
        'waiting' => 'Aguardando etapa anterior',
        'revoked' => 'Revogada',
        'expired' => 'Expirada',
        default => 'Pendente',
    };
}
function demand_confirmation_status_badge_class(?string $status): string
{
    return match ($status) {
        'signed' => 'text-bg-success',
        'waiting' => 'text-bg-info',
        'revoked' => 'text-bg-secondary',
        'expired' => 'text-bg-warning',
        default => 'text-bg-primary',
    };
}
function save_demand_confirmation_signature(string $dataUrl): string
{
    if (!preg_match('/^data:image\/(png|jpeg);base64,/', $dataUrl, $matches)) {
        throw new RuntimeException('Assinatura invalida. Assine novamente no campo indicado.');
    }

    $extension = $matches[1] === 'jpeg' ? 'jpg' : 'png';
    $payload = substr($dataUrl, strpos($dataUrl, ',') + 1);
    $binary = base64_decode($payload, true);

    if ($binary === false || strlen($binary) < 100) {
        throw new RuntimeException('Assinatura nao foi capturada corretamente.');
    }

    if (strlen($binary) > 2 * 1024 * 1024) {
        throw new RuntimeException('A assinatura deve ter no maximo 2 MB.');
    }

    $uploadDir = demand_confirmation_storage_dir();
    ensure_writable_upload_dir($uploadDir, 'assinaturas de demanda');

    $filename = 'assinatura_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $destination = $uploadDir . '/' . $filename;

    if (file_put_contents($destination, $binary) === false) {
        throw new RuntimeException('Nao foi possivel salvar a assinatura.');
    }

    return $filename;
}

function upload_demand_confirmation_document(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Envie uma foto ou PDF do documento para comprovacao.');
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Erro ao enviar o documento de comprovacao.');
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    $mime = mime_content_type($file['tmp_name']);

    if (!isset($allowedTypes[$mime])) {
        throw new RuntimeException('Documento invalido. Use JPG, PNG, WEBP ou PDF.');
    }

    if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
        throw new RuntimeException('O documento deve ter no maximo 10 MB.');
    }

    $uploadDir = demand_confirmation_storage_dir();
    ensure_writable_upload_dir($uploadDir, 'documentos de confirmacao de demanda');

    $filename = 'documento_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $allowedTypes[$mime];
    $destination = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Nao foi possivel salvar o documento de comprovacao.');
    }

    return $filename;
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
        throw new RuntimeException('Formato de imagem invÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡lido. Use JPG, PNG ou WEBP.');
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('A imagem deve ter no mÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ximo 2 MB.');
    }

    $extension = $allowedTypes[$mime];
    $filename = 'item_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;

    $uploadDir = __DIR__ . '/../public/uploads/items';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $destination = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('NÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â£o foi possÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â­vel salvar a imagem.');
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
            throw new RuntimeException('Formato invÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡lido. Use JPG, PNG ou WEBP.');
        }

        if ($files['size'][$index] > 2 * 1024 * 1024) {
            throw new RuntimeException('Cada imagem deve ter no mÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ximo 2 MB.');
        }

        $extension = $allowedTypes[$mime];

        $filename = 'item_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;

        $destination = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($tmpName, $destination)) {
            throw new RuntimeException('NÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â£o foi possÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â­vel salvar uma das imagens.');
        }

        $uploadedPaths[] = '/uploads/items/' . $filename;
    }

    return $uploadedPaths;
}
