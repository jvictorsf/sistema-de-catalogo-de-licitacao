<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';

function pg_bool(mixed $value): string
{
    if (is_string($value)) {
        $normalized = strtolower(trim($value));

        if (in_array($normalized, ['1', 'true', 't', 'yes', 'on'], true)) {
            return 'true';
        }

        if (in_array($normalized, ['0', 'false', 'f', 'no', 'off', ''], true)) {
            return 'false';
        }
    }

    return $value ? 'true' : 'false';
}

function is_missing_database_relation(Throwable $exception): bool
{
    $message = $exception->getMessage();

    return str_contains($message, 'SQLSTATE[42P01]')
        || (str_contains($message, 'relation') && str_contains($message, 'does not exist'))
        || (str_contains($message, 'relação') && str_contains($message, 'não existe'));
}

function log_optional_schema_issue(string $feature, Throwable $exception): void
{
    if (function_exists('app_log')) {
        app_log('warning', 'Recurso indisponivel por schema pendente: ' . $feature, [
            'error' => $exception->getMessage(),
        ]);
    }
}

function database_table_exists(string $tableName): bool
{
    $stmt = db()->prepare("SELECT to_regclass(:table_name)");
    $stmt->execute(['table_name' => 'public.' . $tableName]);
    $relation = $stmt->fetchColumn();

    return $relation !== false && $relation !== null;
}

function item_sort_options(): array
{
    return [
        'tracking_code' => item_tracking_code_sql('i'),
        'name' => 'i.name',
        'category' => 'c.name',
        'unit_type' => 'ut.name',
        'level' => 'i.level',
        'status' => 'i.status',
        'warranty' => 'i.warranty',
        'created_at' => 'i.created_at',
    ];
}

function item_tracking_code_sql(string $alias): string
{
    return "COALESCE({$alias}.tracking_code, 'CL' || LPAD({$alias}.id::TEXT, 6, '0'))";
}

function normalize_decimal_db_value(mixed $value): ?string
{
    $normalized = str_replace(',', '.', trim((string) $value));

    return $normalized === '' ? null : $normalized;
}

function ensure_item_tracking_code(int $id): void
{
    $stmt = db()->prepare("
        UPDATE procurement_items
        SET tracking_code = 'CL' || LPAD(id::TEXT, 6, '0')
        WHERE id = :id
          AND (tracking_code IS NULL OR tracking_code = '')
    ");

    $stmt->execute([
        'id' => $id,
    ]);
}

function get_categories(): array
{
    $stmt = db()->query("
        SELECT id, parent_id, name
        FROM categories
        ORDER BY parent_id NULLS FIRST, name
    ");

    return $stmt->fetchAll();
}

function get_parent_categories(): array
{
    $stmt = db()->query("
        SELECT id, name
        FROM categories
        WHERE parent_id IS NULL
        ORDER BY name
    ");

    return $stmt->fetchAll();
}

function get_subcategories(?int $parentId = null): array
{
    if ($parentId) {
        $stmt = db()->prepare("
            SELECT id, name
            FROM categories
            WHERE parent_id = :parent_id
            ORDER BY name
        ");
        $stmt->execute(['parent_id' => $parentId]);
        return $stmt->fetchAll();
    }

    $stmt = db()->query("
        SELECT id, parent_id, name
        FROM categories
        WHERE parent_id IS NOT NULL
        ORDER BY name
    ");

    return $stmt->fetchAll();
}

function search_items(array|string|null $filters = null): array
{
    if (is_string($filters) || $filters === null) {
        $filters = [
            'q' => $filters,
        ];
    }

    $trackingCodeSql = item_tracking_code_sql('i');

    $sql = "
        SELECT 
            i.*,
            {$trackingCodeSql} AS tracking_code,
            c.name AS category_name,
            s.name AS subcategory_name,
            ut.name AS unit_type_name,
            ut.abbreviation AS unit_type_abbreviation,
            content_ut.name AS package_content_unit_type_name,
            content_ut.abbreviation AS package_content_unit_type_abbreviation
        FROM procurement_items i
        LEFT JOIN categories c ON c.id = i.category_id
        LEFT JOIN categories s ON s.id = i.subcategory_id
        LEFT JOIN unit_types ut ON ut.id = i.unit_type_id
        LEFT JOIN unit_types content_ut ON content_ut.id = i.package_content_unit_type_id
        WHERE 1 = 1
    ";

    $params = [];

    if (!empty($filters['q'])) {
        $sql .= "
            AND (
                {$trackingCodeSql} ILIKE :q OR
                i.name ILIKE :q OR
                i.justification ILIKE :q OR
                i.environmental_impacts ILIKE :q OR
                c.name ILIKE :q OR
                s.name ILIKE :q OR
                content_ut.name ILIKE :q OR
                i.package_content_quantity::TEXT ILIKE :q
            )
        ";

        $params['q'] = '%' . $filters['q'] . '%';
    }

    if (!empty($filters['category_id'])) {
        $sql .= " AND i.category_id = :category_id";
        $params['category_id'] = (int) $filters['category_id'];
    }

    if (!empty($filters['subcategory_id'])) {
        $sql .= " AND i.subcategory_id = :subcategory_id";
        $params['subcategory_id'] = (int) $filters['subcategory_id'];
    }

    if (!empty($filters['level'])) {
        $sql .= " AND i.level = :level";
        $params['level'] = $filters['level'];
    }

    if (!empty($filters['status'])) {
        $sql .= " AND i.status = :status";
        $params['status'] = $filters['status'];
    }

    if (!empty($filters['unit_type_id'])) {
        $sql .= " AND i.unit_type_id = :unit_type_id";
        $params['unit_type_id'] = (int) $filters['unit_type_id'];
    }

    $sortOptions = item_sort_options();
    $sort = (string) ($filters['sort'] ?? 'created_at');
    $direction = strtolower((string) ($filters['direction'] ?? 'desc'));

    if (!isset($sortOptions[$sort])) {
        $sort = 'created_at';
    }

    if (!in_array($direction, ['asc', 'desc'], true)) {
        $direction = 'desc';
    }

    $sql .= ' ORDER BY ' . $sortOptions[$sort] . ' ' . strtoupper($direction) . ', i.id DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function find_item(int $id): ?array
{
    $trackingCodeSql = item_tracking_code_sql('i');

    $stmt = db()->prepare("
        SELECT 
            i.*,
            {$trackingCodeSql} AS tracking_code,
            c.name AS category_name,
            s.name AS subcategory_name,
            ut.name AS unit_type_name,
            ut.abbreviation AS unit_type_abbreviation,
            content_ut.name AS package_content_unit_type_name,
            content_ut.abbreviation AS package_content_unit_type_abbreviation
        FROM procurement_items i
        LEFT JOIN categories c ON c.id = i.category_id
        LEFT JOIN categories s ON s.id = i.subcategory_id
        LEFT JOIN unit_types ut ON ut.id = i.unit_type_id
        LEFT JOIN unit_types content_ut ON content_ut.id = i.package_content_unit_type_id
        WHERE i.id = :id
    ");
    $stmt->execute(['id' => $id]);

    $item = $stmt->fetch();

    return $item ?: null;
}

function create_item(array $data): int
{
    $data['specification'] = normalize_item_specification_json((string) $data['specification']);
    $data['environmental_impacts'] = normalize_environmental_impacts_json($data['environmental_impacts'] ?? '');

    $stmt = db()->prepare("
        INSERT INTO procurement_items (
            category_id,
            subcategory_id,
            unit_type_id,
            package_content_quantity,
            package_content_unit_type_id,
            level,
            status,
            name,
            specification,
            justification,
            warranty,
            environmental_impacts,
            image_path
        ) VALUES (
            :category_id,
            :subcategory_id,
            :unit_type_id,
            :package_content_quantity,
            :package_content_unit_type_id,
            :level,
            :status,
            :name,
            :specification::jsonb,
            :justification,
            :warranty,
            :environmental_impacts,
            :image_path
        )
        RETURNING id
    ");

    $stmt->execute([
        'category_id' => $data['category_id'] ?: null,
        'subcategory_id' => $data['subcategory_id'] ?: null,
        'unit_type_id' => $data['unit_type_id'] ?: null,
        'package_content_quantity' => normalize_decimal_db_value($data['package_content_quantity'] ?? null),
        'package_content_unit_type_id' => $data['package_content_unit_type_id'] ?: null,
        'level' => $data['level'],
        'status' => $data['status'],
        'name' => $data['name'],
        'specification' => $data['specification'],
        'justification' => $data['justification'],
        'warranty' => $data['warranty'],
        'environmental_impacts' => $data['environmental_impacts'],
        'image_path' => $data['image_path'] ?? null,
    ]);

    $id = (int) $stmt->fetchColumn();

    ensure_item_tracking_code($id);

    return $id;
}

function update_item(int $id, array $data): void
{
    $data['specification'] = normalize_item_specification_json((string) $data['specification']);
    $data['environmental_impacts'] = normalize_environmental_impacts_json($data['environmental_impacts'] ?? '');

    $stmt = db()->prepare("
    UPDATE procurement_items SET
        category_id = :category_id,
        subcategory_id = :subcategory_id,
        unit_type_id = :unit_type_id,
        package_content_quantity = :package_content_quantity,
        package_content_unit_type_id = :package_content_unit_type_id,
        level = :level,
        status = :status,
        name = :name,
        specification = :specification::jsonb,
        justification = :justification,
        warranty = :warranty,
        environmental_impacts = :environmental_impacts,
        image_path = :image_path
    WHERE id = :id
");

    $stmt->execute([
        'id' => $id,
        'category_id' => $data['category_id'] ?: null,
        'subcategory_id' => $data['subcategory_id'] ?: null,
        'unit_type_id' => $data['unit_type_id'] ?: null,
        'package_content_quantity' => normalize_decimal_db_value($data['package_content_quantity'] ?? null),
        'package_content_unit_type_id' => $data['package_content_unit_type_id'] ?: null,
        'level' => $data['level'],
        'status' => $data['status'],
        'name' => $data['name'],
        'specification' => $data['specification'],
        'justification' => $data['justification'],
        'warranty' => $data['warranty'],
        'environmental_impacts' => $data['environmental_impacts'],
        'image_path' => $data['image_path'] ?? null,
    ]);
}

function delete_item(int $id): void
{
    $stmt = db()->prepare("DELETE FROM procurement_items WHERE id = :id");
    $stmt->execute(['id' => $id]);
}

function create_category(array $data): int
{
    $stmt = db()->prepare("
        INSERT INTO categories (
            parent_id,
            name
        ) VALUES (
            :parent_id,
            :name
        )
        RETURNING id
    ");

    $stmt->execute([
        'parent_id' => $data['parent_id'] ?: null,
        'name' => $data['name'],
    ]);

    return (int) $stmt->fetchColumn();
}

function update_category(int $id, array $data): void
{
    $stmt = db()->prepare("
        UPDATE categories
        SET
            parent_id = :parent_id,
            name = :name
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $id,
        'parent_id' => $data['parent_id'] ?: null,
        'name' => $data['name'],
    ]);
}

function delete_category(int $id): void
{
    $stmt = db()->prepare("
        DELETE FROM categories
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $id,
    ]);
}

function find_category(int $id): ?array
{
    $stmt = db()->prepare("
        SELECT *
        FROM categories
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $id,
    ]);

    $category = $stmt->fetch();

    return $category ?: null;
}

function get_categories_tree(): array
{
    $stmt = db()->query("
        SELECT
            c.id,
            c.name,
            c.parent_id,
            p.name AS parent_name
        FROM categories c
        LEFT JOIN categories p ON p.id = c.parent_id
        ORDER BY
            p.name NULLS FIRST,
            c.name
    ");

    return $stmt->fetchAll();
}

function get_projects(): array
{
    $stmt = db()->query("
        SELECT *
        FROM procurement_projects
        ORDER BY id DESC
    ");

    return $stmt->fetchAll();
}

function fetch_project_row(int $id): ?array
{
    $stmt = db()->prepare("
        SELECT *
        FROM procurement_projects
        WHERE id = :id
    ");

    $stmt->execute(['id' => $id]);

    $project = $stmt->fetch();

    return $project ?: null;
}

function find_project(int $id): ?array
{
    $project = fetch_project_row($id);

    if (!$project) {
        return null;
    }

    return enforce_project_reopen_deadline($project);
}

function assert_project_editable(?int $projectId): void
{
    if (!$projectId) {
        return;
    }

    $project = find_project($projectId);

    if ($project && project_is_locked($project)) {
        throw new RuntimeException(project_locked_edit_message($project));
    }
}

function project_closure_payload(int $projectId): array
{
    $project = find_project($projectId);

    if (!$project) {
        throw new RuntimeException('Projeto nao encontrado.');
    }

    return [
        'type' => 'project_status_hash',
        'project' => [
            'id' => (int) $project['id'],
            'name' => (string) ($project['name'] ?? ''),
            'description' => (string) ($project['description'] ?? ''),
            'status' => (string) ($project['status'] ?? ''),
            'cancellation_reason' => (string) ($project['cancellation_reason'] ?? ''),
            'canceled_at' => (string) ($project['canceled_at'] ?? ''),
            'reopen_reason' => (string) ($project['reopen_reason'] ?? ''),
            'reopen_mode' => (string) ($project['reopen_mode'] ?? ''),
            'reopen_correction_deadline' => (string) ($project['reopen_correction_deadline'] ?? ''),
            'reopened_at' => (string) ($project['reopened_at'] ?? ''),
        ],
        'demands' => array_map(static fn (array $demand): array => [
            'id' => (int) ($demand['id'] ?? 0),
            'name' => (string) ($demand['name'] ?? ''),
            'secretariat' => (string) ($demand['secretariat_name'] ?? ''),
            'requester_department' => (string) ($demand['requester_department'] ?? ''),
            'responsible_name' => (string) ($demand['responsible_name'] ?? ''),
        ], get_project_demands($projectId)),
        'items' => array_map(static fn (array $item): array => [
            'licitation_number' => $item['licitation_number'] !== null ? (int) $item['licitation_number'] : null,
            'procurement_item_id' => (int) ($item['procurement_item_id'] ?? 0),
            'tracking_code' => (string) ($item['tracking_code'] ?? ''),
            'item_name' => (string) ($item['item_name'] ?? ''),
            'unit' => licitation_annex_unit_text($item),
            'quantity' => (float) ($item['total_approved_quantity'] ?? 0),
            'estimated_unit_price' => (float) ($item['average_unit_price'] ?? 0),
            'estimated_total' => (float) ($item['estimated_total'] ?? 0),
        ], get_project_consolidated_items($projectId)),
        'items_by_demand' => array_map(static fn (array $item): array => [
            'demand_id' => (int) ($item['demand_id'] ?? 0),
            'procurement_item_id' => (int) ($item['procurement_item_id'] ?? 0),
            'quantity' => (float) ($item['approved_quantity'] ?? 0),
            'estimated_total' => (float) ($item['calculated_total'] ?? 0),
        ], get_project_items_by_demand($projectId)),
        'lots' => array_map(static fn (array $lot): array => [
            'id' => (int) ($lot['id'] ?? 0),
            'lot_number' => (int) ($lot['lot_number'] ?? 0),
            'name' => (string) ($lot['name'] ?? ''),
            'justification' => (string) ($lot['justification'] ?? ''),
        ], get_project_lot_denominations($projectId)),
        'lot_assignments' => array_map(static fn (array $assignment): array => [
            'project_lot_id' => (int) ($assignment['project_lot_id'] ?? 0),
            'assignment_type' => (string) ($assignment['assignment_type'] ?? ''),
            'procurement_item_id' => $assignment['procurement_item_id'] !== null ? (int) $assignment['procurement_item_id'] : null,
            'category_id' => $assignment['category_id'] !== null ? (int) $assignment['category_id'] : null,
        ], get_project_lot_assignments($projectId)),
    ];
}

function project_closure_hash(int $projectId): string
{
    return project_annex_hash(project_closure_payload($projectId));
}

function refresh_project_closure_hash(int $projectId): string
{
    $hash = project_closure_hash($projectId);

    $stmt = db()->prepare("
        UPDATE procurement_projects
        SET closure_hash = :closure_hash,
            closed_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");
    $stmt->execute([
        'id' => $projectId,
        'closure_hash' => $hash,
    ]);

    return $hash;
}

function normalize_document_hash_input(string $hash): string
{
    return strtolower((string) preg_replace('/[^a-f0-9]/i', '', $hash));
}

function find_document_hash_records(string $hash): array
{
    $normalized = normalize_document_hash_input($hash);

    if ($normalized === '') {
        return [];
    }

    $needle = strlen($normalized) >= 64 ? substr($normalized, 0, 64) : $normalized . '%';
    $operator = strlen($normalized) >= 64 ? '=' : 'LIKE';
    $records = [];

    if (database_table_exists('project_annex_versions')) {
        $stmt = db()->prepare("
            SELECT
                'annex' AS record_type,
                pav.project_id,
                p.name AS project_name,
                p.status AS project_status,
                pav.annex_type,
                pav.version_number,
                pav.content_hash,
                pav.status,
                pav.item_count,
                pav.total_value,
                pav.generated_at,
                pav.invalidated_at
            FROM project_annex_versions pav
            INNER JOIN procurement_projects p ON p.id = pav.project_id
            WHERE lower(pav.content_hash) {$operator} :hash
            ORDER BY pav.generated_at DESC
            LIMIT 20
        ");
        $stmt->execute(['hash' => $needle]);

        foreach ($stmt->fetchAll() as $record) {
            $record['annex_label'] = project_annex_types()[$record['annex_type']] ?? $record['annex_type'];
            $records[] = $record;
        }
    }

    if (database_table_exists('project_status_events')) {
        $stmt = db()->prepare("
            SELECT
                'project_status_event' AS record_type,
                pse.project_id,
                p.name AS project_name,
                p.status AS project_status,
                pse.event_hash AS content_hash,
                pse.created_at AS generated_at,
                pse.from_status,
                pse.to_status,
                pse.reason,
                pse.reopen_mode,
                pse.correction_deadline
            FROM project_status_events pse
            INNER JOIN procurement_projects p ON p.id = pse.project_id
            WHERE lower(pse.event_hash) {$operator} :hash
            ORDER BY pse.created_at DESC
            LIMIT 20
        ");
        $stmt->execute(['hash' => $needle]);

        foreach ($stmt->fetchAll() as $record) {
            $record['annex_label'] = project_status_event_label($record['to_status'] ?? null);
            $record['status'] = 'valid';
            $records[] = $record;
        }
    }
    try {
        $stmt = db()->prepare("
            SELECT
                'project_closure' AS record_type,
                id AS project_id,
                name AS project_name,
                status AS project_status,
                closure_hash AS content_hash,
                closed_at AS generated_at
            FROM procurement_projects
            WHERE closure_hash IS NOT NULL
              AND lower(closure_hash) {$operator} :hash
            ORDER BY closed_at DESC NULLS LAST
            LIMIT 20
        ");
        $stmt->execute(['hash' => $needle]);

        foreach ($stmt->fetchAll() as $record) {
            $record['annex_label'] = project_status_hash_label($record['project_status'] ?? null);
            $record['status'] = in_array($record['project_status'] ?? '', ['closed', 'canceled', 'reopened'], true) ? 'valid' : 'rectification';
            $records[] = $record;
        }
    } catch (Throwable $exception) {
        if (!str_contains($exception->getMessage(), 'SQLSTATE[42703]')) {
            throw $exception;
        }

        log_optional_schema_issue('hash de fechamento do projeto', $exception);
    }

    return $records;
}

function get_project_demand_group_report(
    int $projectId,
    string $groupBy = 'unit',
    ?string $filterKey = null,
    ?string $filterBy = null
): array
{
    $groupBy = $groupBy === 'secretariat' ? 'secretariat' : 'unit';
    $filterBy = in_array($filterBy, ['unit', 'secretariat'], true) ? $filterBy : $groupBy;
    $filterKey = $filterKey !== null ? trim($filterKey) : null;
    $filterKey = $filterKey !== '' ? $filterKey : null;

    $groups = [];
    $globalQuantity = 0.0;
    $globalTotal = 0.0;

    foreach (get_project_items_by_demand($projectId) as $item) {
        $itemFilterKey = project_demand_report_group_key($filterBy, $item);
        $groupKey = project_demand_report_group_key($groupBy, $item);

        if ($filterKey !== null && $itemFilterKey !== $filterKey) {
            continue;
        }

        $groupName = project_demand_report_group_name($groupBy, $item);
        $itemKey = (int) ($item['procurement_item_id'] ?? 0);
        $quantity = (float) ($item['approved_quantity'] ?? $item['quantity'] ?? 0);
        $total = (float) ($item['calculated_total'] ?? $item['estimated_total'] ?? 0);

        if (!isset($groups[$groupName])) {
            $groups[$groupName] = [
                'key' => $groupKey,
                'name' => $groupName,
                'items' => [],
                'quantity' => 0.0,
                'total' => 0.0,
            ];
        }

        if (!isset($groups[$groupName]['items'][$itemKey])) {
            $groups[$groupName]['items'][$itemKey] = [
                'sequence' => $item['licitation_number'] !== null ? (int) $item['licitation_number'] : null,
                'procurement_item_id' => $itemKey,
                'tracking_code' => (string) ($item['tracking_code'] ?? ''),
                'item_name' => (string) ($item['item_name'] ?? ''),
                'unit' => licitation_annex_unit_text($item),
                'quantity' => 0.0,
                'estimated_unit_price' => null,
                'estimated_total' => 0.0,
                'uses_supplier_average' => false,
            ];
        }

        $groups[$groupName]['items'][$itemKey]['quantity'] += $quantity;
        $groups[$groupName]['items'][$itemKey]['estimated_total'] += $total;
        $groups[$groupName]['items'][$itemKey]['uses_supplier_average'] =
            $groups[$groupName]['items'][$itemKey]['uses_supplier_average']
            || !empty($item['uses_supplier_average']);

        $groups[$groupName]['quantity'] += $quantity;
        $groups[$groupName]['total'] += $total;
        $globalQuantity += $quantity;
        $globalTotal += $total;
    }

    foreach ($groups as $groupName => $group) {
        foreach ($group['items'] as $itemKey => $row) {
            $quantity = (float) ($row['quantity'] ?? 0);
            $groups[$groupName]['items'][$itemKey]['estimated_unit_price'] = $quantity > 0
                ? round_money_value((float) $row['estimated_total'] / $quantity)
                : null;
            $groups[$groupName]['items'][$itemKey]['estimated_total'] = round_money_value((float) $row['estimated_total']);
        }

        uasort($groups[$groupName]['items'], static function (array $left, array $right): int {
            $leftNumber = (int) ($left['sequence'] ?? 0);
            $rightNumber = (int) ($right['sequence'] ?? 0);

            if ($leftNumber > 0 || $rightNumber > 0) {
                return ($leftNumber ?: PHP_INT_MAX) <=> ($rightNumber ?: PHP_INT_MAX);
            }

            return strcasecmp((string) ($left['item_name'] ?? ''), (string) ($right['item_name'] ?? ''));
        });

        $groups[$groupName]['items'] = array_values($groups[$groupName]['items']);
        $groups[$groupName]['total'] = round_money_value((float) $group['total']);
    }

    uasort($groups, static fn (array $left, array $right): int => strcasecmp($left['name'], $right['name']));

    return [
        'group_by' => $groupBy,
        'groups' => array_values($groups),
        'total_quantity' => $globalQuantity,
        'global_total' => round_money_value($globalTotal),
    ];
}

function project_demand_report_group_name(string $groupBy, array $source): string
{
    if ($groupBy === 'secretariat') {
        return (string) (($source['secretariat_name'] ?? '') ?: 'Sem secretaria vinculada');
    }

    return (string) (($source['requester_department'] ?? '') ?: 'Sem unidade vinculada');
}

function project_demand_report_group_key(string $groupBy, array $source): string
{
    if ($groupBy === 'secretariat') {
        return !empty($source['secretariat_id'])
            ? 'secretariat:' . (int) $source['secretariat_id']
            : 'secretariat:none';
    }

    if (!empty($source['requester_unit_id'])) {
        return 'unit:' . (int) $source['requester_unit_id'];
    }

    return 'unit:manual:' . sha1(mb_strtolower(project_demand_report_group_name('unit', $source)));
}

function get_project_demand_report_options(int $projectId, string $groupBy = 'unit'): array
{
    $groupBy = $groupBy === 'secretariat' ? 'secretariat' : 'unit';
    $options = [];

    foreach (get_project_items_by_demand($projectId) as $item) {
        $key = project_demand_report_group_key($groupBy, $item);

        if (isset($options[$key])) {
            continue;
        }

        $options[$key] = [
            'key' => $key,
            'name' => project_demand_report_group_name($groupBy, $item),
        ];
    }

    uasort($options, static fn (array $left, array $right): int => strcasecmp($left['name'], $right['name']));

    return array_values($options);
}

function project_status_hash_label(?string $status): string
{
    return match ($status) {
        'closed' => 'Fechamento do projeto',
        'canceled' => 'Cancelamento do projeto',
        'reopened' => 'Reabertura do projeto',
        default => 'Hash do projeto',
    };
}

function project_status_event_label(?string $status): string
{
    return match ($status) {
        'closed' => 'Fechamento automatico do projeto',
        'canceled' => 'Cancelamento do projeto',
        'reopened' => 'Reabertura do projeto',
        default => 'Evento de status do projeto',
    };
}

function project_status_snapshot(int $projectId, ?array $project = null): array
{
    $project ??= fetch_project_row($projectId) ?? [];

    return [
        'captured_at' => date(DATE_ATOM),
        'project' => $project,
        'financial_summary' => get_project_financial_summary($projectId),
        'demands' => get_project_demands($projectId),
        'consolidated_items' => get_project_consolidated_items($projectId),
        'items_by_demand' => get_project_items_by_demand($projectId),
        'lots' => get_project_lot_denominations($projectId),
        'lot_assignments' => get_project_lot_assignments($projectId),
        'annex_statuses' => get_project_annex_statuses($projectId),
    ];
}

function record_project_status_event(
    int $projectId,
    ?string $fromStatus,
    string $toStatus,
    string $reason,
    ?string $reopenMode = null,
    ?string $correctionDeadline = null,
    ?array $projectSnapshotSource = null
): ?string {
    if (!database_table_exists('project_status_events')) {
        return null;
    }

    $snapshot = project_status_snapshot($projectId, $projectSnapshotSource);
    $payload = [
        'type' => 'project_status_event',
        'project_id' => $projectId,
        'from_status' => $fromStatus,
        'to_status' => $toStatus,
        'reason' => $reason,
        'reopen_mode' => $reopenMode,
        'correction_deadline' => $correctionDeadline,
        'snapshot' => $snapshot,
    ];
    $eventHash = project_annex_hash($payload);

    $stmt = db()->prepare("
        INSERT INTO project_status_events (
            project_id,
            from_status,
            to_status,
            reason,
            reopen_mode,
            correction_deadline,
            snapshot,
            event_hash
        ) VALUES (
            :project_id,
            :from_status,
            :to_status,
            :reason,
            :reopen_mode,
            :correction_deadline,
            CAST(:snapshot AS jsonb),
            :event_hash
        )
    ");

    $stmt->execute([
        'project_id' => $projectId,
        'from_status' => $fromStatus,
        'to_status' => $toStatus,
        'reason' => $reason,
        'reopen_mode' => $reopenMode,
        'correction_deadline' => $correctionDeadline,
        'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'event_hash' => $eventHash,
    ]);

    return $eventHash;
}

function get_project_status_events(int $projectId): array
{
    if (!database_table_exists('project_status_events')) {
        return [];
    }

    $stmt = db()->prepare("
        SELECT *
        FROM project_status_events
        WHERE project_id = :project_id
        ORDER BY created_at DESC, id DESC
    ");
    $stmt->execute(['project_id' => $projectId]);

    return $stmt->fetchAll();
}

function project_reopen_deadline_expired(array $project): bool
{
    if (($project['status'] ?? '') !== 'reopened') {
        return false;
    }

    if (($project['reopen_mode'] ?? '') !== 'correction') {
        return false;
    }

    $deadline = trim((string) ($project['reopen_correction_deadline'] ?? ''));

    return $deadline !== '' && $deadline < date('Y-m-d');
}

function enforce_project_reopen_deadline(array $project): array
{
    if (!project_reopen_deadline_expired($project)) {
        return $project;
    }

    $projectId = (int) $project['id'];
    $reason = 'Fechamento automatico por prazo de correcao expirado.';

    db()->beginTransaction();

    try {
        record_project_status_event(
            $projectId,
            (string) ($project['status'] ?? ''),
            'closed',
            $reason,
            (string) ($project['reopen_mode'] ?? ''),
            (string) ($project['reopen_correction_deadline'] ?? ''),
            $project
        );

        $stmt = db()->prepare("
            UPDATE procurement_projects
            SET status = 'closed'
            WHERE id = :id
        ");
        $stmt->execute(['id' => $projectId]);

        refresh_project_closure_hash($projectId);
        db()->commit();
    } catch (Throwable $exception) {
        db()->rollBack();
        throw $exception;
    }

    return fetch_project_row($projectId) ?? $project;
}

function normalize_project_reopen_deadline(mixed $value): ?string
{
    $date = normalize_optional_date($value);

    if ($date === null) {
        return null;
    }

    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        throw new InvalidArgumentException('Informe o prazo de correcao no formato AAAA-MM-DD.');
    }

    return $date;
}
function create_project(array $data): int
{
    $status = (string) ($data['status'] ?? 'draft');

    if (in_array($status, ['rectification', 'reopened'], true)) {
        throw new InvalidArgumentException('Este status deve ser usado apenas em projetos existentes.');
    }

    $cancellationReason = trim((string) ($data['cancellation_reason'] ?? ''));

    if ($status === 'canceled' && $cancellationReason === '') {
        throw new InvalidArgumentException('Informe a justificativa do cancelamento.');
    }

    $stmt = db()->prepare("
        INSERT INTO procurement_projects (
            name,
            description,
            status,
            cancellation_reason,
            canceled_at
        ) VALUES (
            :name,
            :description,
            :status,
            :cancellation_reason,
            CASE WHEN :status = 'canceled' THEN CURRENT_TIMESTAMP ELSE NULL END
        )
        RETURNING id
    ");

    $stmt->execute([
        'name' => $data['name'],
        'description' => $data['description'] ?? null,
        'status' => $status,
        'cancellation_reason' => $status === 'canceled' ? $cancellationReason : null,
    ]);

    $projectId = (int) $stmt->fetchColumn();

    if (in_array($status, ['closed', 'canceled'], true)) {
        if ($status === 'canceled') {
            record_project_status_event($projectId, null, 'canceled', $cancellationReason);
            invalidate_project_annex_versions($projectId);
        }

        refresh_project_closure_hash($projectId);
    }

    return $projectId;
}

function update_project(int $id, array $data): void
{
    $project = find_project($id);

    if (!$project) {
        throw new RuntimeException('Projeto nao encontrado.');
    }

    $currentStatus = (string) ($project['status'] ?? 'draft');
    $nextStatus = (string) ($data['status'] ?? 'draft');
    $allowedStatuses = array_keys(project_status_options_for_form($project));

    if (!in_array($nextStatus, $allowedStatuses, true)) {
        throw new InvalidArgumentException('Transicao de status invalida para este projeto.');
    }

    if ($currentStatus === 'closed' && $nextStatus === 'rectification') {
        $data['name'] = $project['name'];
        $data['description'] = $project['description'];
    }

    if ($currentStatus === 'closed' && $nextStatus === 'canceled') {
        $data['name'] = $project['name'];
        $data['description'] = $project['description'];
    }

    if ($currentStatus === 'canceled') {
        $data['name'] = $project['name'];
        $data['description'] = $project['description'];
    }

    $cancellationReason = trim((string) ($data['cancellation_reason'] ?? ''));
    $reopenReason = trim((string) ($data['reopen_reason'] ?? ''));
    $reopenMode = (string) ($data['reopen_mode'] ?? 'continuity');
    $reopenDeadline = null;

    if ($nextStatus === 'canceled' && $currentStatus !== 'canceled') {
        if ($cancellationReason === '') {
            throw new InvalidArgumentException('Informe a justificativa do cancelamento.');
        }
    } else {
        $cancellationReason = (string) ($project['cancellation_reason'] ?? '');
    }

    if ($nextStatus === 'reopened' && $currentStatus !== 'canceled') {
        throw new InvalidArgumentException('Apenas projetos cancelados podem ser reabertos.');
    }

    if ($nextStatus === 'reopened' && $currentStatus === 'canceled') {
        if ($reopenReason === '') {
            throw new InvalidArgumentException('Informe a justificativa da reabertura.');
        }

        if (!array_key_exists($reopenMode, project_reopen_mode_options())) {
            throw new InvalidArgumentException('Selecione um tipo de reabertura valido.');
        }

        $reopenDeadline = normalize_project_reopen_deadline($data['reopen_correction_deadline'] ?? null);

        if ($reopenMode === 'correction') {
            if ($reopenDeadline === null) {
                throw new InvalidArgumentException('Informe o prazo de correcao para a reabertura.');
            }

            if ($reopenDeadline < date('Y-m-d')) {
                throw new InvalidArgumentException('O prazo de correcao nao pode estar no passado.');
            }
        } else {
            $reopenDeadline = null;
        }
    } else {
        $reopenReason = (string) ($project['reopen_reason'] ?? '');
        $reopenMode = $project['reopen_mode'] ?: null;
        $reopenDeadline = $project['reopen_correction_deadline'] ?: null;
    }

    db()->beginTransaction();

    try {
        if ($nextStatus === 'canceled' && $currentStatus !== 'canceled') {
            record_project_status_event($id, $currentStatus, 'canceled', $cancellationReason, null, null, $project);
        }

        if ($nextStatus === 'reopened' && $currentStatus === 'canceled') {
            record_project_status_event($id, $currentStatus, 'reopened', $reopenReason, $reopenMode, $reopenDeadline, $project);
        }

        $stmt = db()->prepare("
            UPDATE procurement_projects SET
                name = :name,
                description = :description,
                status = :status,
                cancellation_reason = :cancellation_reason,
                canceled_at = CASE
                    WHEN :status = 'canceled' AND status <> 'canceled' THEN CURRENT_TIMESTAMP
                    ELSE canceled_at
                END,
                reopen_reason = :reopen_reason,
                reopened_at = CASE
                    WHEN :status = 'reopened' AND status <> 'reopened' THEN CURRENT_TIMESTAMP
                    ELSE reopened_at
                END,
                reopen_mode = :reopen_mode,
                reopen_correction_deadline = :reopen_correction_deadline
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'status' => $nextStatus,
            'cancellation_reason' => $cancellationReason !== '' ? $cancellationReason : null,
            'reopen_reason' => $reopenReason !== '' ? $reopenReason : null,
            'reopen_mode' => $reopenMode,
            'reopen_correction_deadline' => $reopenDeadline,
        ]);

        if ($nextStatus === 'canceled' && $currentStatus !== 'canceled') {
            invalidate_project_annex_versions($id);
        }

        if ($nextStatus === 'reopened' && $currentStatus === 'canceled') {
            invalidate_project_annex_versions($id);
        }

        if (in_array($nextStatus, ['closed', 'canceled', 'reopened'], true)) {
            refresh_project_closure_hash($id);
        }

        db()->commit();
    } catch (Throwable $exception) {
        db()->rollBack();
        throw $exception;
    }
}

function delete_project(int $id): void
{
    assert_project_editable($id);

    $stmt = db()->prepare("
        DELETE FROM procurement_projects
        WHERE id = :id
    ");

    $stmt->execute(['id' => $id]);
}

function get_secretariats(bool $activeOnly = false): array
{
    $sql = "
        SELECT *
        FROM secretariats
    ";

    if ($activeOnly) {
        $sql .= " WHERE is_active = TRUE";
    }

    $sql .= " ORDER BY name";

    return db()->query($sql)->fetchAll();
}

function find_secretariat(int $id): ?array
{
    $stmt = db()->prepare("
        SELECT *
        FROM secretariats
        WHERE id = :id
    ");

    $stmt->execute(['id' => $id]);

    $secretariat = $stmt->fetch();

    return $secretariat ?: null;
}

function create_secretariat(array $data): int
{
    $stmt = db()->prepare("
        INSERT INTO secretariats (name, is_active)
        VALUES (:name, :is_active)
        RETURNING id
    ");

    $stmt->execute([
        'name' => $data['name'],
        'is_active' => pg_bool($data['is_active'] ?? true),
    ]);

    return (int) $stmt->fetchColumn();
}

function update_secretariat(int $id, array $data): void
{
    $stmt = db()->prepare("
        UPDATE secretariats SET
            name = :name,
            is_active = :is_active
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $id,
        'name' => $data['name'],
        'is_active' => pg_bool($data['is_active'] ?? true),
    ]);
}

function deactivate_secretariat(int $id): void
{
    $stmt = db()->prepare("
        UPDATE secretariats
        SET is_active = FALSE
        WHERE id = :id
    ");

    $stmt->execute(['id' => $id]);
}

function get_requester_units(bool $activeOnly = false): array
{
    $sql = "
        SELECT
            ru.*,
            p.name AS parent_unit_name,
            p.default_responsible_name AS parent_responsible_name,
            CASE
                WHEN p.id IS NOT NULL THEN p.name || ' - ' || ru.name
                ELSE ru.name
            END AS display_name,
            s.name AS secretariat_name,
            s.is_active AS secretariat_is_active
        FROM requester_units ru
        LEFT JOIN requester_units p ON p.id = ru.parent_id
        LEFT JOIN secretariats s ON s.id = ru.secretariat_id
    ";

    if ($activeOnly) {
        $sql .= " WHERE ru.is_active = TRUE AND COALESCE(p.is_active, TRUE) = TRUE AND COALESCE(s.is_active, TRUE) = TRUE";
    }

    $sql .= " ORDER BY s.name NULLS LAST, p.name NULLS FIRST, ru.name";

    return db()->query($sql)->fetchAll();
}

function get_requester_parent_units(?int $excludeId = null): array
{
    $sql = "
        SELECT
            ru.*,
            s.name AS secretariat_name
        FROM requester_units ru
        LEFT JOIN secretariats s ON s.id = ru.secretariat_id
        WHERE ru.parent_id IS NULL
    ";

    $params = [];

    if ($excludeId) {
        $sql .= " AND ru.id <> :exclude_id";
        $params['exclude_id'] = $excludeId;
    }

    $sql .= " ORDER BY s.name NULLS LAST, ru.name";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function find_requester_unit(int $id): ?array
{
    $stmt = db()->prepare("
        SELECT
            ru.*,
            p.name AS parent_unit_name,
            p.default_responsible_name AS parent_responsible_name,
            CASE
                WHEN p.id IS NOT NULL THEN p.name || ' - ' || ru.name
                ELSE ru.name
            END AS display_name,
            s.name AS secretariat_name
        FROM requester_units ru
        LEFT JOIN requester_units p ON p.id = ru.parent_id
        LEFT JOIN secretariats s ON s.id = ru.secretariat_id
        WHERE ru.id = :id
    ");

    $stmt->execute(['id' => $id]);

    $unit = $stmt->fetch();

    return $unit ?: null;
}

function create_requester_unit(array $data): int
{
    $stmt = db()->prepare("
        INSERT INTO requester_units (
            parent_id,
            secretariat_id,
            name,
            default_responsible_name,
            is_active
        ) VALUES (
            :parent_id,
            :secretariat_id,
            :name,
            :default_responsible_name,
            :is_active
        )
        RETURNING id
    ");

    $stmt->execute([
        'parent_id' => $data['parent_id'] ?: null,
        'secretariat_id' => $data['secretariat_id'] ?: null,
        'name' => $data['name'],
        'default_responsible_name' => $data['default_responsible_name'] ?? null,
        'is_active' => pg_bool($data['is_active'] ?? true),
    ]);

    return (int) $stmt->fetchColumn();
}

function update_requester_unit(int $id, array $data): void
{
    $stmt = db()->prepare("
        UPDATE requester_units SET
            parent_id = :parent_id,
            secretariat_id = :secretariat_id,
            name = :name,
            default_responsible_name = :default_responsible_name,
            is_active = :is_active
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $id,
        'parent_id' => $data['parent_id'] ?: null,
        'secretariat_id' => $data['secretariat_id'] ?: null,
        'name' => $data['name'],
        'default_responsible_name' => $data['default_responsible_name'] ?? null,
        'is_active' => pg_bool($data['is_active'] ?? true),
    ]);
}

function deactivate_requester_unit(int $id): void
{
    $stmt = db()->prepare("
        UPDATE requester_units
        SET is_active = FALSE
        WHERE id = :id
    ");

    $stmt->execute(['id' => $id]);
}

function normalize_cnae_reference_code(mixed $code): string
{
    return substr(only_digits((string) $code), 0, 7);
}

function cnae_reference_to_supplier_cnae(array $reference): array
{
    return [
        'code' => (string) ($reference['code'] ?? ''),
        'name' => (string) ($reference['subclass_description'] ?? ''),
        'description' => (string) ($reference['subclass_description'] ?? ''),
    ];
}

function find_cnae_reference(string $code): ?array
{
    $code = normalize_cnae_reference_code($code);

    if ($code === '') {
        return null;
    }

    try {
        $stmt = db()->prepare('
            SELECT *
            FROM cnae_references
            WHERE code = :code
        ');
        $stmt->execute(['code' => $code]);
        $reference = $stmt->fetch();
    } catch (Throwable $exception) {
        if (is_missing_database_relation($exception)) {
            return null;
        }

        throw $exception;
    }

    return $reference ?: null;
}

function search_cnae_references(string $query, int $limit = 20): array
{
    $query = trim($query);

    if ($query === '') {
        return [];
    }

    $limit = max(1, min($limit, 50));
    $digits = normalize_cnae_reference_code($query);
    $params = [
        'q' => '%' . mb_strtolower($query) . '%',
        'code' => $digits !== '' ? $digits . '%' : '__sem_codigo__',
        'exact_code' => $digits,
    ];

    try {
        $stmt = db()->prepare("
            SELECT *
            FROM cnae_references
            WHERE code LIKE :code
               OR LOWER(COALESCE(subclass_description, '')) LIKE :q
               OR LOWER(COALESCE(class_description, '')) LIKE :q
               OR LOWER(COALESCE(group_description, '')) LIKE :q
               OR LOWER(COALESCE(division_description, '')) LIKE :q
            ORDER BY
                CASE
                    WHEN code = :exact_code THEN 0
                    WHEN code LIKE :code THEN 1
                    ELSE 2
                END,
                subclass_description
            LIMIT {$limit}
        ");
        $stmt->execute($params);
    } catch (Throwable $exception) {
        if (is_missing_database_relation($exception)) {
            return [];
        }

        throw $exception;
    }

    return $stmt->fetchAll();
}

function enrich_supplier_cnae_from_reference(?array $cnae): ?array
{
    if ($cnae === null) {
        return null;
    }

    $code = normalize_cnae_reference_code($cnae['code'] ?? '');
    $reference = $code !== '' ? find_cnae_reference($code) : null;

    if (!$reference) {
        return $cnae;
    }

    $referenceCnae = cnae_reference_to_supplier_cnae($reference);

    return [
        'code' => $referenceCnae['code'],
        'name' => trim((string) ($cnae['name'] ?? '')) !== '' ? $cnae['name'] : $referenceCnae['name'],
        'description' => trim((string) ($cnae['description'] ?? '')) !== '' ? $cnae['description'] : $referenceCnae['description'],
    ];
}

function get_suppliers(bool $activeOnly = false): array
{
    $sql = "
        SELECT *
        FROM suppliers
    ";

    if ($activeOnly) {
        $sql .= " WHERE is_active = TRUE";
    }

    $sql .= " ORDER BY name";

    return array_map('normalize_supplier_row', db()->query($sql)->fetchAll());
}

function get_suppliers_filtered(array $filters = []): array
{
    $sql = "
        SELECT *
        FROM suppliers
        WHERE 1 = 1
    ";
    $params = [];

    $query = trim((string) ($filters['q'] ?? ''));

    if ($query !== '') {
        $queryDigits = only_digits($query);
        $params['q'] = '%' . mb_strtolower($query) . '%';
        $params['q_digits'] = $queryDigits !== '' ? '%' . $queryDigits . '%' : '__sem_digitos__';
        $sql .= "
            AND (
                LOWER(COALESCE(name, '')) LIKE :q
                OR LOWER(COALESCE(trade_name, '')) LIKE :q
                OR LOWER(COALESCE(contact_name, '')) LIKE :q
                OR LOWER(COALESCE(email, '')) LIKE :q
                OR LOWER(COALESCE(city, '')) LIKE :q
                OR LOWER(COALESCE(company_size, '')) LIKE :q
                OR LOWER(COALESCE(special_status, '')) LIKE :q
                OR LOWER(COALESCE(main_cnae::TEXT, '')) LIKE :q
                OR LOWER(COALESCE(secondary_cnaes::TEXT, '')) LIKE :q
                OR LOWER(COALESCE(state_registration, '')) LIKE :q
                OR LOWER(COALESCE(municipal_registration, '')) LIKE :q
                OR LOWER(COALESCE(website_url, '')) LIKE :q
                OR COALESCE(document, '') LIKE :q_digits
                OR COALESCE(phone, '') LIKE :q_digits
            )
        ";
    }

    $status = (string) ($filters['status'] ?? '');

    if ($status === 'active') {
        $sql .= " AND is_active = TRUE";
    } elseif ($status === 'inactive') {
        $sql .= " AND is_active = FALSE";
    }

    $bidding = (string) ($filters['bidding'] ?? '');

    if ($bidding === 'yes') {
        $sql .= " AND participates_bidding = TRUE";
    } elseif ($bidding === 'no') {
        $sql .= " AND participates_bidding = FALSE";
    }

    $state = normalize_supplier_state($filters['state'] ?? null);

    if ($state !== null) {
        $params['state'] = $state;
        $sql .= " AND state = :state";
    }

    $companySize = trim((string) ($filters['company_size'] ?? ''));

    if ($companySize !== '') {
        $params['company_size'] = $companySize;
        $sql .= " AND company_size = :company_size";
    }

    $sql .= " ORDER BY name";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return array_map('normalize_supplier_row', $stmt->fetchAll());
}

function find_supplier(int $id): ?array
{
    $stmt = db()->prepare("
        SELECT *
        FROM suppliers
        WHERE id = :id
    ");

    $stmt->execute(['id' => $id]);

    $supplier = $stmt->fetch();

    return $supplier ? normalize_supplier_row($supplier) : null;
}

function create_supplier(array $data): int
{
    $stmt = db()->prepare("
        INSERT INTO suppliers (
            name,
            trade_name,
            document,
            contact_name,
            email,
            phone,
            address,
            city,
            state,
            postal_code,
            state_registration,
            municipal_registration,
            company_size,
            share_capital,
            special_status,
            special_status_date,
            main_cnae,
            secondary_cnaes,
            participates_bidding,
            website_url,
            bank_name,
            bank_agency,
            bank_account,
            owner_cpf,
            owner_name,
            notes,
            is_active
        ) VALUES (
            :name,
            :trade_name,
            :document,
            :contact_name,
            :email,
            :phone,
            :address,
            :city,
            :state,
            :postal_code,
            :state_registration,
            :municipal_registration,
            :company_size,
            :share_capital,
            :special_status,
            :special_status_date,
            CAST(:main_cnae AS jsonb),
            CAST(:secondary_cnaes AS jsonb),
            :participates_bidding,
            :website_url,
            :bank_name,
            :bank_agency,
            :bank_account,
            :owner_cpf,
            :owner_name,
            :notes,
            :is_active
        )
        RETURNING id
    ");

    $stmt->execute([
        'name' => $data['name'],
        'trade_name' => ($data['trade_name'] ?? '') ?: null,
        'document' => normalize_supplier_document($data['document'] ?? null),
        'contact_name' => $data['contact_name'] ?: null,
        'email' => $data['email'] ?: null,
        'phone' => normalize_supplier_phone($data['phone'] ?? null),
        'address' => normalize_supplier_upper_text($data['address'] ?? null),
        'city' => normalize_supplier_upper_text($data['city'] ?? null),
        'state' => normalize_supplier_state($data['state'] ?? null),
        'postal_code' => normalize_postal_code($data['postal_code'] ?? null),
        'state_registration' => ($data['state_registration'] ?? '') ?: null,
        'municipal_registration' => ($data['municipal_registration'] ?? '') ?: null,
        'company_size' => ($data['company_size'] ?? '') ?: null,
        'share_capital' => normalize_supplier_share_capital($data['share_capital'] ?? null),
        'special_status' => ($data['special_status'] ?? '') ?: null,
        'special_status_date' => normalize_optional_date($data['special_status_date'] ?? null),
        'main_cnae' => supplier_cnae_to_json(normalize_supplier_cnae($data['main_cnae'] ?? [])),
        'secondary_cnaes' => supplier_cnae_list_to_json($data['secondary_cnaes'] ?? []),
        'participates_bidding' => pg_bool($data['participates_bidding'] ?? true),
        'website_url' => normalize_supplier_url($data['website_url'] ?? null),
        'bank_name' => ($data['bank_name'] ?? '') ?: null,
        'bank_agency' => ($data['bank_agency'] ?? '') ?: null,
        'bank_account' => ($data['bank_account'] ?? '') ?: null,
        'owner_cpf' => normalize_supplier_document($data['owner_cpf'] ?? null),
        'owner_name' => ($data['owner_name'] ?? '') ?: null,
        'notes' => $data['notes'] ?: null,
        'is_active' => pg_bool($data['is_active'] ?? true),
    ]);

    return (int) $stmt->fetchColumn();
}

function update_supplier(int $id, array $data): void
{
    $stmt = db()->prepare("
        UPDATE suppliers SET
            name = :name,
            trade_name = :trade_name,
            document = :document,
            contact_name = :contact_name,
            email = :email,
            phone = :phone,
            address = :address,
            city = :city,
            state = :state,
            postal_code = :postal_code,
            state_registration = :state_registration,
            municipal_registration = :municipal_registration,
            company_size = :company_size,
            share_capital = :share_capital,
            special_status = :special_status,
            special_status_date = :special_status_date,
            main_cnae = CAST(:main_cnae AS jsonb),
            secondary_cnaes = CAST(:secondary_cnaes AS jsonb),
            participates_bidding = :participates_bidding,
            website_url = :website_url,
            bank_name = :bank_name,
            bank_agency = :bank_agency,
            bank_account = :bank_account,
            owner_cpf = :owner_cpf,
            owner_name = :owner_name,
            notes = :notes,
            is_active = :is_active
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $id,
        'name' => $data['name'],
        'trade_name' => ($data['trade_name'] ?? '') ?: null,
        'document' => normalize_supplier_document($data['document'] ?? null),
        'contact_name' => $data['contact_name'] ?: null,
        'email' => $data['email'] ?: null,
        'phone' => normalize_supplier_phone($data['phone'] ?? null),
        'address' => normalize_supplier_upper_text($data['address'] ?? null),
        'city' => normalize_supplier_upper_text($data['city'] ?? null),
        'state' => normalize_supplier_state($data['state'] ?? null),
        'postal_code' => normalize_postal_code($data['postal_code'] ?? null),
        'state_registration' => ($data['state_registration'] ?? '') ?: null,
        'municipal_registration' => ($data['municipal_registration'] ?? '') ?: null,
        'company_size' => ($data['company_size'] ?? '') ?: null,
        'share_capital' => normalize_supplier_share_capital($data['share_capital'] ?? null),
        'special_status' => ($data['special_status'] ?? '') ?: null,
        'special_status_date' => normalize_optional_date($data['special_status_date'] ?? null),
        'main_cnae' => supplier_cnae_to_json(normalize_supplier_cnae($data['main_cnae'] ?? [])),
        'secondary_cnaes' => supplier_cnae_list_to_json($data['secondary_cnaes'] ?? []),
        'participates_bidding' => pg_bool($data['participates_bidding'] ?? true),
        'website_url' => normalize_supplier_url($data['website_url'] ?? null),
        'bank_name' => ($data['bank_name'] ?? '') ?: null,
        'bank_agency' => ($data['bank_agency'] ?? '') ?: null,
        'bank_account' => ($data['bank_account'] ?? '') ?: null,
        'owner_cpf' => normalize_supplier_document($data['owner_cpf'] ?? null),
        'owner_name' => ($data['owner_name'] ?? '') ?: null,
        'notes' => $data['notes'] ?: null,
        'is_active' => pg_bool($data['is_active'] ?? true),
    ]);
}

function deactivate_supplier(int $id): void
{
    $stmt = db()->prepare("
        UPDATE suppliers
        SET is_active = FALSE
        WHERE id = :id
    ");

    $stmt->execute(['id' => $id]);
}

function normalize_supplier_document(?string $document): ?string
{
    $digits = only_digits($document);

    return $digits !== '' ? $digits : null;
}

function normalize_supplier_state(?string $state): ?string
{
    $state = strtoupper(trim((string) $state));

    return $state !== '' ? substr($state, 0, 2) : null;
}

function normalize_postal_code(?string $postalCode): ?string
{
    $digits = only_digits($postalCode);

    return $digits !== '' ? $digits : null;
}

function normalize_supplier_phone(?string $phone): ?string
{
    $digits = only_digits($phone);

    return $digits !== '' ? $digits : null;
}

function normalize_supplier_share_capital(mixed $value): ?string
{
    $normalized = trim((string) $value);

    if ($normalized === '') {
        return null;
    }

    $normalized = preg_replace('/[^0-9,.-]/', '', $normalized) ?? '';

    if (str_contains($normalized, ',')) {
        $normalized = str_replace('.', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);
    }

    if ($normalized === '' || !is_numeric($normalized)) {
        return null;
    }

    return number_format((float) $normalized, 2, '.', '');
}

function normalize_supplier_row(array $supplier): array
{
    if (array_key_exists('main_cnae', $supplier)) {
        $supplier['main_cnae'] = supplier_cnae_from_json($supplier['main_cnae'] ?? null);
    }

    if (array_key_exists('secondary_cnaes', $supplier)) {
        $supplier['secondary_cnaes'] = supplier_cnae_list_from_json($supplier['secondary_cnaes'] ?? []);
    }

    return $supplier;
}

function normalize_supplier_upper_text(?string $value): ?string
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    return function_exists('mb_strtoupper')
        ? mb_strtoupper($value, 'UTF-8')
        : strtoupper($value);
}

function normalize_supplier_url(?string $url): ?string
{
    $url = trim((string) $url);

    if ($url === '') {
        return null;
    }

    if (!preg_match('~^https?://~i', $url)) {
        $url = 'https://' . $url;
    }

    return $url;
}

function normalize_supplier_cnae(mixed $value): ?array
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = json_last_error() === JSON_ERROR_NONE ? $decoded : ['code' => $value];
    }

    if (!is_array($value)) {
        return null;
    }

    $code = trim((string) ($value['code'] ?? $value['codigo'] ?? $value['number'] ?? $value['numero'] ?? ''));
    $name = trim((string) ($value['name'] ?? $value['nome'] ?? $value['label'] ?? ''));
    $description = trim((string) ($value['description'] ?? $value['descricao'] ?? $value['descricao_cnae'] ?? ''));

    if ($code === '' && $name === '' && $description === '') {
        return null;
    }

    if ($name === '' && $description !== '') {
        $name = $description;
    }

    return [
        'code' => $code,
        'name' => $name,
        'description' => $description,
    ];
}

function normalize_supplier_cnae_list(mixed $items): array
{
    if (is_string($items)) {
        $decoded = json_decode($items, true);
        $items = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
    }

    if (!is_array($items)) {
        return [];
    }

    $normalized = [];

    foreach ($items as $item) {
        $cnae = normalize_supplier_cnae($item);

        if ($cnae !== null) {
            $normalized[] = $cnae;
        }
    }

    return $normalized;
}

function supplier_cnae_from_json(mixed $value): ?array
{
    return normalize_supplier_cnae($value);
}

function supplier_cnae_list_from_json(mixed $value): array
{
    return normalize_supplier_cnae_list($value);
}

function supplier_cnae_to_json(?array $cnae): ?string
{
    $cnae = enrich_supplier_cnae_from_reference($cnae);

    return $cnae === null
        ? null
        : json_encode($cnae, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function supplier_cnae_list_to_json(mixed $items): string
{
    $items = array_map(
        static fn (array $cnae): array => enrich_supplier_cnae_from_reference($cnae) ?? $cnae,
        normalize_supplier_cnae_list($items)
    );

    return json_encode(
        $items,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}

function normalize_demand_requester_data(array $data): array
{
    $unitId = (int) ($data['requester_unit_id'] ?? 0);

    if (!$unitId) {
        $data['requester_unit_id'] = null;
        $data['secretariat_id'] = !empty($data['secretariat_id']) ? (int) $data['secretariat_id'] : null;
        return $data;
    }

    $unit = find_requester_unit($unitId);

    if (!$unit) {
        $data['requester_unit_id'] = null;
        return $data;
    }

    $data['requester_unit_id'] = (int) $unit['id'];
    $data['secretariat_id'] = $unit['secretariat_id'] ? (int) $unit['secretariat_id'] : null;
    $data['requester_department'] = $unit['display_name'] ?? $unit['name'];

    if (empty($data['responsible_name']) && !empty($unit['default_responsible_name'])) {
        $data['responsible_name'] = $unit['default_responsible_name'];
    }

    if (empty($data['responsible_name']) && !empty($unit['parent_responsible_name'])) {
        $data['responsible_name'] = $unit['parent_responsible_name'];
    }

    return $data;
}

function get_project_demands(int $projectId): array
{
    $stmt = db()->prepare("
        SELECT
            dl.*,
            CASE
                WHEN parent_ru.id IS NOT NULL THEN parent_ru.name || ' - ' || ru.name
                ELSE ru.name
            END AS requester_unit_name,
            ru.default_responsible_name,
            s.name AS secretariat_name
        FROM demand_lists dl
        LEFT JOIN requester_units ru ON ru.id = dl.requester_unit_id
        LEFT JOIN requester_units parent_ru ON parent_ru.id = ru.parent_id
        LEFT JOIN secretariats s ON s.id = dl.secretariat_id
        WHERE dl.project_id = :project_id
        ORDER BY s.name NULLS LAST, dl.name
    ");

    $stmt->execute(['project_id' => $projectId]);

    return $stmt->fetchAll();
}

function find_demand_list(int $id): ?array
{
    $stmt = db()->prepare("
        SELECT
            dl.*,
            CASE
                WHEN parent_ru.id IS NOT NULL THEN parent_ru.name || ' - ' || ru.name
                ELSE ru.name
            END AS requester_unit_name,
            ru.default_responsible_name,
            s.name AS secretariat_name
        FROM demand_lists dl
        LEFT JOIN requester_units ru ON ru.id = dl.requester_unit_id
        LEFT JOIN requester_units parent_ru ON parent_ru.id = ru.parent_id
        LEFT JOIN secretariats s ON s.id = dl.secretariat_id
        WHERE dl.id = :id
    ");

    $stmt->execute(['id' => $id]);

    $demand = $stmt->fetch();

    return $demand ?: null;
}

function create_demand_list(array $data): int
{
    $data = normalize_demand_requester_data($data);
    assert_project_editable((int) $data['project_id']);

    $stmt = db()->prepare("
        INSERT INTO demand_lists (
            project_id,
            requester_unit_id,
            secretariat_id,
            name,
            requester_department,
            responsible_name,
            notes
        ) VALUES (
            :project_id,
            :requester_unit_id,
            :secretariat_id,
            :name,
            :requester_department,
            :responsible_name,
            :notes
        )
        RETURNING id
    ");

    $stmt->execute([
        'project_id' => $data['project_id'],
        'requester_unit_id' => $data['requester_unit_id'] ?? null,
        'secretariat_id' => $data['secretariat_id'] ?? null,
        'name' => $data['name'],
        'requester_department' => $data['requester_department'] ?? null,
        'responsible_name' => $data['responsible_name'] ?? null,
        'notes' => $data['notes'] ?? null,
    ]);

    return (int) $stmt->fetchColumn();
}

function update_demand_list(int $id, array $data): void
{
    assert_project_editable(find_project_id_by_demand_list($id));
    $data = normalize_demand_requester_data($data);

    $stmt = db()->prepare("
        UPDATE demand_lists SET
            requester_unit_id = :requester_unit_id,
            secretariat_id = :secretariat_id,
            name = :name,
            requester_department = :requester_department,
            responsible_name = :responsible_name,
            notes = :notes
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $id,
        'requester_unit_id' => $data['requester_unit_id'] ?? null,
        'secretariat_id' => $data['secretariat_id'] ?? null,
        'name' => $data['name'],
        'requester_department' => $data['requester_department'] ?? null,
        'responsible_name' => $data['responsible_name'] ?? null,
        'notes' => $data['notes'] ?? null,
    ]);
}

function delete_demand_list(int $id): void
{
    $projectId = find_project_id_by_demand_list($id);
    assert_project_editable($projectId);

    $stmt = db()->prepare("
        DELETE FROM demand_lists
        WHERE id = :id
    ");

    $stmt->execute(['id' => $id]);
    invalidate_project_annex_versions($projectId);
}

function get_demand_items(int $demandListId): array
{
    $trackingCodeSql = item_tracking_code_sql('pi');

    $stmt = db()->prepare("
        SELECT
            di.*,
            pi.name AS item_name,
            {$trackingCodeSql} AS tracking_code,
            pi.specification,
            pi.warranty,
            pi.environmental_impacts,
            (COALESCE(di.approved_quantity, di.quantity) * COALESCE(di.estimated_unit_price, 0)) AS estimated_total,
            ut.name AS unit_type_name,
            ut.abbreviation AS unit_type_abbreviation,
            content_ut.name AS package_content_unit_type_name,
            content_ut.abbreviation AS package_content_unit_type_abbreviation,
            pi.package_content_quantity
        FROM demand_items di
        INNER JOIN procurement_items pi ON pi.id = di.procurement_item_id
        LEFT JOIN unit_types ut ON ut.id = pi.unit_type_id
        LEFT JOIN unit_types content_ut ON content_ut.id = pi.package_content_unit_type_id
        WHERE di.demand_list_id = :demand_list_id
        ORDER BY pi.name
    ");

    $stmt->execute(['demand_list_id' => $demandListId]);

    return $stmt->fetchAll();
}

function add_demand_item(array $data): void
{
    assert_project_editable(find_project_id_by_demand_list((int) $data['demand_list_id']));

    $stmt = db()->prepare("
        INSERT INTO demand_items (
            demand_list_id,
            procurement_item_id,
            quantity,
            approved_quantity,
            estimated_unit_price,
            notes
        ) VALUES (
            :demand_list_id,
            :procurement_item_id,
            :quantity,
            :approved_quantity,
            :estimated_unit_price,
            :notes
        )
        ON CONFLICT (demand_list_id, procurement_item_id)
        DO UPDATE SET
            quantity = demand_items.quantity + EXCLUDED.quantity,
            approved_quantity = COALESCE(demand_items.approved_quantity, 0) + EXCLUDED.approved_quantity,
            estimated_unit_price = EXCLUDED.estimated_unit_price,
            notes = EXCLUDED.notes
    ");

    $stmt->execute([
        'demand_list_id' => $data['demand_list_id'],
        'procurement_item_id' => $data['procurement_item_id'],
        'quantity' => $data['quantity'],
        'approved_quantity' => $data['approved_quantity'] ?? $data['quantity'],
        'estimated_unit_price' => $data['estimated_unit_price'] ?? null,
        'notes' => $data['notes'] ?? null,
    ]);

    invalidate_project_annex_versions(find_project_id_by_demand_list((int) $data['demand_list_id']));
}

function delete_demand_item(int $id): void
{
    $projectId = find_project_id_by_demand_item($id);
    assert_project_editable($projectId);

    $stmt = db()->prepare("
        DELETE FROM demand_items
        WHERE id = :id
    ");

    $stmt->execute(['id' => $id]);
    invalidate_project_annex_versions($projectId);
}

function update_demand_item(int $id, array $data): void
{
    $projectId = find_project_id_by_demand_item($id);
    assert_project_editable($projectId);

    $stmt = db()->prepare("
        UPDATE demand_items SET
            quantity = :quantity,
            approved_quantity = :approved_quantity,
            estimated_unit_price = :estimated_unit_price,
            notes = :notes
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $id,
        'quantity' => $data['quantity'],
        'approved_quantity' => $data['approved_quantity'],
        'estimated_unit_price' => $data['estimated_unit_price'],
        'notes' => $data['notes'] ?? null,
    ]);

    invalidate_project_annex_versions($projectId);
}

function normalize_money_value(mixed $value): ?float
{
    if ($value === null) {
        return null;
    }

    $normalized = trim((string) $value);

    if ($normalized === '') {
        return null;
    }

    if (str_contains($normalized, ',')) {
        $normalized = str_replace('.', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);
    }

    return round((float) $normalized, 2);
}

function normalize_optional_date(mixed $value): ?string
{
    $date = trim((string) $value);

    return $date === '' ? null : $date;
}

function project_annex_types(): array
{
    return [
        'annex_i' => 'Anexo I - Planilha de itens, especificacoes, quantitativos e memoria de calculo',
        'annex_ii' => 'Anexo II - Planilha de pesquisa e estimativa de precos',
        'annex_iii' => 'Anexo III - Quadro resumido da estimativa de precos',
        'lot_annex_i' => 'Anexo I por lote - Planilha de itens por denominacao',
        'lot_annex_ii' => 'Anexo II por lote - Pesquisa e estimativa de precos por lote',
        'lot_annex_iii' => 'Anexo III por lote - Quadro resumido por lote',
        'lot_annex_iv' => 'Anexo IV por lote - Quadro resumido da estimativa de precos',
    ];
}

function find_project_id_by_demand_list(int $demandListId): ?int
{
    $stmt = db()->prepare("SELECT project_id FROM demand_lists WHERE id = :id");
    $stmt->execute(['id' => $demandListId]);
    $projectId = $stmt->fetchColumn();

    return $projectId !== false ? (int) $projectId : null;
}

function find_project_id_by_demand_item(int $demandItemId): ?int
{
    $stmt = db()->prepare("
        SELECT dl.project_id
        FROM demand_items di
        INNER JOIN demand_lists dl ON dl.id = di.demand_list_id
        WHERE di.id = :id
    ");
    $stmt->execute(['id' => $demandItemId]);
    $projectId = $stmt->fetchColumn();

    return $projectId !== false ? (int) $projectId : null;
}

function find_project_id_by_supplier_quote(int $quoteId): ?int
{
    $stmt = db()->prepare("
        SELECT dl.project_id
        FROM demand_supplier_quotes q
        INNER JOIN demand_lists dl ON dl.id = q.demand_list_id
        WHERE q.id = :id
    ");
    $stmt->execute(['id' => $quoteId]);
    $projectId = $stmt->fetchColumn();

    return $projectId !== false ? (int) $projectId : null;
}

function invalidate_project_annex_versions(?int $projectId): void
{
    if (!$projectId) {
        return;
    }

    if (!database_table_exists('project_annex_versions')) {
        return;
    }

    try {
        $stmt = db()->prepare("
            UPDATE project_annex_versions
            SET status = 'invalid',
                invalidated_at = COALESCE(invalidated_at, CURRENT_TIMESTAMP)
            WHERE project_id = :project_id
              AND status = 'valid'
        ");
        $stmt->execute(['project_id' => $projectId]);
    } catch (Throwable $exception) {
        if (!is_missing_database_relation($exception)) {
            throw $exception;
        }

        log_optional_schema_issue('versoes de anexos', $exception);
    }
}

function apply_project_licitation_number_updates(int $projectId, array $rowNumbers): void
{
    if (!$rowNumbers) {
        return;
    }

    $offsetStmt = db()->prepare("
        SELECT COALESCE(MAX(licitation_number), 0) + COUNT(*) + 1000
        FROM project_licitation_items
        WHERE project_id = :project_id
    ");
    $offsetStmt->execute(['project_id' => $projectId]);
    $offset = max(1000, (int) $offsetStmt->fetchColumn());

    $shift = db()->prepare("
        UPDATE project_licitation_items
        SET licitation_number = licitation_number + :offset
        WHERE project_id = :project_id
    ");
    $shift->execute([
        'project_id' => $projectId,
        'offset' => $offset,
    ]);

    $update = db()->prepare("
        UPDATE project_licitation_items
        SET licitation_number = :licitation_number
        WHERE id = :id
          AND project_id = :project_id
    ");

    foreach ($rowNumbers as $rowId => $licitationNumber) {
        $update->execute([
            'id' => (int) $rowId,
            'project_id' => $projectId,
            'licitation_number' => (int) $licitationNumber,
        ]);
    }
}

function compact_project_licitation_numbers(int $projectId): void
{
    $stmt = db()->prepare("
        SELECT pli.id
        FROM project_licitation_items pli
        INNER JOIN procurement_items pi ON pi.id = pli.procurement_item_id
        LEFT JOIN categories c ON c.id = pi.category_id
        WHERE pli.project_id = :project_id
        ORDER BY pli.licitation_number, c.name NULLS LAST, pi.name, pi.id
    ");
    $stmt->execute(['project_id' => $projectId]);

    $rowNumbers = [];
    $sequence = 1;

    foreach ($stmt->fetchAll() as $row) {
        $rowNumbers[(int) $row['id']] = $sequence++;
    }

    apply_project_licitation_number_updates($projectId, $rowNumbers);
}

function sync_project_licitation_items(int $projectId): void
{
    $pdo = db();
    $startedTransaction = !$pdo->inTransaction();

    try {
        if ($startedTransaction) {
            $pdo->beginTransaction();
        }

        $delete = $pdo->prepare("
            DELETE FROM project_licitation_items pli
            WHERE pli.project_id = :project_id
              AND NOT EXISTS (
                  SELECT 1
                  FROM demand_items di
                  INNER JOIN demand_lists dl ON dl.id = di.demand_list_id
                  WHERE dl.project_id = pli.project_id
                    AND di.procurement_item_id = pli.procurement_item_id
              )
        ");
        $delete->execute(['project_id' => $projectId]);
        $needsCompact = $delete->rowCount() > 0;

        $nextStmt = $pdo->prepare("
            SELECT COALESCE(MAX(licitation_number), 0) + 1
            FROM project_licitation_items
            WHERE project_id = :project_id
        ");
        $nextStmt->execute(['project_id' => $projectId]);
        $nextNumber = max(1, (int) $nextStmt->fetchColumn());

        $missing = $pdo->prepare("
            SELECT DISTINCT
                pi.id AS procurement_item_id,
                c.name AS category_name,
                pi.name AS item_name
            FROM demand_items di
            INNER JOIN demand_lists dl ON dl.id = di.demand_list_id
            INNER JOIN procurement_items pi ON pi.id = di.procurement_item_id
            LEFT JOIN categories c ON c.id = pi.category_id
            LEFT JOIN project_licitation_items pli
                ON pli.project_id = dl.project_id
               AND pli.procurement_item_id = pi.id
            WHERE dl.project_id = :project_id
              AND pli.id IS NULL
            ORDER BY c.name NULLS LAST, pi.name, pi.id
        ");
        $missing->execute(['project_id' => $projectId]);

        $insert = $pdo->prepare("
            INSERT INTO project_licitation_items (
                project_id,
                procurement_item_id,
                licitation_number
            ) VALUES (
                :project_id,
                :procurement_item_id,
                :licitation_number
            )
            ON CONFLICT DO NOTHING
        ");

        foreach ($missing->fetchAll() as $row) {
            $insert->execute([
                'project_id' => $projectId,
                'procurement_item_id' => (int) $row['procurement_item_id'],
                'licitation_number' => $nextNumber++,
            ]);
        }

        if ($needsCompact) {
            compact_project_licitation_numbers($projectId);
        }

        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if (!is_missing_database_relation($exception)) {
            throw $exception;
        }

        log_optional_schema_issue('numeracao de licitacao', $exception);
    }
}

function get_project_licitation_number_map(int $projectId): array
{
    sync_project_licitation_items($projectId);

    try {
        $stmt = db()->prepare("
            SELECT procurement_item_id, licitation_number
            FROM project_licitation_items
            WHERE project_id = :project_id
        ");
        $stmt->execute(['project_id' => $projectId]);
    } catch (Throwable $exception) {
        if (!is_missing_database_relation($exception)) {
            throw $exception;
        }

        log_optional_schema_issue('numeracao de licitacao', $exception);
        return [];
    }

    $map = [];

    foreach ($stmt->fetchAll() as $row) {
        $map[(int) $row['procurement_item_id']] = (int) $row['licitation_number'];
    }

    return $map;
}

function save_project_licitation_numbers(int $projectId, array $numbers): void
{
    assert_project_editable($projectId);
    sync_project_licitation_items($projectId);

    $pdo = db();
    $startedTransaction = !$pdo->inTransaction();

    try {
        if ($startedTransaction) {
            $pdo->beginTransaction();
        }

        $stmt = $pdo->prepare("
            SELECT id, procurement_item_id, licitation_number
            FROM project_licitation_items
            WHERE project_id = :project_id
            ORDER BY licitation_number
        ");
        $stmt->execute(['project_id' => $projectId]);
        $rows = $stmt->fetchAll();
        $used = [];
        $rowNumbers = [];
        $hasChanges = false;

        foreach ($rows as $row) {
            $procurementItemId = (int) $row['procurement_item_id'];
            $rawNumber = $numbers[$procurementItemId] ?? $numbers[(string) $procurementItemId] ?? null;
            $licitationNumber = (int) $rawNumber;

            if ($licitationNumber <= 0) {
                throw new InvalidArgumentException('Informe apenas numeros de licitacao positivos.');
            }

            if (isset($used[$licitationNumber])) {
                throw new InvalidArgumentException('Cada numero de licitacao deve ser unico no projeto.');
            }

            $used[$licitationNumber] = true;
            $rowNumbers[(int) $row['id']] = $licitationNumber;
            $hasChanges = $hasChanges || $licitationNumber !== (int) $row['licitation_number'];
        }

        if ($hasChanges) {
            apply_project_licitation_number_updates($projectId, $rowNumbers);
            invalidate_project_annex_versions($projectId);
        }

        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if (is_missing_database_relation($exception)) {
            throw new RuntimeException('Atualize o schema do banco antes de ordenar os itens da licitacao.');
        }

        throw $exception;
    }
}

function renumber_project_licitation_items(int $projectId): void
{
    assert_project_editable($projectId);
    sync_project_licitation_items($projectId);

    $pdo = db();
    $startedTransaction = !$pdo->inTransaction();

    try {
        if ($startedTransaction) {
            $pdo->beginTransaction();
        }

        compact_project_licitation_numbers($projectId);
        invalidate_project_annex_versions($projectId);

        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if (is_missing_database_relation($exception)) {
            throw new RuntimeException('Atualize o schema do banco antes de renumerar os itens da licitacao.');
        }

        throw $exception;
    }
}

function get_next_project_lot_number(int $projectId): int
{
    if (!database_table_exists('project_lot_denominations')) {
        return 1;
    }

    $stmt = db()->prepare("
        SELECT COALESCE(MAX(lot_number), 0) + 1
        FROM project_lot_denominations
        WHERE project_id = :project_id
    ");
    $stmt->execute(['project_id' => $projectId]);

    return max(1, (int) $stmt->fetchColumn());
}

function get_project_lot_denominations(int $projectId): array
{
    if (!database_table_exists('project_lot_denominations')) {
        return [];
    }

    $stmt = db()->prepare("
        SELECT
            l.*,
            COUNT(pla.id) AS assignment_count,
            COUNT(pla.id) FILTER (WHERE pla.assignment_type = 'item') AS item_assignment_count,
            COUNT(pla.id) FILTER (WHERE pla.assignment_type = 'category') AS category_assignment_count
        FROM project_lot_denominations l
        LEFT JOIN project_lot_assignments pla ON pla.project_lot_id = l.id
        WHERE l.project_id = :project_id
        GROUP BY l.id
        ORDER BY l.lot_number, l.name
    ");
    $stmt->execute(['project_id' => $projectId]);

    return $stmt->fetchAll();
}

function find_project_lot_denomination(int $id): ?array
{
    if (!database_table_exists('project_lot_denominations')) {
        return null;
    }

    $stmt = db()->prepare("
        SELECT *
        FROM project_lot_denominations
        WHERE id = :id
    ");
    $stmt->execute(['id' => $id]);
    $lot = $stmt->fetch();

    return $lot ?: null;
}

function create_project_lot_denomination(array $data): int
{
    assert_project_editable((int) $data['project_id']);

    $stmt = db()->prepare("
        INSERT INTO project_lot_denominations (
            project_id,
            lot_number,
            name,
            justification
        ) VALUES (
            :project_id,
            :lot_number,
            :name,
            :justification
        )
        RETURNING id
    ");
    $stmt->execute([
        'project_id' => (int) $data['project_id'],
        'lot_number' => (int) $data['lot_number'],
        'name' => trim((string) $data['name']),
        'justification' => trim((string) $data['justification']),
    ]);

    $id = (int) $stmt->fetchColumn();
    invalidate_project_annex_versions((int) $data['project_id']);

    return $id;
}

function update_project_lot_denomination(int $id, array $data): void
{
    $lot = find_project_lot_denomination($id);

    if (!$lot) {
        throw new RuntimeException('Denominacao nao encontrada.');
    }

    assert_project_editable((int) $lot['project_id']);

    $stmt = db()->prepare("
        UPDATE project_lot_denominations
        SET lot_number = :lot_number,
            name = :name,
            justification = :justification
        WHERE id = :id
    ");
    $stmt->execute([
        'id' => $id,
        'lot_number' => (int) $data['lot_number'],
        'name' => trim((string) $data['name']),
        'justification' => trim((string) $data['justification']),
    ]);

    invalidate_project_annex_versions((int) $lot['project_id']);
}

function delete_project_lot_denomination(int $id): void
{
    $lot = find_project_lot_denomination($id);

    if (!$lot) {
        return;
    }

    assert_project_editable((int) $lot['project_id']);

    $stmt = db()->prepare("
        DELETE FROM project_lot_denominations
        WHERE id = :id
    ");
    $stmt->execute(['id' => $id]);

    invalidate_project_annex_versions((int) $lot['project_id']);
}

function get_project_lot_assignments(int $projectId): array
{
    if (!database_table_exists('project_lot_assignments')) {
        return [];
    }

    $trackingCodeSql = item_tracking_code_sql('pi');

    $stmt = db()->prepare("
        SELECT
            pla.*,
            l.project_id,
            l.lot_number,
            l.name AS lot_name,
            pi.name AS item_name,
            {$trackingCodeSql} AS tracking_code,
            c.name AS category_name,
            parent.name AS parent_category_name
        FROM project_lot_assignments pla
        INNER JOIN project_lot_denominations l ON l.id = pla.project_lot_id
        LEFT JOIN procurement_items pi ON pi.id = pla.procurement_item_id
        LEFT JOIN categories c ON c.id = pla.category_id
        LEFT JOIN categories parent ON parent.id = c.parent_id
        WHERE l.project_id = :project_id
        ORDER BY l.lot_number, pla.assignment_type, COALESCE(pi.name, c.name)
    ");
    $stmt->execute(['project_id' => $projectId]);

    return $stmt->fetchAll();
}

function add_project_lot_assignment(int $projectLotId, string $assignmentType, ?int $procurementItemId, ?int $categoryId): void
{
    $lot = find_project_lot_denomination($projectLotId);

    if (!$lot) {
        throw new RuntimeException('Denominacao nao encontrada.');
    }

    $assignmentType = $assignmentType === 'category' ? 'category' : 'item';
    $projectId = (int) $lot['project_id'];
    assert_project_editable($projectId);

    if ($assignmentType === 'item') {
        if (!$procurementItemId) {
            throw new InvalidArgumentException('Selecione um produto.');
        }

        $delete = db()->prepare("
            DELETE FROM project_lot_assignments pla
            USING project_lot_denominations l
            WHERE l.id = pla.project_lot_id
              AND l.project_id = :project_id
              AND pla.assignment_type = 'item'
              AND pla.procurement_item_id = :procurement_item_id
        ");
        $delete->execute([
            'project_id' => $projectId,
            'procurement_item_id' => $procurementItemId,
        ]);
    } else {
        if (!$categoryId) {
            throw new InvalidArgumentException('Selecione uma categoria ou subcategoria.');
        }

        $delete = db()->prepare("
            DELETE FROM project_lot_assignments pla
            USING project_lot_denominations l
            WHERE l.id = pla.project_lot_id
              AND l.project_id = :project_id
              AND pla.assignment_type = 'category'
              AND pla.category_id = :category_id
        ");
        $delete->execute([
            'project_id' => $projectId,
            'category_id' => $categoryId,
        ]);
    }

    $stmt = db()->prepare("
        INSERT INTO project_lot_assignments (
            project_lot_id,
            assignment_type,
            procurement_item_id,
            category_id
        ) VALUES (
            :project_lot_id,
            :assignment_type,
            :procurement_item_id,
            :category_id
        )
    ");
    $stmt->execute([
        'project_lot_id' => $projectLotId,
        'assignment_type' => $assignmentType,
        'procurement_item_id' => $assignmentType === 'item' ? $procurementItemId : null,
        'category_id' => $assignmentType === 'category' ? $categoryId : null,
    ]);

    invalidate_project_annex_versions($projectId);
}

function delete_project_lot_assignment(int $id): void
{
    if (!database_table_exists('project_lot_assignments')) {
        return;
    }

    $stmt = db()->prepare("
        SELECT l.project_id
        FROM project_lot_assignments pla
        INNER JOIN project_lot_denominations l ON l.id = pla.project_lot_id
        WHERE pla.id = :id
    ");
    $stmt->execute(['id' => $id]);
    $projectId = $stmt->fetchColumn();

    assert_project_editable($projectId !== false ? (int) $projectId : null);

    $delete = db()->prepare("
        DELETE FROM project_lot_assignments
        WHERE id = :id
    ");
    $delete->execute(['id' => $id]);

    invalidate_project_annex_versions($projectId !== false ? (int) $projectId : null);
}

function project_lot_unassigned_group(): array
{
    return [
        'lot_id' => null,
        'lot_number' => null,
        'name' => 'Itens sem denominacao',
        'justification' => 'Itens ainda nao vinculados a uma denominacao de lote.',
        'is_unassigned' => true,
        'items' => [],
        'subtotal' => 0.0,
    ];
}

function project_lot_key(?int $lotId): string
{
    return $lotId !== null ? 'lot:' . $lotId : 'unassigned';
}

function get_project_lot_groups(int $projectId, ?array $items = null): array
{
    $items = $items ?? get_project_licitation_annex_i_items($projectId);
    $lots = get_project_lot_denominations($projectId);
    $assignments = get_project_lot_assignments($projectId);
    $groups = [];
    $lotById = [];

    foreach ($lots as $lot) {
        $lotId = (int) $lot['id'];
        $key = project_lot_key($lotId);
        $lotById[$lotId] = $lot;
        $groups[$key] = [
            'lot_id' => $lotId,
            'lot_number' => (int) $lot['lot_number'],
            'name' => $lot['name'],
            'justification' => $lot['justification'],
            'is_unassigned' => false,
            'items' => [],
            'subtotal' => 0.0,
        ];
    }

    $directAssignments = [];
    $categoryAssignments = [];

    foreach ($assignments as $assignment) {
        $lotId = (int) $assignment['project_lot_id'];

        if (!isset($lotById[$lotId])) {
            continue;
        }

        if (($assignment['assignment_type'] ?? '') === 'item') {
            $directAssignments[(int) $assignment['procurement_item_id']] = $lotId;
            continue;
        }

        if (($assignment['assignment_type'] ?? '') === 'category') {
            $categoryAssignments[] = [
                'category_id' => (int) $assignment['category_id'],
                'lot_id' => $lotId,
                'lot_number' => (int) $assignment['lot_number'],
            ];
        }
    }

    usort($categoryAssignments, static fn (array $left, array $right): int => $left['lot_number'] <=> $right['lot_number']);

    foreach ($items as $item) {
        $procurementItemId = (int) ($item['procurement_item_id'] ?? 0);
        $lotId = $directAssignments[$procurementItemId] ?? null;

        if ($lotId === null) {
            $itemCategoryIds = array_filter([
                (int) ($item['category_id'] ?? 0),
                (int) ($item['subcategory_id'] ?? 0),
            ]);

            foreach ($categoryAssignments as $assignment) {
                if (in_array((int) $assignment['category_id'], $itemCategoryIds, true)) {
                    $lotId = (int) $assignment['lot_id'];
                    break;
                }
            }
        }

        $key = project_lot_key($lotId);

        if (!isset($groups[$key])) {
            $groups[$key] = project_lot_unassigned_group();
        }

        $groups[$key]['items'][] = $item;

        if (($item['estimated_total'] ?? null) !== null) {
            $groups[$key]['subtotal'] += (float) $item['estimated_total'];
        }
    }

    foreach ($groups as $key => $group) {
        usort($groups[$key]['items'], static function (array $left, array $right): int {
            return ((int) ($left['sequence'] ?? $left['licitation_number'] ?? PHP_INT_MAX))
                <=> ((int) ($right['sequence'] ?? $right['licitation_number'] ?? PHP_INT_MAX));
        });
    }

    uasort($groups, static function (array $left, array $right): int {
        $leftNumber = $left['lot_number'] ?? PHP_INT_MAX;
        $rightNumber = $right['lot_number'] ?? PHP_INT_MAX;

        return $leftNumber <=> $rightNumber;
    });

    return array_values(array_filter($groups, static fn (array $group): bool => (bool) ($group['items'] ?? [])));
}

function find_project_lot_group_for_item(array $lotGroups, int $procurementItemId): array
{
    foreach ($lotGroups as $group) {
        foreach ($group['items'] ?? [] as $item) {
            if ((int) ($item['procurement_item_id'] ?? 0) === $procurementItemId) {
                return $group;
            }
        }
    }

    return project_lot_unassigned_group();
}

function get_project_lot_licitation_annex_i_groups(int $projectId): array
{
    return get_project_lot_groups($projectId, get_project_licitation_annex_i_items($projectId));
}

function get_project_lot_licitation_annex_ii_groups(int $projectId): array
{
    $baseLotGroups = get_project_lot_groups($projectId, get_project_licitation_annex_i_items($projectId));
    $annex = get_project_licitation_annex_ii_groups($projectId);
    $lotGroups = [];
    $globalTotal = 0.0;

    foreach ($baseLotGroups as $group) {
        $key = project_lot_key($group['lot_id'] !== null ? (int) $group['lot_id'] : null);
        $group['supplier_groups'] = [];
        $group['subtotal'] = 0.0;
        $lotGroups[$key] = $group;
    }

    foreach ($annex['groups'] ?? [] as $supplierGroup) {
        foreach ($supplierGroup['items'] ?? [] as $item) {
            $lot = find_project_lot_group_for_item($baseLotGroups, (int) ($item['procurement_item_id'] ?? 0));
            $lotKey = project_lot_key($lot['lot_id'] !== null ? (int) $lot['lot_id'] : null);

            if (!isset($lotGroups[$lotKey])) {
                $lot['supplier_groups'] = [];
                $lot['subtotal'] = 0.0;
                $lotGroups[$lotKey] = $lot;
            }

            $supplierGroupKey = (string) ($supplierGroup['key'] ?? 'sem-cotacao');

            if (!isset($lotGroups[$lotKey]['supplier_groups'][$supplierGroupKey])) {
                $lotGroups[$lotKey]['supplier_groups'][$supplierGroupKey] = array_merge($supplierGroup, [
                    'items' => [],
                    'subtotal' => 0.0,
                ]);
            }

            $lotGroups[$lotKey]['supplier_groups'][$supplierGroupKey]['items'][] = $item;

            if (($item['estimated_total'] ?? null) !== null) {
                $estimatedTotal = (float) $item['estimated_total'];
                $lotGroups[$lotKey]['supplier_groups'][$supplierGroupKey]['subtotal'] += $estimatedTotal;
                $lotGroups[$lotKey]['subtotal'] += $estimatedTotal;
                $globalTotal += $estimatedTotal;
            }
        }
    }

    foreach ($lotGroups as $lotKey => $lotGroup) {
        $lotGroups[$lotKey]['supplier_groups'] = array_values(array_filter(
            $lotGroup['supplier_groups'],
            static fn (array $supplierGroup): bool => (bool) ($supplierGroup['items'] ?? [])
        ));
    }

    return [
        'lots' => array_values(array_filter(
            $lotGroups,
            static fn (array $lotGroup): bool => (bool) ($lotGroup['supplier_groups'] ?? [])
        )),
        'global_total' => round_money_value($globalTotal),
    ];
}

function get_project_lot_licitation_annex_iii_groups(int $projectId): array
{
    $summary = get_project_licitation_annex_iii_summary($projectId);
    $lotGroups = get_project_lot_groups($projectId, $summary['items'] ?? []);
    $globalTotal = 0.0;

    foreach ($lotGroups as $index => $lotGroup) {
        $subtotal = 0.0;

        foreach ($lotGroup['items'] ?? [] as $item) {
            if (($item['estimated_total'] ?? null) !== null) {
                $subtotal += (float) $item['estimated_total'];
            }
        }

        $lotGroups[$index]['subtotal'] = round_money_value($subtotal);
        $globalTotal += $subtotal;
    }

    return [
        'lots' => $lotGroups,
        'global_total' => round_money_value($globalTotal),
    ];
}

function project_annex_hash(array $payload): string
{
    return hash(
        'sha256',
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)
    );
}

function project_annex_payload(int $projectId, string $annexType): array
{
    if ($annexType === 'annex_i') {
        return [
            'type' => $annexType,
            'items' => array_map(static function (array $item): array {
                return [
                    'sequence' => (int) ($item['sequence'] ?? 0),
                    'procurement_item_id' => (int) ($item['procurement_item_id'] ?? 0),
                    'tracking_code' => (string) ($item['tracking_code'] ?? ''),
                    'item_name' => (string) ($item['item_name'] ?? ''),
                    'unit' => licitation_annex_unit_text($item),
                    'quantity' => (float) ($item['annex_quantity'] ?? 0),
                    'specification' => licitation_annex_specification_text($item),
                    'demand_memory' => array_map(static fn (array $memory): array => [
                        'demand_id' => (int) ($memory['demand_id'] ?? 0),
                        'quantity' => (float) ($memory['quantity'] ?? 0),
                    ], $item['demand_memory'] ?? []),
                ];
            }, get_project_licitation_annex_i_items($projectId)),
        ];
    }

    if ($annexType === 'annex_ii') {
        $annex = get_project_licitation_annex_ii_groups($projectId);

        return [
            'type' => $annexType,
            'global_total' => (float) ($annex['global_total'] ?? 0),
            'groups' => array_map(static function (array $group): array {
                return [
                    'key' => (string) ($group['key'] ?? ''),
                    'suppliers' => array_map(static fn (array $supplier): array => [
                        'key' => (string) ($supplier['key'] ?? ''),
                        'id' => (int) ($supplier['id'] ?? 0),
                        'name' => (string) ($supplier['name'] ?? ''),
                        'document' => (string) ($supplier['document'] ?? ''),
                        'proposal_dates' => array_values($supplier['proposal_dates'] ?? []),
                    ], $group['suppliers'] ?? []),
                    'items' => array_map(static fn (array $item): array => [
                        'sequence' => (int) ($item['sequence'] ?? 0),
                        'procurement_item_id' => (int) ($item['procurement_item_id'] ?? 0),
                        'item_name' => (string) ($item['item_name'] ?? ''),
                        'unit' => licitation_annex_unit_text($item),
                        'quantity' => (float) ($item['annex_quantity'] ?? 0),
                        'supplier_prices' => $item['supplier_prices'] ?? [],
                        'estimated_unit_price' => ($item['estimated_unit_price'] ?? null) !== null
                            ? (float) $item['estimated_unit_price']
                            : null,
                        'estimated_total' => ($item['estimated_total'] ?? null) !== null
                            ? (float) $item['estimated_total']
                            : null,
                    ], $group['items'] ?? []),
                    'subtotal' => (float) ($group['subtotal'] ?? 0),
                ];
            }, $annex['groups'] ?? []),
        ];
    }

    if ($annexType === 'annex_iii') {
        $summary = get_project_licitation_annex_iii_summary($projectId);

        return [
            'type' => $annexType,
            'global_total' => (float) ($summary['global_total'] ?? 0),
            'items' => array_map(static fn (array $item): array => [
                'sequence' => (int) ($item['sequence'] ?? 0),
                'procurement_item_id' => (int) ($item['procurement_item_id'] ?? 0),
                'item_name' => (string) ($item['item_name'] ?? ''),
                'unit' => licitation_annex_unit_text($item),
                'quantity' => (float) ($item['annex_quantity'] ?? 0),
                'estimated_unit_price' => ($item['estimated_unit_price'] ?? null) !== null
                    ? (float) $item['estimated_unit_price']
                    : null,
                'estimated_total' => ($item['estimated_total'] ?? null) !== null
                    ? (float) $item['estimated_total']
                    : null,
            ], $summary['items'] ?? []),
        ];
    }

    if ($annexType === 'lot_annex_i') {
        return [
            'type' => $annexType,
            'lots' => array_map(static fn (array $lot): array => [
                'lot_number' => $lot['lot_number'] !== null ? (int) $lot['lot_number'] : null,
                'name' => (string) ($lot['name'] ?? ''),
                'justification' => (string) ($lot['justification'] ?? ''),
                'items' => array_map(static fn (array $item): array => [
                    'sequence' => (int) ($item['sequence'] ?? 0),
                    'procurement_item_id' => (int) ($item['procurement_item_id'] ?? 0),
                    'tracking_code' => (string) ($item['tracking_code'] ?? ''),
                    'item_name' => (string) ($item['item_name'] ?? ''),
                    'unit' => licitation_annex_unit_text($item),
                    'quantity' => (float) ($item['annex_quantity'] ?? 0),
                    'specification' => licitation_annex_specification_text($item),
                    'demand_memory' => array_map(static fn (array $memory): array => [
                        'demand_id' => (int) ($memory['demand_id'] ?? 0),
                        'quantity' => (float) ($memory['quantity'] ?? 0),
                    ], $item['demand_memory'] ?? []),
                ], $lot['items'] ?? []),
            ], get_project_lot_licitation_annex_i_groups($projectId)),
        ];
    }

    if ($annexType === 'lot_annex_ii') {
        $annex = get_project_lot_licitation_annex_ii_groups($projectId);

        return [
            'type' => $annexType,
            'global_total' => (float) ($annex['global_total'] ?? 0),
            'lots' => array_map(static fn (array $lot): array => [
                'lot_number' => $lot['lot_number'] !== null ? (int) $lot['lot_number'] : null,
                'name' => (string) ($lot['name'] ?? ''),
                'justification' => (string) ($lot['justification'] ?? ''),
                'supplier_groups' => array_map(static fn (array $supplierGroup): array => [
                    'key' => (string) ($supplierGroup['key'] ?? ''),
                    'suppliers' => array_map(static fn (array $supplier): array => [
                        'key' => (string) ($supplier['key'] ?? ''),
                        'id' => (int) ($supplier['id'] ?? 0),
                        'name' => (string) ($supplier['name'] ?? ''),
                        'document' => (string) ($supplier['document'] ?? ''),
                        'proposal_dates' => array_values($supplier['proposal_dates'] ?? []),
                    ], $supplierGroup['suppliers'] ?? []),
                    'items' => array_map(static fn (array $item): array => [
                        'sequence' => (int) ($item['sequence'] ?? 0),
                        'procurement_item_id' => (int) ($item['procurement_item_id'] ?? 0),
                        'item_name' => (string) ($item['item_name'] ?? ''),
                        'unit' => licitation_annex_unit_text($item),
                        'quantity' => (float) ($item['annex_quantity'] ?? 0),
                        'supplier_prices' => $item['supplier_prices'] ?? [],
                        'estimated_unit_price' => ($item['estimated_unit_price'] ?? null) !== null
                            ? (float) $item['estimated_unit_price']
                            : null,
                        'estimated_total' => ($item['estimated_total'] ?? null) !== null
                            ? (float) $item['estimated_total']
                            : null,
                    ], $supplierGroup['items'] ?? []),
                    'subtotal' => (float) ($supplierGroup['subtotal'] ?? 0),
                ], $lot['supplier_groups'] ?? []),
                'subtotal' => (float) ($lot['subtotal'] ?? 0),
            ], $annex['lots'] ?? []),
        ];
    }

    if ($annexType === 'lot_annex_iii') {
        return [
            'type' => $annexType,
            'lots' => array_map(static fn (array $lot): array => [
                'lot_number' => $lot['lot_number'] !== null ? (int) $lot['lot_number'] : null,
                'name' => (string) ($lot['name'] ?? ''),
                'justification' => (string) ($lot['justification'] ?? ''),
                'items' => array_map(static fn (array $item): array => [
                    'sequence' => (int) ($item['sequence'] ?? 0),
                    'procurement_item_id' => (int) ($item['procurement_item_id'] ?? 0),
                    'item_name' => (string) ($item['item_name'] ?? ''),
                ], $lot['items'] ?? []),
            ], get_project_lot_licitation_annex_i_groups($projectId)),
        ];
    }

    if ($annexType === 'lot_annex_iv') {
        $annex = get_project_lot_licitation_annex_ii_groups($projectId);

        return [
            'type' => $annexType,
            'global_total' => (float) ($annex['global_total'] ?? 0),
            'lots' => array_map(static fn (array $lot): array => [
                'lot_number' => $lot['lot_number'] !== null ? (int) $lot['lot_number'] : null,
                'name' => (string) ($lot['name'] ?? ''),
                'items' => array_map(static fn (array $item): array => [
                    'sequence' => (int) ($item['sequence'] ?? 0),
                    'procurement_item_id' => (int) ($item['procurement_item_id'] ?? 0),
                    'item_name' => (string) ($item['item_name'] ?? ''),
                ], $lot['items'] ?? []),
                'subtotal' => (float) ($lot['subtotal'] ?? 0),
            ], $annex['lots'] ?? []),
        ];
    }

    throw new InvalidArgumentException('Tipo de anexo invalido.');
}

function project_annex_payload_item_count(string $annexType, array $payload): int
{
    if ($annexType === 'annex_ii') {
        $count = 0;

        foreach ($payload['groups'] ?? [] as $group) {
            $count += count($group['items'] ?? []);
        }

        return $count;
    }

    if ($annexType === 'lot_annex_ii') {
        $count = 0;

        foreach ($payload['lots'] ?? [] as $lot) {
            foreach ($lot['supplier_groups'] ?? [] as $supplierGroup) {
                $count += count($supplierGroup['items'] ?? []);
            }
        }

        return $count;
    }

    if (in_array($annexType, ['lot_annex_i', 'lot_annex_iii', 'lot_annex_iv'], true)) {
        $count = 0;

        foreach ($payload['lots'] ?? [] as $lot) {
            $count += count($lot['items'] ?? []);
        }

        return $count;
    }

    return count($payload['items'] ?? []);
}

function project_annex_payload_total(string $annexType, array $payload): ?float
{
    if (in_array($annexType, ['annex_ii', 'annex_iii', 'lot_annex_ii', 'lot_annex_iv'], true)) {
        return (float) ($payload['global_total'] ?? 0);
    }

    return null;
}

function refresh_project_annex_version_status(int $projectId, string $annexType, string $currentHash): void
{
    if (!database_table_exists('project_annex_versions')) {
        return;
    }

    try {
        $stmt = db()->prepare("
            UPDATE project_annex_versions
            SET status = 'invalid',
                invalidated_at = COALESCE(invalidated_at, CURRENT_TIMESTAMP)
            WHERE project_id = :project_id
              AND annex_type = :annex_type
              AND status = 'valid'
              AND content_hash <> :content_hash
        ");
        $stmt->execute([
            'project_id' => $projectId,
            'annex_type' => $annexType,
            'content_hash' => $currentHash,
        ]);
    } catch (Throwable $exception) {
        if (!is_missing_database_relation($exception)) {
            throw $exception;
        }

        log_optional_schema_issue('versoes de anexos', $exception);
    }
}

function register_project_annex_version(int $projectId, string $annexType): array
{
    $payload = project_annex_payload($projectId, $annexType);
    $hash = project_annex_hash($payload);
    $itemCount = project_annex_payload_item_count($annexType, $payload);
    $totalValue = project_annex_payload_total($annexType, $payload);

    if (!database_table_exists('project_annex_versions')) {
        return [
            'annex_type' => $annexType,
            'version_number' => null,
            'content_hash' => $hash,
            'status' => 'untracked',
            'item_count' => $itemCount,
            'total_value' => $totalValue,
        ];
    }

    try {
        refresh_project_annex_version_status($projectId, $annexType, $hash);

        $existing = db()->prepare("
            SELECT *
            FROM project_annex_versions
            WHERE project_id = :project_id
              AND annex_type = :annex_type
              AND content_hash = :content_hash
            LIMIT 1
        ");
        $existing->execute([
            'project_id' => $projectId,
            'annex_type' => $annexType,
            'content_hash' => $hash,
        ]);
        $version = $existing->fetch();

        if ($version) {
            $update = db()->prepare("
                UPDATE project_annex_versions
                SET status = 'valid',
                    item_count = :item_count,
                    total_value = :total_value,
                    generated_at = CURRENT_TIMESTAMP,
                    invalidated_at = NULL
                WHERE id = :id
            ");
            $update->execute([
                'id' => (int) $version['id'],
                'item_count' => $itemCount,
                'total_value' => $totalValue,
            ]);

            $version['status'] = 'valid';
            $version['item_count'] = $itemCount;
            $version['total_value'] = $totalValue;
            $version['content_hash'] = $hash;

            return $version;
        }

        $next = db()->prepare("
            SELECT COALESCE(MAX(version_number), 0) + 1
            FROM project_annex_versions
            WHERE project_id = :project_id
              AND annex_type = :annex_type
        ");
        $next->execute([
            'project_id' => $projectId,
            'annex_type' => $annexType,
        ]);

        $insert = db()->prepare("
            INSERT INTO project_annex_versions (
                project_id,
                annex_type,
                version_number,
                content_hash,
                status,
                item_count,
                total_value
            ) VALUES (
                :project_id,
                :annex_type,
                :version_number,
                :content_hash,
                'valid',
                :item_count,
                :total_value
            )
            RETURNING *
        ");
        $insert->execute([
            'project_id' => $projectId,
            'annex_type' => $annexType,
            'version_number' => (int) $next->fetchColumn(),
            'content_hash' => $hash,
            'item_count' => $itemCount,
            'total_value' => $totalValue,
        ]);

        return $insert->fetch();
    } catch (Throwable $exception) {
        if (!is_missing_database_relation($exception)) {
            throw $exception;
        }

        log_optional_schema_issue('versoes de anexos', $exception);

        return [
            'annex_type' => $annexType,
            'version_number' => null,
            'content_hash' => $hash,
            'status' => 'untracked',
            'item_count' => $itemCount,
            'total_value' => $totalValue,
        ];
    }
}

function get_project_annex_statuses(int $projectId): array
{
    $statuses = [];

    foreach (project_annex_types() as $annexType => $label) {
        $payload = project_annex_payload($projectId, $annexType);
        $hash = project_annex_hash($payload);

        refresh_project_annex_version_status($projectId, $annexType, $hash);

        try {
            if (!database_table_exists('project_annex_versions')) {
                $latest = null;
            } else {
                $stmt = db()->prepare("
                    SELECT *
                    FROM project_annex_versions
                    WHERE project_id = :project_id
                      AND annex_type = :annex_type
                    ORDER BY version_number DESC
                    LIMIT 1
                ");
                $stmt->execute([
                    'project_id' => $projectId,
                    'annex_type' => $annexType,
                ]);
                $latest = $stmt->fetch() ?: null;
            }
        } catch (Throwable $exception) {
            if (!is_missing_database_relation($exception)) {
                throw $exception;
            }

            log_optional_schema_issue('versoes de anexos', $exception);
            $latest = null;
        }

        $isCurrent = $latest
            && ($latest['content_hash'] ?? '') === $hash
            && ($latest['status'] ?? '') === 'valid';

        $statuses[$annexType] = [
            'label' => $label,
            'status' => $latest ? ($isCurrent ? 'valid' : 'stale') : 'pending',
            'current_hash' => $hash,
            'short_hash' => substr($hash, 0, 12),
            'version_number' => $latest['version_number'] ?? null,
            'generated_at' => $latest['generated_at'] ?? null,
            'item_count' => project_annex_payload_item_count($annexType, $payload),
            'total_value' => project_annex_payload_total($annexType, $payload),
        ];
    }

    return $statuses;
}

function get_demand_supplier_quotes(int $demandListId): array
{
    try {
        $stmt = db()->prepare("
            SELECT
                q.*,
                s.name AS supplier_name,
                s.trade_name AS supplier_trade_name,
                s.document AS supplier_document,
                s.contact_name AS supplier_contact_name,
                s.email AS supplier_email,
                s.phone AS supplier_phone,
                s.address AS supplier_address,
                s.city AS supplier_city,
                s.state AS supplier_state,
                s.postal_code AS supplier_postal_code,
                COUNT(qi.id) FILTER (WHERE qi.unit_price IS NOT NULL) AS priced_items_count,
                COALESCE(
                    SUM(qi.unit_price * COALESCE(di.approved_quantity, di.quantity))
                        FILTER (WHERE qi.unit_price IS NOT NULL),
                    0
                ) AS total_quote_value
            FROM demand_supplier_quotes q
            INNER JOIN suppliers s ON s.id = q.supplier_id
            LEFT JOIN demand_supplier_quote_items qi ON qi.demand_supplier_quote_id = q.id
            LEFT JOIN demand_items di ON di.id = qi.demand_item_id
            WHERE q.demand_list_id = :demand_list_id
            GROUP BY
                q.id,
                s.name,
                s.trade_name,
                s.document,
                s.contact_name,
                s.email,
                s.phone,
                s.address,
                s.city,
                s.state,
                s.postal_code
            ORDER BY s.name
        ");

        $stmt->execute(['demand_list_id' => $demandListId]);

        return $stmt->fetchAll();
    } catch (Throwable $exception) {
        if (!is_missing_database_relation($exception)) {
            throw $exception;
        }

        log_optional_schema_issue('orcamentos de fornecedores', $exception);
        return [];
    }
}

function find_demand_supplier_quote(int $id): ?array
{
    $stmt = db()->prepare("
        SELECT
            q.*,
            s.name AS supplier_name,
            s.document AS supplier_document,
            s.contact_name AS supplier_contact_name
        FROM demand_supplier_quotes q
        INNER JOIN suppliers s ON s.id = q.supplier_id
        WHERE q.id = :id
    ");

    $stmt->execute(['id' => $id]);

    $quote = $stmt->fetch();

    return $quote ?: null;
}

function find_demand_supplier_quote_by_supplier(int $demandListId, int $supplierId): ?array
{
    $stmt = db()->prepare("
        SELECT *
        FROM demand_supplier_quotes
        WHERE demand_list_id = :demand_list_id
          AND supplier_id = :supplier_id
    ");

    $stmt->execute([
        'demand_list_id' => $demandListId,
        'supplier_id' => $supplierId,
    ]);

    $quote = $stmt->fetch();

    return $quote ?: null;
}

function normalize_supplier_quote_collected_by(array $data): ?string
{
    $collectedBy = trim((string) ($data['collected_by'] ?? ''));

    return $collectedBy !== '' ? $collectedBy : null;
}

function normalize_supplier_quote_quoted_by(array $data): ?string
{
    $quotedBy = trim((string) ($data['quoted_by'] ?? ''));

    if ($quotedBy !== '') {
        return $quotedBy;
    }

    $supplierId = (int) ($data['supplier_id'] ?? 0);

    if ($supplierId <= 0) {
        return null;
    }

    $stmt = db()->prepare('SELECT contact_name FROM suppliers WHERE id = :id');
    $stmt->execute(['id' => $supplierId]);

    $contactName = trim((string) $stmt->fetchColumn());

    return $contactName !== '' ? $contactName : null;
}

function create_demand_supplier_quote(array $data): int
{
    assert_project_editable(find_project_id_by_demand_list((int) $data['demand_list_id']));

    $stmt = db()->prepare("
        INSERT INTO demand_supplier_quotes (
            demand_list_id,
            supplier_id,
            quote_number,
            quote_date,
            validity_date,
            quoted_by,
            collected_by,
            attachment_path,
            notes,
            status
        ) VALUES (
            :demand_list_id,
            :supplier_id,
            :quote_number,
            :quote_date,
            :validity_date,
            :quoted_by,
            :collected_by,
            :attachment_path,
            :notes,
            :status
        )
        RETURNING id
    ");

    $stmt->execute([
        'demand_list_id' => $data['demand_list_id'],
        'supplier_id' => $data['supplier_id'],
        'quote_number' => $data['quote_number'] ?: null,
        'quote_date' => normalize_optional_date($data['quote_date'] ?? null),
        'validity_date' => normalize_optional_date($data['validity_date'] ?? null),
        'quoted_by' => normalize_supplier_quote_quoted_by($data),
        'collected_by' => normalize_supplier_quote_collected_by($data),
        'attachment_path' => $data['attachment_path'] ?: null,
        'notes' => $data['notes'] ?: null,
        'status' => $data['status'] ?? 'received',
    ]);

    $id = (int) $stmt->fetchColumn();
    invalidate_project_annex_versions(find_project_id_by_demand_list((int) $data['demand_list_id']));

    return $id;
}

function update_demand_supplier_quote(int $id, array $data): void
{
    $projectId = find_project_id_by_supplier_quote($id);
    assert_project_editable($projectId);

    $stmt = db()->prepare("
        UPDATE demand_supplier_quotes SET
            supplier_id = :supplier_id,
            quote_number = :quote_number,
            quote_date = :quote_date,
            validity_date = :validity_date,
            quoted_by = :quoted_by,
            collected_by = :collected_by,
            attachment_path = :attachment_path,
            notes = :notes,
            status = :status
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $id,
        'supplier_id' => $data['supplier_id'],
        'quote_number' => $data['quote_number'] ?: null,
        'quote_date' => normalize_optional_date($data['quote_date'] ?? null),
        'validity_date' => normalize_optional_date($data['validity_date'] ?? null),
        'quoted_by' => normalize_supplier_quote_quoted_by($data),
        'collected_by' => normalize_supplier_quote_collected_by($data),
        'attachment_path' => $data['attachment_path'] ?: null,
        'notes' => $data['notes'] ?: null,
        'status' => $data['status'] ?? 'received',
    ]);

    invalidate_project_annex_versions($projectId);
}

function delete_demand_supplier_quote(int $id): void
{
    $projectId = find_project_id_by_supplier_quote($id);
    assert_project_editable($projectId);

    $stmt = db()->prepare("
        DELETE FROM demand_supplier_quotes
        WHERE id = :id
    ");

    $stmt->execute(['id' => $id]);
    invalidate_project_annex_versions($projectId);
}

function get_demand_supplier_quote_items(int $quoteId): array
{
    $stmt = db()->prepare("
        SELECT
            qi.*,
            source_qi.unit_price AS reused_unit_price,
            source_q.quote_number AS reused_quote_number,
            source_q.quote_date AS reused_quote_date,
            source_q.attachment_path AS reused_attachment_path,
            source_s.name AS reused_supplier_name,
            source_dl.name AS reused_demand_name
        FROM demand_supplier_quote_items qi
        LEFT JOIN demand_supplier_quote_items source_qi ON source_qi.id = qi.reused_from_quote_item_id
        LEFT JOIN demand_supplier_quotes source_q ON source_q.id = source_qi.demand_supplier_quote_id
        LEFT JOIN suppliers source_s ON source_s.id = source_q.supplier_id
        LEFT JOIN demand_items source_di ON source_di.id = source_qi.demand_item_id
        LEFT JOIN demand_lists source_dl ON source_dl.id = source_di.demand_list_id
        WHERE qi.demand_supplier_quote_id = :quote_id
    ");

    $stmt->execute(['quote_id' => $quoteId]);

    $items = [];

    foreach ($stmt->fetchAll() as $item) {
        $items[(int) $item['demand_item_id']] = $item;
    }

    return $items;
}

function get_reusable_project_quote_items_for_demand(int $demandListId): array
{
    try {
        $stmt = db()->prepare("
            SELECT
                target_di.id AS target_demand_item_id,
                qi.id AS source_quote_item_id,
                qi.unit_price,
                qi.notes,
                q.id AS source_quote_id,
                q.supplier_id,
                q.quote_number,
                q.quote_date,
                q.validity_date,
                q.attachment_path,
                s.name AS supplier_name,
                s.document AS supplier_document,
                source_dl.id AS source_demand_id,
                source_dl.name AS source_demand_name
            FROM demand_items target_di
            INNER JOIN demand_lists target_dl ON target_dl.id = target_di.demand_list_id
            INNER JOIN demand_lists source_dl
                ON source_dl.project_id = target_dl.project_id
               AND source_dl.id <> target_dl.id
            INNER JOIN demand_items source_di
                ON source_di.demand_list_id = source_dl.id
               AND source_di.procurement_item_id = target_di.procurement_item_id
            INNER JOIN demand_supplier_quote_items qi
                ON qi.demand_item_id = source_di.id
               AND qi.unit_price IS NOT NULL
            INNER JOIN demand_supplier_quotes q
                ON q.id = qi.demand_supplier_quote_id
               AND q.status <> 'discarded'
            INNER JOIN suppliers s ON s.id = q.supplier_id
            WHERE target_dl.id = :demand_list_id
            ORDER BY
                target_di.id,
                q.quote_date DESC NULLS LAST,
                qi.created_at DESC,
                s.name
        ");

        $stmt->execute(['demand_list_id' => $demandListId]);

        $items = [];

        foreach ($stmt->fetchAll() as $item) {
            $items[(int) $item['target_demand_item_id']][] = $item;
        }

        return $items;
    } catch (Throwable $exception) {
        if (!is_missing_database_relation($exception)) {
            throw $exception;
        }

        log_optional_schema_issue('reaproveitamento de precos', $exception);
        return [];
    }
}

function get_selected_demand_price_references(int $demandListId): array
{
    try {
        $stmt = db()->prepare("
            SELECT
                pr.*,
                source_qi.unit_price,
                source_qi.notes AS source_notes,
                q.quote_number,
                q.quote_date,
                q.validity_date,
                q.attachment_path,
                s.name AS supplier_name,
                s.document AS supplier_document,
                s.trade_name AS supplier_trade_name,
                s.contact_name AS supplier_contact_name,
                s.email AS supplier_email,
                s.phone AS supplier_phone,
                s.address AS supplier_address,
                s.city AS supplier_city,
                s.state AS supplier_state,
                s.postal_code AS supplier_postal_code,
                source_dl.name AS source_demand_name,
                source_p.name AS source_project_name
            FROM demand_price_references pr
            INNER JOIN demand_items target_di ON target_di.id = pr.demand_item_id
            INNER JOIN demand_supplier_quote_items source_qi ON source_qi.id = pr.source_quote_item_id
            INNER JOIN demand_supplier_quotes q ON q.id = source_qi.demand_supplier_quote_id
            INNER JOIN suppliers s ON s.id = q.supplier_id
            INNER JOIN demand_items source_di ON source_di.id = source_qi.demand_item_id
            INNER JOIN demand_lists source_dl ON source_dl.id = source_di.demand_list_id
            INNER JOIN procurement_projects source_p ON source_p.id = source_dl.project_id
            WHERE target_di.demand_list_id = :demand_list_id
            ORDER BY target_di.id, q.quote_date DESC NULLS LAST, s.name
        ");

        $stmt->execute(['demand_list_id' => $demandListId]);

        $references = [];

        foreach ($stmt->fetchAll() as $reference) {
            $references[(int) $reference['demand_item_id']][(int) $reference['source_quote_item_id']] = $reference;
        }

        return $references;
    } catch (Throwable $exception) {
        if (!is_missing_database_relation($exception)) {
            throw $exception;
        }

        log_optional_schema_issue('banco de precos selecionado', $exception);
        return [];
    }
}

function get_demand_price_bank_candidates(int $demandListId, int $months = 0): array
{
    try {
        $sql = "
            SELECT
                target_di.id AS target_demand_item_id,
                qi.id AS source_quote_item_id,
                qi.unit_price,
                qi.notes,
                q.id AS source_quote_id,
                q.supplier_id,
                q.quote_number,
                q.quote_date,
                q.validity_date,
                q.attachment_path,
                s.name AS supplier_name,
                s.document AS supplier_document,
                source_dl.id AS source_demand_id,
                source_dl.name AS source_demand_name,
                source_p.id AS source_project_id,
                source_p.name AS source_project_name
            FROM demand_items target_di
            INNER JOIN demand_lists target_dl ON target_dl.id = target_di.demand_list_id
            INNER JOIN demand_items source_di
                ON source_di.procurement_item_id = target_di.procurement_item_id
               AND source_di.id <> target_di.id
            INNER JOIN demand_lists source_dl ON source_dl.id = source_di.demand_list_id
            INNER JOIN procurement_projects source_p ON source_p.id = source_dl.project_id
            INNER JOIN demand_supplier_quote_items qi
                ON qi.demand_item_id = source_di.id
               AND qi.unit_price IS NOT NULL
            INNER JOIN demand_supplier_quotes q
                ON q.id = qi.demand_supplier_quote_id
               AND q.status <> 'discarded'
            INNER JOIN suppliers s ON s.id = q.supplier_id
            WHERE target_dl.id = :demand_list_id
        ";

        $params = [
            'demand_list_id' => $demandListId,
        ];

        if ($months > 0) {
            $sql .= " AND q.quote_date >= (CURRENT_DATE - (:months || ' months')::interval)";
            $params['months'] = $months;
        }

        $sql .= "
            ORDER BY
                target_di.id,
                q.quote_date DESC NULLS LAST,
                qi.created_at DESC,
                s.name
        ";

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        $items = [];

        foreach ($stmt->fetchAll() as $item) {
            $items[(int) $item['target_demand_item_id']][] = $item;
        }

        return $items;
    } catch (Throwable $exception) {
        if (!is_missing_database_relation($exception)) {
            throw $exception;
        }

        log_optional_schema_issue('candidatos do banco de precos', $exception);
        return [];
    }
}

function save_demand_price_references(int $demandListId, array $selectedReferences): void
{
    $projectId = find_project_id_by_demand_list($demandListId);
    assert_project_editable($projectId);

    db()->beginTransaction();

    try {
        $delete = db()->prepare("
            DELETE FROM demand_price_references pr
            USING demand_items di
            WHERE di.id = pr.demand_item_id
              AND di.demand_list_id = :demand_list_id
        ");

        $delete->execute(['demand_list_id' => $demandListId]);

        $insert = db()->prepare("
            INSERT INTO demand_price_references (
                demand_item_id,
                source_quote_item_id
            )
            SELECT
                target_di.id,
                source_qi.id
            FROM demand_items target_di
            INNER JOIN demand_supplier_quote_items source_qi ON source_qi.id = :source_quote_item_id
            INNER JOIN demand_supplier_quotes q
                ON q.id = source_qi.demand_supplier_quote_id
               AND q.status <> 'discarded'
            INNER JOIN demand_items source_di ON source_di.id = source_qi.demand_item_id
            WHERE target_di.id = :demand_item_id
              AND target_di.demand_list_id = :demand_list_id
              AND target_di.procurement_item_id = source_di.procurement_item_id
              AND source_qi.unit_price IS NOT NULL
            ON CONFLICT (demand_item_id, source_quote_item_id) DO NOTHING
        ");

        foreach ($selectedReferences as $demandItemId => $sourceQuoteItemIds) {
            if (!is_array($sourceQuoteItemIds)) {
                continue;
            }

            foreach (array_keys($sourceQuoteItemIds) as $sourceQuoteItemId) {
                $insert->execute([
                    'demand_list_id' => $demandListId,
                    'demand_item_id' => (int) $demandItemId,
                    'source_quote_item_id' => (int) $sourceQuoteItemId,
                ]);
            }
        }

        invalidate_project_annex_versions($projectId);
        db()->commit();
    } catch (Throwable $exception) {
        db()->rollBack();
        throw $exception;
    }
}

function save_demand_supplier_quote_items(int $quoteId, array $prices, array $notes = [], array $sourceQuoteItemIds = []): void
{
    $projectId = find_project_id_by_supplier_quote($quoteId);
    assert_project_editable($projectId);

    db()->beginTransaction();

    try {
        $upsert = db()->prepare("
            INSERT INTO demand_supplier_quote_items (
                demand_supplier_quote_id,
                demand_item_id,
                unit_price,
                notes,
                reused_from_quote_item_id
            ) VALUES (
                :quote_id,
                :demand_item_id,
                :unit_price,
                :notes,
                :reused_from_quote_item_id
            )
            ON CONFLICT (demand_supplier_quote_id, demand_item_id)
            DO UPDATE SET
                unit_price = EXCLUDED.unit_price,
                notes = EXCLUDED.notes,
                reused_from_quote_item_id = EXCLUDED.reused_from_quote_item_id
        ");

        $delete = db()->prepare("
            DELETE FROM demand_supplier_quote_items
            WHERE demand_supplier_quote_id = :quote_id
              AND demand_item_id = :demand_item_id
        ");

        foreach ($prices as $demandItemId => $rawPrice) {
            $demandItemId = (int) $demandItemId;
            $unitPrice = normalize_money_value($rawPrice);
            $note = trim((string) ($notes[$demandItemId] ?? ''));
            $sourceQuoteItemId = (int) ($sourceQuoteItemIds[$demandItemId] ?? 0);

            if ($unitPrice === null && $note === '') {
                $delete->execute([
                    'quote_id' => $quoteId,
                    'demand_item_id' => $demandItemId,
                ]);

                continue;
            }

            $upsert->execute([
                'quote_id' => $quoteId,
                'demand_item_id' => $demandItemId,
                'unit_price' => $unitPrice,
                'notes' => $note ?: null,
                'reused_from_quote_item_id' => $sourceQuoteItemId > 0 ? $sourceQuoteItemId : null,
            ]);
        }

        invalidate_project_annex_versions($projectId);
        db()->commit();
    } catch (Throwable $exception) {
        db()->rollBack();
        throw $exception;
    }
}

function project_global_price_bank_candidate_key(array $row): string
{
    return sha1(implode('|', [
        (string) (int) ($row['source_project_id'] ?? 0),
        (string) (int) ($row['supplier_id'] ?? 0),
        (string) ($row['quote_number'] ?? ''),
        (string) ($row['quote_date'] ?? ''),
        (string) ($row['validity_date'] ?? ''),
        (string) ($row['quoted_by'] ?? ''),
        (string) ($row['collected_by'] ?? ''),
    ]));
}

function get_project_global_price_bank_candidates(int $projectId, int $months = 0): array
{
    try {
        $targetItems = get_project_consolidated_items($projectId);
        $targetQuantities = [];

        foreach ($targetItems as $item) {
            $targetQuantities[(int) $item['procurement_item_id']] = (float) ($item['total_approved_quantity'] ?? $item['total_quantity'] ?? 0);
        }

        if (!$targetQuantities) {
            return [];
        }

        $sql = "
            SELECT
                source_p.id AS source_project_id,
                source_p.name AS source_project_name,
                s.id AS supplier_id,
                s.name AS supplier_name,
                s.document AS supplier_document,
                q.id AS source_quote_id,
                q.quote_number,
                q.quote_date,
                q.validity_date,
                q.quoted_by,
                q.collected_by,
                q.attachment_path,
                q.notes,
                q.status,
                source_di.procurement_item_id,
                qi.id AS source_quote_item_id,
                qi.unit_price,
                qi.notes AS item_notes
            FROM demand_supplier_quote_items qi
            INNER JOIN demand_supplier_quotes q
                ON q.id = qi.demand_supplier_quote_id
               AND COALESCE(q.status, 'received') <> 'discarded'
            INNER JOIN suppliers s
                ON s.id = q.supplier_id
               AND s.is_active = TRUE
            INNER JOIN demand_items source_di ON source_di.id = qi.demand_item_id
            INNER JOIN demand_lists source_dl ON source_dl.id = source_di.demand_list_id
            INNER JOIN procurement_projects source_p ON source_p.id = source_dl.project_id
            WHERE source_p.id <> :project_id
              AND source_di.procurement_item_id = ANY((:procurement_item_ids)::int[])
              AND qi.unit_price IS NOT NULL
        ";

        $params = [
            'project_id' => $projectId,
            'procurement_item_ids' => '{' . implode(',', array_map('intval', array_keys($targetQuantities))) . '}',
        ];

        if ($months > 0) {
            $sql .= " AND q.quote_date >= (CURRENT_DATE - (:months || ' months')::interval)";
            $params['months'] = $months;
        }

        $sql .= "
            ORDER BY
                source_p.name,
                s.name,
                q.quote_date DESC NULLS LAST,
                qi.created_at DESC
        ";

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        $candidates = [];

        foreach ($stmt->fetchAll() as $row) {
            $key = project_global_price_bank_candidate_key($row);
            $procurementItemId = (int) $row['procurement_item_id'];

            if (!isset($targetQuantities[$procurementItemId])) {
                continue;
            }

            if (!isset($candidates[$key])) {
                $candidates[$key] = [
                    'key' => $key,
                    'source_project_id' => (int) $row['source_project_id'],
                    'source_project_name' => $row['source_project_name'],
                    'supplier_id' => (int) $row['supplier_id'],
                    'supplier_name' => $row['supplier_name'],
                    'supplier_document' => $row['supplier_document'],
                    'quote_number' => $row['quote_number'],
                    'quote_date' => $row['quote_date'],
                    'validity_date' => $row['validity_date'],
                    'quoted_by' => $row['quoted_by'],
                    'collected_by' => $row['collected_by'],
                    'notes' => $row['notes'],
                    'status' => $row['status'] ?: 'received',
                    'attachment_paths' => [],
                    'matched_item_ids' => [],
                    'price_values' => [],
                    'prices' => [],
                    'item_notes' => [],
                    'source_quote_item_ids' => [],
                    'estimated_total' => 0.0,
                    'target_item_count' => count($targetQuantities),
                    'matched_item_count' => 0,
                ];
            }

            $attachmentPath = trim((string) ($row['attachment_path'] ?? ''));

            if ($attachmentPath !== '') {
                $candidates[$key]['attachment_paths'][$attachmentPath] = $attachmentPath;
            }

            $candidates[$key]['matched_item_ids'][$procurementItemId] = true;
            $candidates[$key]['price_values'][$procurementItemId][] = (float) $row['unit_price'];

            if (empty($candidates[$key]['source_quote_item_ids'][$procurementItemId])) {
                $candidates[$key]['source_quote_item_ids'][$procurementItemId] = (int) $row['source_quote_item_id'];
            }

            $itemNote = trim((string) ($row['item_notes'] ?? ''));

            if ($itemNote !== '' && empty($candidates[$key]['item_notes'][$procurementItemId])) {
                $candidates[$key]['item_notes'][$procurementItemId] = $itemNote;
            }
        }

        foreach ($candidates as $key => $candidate) {
            $estimatedTotal = 0.0;
            $prices = [];

            foreach ($candidate['price_values'] as $procurementItemId => $values) {
                $unitPrice = array_sum($values) / max(1, count($values));
                $prices[$procurementItemId] = number_format($unitPrice, 2, '.', '');
                $estimatedTotal += $unitPrice * ($targetQuantities[$procurementItemId] ?? 0.0);
            }

            $candidates[$key]['prices'] = $prices;
            $candidates[$key]['estimated_total'] = round($estimatedTotal, 2);
            $candidates[$key]['matched_item_count'] = count($candidate['matched_item_ids']);
            $candidates[$key]['attachment_paths'] = array_values($candidate['attachment_paths']);
            unset($candidates[$key]['matched_item_ids'], $candidates[$key]['price_values']);
        }

        usort($candidates, static function (array $left, array $right): int {
            return ((int) $right['matched_item_count'] <=> (int) $left['matched_item_count'])
                ?: strcasecmp((string) $left['source_project_name'], (string) $right['source_project_name'])
                ?: strcasecmp((string) $left['supplier_name'], (string) $right['supplier_name'])
                ?: strcmp((string) ($right['quote_date'] ?? ''), (string) ($left['quote_date'] ?? ''));
        });

        return $candidates;
    } catch (Throwable $exception) {
        if (!is_missing_database_relation($exception)) {
            throw $exception;
        }

        log_optional_schema_issue('banco de precos de orcamentos gerais', $exception);
        return [];
    }
}

function find_project_global_price_bank_candidate(int $projectId, string $key, int $months = 0): ?array
{
    foreach (get_project_global_price_bank_candidates($projectId, $months) as $candidate) {
        if (hash_equals((string) $candidate['key'], $key)) {
            return $candidate;
        }
    }

    return null;
}

function save_project_supplier_quote(array $data, array $prices, array $notes = [], array $preserveBlankPriceKeys = [], array $sourceQuoteItemIds = []): array
{
    $projectId = (int) ($data['project_id'] ?? 0);
    $supplierId = (int) ($data['supplier_id'] ?? 0);
    $priceKey = ($data['price_key'] ?? 'demand_item_id') === 'procurement_item_id'
        ? 'procurement_item_id'
        : 'demand_item_id';
    $uploadedAttachmentPath = trim((string) ($data['attachment_path'] ?? ''));
    $removeAttachment = !empty($data['remove_attachment']);

    if ($projectId <= 0 || $supplierId <= 0) {
        throw new InvalidArgumentException('Projeto e fornecedor sao obrigatorios.');
    }

    assert_project_editable($projectId);

    $demands = get_project_demands($projectId);
    $quoteCount = 0;
    $pricedItemsCount = 0;

    db()->beginTransaction();

    try {
        $upsert = db()->prepare("
            INSERT INTO demand_supplier_quote_items (
                demand_supplier_quote_id,
                demand_item_id,
                unit_price,
                notes,
                reused_from_quote_item_id
            ) VALUES (
                :quote_id,
                :demand_item_id,
                :unit_price,
                :notes,
                :reused_from_quote_item_id
            )
            ON CONFLICT (demand_supplier_quote_id, demand_item_id)
            DO UPDATE SET
                unit_price = EXCLUDED.unit_price,
                notes = EXCLUDED.notes,
                reused_from_quote_item_id = EXCLUDED.reused_from_quote_item_id
        ");

        $delete = db()->prepare("
            DELETE FROM demand_supplier_quote_items
            WHERE demand_supplier_quote_id = :quote_id
              AND demand_item_id = :demand_item_id
        ");

        foreach ($demands as $demand) {
            $demandId = (int) $demand['id'];
            $items = get_demand_items($demandId);

            if (!$items) {
                continue;
            }

            $existingQuote = find_demand_supplier_quote_by_supplier($demandId, $supplierId);
            $attachmentPath = $uploadedAttachmentPath !== ''
                ? $uploadedAttachmentPath
                : ($removeAttachment ? null : ($existingQuote['attachment_path'] ?? null));
            $quoteData = array_merge($data, [
                'demand_list_id' => $demandId,
                'supplier_id' => $supplierId,
                'attachment_path' => $attachmentPath,
            ]);
            $itemUpserts = [];
            $itemDeletes = [];

            foreach ($items as $item) {
                $demandItemId = (int) $item['id'];
                $inputKey = $priceKey === 'procurement_item_id'
                    ? (int) $item['procurement_item_id']
                    : $demandItemId;
                $rawPrice = $prices[$inputKey] ?? $prices[(string) $inputKey] ?? null;
                $note = trim((string) ($notes[$inputKey] ?? $notes[(string) $inputKey] ?? ''));
                $unitPrice = normalize_money_value($rawPrice);
                $preserveBlankPrice = !empty($preserveBlankPriceKeys[$inputKey])
                    || !empty($preserveBlankPriceKeys[(string) $inputKey]);
                $sourceQuoteItemId = (int) ($sourceQuoteItemIds[$inputKey] ?? $sourceQuoteItemIds[(string) $inputKey] ?? 0);

                if ($preserveBlankPrice && $unitPrice === null) {
                    continue;
                }

                if ($unitPrice === null && $note === '') {
                    if ($existingQuote) {
                        $itemDeletes[] = $demandItemId;
                    }

                    continue;
                }

                $itemUpserts[] = [
                    'demand_item_id' => $demandItemId,
                    'unit_price' => $unitPrice,
                    'notes' => $note ?: null,
                    'reused_from_quote_item_id' => $sourceQuoteItemId > 0 ? $sourceQuoteItemId : null,
                ];
            }

            $quoteHasMetadata = trim((string) ($quoteData['quote_number'] ?? '')) !== ''
                || normalize_optional_date($quoteData['quote_date'] ?? null) !== null
                || normalize_optional_date($quoteData['validity_date'] ?? null) !== null
                || trim((string) ($quoteData['quoted_by'] ?? '')) !== ''
                || trim((string) ($quoteData['collected_by'] ?? '')) !== ''
                || trim((string) ($quoteData['notes'] ?? '')) !== ''
                || trim((string) ($quoteData['attachment_path'] ?? '')) !== '';

            if (!$existingQuote && !$itemUpserts && !$quoteHasMetadata) {
                continue;
            }

            if ($existingQuote && !$itemUpserts && !$quoteHasMetadata) {
                delete_demand_supplier_quote((int) $existingQuote['id']);
                continue;
            }

            if ($existingQuote) {
                update_demand_supplier_quote((int) $existingQuote['id'], $quoteData);
                $quoteId = (int) $existingQuote['id'];
            } else {
                $quoteId = create_demand_supplier_quote($quoteData);
            }

            foreach ($itemDeletes as $demandItemId) {
                $delete->execute([
                    'quote_id' => $quoteId,
                    'demand_item_id' => $demandItemId,
                ]);
            }

            foreach ($itemUpserts as $itemUpsert) {
                $upsert->execute([
                    'quote_id' => $quoteId,
                    'demand_item_id' => $itemUpsert['demand_item_id'],
                    'unit_price' => $itemUpsert['unit_price'],
                    'notes' => $itemUpsert['notes'],
                    'reused_from_quote_item_id' => $itemUpsert['reused_from_quote_item_id'],
                ]);

                if ($itemUpsert['unit_price'] !== null) {
                    $pricedItemsCount++;
                }
            }

            $quoteCount++;
        }

        invalidate_project_annex_versions($projectId);
        db()->commit();
    } catch (Throwable $exception) {
        db()->rollBack();
        throw $exception;
    }

    return [
        'quotes' => $quoteCount,
        'priced_items' => $pricedItemsCount,
    ];
}

function get_demand_budget_report(int $demandListId): array
{
    $items = get_demand_items($demandListId);
    $quotes = array_values(array_filter(
        get_demand_supplier_quotes($demandListId),
        static fn (array $quote): bool => ($quote['status'] ?? '') !== 'discarded'
    ));
    $selectedHistoricalReferences = get_selected_demand_price_references($demandListId);
    $prices = [];

    if ($quotes) {
        try {
            $stmt = db()->prepare("
                SELECT
                    qi.demand_item_id,
                    qi.unit_price,
                    qi.notes,
                    qi.reused_from_quote_item_id,
                    source_s.name AS reused_supplier_name,
                    source_dl.name AS reused_demand_name,
                    q.id AS quote_id
                FROM demand_supplier_quote_items qi
                INNER JOIN demand_supplier_quotes q ON q.id = qi.demand_supplier_quote_id
                LEFT JOIN demand_supplier_quote_items source_qi ON source_qi.id = qi.reused_from_quote_item_id
                LEFT JOIN demand_supplier_quotes source_q ON source_q.id = source_qi.demand_supplier_quote_id
                LEFT JOIN suppliers source_s ON source_s.id = source_q.supplier_id
                LEFT JOIN demand_items source_di ON source_di.id = source_qi.demand_item_id
                LEFT JOIN demand_lists source_dl ON source_dl.id = source_di.demand_list_id
                WHERE q.demand_list_id = :demand_list_id
            ");

            $stmt->execute(['demand_list_id' => $demandListId]);

            foreach ($stmt->fetchAll() as $price) {
                $prices[(int) $price['demand_item_id']][(int) $price['quote_id']] = $price;
            }
        } catch (Throwable $exception) {
            if (!is_missing_database_relation($exception)) {
                throw $exception;
            }

            log_optional_schema_issue('itens de orcamentos de fornecedores', $exception);
        }
    }

    $supplierTotals = [];
    $historicalReferences = [];
    $historicalTotal = 0.0;
    $rows = [];
    $totalAverage = 0.0;
    $pricedRows = 0;

    foreach ($quotes as $quote) {
        $supplierTotals[(int) $quote['id']] = 0.0;
    }

    foreach ($items as $item) {
        $itemId = (int) $item['id'];
        $quantity = (float) ($item['approved_quantity'] ?? $item['quantity'] ?? 0);
        $unitPrices = [];
        $supplierPrices = [];
        $supplierNotes = [];
        $supplierOrigins = [];
        $itemHistoricalReferences = [];

        foreach ($quotes as $quote) {
            $quoteId = (int) $quote['id'];
            $priceRow = $prices[$itemId][$quoteId] ?? null;
            $unitPrice = $priceRow && $priceRow['unit_price'] !== null
                ? (float) $priceRow['unit_price']
                : null;

            $supplierPrices[$quoteId] = $unitPrice;
            $supplierNotes[$quoteId] = $priceRow['notes'] ?? null;
            $supplierOrigins[$quoteId] = $priceRow && !empty($priceRow['reused_from_quote_item_id'])
                ? trim(($priceRow['reused_supplier_name'] ?? '') . ' - ' . ($priceRow['reused_demand_name'] ?? ''))
                : null;

            if ($unitPrice !== null) {
                $unitPrices[] = $unitPrice;
                $supplierTotals[$quoteId] += $unitPrice * $quantity;
            }
        }

        foreach ($selectedHistoricalReferences[$itemId] ?? [] as $reference) {
            if ($reference['unit_price'] === null) {
                continue;
            }

            $unitPrice = (float) $reference['unit_price'];
            $unitPrices[] = $unitPrice;

            $referenceTotal = $unitPrice * $quantity;
            $historicalTotal += $referenceTotal;

            $reference['reference_total'] = $referenceTotal;
            $itemHistoricalReferences[] = $reference;
            $historicalReferences[] = array_merge($reference, [
                'target_item_name' => $item['item_name'],
                'target_tracking_code' => $item['tracking_code'],
                'target_quantity' => $quantity,
            ]);
        }

        $averageUnitPrice = $unitPrices
            ? array_sum($unitPrices) / count($unitPrices)
            : null;
        $averageTotal = $averageUnitPrice !== null
            ? $averageUnitPrice * $quantity
            : null;

        if ($averageTotal !== null) {
            $totalAverage += $averageTotal;
            $pricedRows++;
        }

        $rows[] = array_merge($item, [
            'budget_quantity' => $quantity,
            'supplier_prices' => $supplierPrices,
            'supplier_notes' => $supplierNotes,
            'supplier_origins' => $supplierOrigins,
            'historical_references' => $itemHistoricalReferences,
            'average_unit_price' => $averageUnitPrice,
            'average_total' => $averageTotal,
            'price_count' => count($unitPrices),
        ]);
    }

    return [
        'items' => $rows,
        'quotes' => $quotes,
        'supplier_totals' => $supplierTotals,
        'historical_references' => $historicalReferences,
        'historical_total' => $historicalTotal,
        'total_average' => $totalAverage,
        'priced_rows' => $pricedRows,
    ];
}

function get_project_demand_budget_items(int $projectId): array
{
    $budgetItems = [];

    foreach (get_project_demands($projectId) as $demand) {
        $budget = get_demand_budget_report((int) $demand['id']);

        foreach ($budget['items'] as $item) {
            $budgetItems[(int) $item['id']] = $item;
        }
    }

    return $budgetItems;
}

function calculated_project_budget_values(array $demandItem, array $budgetItems): array
{
    $budgetItem = $budgetItems[(int) ($demandItem['demand_item_id'] ?? $demandItem['id'] ?? 0)] ?? [];
    $averageUnitPrice = $budgetItem['average_unit_price'] ?? null;
    $averageTotal = $budgetItem['average_total'] ?? null;
    $manualUnitPrice = $demandItem['estimated_unit_price'] ?? null;
    $manualTotal = $demandItem['estimated_total'] ?? null;

    return [
        'calculated_unit_price' => $averageUnitPrice !== null
            ? (float) $averageUnitPrice
            : ($manualUnitPrice !== null ? (float) $manualUnitPrice : 0.0),
        'calculated_total' => $averageTotal !== null
            ? (float) $averageTotal
            : ($manualTotal !== null ? (float) $manualTotal : 0.0),
        'uses_supplier_average' => $averageUnitPrice !== null,
        'price_count' => (int) ($budgetItem['price_count'] ?? 0),
    ];
}

function get_project_consolidated_items(int $projectId): array
{
    $trackingCodeSql = item_tracking_code_sql('pi');

    $stmt = db()->prepare("
        SELECT
            pi.id AS procurement_item_id,
            {$trackingCodeSql} AS tracking_code,
            pi.name AS item_name,
            pi.category_id,
            c.name AS category_name,
            pi.subcategory_id,
            s.name AS subcategory_name,
            pi.specification,
            pi.justification,
            pi.environmental_impacts,
            ut.name AS unit_type_name,
            ut.abbreviation AS unit_type_abbreviation,
            content_ut.name AS package_content_unit_type_name,
            content_ut.abbreviation AS package_content_unit_type_abbreviation,
            pi.package_content_quantity,
            SUM(di.quantity) AS total_quantity,
            SUM(COALESCE(di.approved_quantity, di.quantity)) AS total_approved_quantity,
            AVG(COALESCE(di.estimated_unit_price, 0)) AS average_unit_price,
            SUM(COALESCE(di.approved_quantity, di.quantity) * COALESCE(di.estimated_unit_price, 0)) AS estimated_total,
            COUNT(DISTINCT dl.id) AS demand_count
        FROM demand_items di
        INNER JOIN demand_lists dl ON dl.id = di.demand_list_id
        INNER JOIN procurement_items pi ON pi.id = di.procurement_item_id
        LEFT JOIN categories c ON c.id = pi.category_id
        LEFT JOIN categories s ON s.id = pi.subcategory_id
        LEFT JOIN unit_types ut ON ut.id = pi.unit_type_id
        LEFT JOIN unit_types content_ut ON content_ut.id = pi.package_content_unit_type_id
        WHERE dl.project_id = :project_id
        GROUP BY
            pi.id,
            {$trackingCodeSql},
            pi.name,
            pi.category_id,
            c.name,
            pi.subcategory_id,
            s.name,
            pi.specification,
            pi.justification,
            pi.environmental_impacts,
            ut.name,
            ut.abbreviation,
            content_ut.name,
            content_ut.abbreviation,
            pi.package_content_quantity
        ORDER BY c.name NULLS LAST, pi.name
    ");

    $stmt->execute(['project_id' => $projectId]);

    $items = $stmt->fetchAll();

    if (!$items) {
        return [];
    }

    $budgetItems = get_project_demand_budget_items($projectId);
    $licitationNumbers = get_project_licitation_number_map($projectId);
    $itemTotals = [];

    $stmt = db()->prepare("
        SELECT
            di.id AS demand_item_id,
            di.procurement_item_id,
            di.quantity,
            COALESCE(di.approved_quantity, di.quantity) AS approved_quantity,
            di.estimated_unit_price,
            (COALESCE(di.approved_quantity, di.quantity) * COALESCE(di.estimated_unit_price, 0)) AS estimated_total
        FROM demand_items di
        INNER JOIN demand_lists dl ON dl.id = di.demand_list_id
        WHERE dl.project_id = :project_id
    ");

    $stmt->execute(['project_id' => $projectId]);

    foreach ($stmt->fetchAll() as $demandItem) {
        $procurementItemId = (int) $demandItem['procurement_item_id'];
        $values = calculated_project_budget_values($demandItem, $budgetItems);

        if (!isset($itemTotals[$procurementItemId])) {
            $itemTotals[$procurementItemId] = [
                'estimated_total' => 0.0,
                'uses_supplier_average' => false,
            ];
        }

        $itemTotals[$procurementItemId]['estimated_total'] += $values['calculated_total'];
        $itemTotals[$procurementItemId]['uses_supplier_average'] = $itemTotals[$procurementItemId]['uses_supplier_average']
            || $values['uses_supplier_average'];
    }

    foreach ($items as $index => $item) {
        $procurementItemId = (int) $item['procurement_item_id'];
        $total = $itemTotals[$procurementItemId]['estimated_total'] ?? (float) $item['estimated_total'];
        $approvedQuantity = (float) ($item['total_approved_quantity'] ?? 0);

        $items[$index]['licitation_number'] = $licitationNumbers[$procurementItemId] ?? null;
        $items[$index]['estimated_total'] = $total;
        $items[$index]['average_unit_price'] = $approvedQuantity > 0
            ? $total / $approvedQuantity
            : 0;
        $items[$index]['uses_supplier_average'] = $itemTotals[$procurementItemId]['uses_supplier_average'] ?? false;
    }

    usort($items, static function (array $left, array $right): int {
        $leftNumber = (int) ($left['licitation_number'] ?? 0);
        $rightNumber = (int) ($right['licitation_number'] ?? 0);

        if ($leftNumber > 0 || $rightNumber > 0) {
            return ($leftNumber ?: PHP_INT_MAX) <=> ($rightNumber ?: PHP_INT_MAX);
        }

        return strcasecmp((string) ($left['category_name'] ?? ''), (string) ($right['category_name'] ?? ''))
            ?: strcasecmp((string) ($left['item_name'] ?? ''), (string) ($right['item_name'] ?? ''));
    });

    return $items;
}

function get_project_items_by_demand(int $projectId): array
{
    $trackingCodeSql = item_tracking_code_sql('pi');

    $stmt = db()->prepare("
        SELECT
            di.id AS demand_item_id,
            pi.id AS procurement_item_id,
            dl.id AS demand_id,
            dl.name AS demand_name,
            dl.requester_unit_id,
            dl.secretariat_id,
            s.name AS secretariat_name,
            dl.requester_department,
            dl.responsible_name,
            {$trackingCodeSql} AS tracking_code,
            pi.name AS item_name,
            ut.name AS unit_type_name,
            ut.abbreviation AS unit_type_abbreviation,
            content_ut.name AS package_content_unit_type_name,
            content_ut.abbreviation AS package_content_unit_type_abbreviation,
            pi.package_content_quantity,
            di.quantity,
            COALESCE(di.approved_quantity, di.quantity) AS approved_quantity,
            di.estimated_unit_price,
            (COALESCE(di.approved_quantity, di.quantity) * COALESCE(di.estimated_unit_price, 0)) AS estimated_total,
            di.notes
        FROM demand_items di
        INNER JOIN demand_lists dl ON dl.id = di.demand_list_id
        INNER JOIN procurement_items pi ON pi.id = di.procurement_item_id
        LEFT JOIN secretariats s ON s.id = dl.secretariat_id
        LEFT JOIN unit_types ut ON ut.id = pi.unit_type_id
        LEFT JOIN unit_types content_ut ON content_ut.id = pi.package_content_unit_type_id
        WHERE dl.project_id = :project_id
        ORDER BY s.name NULLS LAST, dl.name, pi.name
    ");

    $stmt->execute(['project_id' => $projectId]);

    $items = $stmt->fetchAll();
    $budgetItems = get_project_demand_budget_items($projectId);
    $licitationNumbers = get_project_licitation_number_map($projectId);

    foreach ($items as $index => $item) {
        $procurementItemId = (int) $item['procurement_item_id'];
        $items[$index] = array_merge(
            $item,
            ['licitation_number' => $licitationNumbers[$procurementItemId] ?? null],
            calculated_project_budget_values($item, $budgetItems)
        );
    }

    usort($items, static function (array $left, array $right): int {
        $leftNumber = (int) ($left['licitation_number'] ?? 0);
        $rightNumber = (int) ($right['licitation_number'] ?? 0);

        if ($leftNumber > 0 || $rightNumber > 0) {
            return ($leftNumber ?: PHP_INT_MAX) <=> ($rightNumber ?: PHP_INT_MAX)
                ?: strcasecmp((string) ($left['demand_name'] ?? ''), (string) ($right['demand_name'] ?? ''));
        }

        return strcasecmp((string) ($left['secretariat_name'] ?? ''), (string) ($right['secretariat_name'] ?? ''))
            ?: strcasecmp((string) ($left['demand_name'] ?? ''), (string) ($right['demand_name'] ?? ''))
            ?: strcasecmp((string) ($left['item_name'] ?? ''), (string) ($right['item_name'] ?? ''));
    });

    return $items;
}

function get_project_licitation_annex_i_items(int $projectId): array
{
    $items = get_project_consolidated_items($projectId);
    $itemsByDemand = get_project_items_by_demand($projectId);
    $memoryByItem = [];

    foreach ($itemsByDemand as $demandItem) {
        $procurementItemId = (int) $demandItem['procurement_item_id'];

        $memoryByItem[$procurementItemId][] = [
            'demand_id' => (int) $demandItem['demand_id'],
            'demand_name' => $demandItem['demand_name'],
            'secretariat_name' => $demandItem['secretariat_name'] ?? null,
            'requester_department' => $demandItem['requester_department'] ?? null,
            'quantity' => (float) ($demandItem['approved_quantity'] ?? $demandItem['quantity'] ?? 0),
        ];
    }

    foreach ($items as $index => $item) {
        $procurementItemId = (int) $item['procurement_item_id'];

        $items[$index]['sequence'] = (int) ($item['licitation_number'] ?? 0) ?: $index + 1;
        $items[$index]['annex_quantity'] = (float) (
            $item['total_approved_quantity']
            ?? $item['total_quantity']
            ?? 0
        );
        $items[$index]['demand_memory'] = $memoryByItem[$procurementItemId] ?? [];
    }

    return $items;
}

function get_project_licitation_annex_ii_groups(int $projectId): array
{
    $baseItems = [];

    foreach (get_project_licitation_annex_i_items($projectId) as $item) {
        $baseItems[(int) $item['procurement_item_id']] = $item;
    }

    $rows = [];

    foreach (get_project_demands($projectId) as $demand) {
        $budget = get_demand_budget_report((int) $demand['id']);
        $quotesById = [];

        foreach ($budget['quotes'] as $quote) {
            $quotesById[(int) $quote['id']] = $quote;
        }

        foreach ($budget['items'] as $item) {
            $procurementItemId = (int) $item['procurement_item_id'];
            $baseItem = $baseItems[$procurementItemId] ?? $item;
            $quantity = (float) ($item['budget_quantity'] ?? $item['approved_quantity'] ?? $item['quantity'] ?? 0);
            $suppliers = [];

            foreach ($item['supplier_prices'] ?? [] as $quoteId => $unitPrice) {
                if ($unitPrice === null) {
                    continue;
                }

                $quote = $quotesById[(int) $quoteId] ?? [];
                $supplierId = (int) ($quote['supplier_id'] ?? 0);

                $suppliers[] = [
                    'key' => 'supplier:' . $supplierId,
                    'id' => $supplierId,
                    'name' => $quote['supplier_name'] ?? 'Fornecedor',
                    'trade_name' => $quote['supplier_trade_name'] ?? null,
                    'document' => $quote['supplier_document'] ?? null,
                    'contact_name' => $quote['supplier_contact_name'] ?? null,
                    'email' => $quote['supplier_email'] ?? null,
                    'phone' => $quote['supplier_phone'] ?? null,
                    'address' => $quote['supplier_address'] ?? null,
                    'city' => $quote['supplier_city'] ?? null,
                    'state' => $quote['supplier_state'] ?? null,
                    'postal_code' => $quote['supplier_postal_code'] ?? null,
                    'proposal_date' => $quote['quote_date'] ?? null,
                    'unit_price' => (float) $unitPrice,
                ];
            }

            foreach ($item['historical_references'] ?? [] as $reference) {
                if (($reference['unit_price'] ?? null) === null) {
                    continue;
                }

                $referenceId = (int) ($reference['source_quote_item_id'] ?? $reference['id'] ?? 0);
                $sourceLabel = implode(' - ', array_values(array_filter([
                    trim((string) ($reference['source_project_name'] ?? '')),
                    trim((string) ($reference['source_demand_name'] ?? '')),
                ])));

                $suppliers[] = [
                    'key' => 'historical:' . $referenceId,
                    'id' => $referenceId,
                    'name' => $reference['supplier_name'] ?? 'Fornecedor historico',
                    'trade_name' => $reference['supplier_trade_name'] ?? null,
                    'document' => $reference['supplier_document'] ?? null,
                    'contact_name' => $reference['supplier_contact_name'] ?? null,
                    'email' => $reference['supplier_email'] ?? null,
                    'phone' => $reference['supplier_phone'] ?? null,
                    'address' => $reference['supplier_address'] ?? null,
                    'city' => $reference['supplier_city'] ?? null,
                    'state' => $reference['supplier_state'] ?? null,
                    'postal_code' => $reference['supplier_postal_code'] ?? null,
                    'proposal_date' => $reference['quote_date'] ?? null,
                    'source_label' => $sourceLabel,
                    'unit_price' => (float) $reference['unit_price'],
                ];
            }

            $rows[] = array_merge($baseItem, [
                'annex_quantity' => $quantity,
                'manual_unit_price' => $item['estimated_unit_price'] !== null
                    ? (float) $item['estimated_unit_price']
                    : null,
                'suppliers' => $suppliers,
                'demand_memory' => [[
                    'demand_id' => (int) $demand['id'],
                    'demand_name' => $demand['name'],
                    'secretariat_name' => $demand['secretariat_name'] ?? null,
                    'requester_department' => $demand['requester_department'] ?? null,
                    'quantity' => $quantity,
                ]],
            ]);
        }
    }

    return build_licitation_annex_ii_groups_from_rows($rows);
}

function get_project_licitation_annex_iii_summary(int $projectId): array
{
    $annex = get_project_licitation_annex_ii_groups($projectId);
    $items = [];
    $globalTotal = 0.0;

    foreach ($annex['groups'] ?? [] as $group) {
        foreach ($group['items'] ?? [] as $item) {
            $procurementItemId = (int) ($item['procurement_item_id'] ?? 0);

            if ($procurementItemId <= 0) {
                continue;
            }

            if (!isset($items[$procurementItemId])) {
                $items[$procurementItemId] = array_merge($item, [
                    'annex_quantity' => 0.0,
                    'estimated_total' => null,
                    'estimated_unit_price' => null,
                ]);
            }

            $quantity = (float) ($item['annex_quantity'] ?? 0);
            $estimatedTotal = $item['estimated_total'] !== null
                ? (float) $item['estimated_total']
                : null;

            $items[$procurementItemId]['annex_quantity'] += $quantity;

            if ($estimatedTotal !== null) {
                $items[$procurementItemId]['estimated_total'] = ($items[$procurementItemId]['estimated_total'] ?? 0.0)
                    + $estimatedTotal;
                $globalTotal += $estimatedTotal;
            }
        }
    }

    foreach ($items as $index => $item) {
        $quantity = (float) ($item['annex_quantity'] ?? 0);
        $total = $item['estimated_total'] !== null ? (float) $item['estimated_total'] : null;

        $items[$index]['estimated_unit_price'] = $quantity > 0 && $total !== null
            ? round_money_value($total / $quantity)
            : null;
        $items[$index]['estimated_total'] = $total !== null ? round_money_value($total) : null;
    }

    usort($items, static function (array $left, array $right): int {
        return ((int) ($left['sequence'] ?? PHP_INT_MAX))
            <=> ((int) ($right['sequence'] ?? PHP_INT_MAX));
    });

    return [
        'items' => array_values($items),
        'global_total' => round_money_value($globalTotal),
    ];
}

function get_project_financial_summary(int $projectId): array
{
    $stmt = db()->prepare("
        SELECT
            SUM(di.quantity) AS total_requested_quantity,
            SUM(COALESCE(di.approved_quantity, di.quantity)) AS total_approved_quantity,
            SUM(COALESCE(di.approved_quantity, di.quantity) * COALESCE(di.estimated_unit_price, 0)) AS total_estimated_value
        FROM demand_items di
        INNER JOIN demand_lists dl ON dl.id = di.demand_list_id
        WHERE dl.project_id = :project_id
    ");

    $stmt->execute([
        'project_id' => $projectId,
    ]);

    $summary = $stmt->fetch() ?: [
        'total_requested_quantity' => 0,
        'total_approved_quantity' => 0,
        'total_estimated_value' => 0,
    ];

    $summary['total_requested_quantity'] = $summary['total_requested_quantity'] ?? 0;
    $summary['total_approved_quantity'] = $summary['total_approved_quantity'] ?? 0;
    $annex = get_project_licitation_annex_ii_groups($projectId);
    $summary['total_estimated_value'] = (float) ($annex['global_total'] ?? 0);
    $summary['uses_supplier_average'] = false;

    foreach ($annex['groups'] ?? [] as $group) {
        foreach ($group['items'] ?? [] as $item) {
            if (array_filter($item['supplier_prices'] ?? [], static fn (mixed $price): bool => $price !== null)) {
                $summary['uses_supplier_average'] = true;
                break 2;
            }
        }
    }

    return $summary;
}

function get_project_secretariat_summary(int $projectId): array
{
    $summary = [];

    foreach (get_project_demands($projectId) as $demand) {
        $secretariatName = $demand['secretariat_name'] ?: 'Sem secretaria vinculada';

        if (!isset($summary[$secretariatName])) {
            $summary[$secretariatName] = [
                'secretariat_name' => $secretariatName,
                'demand_count' => 0,
                'total_requested_quantity' => 0,
                'total_approved_quantity' => 0,
                'total_estimated_value' => 0,
                'uses_supplier_average' => false,
            ];
        }

        $demandSummary = get_demand_financial_summary((int) $demand['id']);

        $summary[$secretariatName]['demand_count']++;
        $summary[$secretariatName]['total_requested_quantity'] += (float) ($demandSummary['total_requested_quantity'] ?? 0);
        $summary[$secretariatName]['total_approved_quantity'] += (float) ($demandSummary['total_approved_quantity'] ?? 0);
        $summary[$secretariatName]['total_estimated_value'] += (float) ($demandSummary['total_estimated_value'] ?? 0);
        $summary[$secretariatName]['uses_supplier_average'] = $summary[$secretariatName]['uses_supplier_average']
            || !empty($demandSummary['uses_supplier_average']);
    }

    ksort($summary, SORT_NATURAL | SORT_FLAG_CASE);

    return array_values($summary);
}

function insert_cloned_demand_list(array $demand, int $newProjectId): int
{
    $stmt = db()->prepare("
        INSERT INTO demand_lists (
            project_id,
            requester_unit_id,
            secretariat_id,
            name,
            requester_department,
            responsible_name,
            notes
        ) VALUES (
            :project_id,
            :requester_unit_id,
            :secretariat_id,
            :name,
            :requester_department,
            :responsible_name,
            :notes
        )
        RETURNING id
    ");

    $stmt->execute([
        'project_id' => $newProjectId,
        'requester_unit_id' => $demand['requester_unit_id'] ?? null,
        'secretariat_id' => $demand['secretariat_id'] ?? null,
        'name' => $demand['name'],
        'requester_department' => $demand['requester_department'] ?? null,
        'responsible_name' => $demand['responsible_name'] ?? null,
        'notes' => $demand['notes'] ?? null,
    ]);

    return (int) $stmt->fetchColumn();
}

function insert_cloned_demand_item(array $item, int $newDemandId): int
{
    $stmt = db()->prepare("
        INSERT INTO demand_items (
            demand_list_id,
            procurement_item_id,
            quantity,
            approved_quantity,
            estimated_unit_price,
            notes
        ) VALUES (
            :demand_list_id,
            :procurement_item_id,
            :quantity,
            :approved_quantity,
            :estimated_unit_price,
            :notes
        )
        RETURNING id
    ");

    $stmt->execute([
        'demand_list_id' => $newDemandId,
        'procurement_item_id' => $item['procurement_item_id'],
        'quantity' => $item['quantity'],
        'approved_quantity' => $item['approved_quantity'] ?? null,
        'estimated_unit_price' => $item['estimated_unit_price'] ?? null,
        'notes' => $item['notes'] ?? null,
    ]);

    return (int) $stmt->fetchColumn();
}

function clone_project_demands_and_items(int $projectId, int $newProjectId): array
{
    $demandIdMap = [];
    $demandItemIdMap = [];

    foreach (get_project_demands($projectId) as $demand) {
        $oldDemandId = (int) $demand['id'];
        $newDemandId = insert_cloned_demand_list($demand, $newProjectId);
        $demandIdMap[$oldDemandId] = $newDemandId;

        foreach (get_demand_items($oldDemandId) as $item) {
            $demandItemIdMap[(int) $item['id']] = insert_cloned_demand_item($item, $newDemandId);
        }
    }

    return [$demandIdMap, $demandItemIdMap];
}

function clone_project_licitation_numbers(int $projectId, int $newProjectId): void
{
    if (!database_table_exists('project_licitation_items')) {
        return;
    }

    $stmt = db()->prepare("
        INSERT INTO project_licitation_items (
            project_id,
            procurement_item_id,
            licitation_number
        )
        SELECT
            :new_project_id,
            procurement_item_id,
            licitation_number
        FROM project_licitation_items
        WHERE project_id = :project_id
        ON CONFLICT DO NOTHING
    ");

    $stmt->execute([
        'project_id' => $projectId,
        'new_project_id' => $newProjectId,
    ]);
}

function clone_project_lot_denominations(int $projectId, int $newProjectId): void
{
    if (
        !database_table_exists('project_lot_denominations')
        || !database_table_exists('project_lot_assignments')
    ) {
        return;
    }

    $lotMap = [];
    $selectLots = db()->prepare("
        SELECT *
        FROM project_lot_denominations
        WHERE project_id = :project_id
        ORDER BY lot_number, id
    ");
    $selectLots->execute(['project_id' => $projectId]);

    $insertLot = db()->prepare("
        INSERT INTO project_lot_denominations (
            project_id,
            lot_number,
            name,
            justification
        ) VALUES (
            :project_id,
            :lot_number,
            :name,
            :justification
        )
        RETURNING id
    ");

    foreach ($selectLots->fetchAll() as $lot) {
        $insertLot->execute([
            'project_id' => $newProjectId,
            'lot_number' => (int) $lot['lot_number'],
            'name' => $lot['name'],
            'justification' => $lot['justification'],
        ]);

        $lotMap[(int) $lot['id']] = (int) $insertLot->fetchColumn();
    }

    if (!$lotMap) {
        return;
    }

    $selectAssignments = db()->prepare("
        SELECT pla.*
        FROM project_lot_assignments pla
        INNER JOIN project_lot_denominations l ON l.id = pla.project_lot_id
        WHERE l.project_id = :project_id
        ORDER BY l.lot_number, pla.id
    ");
    $selectAssignments->execute(['project_id' => $projectId]);

    $insertAssignment = db()->prepare("
        INSERT INTO project_lot_assignments (
            project_lot_id,
            assignment_type,
            procurement_item_id,
            category_id
        ) VALUES (
            :project_lot_id,
            :assignment_type,
            :procurement_item_id,
            :category_id
        )
        ON CONFLICT DO NOTHING
    ");

    foreach ($selectAssignments->fetchAll() as $assignment) {
        $oldLotId = (int) $assignment['project_lot_id'];

        if (!isset($lotMap[$oldLotId])) {
            continue;
        }

        $insertAssignment->execute([
            'project_lot_id' => $lotMap[$oldLotId],
            'assignment_type' => $assignment['assignment_type'],
            'procurement_item_id' => $assignment['procurement_item_id'] ?? null,
            'category_id' => $assignment['category_id'] ?? null,
        ]);
    }
}

function get_projects_with_lot_denominations(?int $excludeProjectId = null): array
{
    if (!database_table_exists('project_lot_denominations')) {
        return [];
    }

    $sql = "
        SELECT
            p.id,
            p.name,
            p.status,
            COUNT(l.id) AS lot_count
        FROM procurement_projects p
        INNER JOIN project_lot_denominations l ON l.project_id = p.id
    ";
    $params = [];

    if ($excludeProjectId !== null) {
        $sql .= " WHERE p.id <> :exclude_project_id";
        $params['exclude_project_id'] = $excludeProjectId;
    }

    $sql .= "
        GROUP BY p.id, p.name, p.status
        ORDER BY p.name
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function copy_project_lot_denominations_from_project(int $sourceProjectId, int $targetProjectId, bool $replaceExisting = false): int
{
    if ($sourceProjectId === $targetProjectId) {
        throw new InvalidArgumentException('Selecione um projeto de origem diferente.');
    }

    assert_project_editable($targetProjectId);

    $sourceLots = get_project_lot_denominations($sourceProjectId);

    if (!$sourceLots) {
        throw new RuntimeException('O projeto de origem nao possui denominacoes.');
    }

    $targetLots = get_project_lot_denominations($targetProjectId);

    if ($targetLots && !$replaceExisting) {
        throw new RuntimeException('Este projeto ja possui denominacoes. Marque a opcao de substituir para copiar.');
    }

    db()->beginTransaction();

    try {
        if ($replaceExisting) {
            $delete = db()->prepare("
                DELETE FROM project_lot_denominations
                WHERE project_id = :project_id
            ");
            $delete->execute(['project_id' => $targetProjectId]);
        }

        clone_project_lot_denominations($sourceProjectId, $targetProjectId);
        invalidate_project_annex_versions($targetProjectId);
        db()->commit();
    } catch (Throwable $exception) {
        db()->rollBack();
        throw $exception;
    }

    return count($sourceLots);
}
function clone_project_supplier_quotes(array $demandIdMap, array $demandItemIdMap): array
{
    if (
        !$demandIdMap
        || !database_table_exists('demand_supplier_quotes')
        || !database_table_exists('demand_supplier_quote_items')
    ) {
        return [];
    }

    $quoteItemIdMap = [];
    $pendingQuoteItemReuse = [];

    $selectQuotes = db()->prepare("
        SELECT *
        FROM demand_supplier_quotes
        WHERE demand_list_id = :demand_list_id
        ORDER BY id
    ");

    $insertQuote = db()->prepare("
        INSERT INTO demand_supplier_quotes (
            demand_list_id,
            supplier_id,
            quote_number,
            quote_date,
            validity_date,
            quoted_by,
            collected_by,
            attachment_path,
            notes,
            status
        ) VALUES (
            :demand_list_id,
            :supplier_id,
            :quote_number,
            :quote_date,
            :validity_date,
            :quoted_by,
            :collected_by,
            :attachment_path,
            :notes,
            :status
        )
        RETURNING id
    ");

    $selectQuoteItems = db()->prepare("
        SELECT *
        FROM demand_supplier_quote_items
        WHERE demand_supplier_quote_id = :quote_id
        ORDER BY id
    ");

    $insertQuoteItem = db()->prepare("
        INSERT INTO demand_supplier_quote_items (
            demand_supplier_quote_id,
            demand_item_id,
            unit_price,
            notes,
            reused_from_quote_item_id
        ) VALUES (
            :quote_id,
            :demand_item_id,
            :unit_price,
            :notes,
            NULL
        )
        RETURNING id
    ");

    foreach ($demandIdMap as $oldDemandId => $newDemandId) {
        $selectQuotes->execute(['demand_list_id' => $oldDemandId]);

        foreach ($selectQuotes->fetchAll() as $quote) {
            $insertQuote->execute([
                'demand_list_id' => $newDemandId,
                'supplier_id' => $quote['supplier_id'],
                'quote_number' => $quote['quote_number'] ?? null,
                'quote_date' => $quote['quote_date'] ?? null,
                'validity_date' => $quote['validity_date'] ?? null,
                'quoted_by' => $quote['quoted_by'] ?? null,
                'collected_by' => $quote['collected_by'] ?? null,
                'attachment_path' => $quote['attachment_path'] ?? null,
                'notes' => $quote['notes'] ?? null,
                'status' => $quote['status'] ?? 'received',
            ]);

            $newQuoteId = (int) $insertQuote->fetchColumn();
            $selectQuoteItems->execute(['quote_id' => (int) $quote['id']]);

            foreach ($selectQuoteItems->fetchAll() as $quoteItem) {
                $oldDemandItemId = (int) $quoteItem['demand_item_id'];

                if (!isset($demandItemIdMap[$oldDemandItemId])) {
                    continue;
                }

                $insertQuoteItem->execute([
                    'quote_id' => $newQuoteId,
                    'demand_item_id' => $demandItemIdMap[$oldDemandItemId],
                    'unit_price' => $quoteItem['unit_price'] ?? null,
                    'notes' => $quoteItem['notes'] ?? null,
                ]);

                $newQuoteItemId = (int) $insertQuoteItem->fetchColumn();
                $quoteItemIdMap[(int) $quoteItem['id']] = $newQuoteItemId;

                if (!empty($quoteItem['reused_from_quote_item_id'])) {
                    $pendingQuoteItemReuse[$newQuoteItemId] = (int) $quoteItem['reused_from_quote_item_id'];
                }
            }
        }
    }

    if ($pendingQuoteItemReuse) {
        $updateReuse = db()->prepare("
            UPDATE demand_supplier_quote_items
            SET reused_from_quote_item_id = :reused_from_quote_item_id
            WHERE id = :id
        ");

        foreach ($pendingQuoteItemReuse as $newQuoteItemId => $oldSourceQuoteItemId) {
            $updateReuse->execute([
                'id' => $newQuoteItemId,
                'reused_from_quote_item_id' => $quoteItemIdMap[$oldSourceQuoteItemId] ?? $oldSourceQuoteItemId,
            ]);
        }
    }

    return $quoteItemIdMap;
}

function clone_project_price_references(array $demandItemIdMap, array $quoteItemIdMap): void
{
    if (!$demandItemIdMap || !database_table_exists('demand_price_references')) {
        return;
    }

    $selectReferences = db()->prepare("
        SELECT *
        FROM demand_price_references
        WHERE demand_item_id = :demand_item_id
        ORDER BY id
    ");

    $insertReference = db()->prepare("
        INSERT INTO demand_price_references (
            demand_item_id,
            source_quote_item_id,
            notes
        ) VALUES (
            :demand_item_id,
            :source_quote_item_id,
            :notes
        )
        ON CONFLICT DO NOTHING
    ");

    foreach ($demandItemIdMap as $oldDemandItemId => $newDemandItemId) {
        $selectReferences->execute(['demand_item_id' => $oldDemandItemId]);

        foreach ($selectReferences->fetchAll() as $reference) {
            $oldSourceQuoteItemId = (int) $reference['source_quote_item_id'];

            $insertReference->execute([
                'demand_item_id' => $newDemandItemId,
                'source_quote_item_id' => $quoteItemIdMap[$oldSourceQuoteItemId] ?? $oldSourceQuoteItemId,
                'notes' => $reference['notes'] ?? null,
            ]);
        }
    }
}

function duplicate_project(int $projectId): int
{
    $project = find_project($projectId);

    if (!$project) {
        throw new RuntimeException('Projeto nao encontrado.');
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $newProjectId = create_project([
            'name' => $project['name'] . ' - Copia',
            'description' => $project['description'],
            'status' => 'draft',
        ]);

        [$demandIdMap, $demandItemIdMap] = clone_project_demands_and_items($projectId, $newProjectId);
        clone_project_licitation_numbers($projectId, $newProjectId);
        clone_project_lot_denominations($projectId, $newProjectId);
        $quoteItemIdMap = clone_project_supplier_quotes($demandIdMap, $demandItemIdMap);
        clone_project_price_references($demandItemIdMap, $quoteItemIdMap);

        $pdo->commit();

        return $newProjectId;
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}

function get_item_images(int $itemId): array
{
    $stmt = db()->prepare("
        SELECT *
        FROM procurement_item_images
        WHERE procurement_item_id = :item_id
        ORDER BY is_primary DESC, id ASC
    ");

    $stmt->execute([
        'item_id' => $itemId,
    ]);

    return $stmt->fetchAll();
}

function get_item_primary_image(int $itemId): ?array
{
    $stmt = db()->prepare("
        SELECT *
        FROM procurement_item_images
        WHERE procurement_item_id = :item_id
        ORDER BY is_primary DESC, id ASC
        LIMIT 1
    ");

    $stmt->execute([
        'item_id' => $itemId,
    ]);

    $image = $stmt->fetch();

    return $image ?: null;
}

function add_item_images(int $itemId, array $paths): void
{
    if (!$paths) {
        return;
    }

    $hasPrimary = get_item_primary_image($itemId) !== null;

    foreach ($paths as $index => $path) {
        $isPrimary = !$hasPrimary && $index === 0;

        $stmt = db()->prepare("
            INSERT INTO procurement_item_images (
                procurement_item_id,
                image_path,
                is_primary
            ) VALUES (
                :procurement_item_id,
                :image_path,
                :is_primary
            )
        ");

        $stmt->execute([
            'procurement_item_id' => $itemId,
            'image_path' => $path,
            'is_primary' => $isPrimary ? 'true' : 'false',
        ]);
    }
}

function set_item_primary_image(int $itemId, int $imageId): void
{
    db()->beginTransaction();

    try {
        $stmt = db()->prepare("
            UPDATE procurement_item_images
            SET is_primary = FALSE
            WHERE procurement_item_id = :item_id
        ");

        $stmt->execute([
            'item_id' => $itemId,
        ]);

        $stmt = db()->prepare("
            UPDATE procurement_item_images
            SET is_primary = TRUE
            WHERE id = :image_id
              AND procurement_item_id = :item_id
        ");

        $stmt->execute([
            'image_id' => $imageId,
            'item_id' => $itemId,
        ]);

        db()->commit();
    } catch (Throwable $exception) {
        db()->rollBack();
        throw $exception;
    }
}

function delete_item_image(int $imageId): void
{
    $stmt = db()->prepare("
        SELECT *
        FROM procurement_item_images
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $imageId,
    ]);

    $image = $stmt->fetch();

    if (!$image) {
        return;
    }

    $filePath = __DIR__ . '/../public' . $image['image_path'];

    $stmt = db()->prepare("
        DELETE FROM procurement_item_images
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $imageId,
    ]);

    if (is_file($filePath)) {
        unlink($filePath);
    }

    $remaining = get_item_images((int) $image['procurement_item_id']);

    $hasPrimary = false;

    foreach ($remaining as $itemImage) {
        if ($itemImage['is_primary']) {
            $hasPrimary = true;
            break;
        }
    }

    if (!$hasPrimary && $remaining) {
        set_item_primary_image(
            (int) $image['procurement_item_id'],
            (int) $remaining[0]['id']
        );
    }
}

function duplicate_item(int $id): int
{
    $item = find_item($id);

    if (!$item) {
        throw new RuntimeException('Item não encontrado.');
    }

    $newName = $item['name'] . ' - Cópia';

    $stmt = db()->prepare("
        INSERT INTO procurement_items (
            category_id,
            subcategory_id,
            unit_type_id,
            package_content_quantity,
            package_content_unit_type_id,
            level,
            status,
            name,
            specification,
            justification,
            warranty,
            environmental_impacts,
            image_path
        ) VALUES (
            :category_id,
            :subcategory_id,
            :unit_type_id,
            :package_content_quantity,
            :package_content_unit_type_id,
            :level,
            :status,
            :name,
            :specification::jsonb,
            :justification,
            :warranty,
            :environmental_impacts,
            :image_path
        )
        RETURNING id
    ");

    $specification = is_string($item['specification'])
        ? $item['specification']
        : json_encode($item['specification'], JSON_UNESCAPED_UNICODE);

    $stmt->execute([
        'category_id' => $item['category_id'] ?: null,
        'subcategory_id' => $item['subcategory_id'] ?: null,
        'unit_type_id' => $item['unit_type_id'] ?: null,
        'package_content_quantity' => normalize_decimal_db_value($item['package_content_quantity'] ?? null),
        'package_content_unit_type_id' => $item['package_content_unit_type_id'] ?: null,
        'level' => $item['level'],
        'status' => $item['status'] ?? 'draft',
        'name' => $newName,
        'specification' => normalize_item_specification_json((string) $specification),
        'justification' => $item['justification'],
        'warranty' => $item['warranty'],
        'environmental_impacts' => normalize_environmental_impacts_json($item['environmental_impacts'] ?? ''),
        'image_path' => $item['image_path'] ?? null,
    ]);

    $newId = (int) $stmt->fetchColumn();

    $images = get_item_images($id);

    foreach ($images as $image) {
        $oldFile = __DIR__ . '/../public' . $image['image_path'];

        if (!is_file($oldFile)) {
            continue;
        }

        $extension = pathinfo($oldFile, PATHINFO_EXTENSION);
        $newFilename = 'item_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
        $newRelativePath = '/uploads/items/' . $newFilename;
        $newFile = __DIR__ . '/../public' . $newRelativePath;

        copy($oldFile, $newFile);

        $stmtImage = db()->prepare("
            INSERT INTO procurement_item_images (
                procurement_item_id,
                image_path,
                is_primary
            ) VALUES (
                :procurement_item_id,
                :image_path,
                :is_primary
            )
        ");

        $stmtImage->execute([
            'procurement_item_id' => $newId,
            'image_path' => $newRelativePath,
            'is_primary' => $image['is_primary'] ? 'true' : 'false',
        ]);
    }

    return $newId;
}

function get_unit_types(): array
{
    $stmt = db()->query("
        SELECT *
        FROM unit_types
        ORDER BY name
    ");

    return $stmt->fetchAll();
}

function find_unit_type(int $id): ?array
{
    $stmt = db()->prepare("
        SELECT *
        FROM unit_types
        WHERE id = :id
    ");

    $stmt->execute(['id' => $id]);

    $unitType = $stmt->fetch();

    return $unitType ?: null;
}

function create_unit_type(array $data): int
{
    $stmt = db()->prepare("
        INSERT INTO unit_types (
            name,
            abbreviation,
            description
        ) VALUES (
            :name,
            :abbreviation,
            :description
        )
        RETURNING id
    ");

    $stmt->execute([
        'name' => $data['name'],
        'abbreviation' => $data['abbreviation'] ?? null,
        'description' => $data['description'] ?? null,
    ]);

    return (int) $stmt->fetchColumn();
}

function update_unit_type(int $id, array $data): void
{
    $stmt = db()->prepare("
        UPDATE unit_types SET
            name = :name,
            abbreviation = :abbreviation,
            description = :description
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $id,
        'name' => $data['name'],
        'abbreviation' => $data['abbreviation'] ?? null,
        'description' => $data['description'] ?? null,
    ]);
}

function delete_unit_type(int $id): void
{
    $stmt = db()->prepare("
        DELETE FROM unit_types
        WHERE id = :id
    ");

    $stmt->execute(['id' => $id]);
}

function get_justification_templates(): array
{
    return db()->query("
        SELECT jt.*, c.name AS category_name
        FROM justification_templates jt
        LEFT JOIN categories c ON c.id = jt.category_id
        ORDER BY jt.title
    ")->fetchAll();
}

function get_environmental_impact_templates(): array
{
    return db()->query("
        SELECT eit.*, c.name AS category_name
        FROM environmental_impact_templates eit
        LEFT JOIN categories c ON c.id = eit.category_id
        ORDER BY NULLIF(to_jsonb(eit)->>'code', '') NULLS LAST, eit.title
    ")->fetchAll();
}

function create_justification_template(array $data): int
{
    $stmt = db()->prepare("
        INSERT INTO justification_templates (title, content, category_id, is_active)
        VALUES (:title, :content, :category_id, :is_active)
        RETURNING id
    ");

    $stmt->execute([
        'title' => $data['title'],
        'content' => $data['content'],
        'category_id' => $data['category_id'] ?: null,
        'is_active' => pg_bool($data['is_active'] ?? true),
    ]);

    return (int) $stmt->fetchColumn();
}

function create_environmental_impact_template(array $data): int
{
    $stmt = db()->prepare("
        INSERT INTO environmental_impact_templates (code, title, content, category_id, is_active)
        VALUES (:code, :title, :content, :category_id, :is_active)
        RETURNING id
    ");

    $stmt->execute([
        'code' => $data['code'] ?? null,
        'title' => $data['title'],
        'content' => $data['content'],
        'category_id' => $data['category_id'] ?: null,
        'is_active' => pg_bool($data['is_active'] ?? true),
    ]);

    return (int) $stmt->fetchColumn();
}

function get_item_kits(): array
{
    return db()->query("
        SELECT *
        FROM item_kits
        ORDER BY name
    ")->fetchAll();
}

function find_item_kit(int $id): ?array
{
    $stmt = db()->prepare("SELECT * FROM item_kits WHERE id = :id");
    $stmt->execute(['id' => $id]);

    $kit = $stmt->fetch();

    return $kit ?: null;
}

function create_item_kit(array $data): int
{
    $stmt = db()->prepare("
        INSERT INTO item_kits (name, description, is_active)
        VALUES (:name, :description, :is_active)
        RETURNING id
    ");

    $stmt->execute([
        'name' => $data['name'],
        'description' => $data['description'] ?? null,
        'is_active' => pg_bool($data['is_active'] ?? true),
    ]);

    return (int) $stmt->fetchColumn();
}

function get_item_kit_items(int $kitId): array
{
    $trackingCodeSql = item_tracking_code_sql('pi');

    $stmt = db()->prepare("
        SELECT
            iki.*,
            pi.name AS item_name,
            {$trackingCodeSql} AS tracking_code
        FROM item_kit_items iki
        INNER JOIN procurement_items pi ON pi.id = iki.procurement_item_id
        WHERE iki.kit_id = :kit_id
        ORDER BY pi.name
    ");

    $stmt->execute(['kit_id' => $kitId]);

    return $stmt->fetchAll();
}

function add_item_to_kit(array $data): void
{
    $stmt = db()->prepare("
        INSERT INTO item_kit_items (
            kit_id,
            procurement_item_id,
            quantity,
            notes
        ) VALUES (
            :kit_id,
            :procurement_item_id,
            :quantity,
            :notes
        )
        ON CONFLICT (kit_id, procurement_item_id)
        DO UPDATE SET
            quantity = EXCLUDED.quantity,
            notes = EXCLUDED.notes
    ");

    $stmt->execute([
        'kit_id' => $data['kit_id'],
        'procurement_item_id' => $data['procurement_item_id'],
        'quantity' => $data['quantity'],
        'notes' => $data['notes'] ?? null,
    ]);
}

function delete_item_kit_item(int $id): void
{
    $stmt = db()->prepare("DELETE FROM item_kit_items WHERE id = :id");
    $stmt->execute(['id' => $id]);
}

function add_kit_to_demand(int $demandListId, int $kitId, float $multiplier = 1): void
{
    $items = get_item_kit_items($kitId);

    foreach ($items as $item) {
        $quantity = (float) $item['quantity'] * $multiplier;

        add_demand_item([
            'demand_list_id' => $demandListId,
            'procurement_item_id' => $item['procurement_item_id'],
            'quantity' => $quantity,
            'approved_quantity' => $quantity,
            'estimated_unit_price' => null,
            'notes' => $item['notes'] ?? null,
        ]);
    }
}

function find_justification_template(int $id): ?array
{
    $stmt = db()->prepare("
        SELECT *
        FROM justification_templates
        WHERE id = :id
    ");

    $stmt->execute(['id' => $id]);

    $template = $stmt->fetch();

    return $template ?: null;
}

function update_justification_template(int $id, array $data): void
{
    $stmt = db()->prepare("
        UPDATE justification_templates SET
            title = :title,
            content = :content,
            category_id = :category_id,
            is_active = :is_active
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $id,
        'title' => $data['title'],
        'content' => $data['content'],
        'category_id' => $data['category_id'] ?: null,
        'is_active' => pg_bool($data['is_active'] ?? true),
    ]);
}

function delete_justification_template(int $id): void
{
    $stmt = db()->prepare("
        DELETE FROM justification_templates
        WHERE id = :id
    ");

    $stmt->execute(['id' => $id]);
}

function find_environmental_impact_template(int $id): ?array
{
    $stmt = db()->prepare("
        SELECT *
        FROM environmental_impact_templates
        WHERE id = :id
    ");

    $stmt->execute(['id' => $id]);

    $template = $stmt->fetch();

    return $template ?: null;
}

function update_environmental_impact_template(int $id, array $data): void
{
    $stmt = db()->prepare("
        UPDATE environmental_impact_templates SET
            code = :code,
            title = :title,
            content = :content,
            category_id = :category_id,
            is_active = :is_active
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $id,
        'code' => $data['code'] ?? null,
        'title' => $data['title'],
        'content' => $data['content'],
        'category_id' => $data['category_id'] ?: null,
        'is_active' => pg_bool($data['is_active'] ?? true),
    ]);
}

function delete_environmental_impact_template(int $id): void
{
    $stmt = db()->prepare("
        DELETE FROM environmental_impact_templates
        WHERE id = :id
    ");

    $stmt->execute(['id' => $id]);
}

function update_item_kit(int $id, array $data): void
{
    $stmt = db()->prepare("
        UPDATE item_kits SET
            name = :name,
            description = :description,
            is_active = :is_active
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $id,
        'name' => $data['name'],
        'description' => $data['description'] ?? null,
        'is_active' => pg_bool($data['is_active'] ?? true),
    ]);
}

function delete_item_kit(int $id): void
{
    $stmt = db()->prepare("
        DELETE FROM item_kits
        WHERE id = :id
    ");

    $stmt->execute(['id' => $id]);
}

function get_project_signature_blocks(int $projectId): array
{
    $stmt = db()->prepare("
        SELECT
            dl.id,
            dl.name,
            s.name AS secretariat_name,
            dl.requester_department,
            dl.responsible_name
        FROM demand_lists dl
        LEFT JOIN secretariats s ON s.id = dl.secretariat_id
        WHERE dl.project_id = :project_id
        ORDER BY s.name NULLS LAST, dl.name
    ");

    $stmt->execute([
        'project_id' => $projectId,
    ]);

    return $stmt->fetchAll();
}

function get_demand_financial_summary(int $demandListId): array
{
    $stmt = db()->prepare("
        SELECT
            SUM(quantity) AS total_requested_quantity,
            SUM(COALESCE(approved_quantity, quantity)) AS total_approved_quantity,
            SUM(COALESCE(approved_quantity, quantity) * COALESCE(estimated_unit_price, 0)) AS total_estimated_value
        FROM demand_items
        WHERE demand_list_id = :demand_list_id
    ");

    $stmt->execute([
        'demand_list_id' => $demandListId,
    ]);

    $summary = $stmt->fetch() ?: [
        'total_requested_quantity' => 0,
        'total_approved_quantity' => 0,
        'total_estimated_value' => 0,
    ];

    $summary['total_requested_quantity'] = $summary['total_requested_quantity'] ?? 0;
    $summary['total_approved_quantity'] = $summary['total_approved_quantity'] ?? 0;
    $summary['total_estimated_value'] = $summary['total_estimated_value'] ?? 0;

    $budget = get_demand_budget_report($demandListId);

    if ($budget['items'] ?? []) {
        $summary['total_estimated_value'] = 0.0;
        $summary['uses_supplier_average'] = false;

        foreach ($budget['items'] as $item) {
            if (($item['average_total'] ?? null) !== null) {
                $summary['total_estimated_value'] += (float) $item['average_total'];
                $summary['uses_supplier_average'] = true;
                continue;
            }

            $summary['total_estimated_value'] += (float) ($item['estimated_total'] ?? 0);
        }
    }

    return $summary;
}

function find_similar_items(string $name, ?int $ignoreId = null, float $threshold = 0.25): array
{
    $trackingCodeSql = item_tracking_code_sql('procurement_items');

    $sql = "
        SELECT
            id,
            {$trackingCodeSql} AS tracking_code,
            name,
            level,
            status,
            similarity(name, :name) AS similarity_score
        FROM procurement_items
        WHERE similarity(name, :name) >= :threshold
    ";

    $params = [
        'name' => $name,
        'threshold' => $threshold,
    ];

    if ($ignoreId) {
        $sql .= " AND id <> :ignore_id";
        $params['ignore_id'] = $ignoreId;
    }

    $sql .= "
        ORDER BY similarity_score DESC, name ASC
        LIMIT 10
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function get_item_versions(int $itemId): array
{
    $stmt = db()->prepare("
        SELECT
            v.*,
            ut.name AS unit_type_name,
            ut.abbreviation AS unit_type_abbreviation,
            content_ut.name AS package_content_unit_type_name,
            content_ut.abbreviation AS package_content_unit_type_abbreviation
        FROM procurement_item_versions v
        LEFT JOIN unit_types ut ON ut.id = v.unit_type_id
        LEFT JOIN unit_types content_ut ON content_ut.id = v.package_content_unit_type_id
        WHERE v.procurement_item_id = :item_id
        ORDER BY v.version_number DESC
    ");

    $stmt->execute([
        'item_id' => $itemId,
    ]);

    return $stmt->fetchAll();
}

function find_item_version(int $id): ?array
{
    $stmt = db()->prepare("
        SELECT
            v.*,
            ut.name AS unit_type_name,
            ut.abbreviation AS unit_type_abbreviation,
            content_ut.name AS package_content_unit_type_name,
            content_ut.abbreviation AS package_content_unit_type_abbreviation
        FROM procurement_item_versions v
        LEFT JOIN unit_types ut ON ut.id = v.unit_type_id
        LEFT JOIN unit_types content_ut ON content_ut.id = v.package_content_unit_type_id
        WHERE v.id = :id
    ");

    $stmt->execute([
        'id' => $id,
    ]);

    $version = $stmt->fetch();

    return $version ?: null;
}

function get_next_item_version_number(int $itemId): int
{
    $stmt = db()->prepare("
        SELECT COALESCE(MAX(version_number), 0) + 1
        FROM procurement_item_versions
        WHERE procurement_item_id = :item_id
    ");

    $stmt->execute([
        'item_id' => $itemId,
    ]);

    return (int) $stmt->fetchColumn();
}

function create_item_version(int $itemId, ?string $notes = null): int
{
    $item = find_item($itemId);

    if (!$item) {
        throw new RuntimeException('Item não encontrado.');
    }

    $versionNumber = get_next_item_version_number($itemId);

    $stmt = db()->prepare("
        INSERT INTO procurement_item_versions (
            procurement_item_id,
            version_number,
            name,
            specification,
            justification,
            warranty,
            environmental_impacts,
            level,
            status,
            unit_type_id,
            package_content_quantity,
            package_content_unit_type_id,
            notes
        ) VALUES (
            :procurement_item_id,
            :version_number,
            :name,
            :specification::jsonb,
            :justification,
            :warranty,
            :environmental_impacts,
            :level,
            :status,
            :unit_type_id,
            :package_content_quantity,
            :package_content_unit_type_id,
            :notes
        )
        RETURNING id
    ");

    $stmt->execute([
        'procurement_item_id' => $itemId,
        'version_number' => $versionNumber,
        'name' => $item['name'],
        'specification' => is_string($item['specification'])
            ? $item['specification']
            : json_encode($item['specification'], JSON_UNESCAPED_UNICODE),
        'justification' => $item['justification'],
        'warranty' => $item['warranty'],
        'environmental_impacts' => $item['environmental_impacts'],
        'level' => $item['level'],
        'status' => $item['status'] ?? 'draft',
        'unit_type_id' => $item['unit_type_id'] ?? null,
        'package_content_quantity' => normalize_decimal_db_value($item['package_content_quantity'] ?? null),
        'package_content_unit_type_id' => $item['package_content_unit_type_id'] ?? null,
        'notes' => $notes,
    ]);

    return (int) $stmt->fetchColumn();
}

function restore_item_version(int $versionId): int
{
    $version = find_item_version($versionId);

    if (!$version) {
        throw new RuntimeException('Versão não encontrada.');
    }

    $itemId = (int) $version['procurement_item_id'];

    create_item_version(
        $itemId,
        'Snapshot automático antes de restaurar a versão ' . $version['version_number']
    );

    $stmt = db()->prepare("
        UPDATE procurement_items SET
            name = :name,
            specification = :specification::jsonb,
            justification = :justification,
            warranty = :warranty,
            environmental_impacts = :environmental_impacts,
            level = :level,
            status = :status,
            unit_type_id = :unit_type_id,
            package_content_quantity = :package_content_quantity,
            package_content_unit_type_id = :package_content_unit_type_id
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $itemId,
        'name' => $version['name'],
        'specification' => normalize_item_specification_json(is_string($version['specification'])
            ? $version['specification']
            : json_encode($version['specification'], JSON_UNESCAPED_UNICODE)),
        'justification' => $version['justification'],
        'warranty' => $version['warranty'],
        'environmental_impacts' => normalize_environmental_impacts_json($version['environmental_impacts'] ?? ''),
        'level' => $version['level'],
        'status' => $version['status'] ?? 'draft',
        'unit_type_id' => $version['unit_type_id'] ?? null,
        'package_content_quantity' => normalize_decimal_db_value($version['package_content_quantity'] ?? null),
        'package_content_unit_type_id' => $version['package_content_unit_type_id'] ?? null,
    ]);

    return $itemId;
}

function get_dashboard_summary(): array
{
    $summary = [];

    $summary['total_items'] = (int) db()->query("
        SELECT COUNT(*) FROM procurement_items
    ")->fetchColumn();

    $summary['total_projects'] = (int) db()->query("
        SELECT COUNT(*) FROM procurement_projects
    ")->fetchColumn();

    $summary['total_demands'] = (int) db()->query("
        SELECT COUNT(*) FROM demand_lists
    ")->fetchColumn();

    $summary['total_kits'] = (int) db()->query("
        SELECT COUNT(*) FROM item_kits
    ")->fetchColumn();

    $summary['total_suppliers'] = (int) db()->query("
        SELECT COUNT(*) FROM suppliers
    ")->fetchColumn();

    $summary['active_suppliers'] = (int) db()->query("
        SELECT COUNT(*) FROM suppliers WHERE is_active = TRUE
    ")->fetchColumn();

    $summary['total_secretariats'] = (int) db()->query("
        SELECT COUNT(*) FROM secretariats
    ")->fetchColumn();

    $summary['total_requester_units'] = (int) db()->query("
        SELECT COUNT(*) FROM requester_units
    ")->fetchColumn();

    $summary['total_estimated_value'] = (float) db()->query("
        SELECT COALESCE(
            SUM(COALESCE(approved_quantity, quantity) * COALESCE(estimated_unit_price, 0)),
            0
        )
        FROM demand_items
    ")->fetchColumn();

    return $summary;
}

function get_items_by_status(): array
{
    return db()->query("
        SELECT status, COUNT(*) AS total
        FROM procurement_items
        GROUP BY status
        ORDER BY total DESC
    ")->fetchAll();
}

function get_items_by_category(): array
{
    return db()->query("
        SELECT
            COALESCE(c.name, 'Sem categoria') AS category_name,
            COUNT(*) AS total
        FROM procurement_items i
        LEFT JOIN categories c ON c.id = i.category_id
        GROUP BY c.name
        ORDER BY total DESC, category_name
    ")->fetchAll();
}

function get_project_financial_ranking(): array
{
    return db()->query("
        SELECT
            p.id,
            p.name,
            COALESCE(
                SUM(COALESCE(di.approved_quantity, di.quantity) * COALESCE(di.estimated_unit_price, 0)),
                0
            ) AS total_estimated_value
        FROM procurement_projects p
        LEFT JOIN demand_lists dl ON dl.project_id = p.id
        LEFT JOIN demand_items di ON di.demand_list_id = dl.id
        GROUP BY p.id, p.name
        ORDER BY total_estimated_value DESC, p.name
        LIMIT 10
    ")->fetchAll();
}

function get_projects_by_status(): array
{
    return db()->query("
        SELECT status, COUNT(*) AS total
        FROM procurement_projects
        GROUP BY status
        ORDER BY total DESC, status
    ")->fetchAll();
}

function get_recent_projects_for_dashboard(int $limit = 6): array
{
    $stmt = db()->prepare("
        SELECT
            p.id,
            p.name,
            p.status,
            p.created_at,
            COUNT(DISTINCT dl.id) AS demand_count,
            COALESCE(
                SUM(COALESCE(di.approved_quantity, di.quantity) * COALESCE(di.estimated_unit_price, 0)),
                0
            ) AS total_estimated_value
        FROM procurement_projects p
        LEFT JOIN demand_lists dl ON dl.project_id = p.id
        LEFT JOIN demand_items di ON di.demand_list_id = dl.id
        GROUP BY p.id, p.name, p.status, p.created_at
        ORDER BY p.created_at DESC, p.id DESC
        LIMIT :limit
    ");

    $stmt->bindValue('limit', max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function get_dashboard_annex_attention(int $limit = 8): array
{
    $summary = [
        'valid' => 0,
        'stale' => 0,
        'pending' => 0,
    ];
    $items = [];

    foreach (get_projects() as $project) {
        foreach (get_project_annex_statuses((int) $project['id']) as $status) {
            $statusKey = (string) ($status['status'] ?? 'pending');

            if (!isset($summary[$statusKey])) {
                $summary[$statusKey] = 0;
            }

            $summary[$statusKey]++;

            if (in_array($statusKey, ['stale', 'pending'], true)) {
                $items[] = [
                    'project_id' => (int) $project['id'],
                    'project_name' => (string) $project['name'],
                    'label' => (string) ($status['label'] ?? ''),
                    'status' => $statusKey,
                    'short_hash' => (string) ($status['short_hash'] ?? ''),
                    'version_number' => $status['version_number'] ?? null,
                ];
            }
        }
    }

    usort($items, static function (array $left, array $right): int {
        $weight = [
            'stale' => 0,
            'pending' => 1,
        ];

        return ($weight[$left['status']] ?? 2) <=> ($weight[$right['status']] ?? 2)
            ?: strcasecmp((string) $left['project_name'], (string) $right['project_name'])
            ?: strcasecmp((string) $left['label'], (string) $right['label']);
    });

    return [
        'summary' => $summary,
        'items' => array_slice($items, 0, max(1, $limit)),
        'total_attention' => count($items),
    ];
}

function project_bi_project_rows(array $filters = []): array
{
    $sql = "
        SELECT
            p.id,
            p.name,
            p.status,
            p.created_at,
            COUNT(DISTINCT dl.id) AS demand_count,
            COUNT(DISTINCT di.procurement_item_id) AS item_count,
            COUNT(DISTINCT q.supplier_id) AS supplier_count,
            COUNT(DISTINCT q.id) AS quote_count
        FROM procurement_projects p
        LEFT JOIN demand_lists dl ON dl.project_id = p.id
        LEFT JOIN demand_items di ON di.demand_list_id = dl.id
        LEFT JOIN demand_supplier_quotes q
            ON q.demand_list_id = dl.id
           AND COALESCE(q.status, 'received') <> 'discarded'
        WHERE 1 = 1
    ";
    $params = [];

    $query = trim((string) ($filters['q'] ?? ''));

    if ($query !== '') {
        $sql .= " AND LOWER(COALESCE(p.name, '') || ' ' || COALESCE(p.description, '')) LIKE :q";
        $params['q'] = '%' . mb_strtolower($query) . '%';
    }

    $status = trim((string) ($filters['status'] ?? ''));

    if ($status !== '') {
        $sql .= " AND p.status = :status";
        $params['status'] = $status;
    }

    $sql .= "
        GROUP BY p.id, p.name, p.status, p.created_at
        ORDER BY p.created_at DESC NULLS LAST, p.id DESC
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as $index => $row) {
        $summary = get_project_financial_summary((int) $row['id']);
        $rows[$index]['total_estimated_value'] = (float) ($summary['total_estimated_value'] ?? 0);
        $rows[$index]['uses_supplier_average'] = !empty($summary['uses_supplier_average']);
    }

    usort($rows, static function (array $left, array $right): int {
        return ((float) $right['total_estimated_value'] <=> (float) $left['total_estimated_value'])
            ?: strcasecmp((string) $left['name'], (string) $right['name']);
    });

    return $rows;
}

function project_bi_status_summary(array $projectRows): array
{
    $summary = [];

    foreach ($projectRows as $row) {
        $status = (string) ($row['status'] ?? 'draft');

        if (!isset($summary[$status])) {
            $summary[$status] = [
                'status' => $status,
                'label' => project_status_label($status),
                'total' => 0,
                'estimated_total' => 0.0,
            ];
        }

        $summary[$status]['total']++;
        $summary[$status]['estimated_total'] += (float) ($row['total_estimated_value'] ?? 0);
    }

    uasort($summary, static fn (array $left, array $right): int => ((int) $right['total'] <=> (int) $left['total']));

    return array_values($summary);
}

function project_bi_supplier_ranking(int $projectId = 0, int $limit = 10): array
{
    $sql = "
        SELECT
            s.id,
            s.name,
            s.document,
            COUNT(DISTINCT q.id) AS quote_count,
            COUNT(DISTINCT dl.project_id) AS project_count,
            COUNT(DISTINCT qi.demand_item_id) AS priced_item_count,
            AVG(qi.unit_price) AS average_unit_price,
            MIN(qi.unit_price) AS min_unit_price,
            MAX(qi.unit_price) AS max_unit_price
        FROM suppliers s
        INNER JOIN demand_supplier_quotes q
            ON q.supplier_id = s.id
           AND COALESCE(q.status, 'received') <> 'discarded'
        INNER JOIN demand_lists dl ON dl.id = q.demand_list_id
        LEFT JOIN demand_supplier_quote_items qi
            ON qi.demand_supplier_quote_id = q.id
           AND qi.unit_price IS NOT NULL
        WHERE 1 = 1
    ";
    $params = [];

    if ($projectId > 0) {
        $sql .= " AND dl.project_id = :project_id";
        $params['project_id'] = $projectId;
    }

    $sql .= "
        GROUP BY s.id, s.name, s.document
        ORDER BY quote_count DESC, priced_item_count DESC, s.name
        LIMIT :limit
    ";

    $stmt = db()->prepare($sql);
    $stmt->bindValue('limit', max(1, $limit), PDO::PARAM_INT);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();

    return $stmt->fetchAll();
}

function project_bi_item_supplier_prices(int $projectId, int $procurementItemId): array
{
    if ($projectId <= 0 || $procurementItemId <= 0) {
        return [];
    }

    $stmt = db()->prepare("
        SELECT
            s.id AS supplier_id,
            s.name AS supplier_name,
            s.document AS supplier_document,
            COUNT(DISTINCT q.id) AS quote_count,
            COUNT(qi.id) AS price_count,
            AVG(qi.unit_price) AS average_unit_price,
            MIN(qi.unit_price) AS min_unit_price,
            MAX(qi.unit_price) AS max_unit_price,
            STDDEV_POP(qi.unit_price) AS stddev_unit_price,
            MAX(q.quote_date) AS latest_quote_date
        FROM demand_supplier_quote_items qi
        INNER JOIN demand_supplier_quotes q
            ON q.id = qi.demand_supplier_quote_id
           AND COALESCE(q.status, 'received') <> 'discarded'
        INNER JOIN suppliers s ON s.id = q.supplier_id
        INNER JOIN demand_items di ON di.id = qi.demand_item_id
        INNER JOIN demand_lists dl ON dl.id = di.demand_list_id
        WHERE dl.project_id = :project_id
          AND di.procurement_item_id = :procurement_item_id
          AND qi.unit_price IS NOT NULL
        GROUP BY s.id, s.name, s.document
        ORDER BY average_unit_price, s.name
    ");

    $stmt->execute([
        'project_id' => $projectId,
        'procurement_item_id' => $procurementItemId,
    ]);

    return $stmt->fetchAll();
}

function project_bi_percentile(array $values, float $percentile): ?float
{
    $values = array_values(array_filter(array_map('floatval', $values), static fn (float $value): bool => $value >= 0));
    sort($values, SORT_NUMERIC);
    $count = count($values);

    if ($count === 0) {
        return null;
    }

    if ($count === 1) {
        return $values[0];
    }

    $position = ($count - 1) * $percentile;
    $lower = (int) floor($position);
    $upper = (int) ceil($position);

    if ($lower === $upper) {
        return $values[$lower];
    }

    return $values[$lower] + (($values[$upper] - $values[$lower]) * ($position - $lower));
}

function project_bi_price_statistics(array $values): array
{
    $values = array_values(array_filter(array_map('floatval', $values), static fn (float $value): bool => $value >= 0));
    sort($values, SORT_NUMERIC);
    $count = count($values);

    if ($count === 0) {
        return [
            'count' => 0,
            'min' => null,
            'max' => null,
            'average' => null,
            'median' => null,
            'mode' => null,
            'stddev' => null,
            'q1' => null,
            'q3' => null,
            'iqr' => null,
            'lower_bound' => null,
            'upper_bound' => null,
            'coefficient_variation' => null,
        ];
    }

    $average = array_sum($values) / $count;
    $variance = 0.0;

    foreach ($values as $value) {
        $variance += ($value - $average) ** 2;
    }

    $stddev = sqrt($variance / $count);
    $frequencies = [];

    foreach ($values as $value) {
        $key = number_format($value, 2, '.', '');
        $frequencies[$key] = ($frequencies[$key] ?? 0) + 1;
    }

    arsort($frequencies);
    $topFrequency = (int) reset($frequencies);
    $mode = $topFrequency > 1 ? (float) array_key_first($frequencies) : null;
    $q1 = project_bi_percentile($values, 0.25);
    $q3 = project_bi_percentile($values, 0.75);
    $iqr = $q1 !== null && $q3 !== null ? $q3 - $q1 : null;

    return [
        'count' => $count,
        'min' => $values[0],
        'max' => $values[$count - 1],
        'average' => $average,
        'median' => project_bi_percentile($values, 0.5),
        'mode' => $mode,
        'stddev' => $stddev,
        'q1' => $q1,
        'q3' => $q3,
        'iqr' => $iqr,
        'lower_bound' => $iqr !== null ? $q1 - (1.5 * $iqr) : null,
        'upper_bound' => $iqr !== null ? $q3 + (1.5 * $iqr) : null,
        'coefficient_variation' => $average > 0 ? $stddev / $average : null,
    ];
}

function project_bi_is_outlier(float $value, array $stats): bool
{
    if (($stats['count'] ?? 0) < 3) {
        return false;
    }

    if (($stats['iqr'] ?? null) !== null && (float) $stats['iqr'] > 0) {
        return $value < (float) $stats['lower_bound'] || $value > (float) $stats['upper_bound'];
    }

    if (($stats['stddev'] ?? null) !== null && (float) $stats['stddev'] > 0) {
        return abs($value - (float) $stats['average']) > (2 * (float) $stats['stddev']);
    }

    return false;
}
function catalog_json_scopes(): array
{
    return [
        'all' => 'Base completa',
        'items' => 'Itens',
        'projects' => 'Projetos e demandas',
        'requesters' => 'Secretarias e unidades demandantes',
        'suppliers' => 'Fornecedores',
        'categories' => 'Categorias',
        'unit_types' => 'Tipos de unidade',
        'kits' => 'Kits',
        'templates' => 'Biblioteca',
    ];
}

function catalog_json_table_definitions(): array
{
    return [
        'categories' => [
            'label' => 'Categorias',
            'columns' => ['id', 'parent_id', 'name', 'created_at', 'updated_at'],
            'json' => [],
        ],
        'unit_types' => [
            'label' => 'Tipos de unidade',
            'columns' => ['id', 'name', 'abbreviation', 'description', 'created_at', 'updated_at'],
            'json' => [],
        ],
        'procurement_items' => [
            'label' => 'Itens',
            'columns' => [
                'id',
                'tracking_code',
                'category_id',
                'subcategory_id',
                'unit_type_id',
                'package_content_quantity',
                'package_content_unit_type_id',
                'level',
                'status',
                'name',
                'specification',
                'justification',
                'warranty',
                'environmental_impacts',
                'image_path',
                'created_at',
                'updated_at',
            ],
            'json' => ['specification'],
        ],
        'procurement_item_images' => [
            'label' => 'Imagens dos itens',
            'columns' => ['id', 'procurement_item_id', 'image_path', 'is_primary', 'created_at'],
            'json' => [],
        ],
        'procurement_item_versions' => [
            'label' => 'Versoes dos itens',
            'columns' => [
                'id',
                'procurement_item_id',
                'version_number',
                'name',
                'specification',
                'justification',
                'warranty',
                'environmental_impacts',
                'level',
                'status',
                'unit_type_id',
                'package_content_quantity',
                'package_content_unit_type_id',
                'notes',
                'created_at',
            ],
            'json' => ['specification'],
        ],
        'procurement_projects' => [
            'label' => 'Projetos',
            'columns' => ['id', 'name', 'description', 'status', 'closure_hash', 'closed_at', 'cancellation_reason', 'canceled_at', 'reopen_reason', 'reopened_at', 'reopen_mode', 'reopen_correction_deadline', 'created_at', 'updated_at'],
            'json' => [],
        ],
        'secretariats' => [
            'label' => 'Secretarias',
            'columns' => ['id', 'name', 'is_active', 'created_at', 'updated_at'],
            'json' => [],
        ],
        'requester_units' => [
            'label' => 'Unidades demandantes',
            'columns' => [
                'id',
                'parent_id',
                'secretariat_id',
                'name',
                'default_responsible_name',
                'is_active',
                'created_at',
                'updated_at',
            ],
            'json' => [],
        ],
        'suppliers' => [
            'label' => 'Fornecedores',
            'columns' => [
                'id',
                'name',
                'trade_name',
                'document',
                'contact_name',
                'email',
                'phone',
                'address',
                'city',
                'state',
                'postal_code',
                'state_registration',
                'municipal_registration',
                'company_size',
                'share_capital',
                'special_status',
                'special_status_date',
                'main_cnae',
                'secondary_cnaes',
                'participates_bidding',
                'website_url',
                'bank_name',
                'bank_agency',
                'bank_account',
                'owner_cpf',
                'owner_name',
                'notes',
                'is_active',
                'created_at',
                'updated_at',
            ],
            'json' => ['main_cnae', 'secondary_cnaes'],
        ],
        'demand_lists' => [
            'label' => 'Demandas',
            'columns' => [
                'id',
                'project_id',
                'requester_unit_id',
                'secretariat_id',
                'name',
                'requester_department',
                'responsible_name',
                'notes',
                'created_at',
                'updated_at',
            ],
            'json' => [],
        ],
        'demand_items' => [
            'label' => 'Itens das demandas',
            'columns' => [
                'id',
                'demand_list_id',
                'procurement_item_id',
                'quantity',
                'approved_quantity',
                'estimated_unit_price',
                'notes',
                'created_at',
                'updated_at',
            ],
            'json' => [],
        ],
        'demand_supplier_quotes' => [
            'label' => 'Orcamentos de fornecedores',
            'columns' => [
                'id',
                'demand_list_id',
                'supplier_id',
                'quote_number',
                'quote_date',
                'validity_date',
                'quoted_by',
                'collected_by',
                'attachment_path',
                'notes',
                'status',
                'created_at',
                'updated_at',
            ],
            'json' => [],
        ],
        'demand_supplier_quote_items' => [
            'label' => 'Valores dos orcamentos',
            'columns' => [
                'id',
                'demand_supplier_quote_id',
                'demand_item_id',
                'unit_price',
                'notes',
                'reused_from_quote_item_id',
                'created_at',
                'updated_at',
            ],
            'json' => [],
        ],
        'demand_price_references' => [
            'label' => 'Referencias historicas de precos',
            'columns' => [
                'id',
                'demand_item_id',
                'source_quote_item_id',
                'notes',
                'created_at',
                'updated_at',
            ],
            'json' => [],
        ],
        'project_licitation_items' => [
            'label' => 'Numeracao de licitacao',
            'columns' => [
                'id',
                'project_id',
                'procurement_item_id',
                'licitation_number',
                'created_at',
                'updated_at',
            ],
            'json' => [],
        ],
        'project_annex_versions' => [
            'label' => 'Versoes dos anexos',
            'columns' => [
                'id',
                'project_id',
                'annex_type',
                'version_number',
                'content_hash',
                'status',
                'item_count',
                'total_value',
                'generated_at',
                'invalidated_at',
                'created_at',
                'updated_at',
            ],
            'json' => [],
        ],
        'project_status_events' => [
            'label' => 'Eventos de status dos projetos',
            'columns' => [
                'id',
                'project_id',
                'from_status',
                'to_status',
                'reason',
                'reopen_mode',
                'correction_deadline',
                'snapshot',
                'event_hash',
                'created_at',
            ],
            'json' => ['snapshot'],
        ],
        'project_lot_denominations' => [
            'label' => 'Denominacoes de lotes',
            'columns' => [
                'id',
                'project_id',
                'lot_number',
                'name',
                'justification',
                'created_at',
                'updated_at',
            ],
            'json' => [],
        ],
        'project_lot_assignments' => [
            'label' => 'Vinculos de lotes',
            'columns' => [
                'id',
                'project_lot_id',
                'assignment_type',
                'procurement_item_id',
                'category_id',
                'created_at',
            ],
            'json' => [],
        ],
        'justification_templates' => [
            'label' => 'Modelos de justificativa',
            'columns' => ['id', 'title', 'content', 'category_id', 'is_active', 'created_at', 'updated_at'],
            'json' => [],
        ],
        'environmental_impact_templates' => [
            'label' => 'Modelos de impacto ambiental',
            'columns' => ['id', 'code', 'title', 'content', 'category_id', 'is_active', 'created_at', 'updated_at'],
            'json' => [],
        ],
        'item_kits' => [
            'label' => 'Kits',
            'columns' => ['id', 'name', 'description', 'is_active', 'created_at', 'updated_at'],
            'json' => [],
        ],
        'item_kit_items' => [
            'label' => 'Itens dos kits',
            'columns' => ['id', 'kit_id', 'procurement_item_id', 'quantity', 'notes', 'created_at', 'updated_at'],
            'json' => [],
        ],
    ];
}

function catalog_json_scope_tables(string $scope): array
{
    $scopes = [
        'all' => [
            'categories',
            'unit_types',
            'procurement_items',
            'procurement_item_images',
            'procurement_item_versions',
            'procurement_projects',
            'secretariats',
            'requester_units',
            'suppliers',
            'demand_lists',
            'demand_items',
            'demand_supplier_quotes',
            'demand_supplier_quote_items',
            'demand_price_references',
            'project_licitation_items',
            'project_annex_versions',
            'project_status_events',
            'project_lot_denominations',
            'project_lot_assignments',
            'justification_templates',
            'environmental_impact_templates',
            'item_kits',
            'item_kit_items',
        ],
        'items' => [
            'categories',
            'unit_types',
            'procurement_items',
            'procurement_item_images',
            'procurement_item_versions',
        ],
        'projects' => [
            'procurement_projects',
            'secretariats',
            'requester_units',
            'suppliers',
            'demand_lists',
            'demand_items',
            'demand_supplier_quotes',
            'demand_supplier_quote_items',
            'demand_price_references',
            'project_licitation_items',
            'project_annex_versions',
            'project_status_events',
            'project_lot_denominations',
            'project_lot_assignments',
        ],
        'requesters' => [
            'secretariats',
            'requester_units',
        ],
        'suppliers' => ['suppliers'],
        'categories' => ['categories'],
        'unit_types' => ['unit_types'],
        'kits' => [
            'item_kits',
            'item_kit_items',
        ],
        'templates' => [
            'justification_templates',
            'environmental_impact_templates',
        ],
    ];

    if (!isset($scopes[$scope])) {
        throw new InvalidArgumentException('Tipo de exportacao/importacao invalido.');
    }

    return $scopes[$scope];
}

function export_catalog_data(string $scope): array
{
    $definitions = catalog_json_table_definitions();
    $tables = catalog_json_scope_tables($scope);
    $data = [];

    foreach ($tables as $table) {
        $columns = implode(', ', $definitions[$table]['columns']);
        $data[$table] = db()->query("SELECT {$columns} FROM {$table} ORDER BY id")->fetchAll();
    }

    return [
        'system' => APP_NAME,
        'scope' => $scope,
        'exported_at' => date(DATE_ATOM),
        'format_version' => 1,
        'data' => $data,
    ];
}

function catalog_json_import_template(string $scope): array
{
    $definitions = catalog_json_table_definitions();
    $tables = catalog_json_scope_tables($scope);
    $data = [];

    foreach ($tables as $table) {
        $data[$table] = [
            catalog_json_sample_row($table, $definitions[$table]['columns']),
        ];
    }

    return [
        'system' => APP_NAME,
        'scope' => $scope,
        'format_version' => 1,
        'data' => $data,
    ];
}

function catalog_json_sample_row(string $table, array $columns): array
{
    $row = [];

    foreach ($columns as $column) {
        $row[$column] = null;
    }

    unset($row['id'], $row['created_at'], $row['updated_at']);

    if ($table === 'procurement_items') {
        return array_merge($row, [
            'tracking_code' => null,
            'category_id' => null,
            'subcategory_id' => null,
            'unit_type_id' => null,
            'package_content_quantity' => 100,
            'package_content_unit_type_id' => 1,
            'level' => 'C',
            'status' => 'draft',
            'name' => 'Nome do item',
            'specification' => default_item_specification(),
            'justification' => 'Justificativa administrativa do item.',
            'warranty' => 'Garantia minima de 12 meses, conforme padrao de mercado.',
            'environmental_impacts' => [
                'Selecionar impactos ambientais aplicaveis.',
            ],
            'image_path' => null,
        ]);
    }

    if ($table === 'environmental_impact_templates') {
        return array_merge($row, [
            'code' => 'IA009',
            'title' => 'Titulo do impacto',
            'content' => 'Descricao reutilizavel do impacto ambiental.',
            'category_id' => null,
            'is_active' => true,
        ]);
    }

    if ($table === 'justification_templates') {
        return array_merge($row, [
            'title' => 'Titulo da justificativa',
            'content' => 'Texto reutilizavel de justificativa.',
            'category_id' => null,
            'is_active' => true,
        ]);
    }

    if ($table === 'categories') {
        return array_merge($row, [
            'parent_id' => null,
            'name' => 'Nome da categoria',
        ]);
    }

    if ($table === 'unit_types') {
        return array_merge($row, [
            'name' => 'Unidade',
            'abbreviation' => 'un',
            'description' => 'Descricao do tipo de unidade.',
        ]);
    }

    if ($table === 'procurement_projects') {
        return array_merge($row, [
            'name' => 'Nome do projeto',
            'description' => 'Descricao do projeto de contratacao.',
            'status' => 'draft',
        ]);
    }

    if ($table === 'secretariats') {
        return array_merge($row, [
            'name' => 'Nome da secretaria',
            'is_active' => true,
        ]);
    }

    if ($table === 'requester_units') {
        return array_merge($row, [
            'parent_id' => null,
            'secretariat_id' => 1,
            'name' => 'Nome da unidade, setor ou subunidade',
            'default_responsible_name' => 'Responsavel padrao',
            'is_active' => true,
        ]);
    }

    if ($table === 'suppliers') {
        return array_merge($row, [
            'name' => 'Nome do fornecedor',
            'document' => '00.000.000/0001-00',
            'contact_name' => 'Nome do contato',
            'email' => 'contato@fornecedor.com.br',
            'phone' => '(00) 0000-0000',
            'address' => 'RUA DO FORNECEDOR, 100, CENTRO',
            'city' => 'CIDADE',
            'state' => 'UF',
            'postal_code' => '00000-000',
            'state_registration' => 'ISENTO',
            'municipal_registration' => null,
            'company_size' => 'ME',
            'share_capital' => '10000.00',
            'special_status' => null,
            'special_status_date' => null,
            'main_cnae' => ['code' => '0000-0/00', 'name' => 'Atividade principal', 'description' => 'Descricao da atividade principal'],
            'secondary_cnaes' => [[
                'code' => '0000-0/01',
                'name' => 'Atividade secundaria',
                'description' => 'Descricao da atividade secundaria',
            ]],
            'participates_bidding' => true,
            'website_url' => 'https://fornecedor.com.br',
            'notes' => null,
            'is_active' => true,
        ]);
    }

    if ($table === 'demand_lists') {
        return array_merge($row, [
            'project_id' => 1,
            'requester_unit_id' => 1,
            'secretariat_id' => 1,
            'name' => 'Nome da demanda',
            'requester_department' => 'Unidade solicitante',
            'responsible_name' => 'Responsavel',
            'notes' => null,
        ]);
    }

    if ($table === 'demand_items') {
        return array_merge($row, [
            'demand_list_id' => 1,
            'procurement_item_id' => 1,
            'quantity' => 1,
            'approved_quantity' => 1,
            'estimated_unit_price' => 0,
            'notes' => null,
        ]);
    }

    if ($table === 'demand_supplier_quotes') {
        return array_merge($row, [
            'demand_list_id' => 1,
            'supplier_id' => 1,
            'quote_number' => 'ORC-001',
            'quote_date' => date('Y-m-d'),
            'validity_date' => null,
            'quoted_by' => 'Contato do fornecedor',
            'collected_by' => 'Servidor responsavel pela coleta',
            'attachment_path' => '/uploads/supplier_quotes/orcamento.pdf',
            'notes' => null,
            'status' => 'received',
        ]);
    }

    if ($table === 'demand_supplier_quote_items') {
        return array_merge($row, [
            'demand_supplier_quote_id' => 1,
            'demand_item_id' => 1,
            'unit_price' => 0,
            'notes' => null,
            'reused_from_quote_item_id' => null,
        ]);
    }

    if ($table === 'demand_price_references') {
        return array_merge($row, [
            'demand_item_id' => 1,
            'source_quote_item_id' => 1,
            'notes' => null,
        ]);
    }

    if ($table === 'project_licitation_items') {
        return array_merge($row, [
            'project_id' => 1,
            'procurement_item_id' => 1,
            'licitation_number' => 1,
        ]);
    }

    if ($table === 'project_annex_versions') {
        return array_merge($row, [
            'project_id' => 1,
            'annex_type' => 'annex_i',
            'version_number' => 1,
            'content_hash' => str_repeat('0', 64),
            'status' => 'valid',
            'item_count' => 1,
            'total_value' => null,
            'generated_at' => date('Y-m-d H:i:s'),
            'invalidated_at' => null,
        ]);
    }

    if ($table === 'project_lot_denominations') {
        return array_merge($row, [
            'project_id' => 1,
            'lot_number' => 1,
            'name' => 'Computadores e estacoes de trabalho',
            'justification' => 'Equipamentos computacionais destinados a postos de trabalho, normalmente comercializados pelo mesmo segmento de fornecedores especializados.',
        ]);
    }

    if ($table === 'project_lot_assignments') {
        return array_merge($row, [
            'project_lot_id' => 1,
            'assignment_type' => 'category',
            'procurement_item_id' => null,
            'category_id' => 1,
        ]);
    }

    if ($table === 'item_kits') {
        return array_merge($row, [
            'name' => 'Nome do kit',
            'description' => 'Descricao do kit.',
            'is_active' => true,
        ]);
    }

    if ($table === 'item_kit_items') {
        return array_merge($row, [
            'kit_id' => 1,
            'procurement_item_id' => 1,
            'quantity' => 1,
            'notes' => null,
        ]);
    }

    return $row;
}

function import_catalog_data(string $scope, array $payload): array
{
    $definitions = catalog_json_table_definitions();
    $tables = catalog_json_scope_tables($scope);
    $summary = [];

    db()->beginTransaction();

    try {
        foreach ($tables as $table) {
            $rows = extract_catalog_import_rows($scope, $table, $tables, $payload);

            if (!$rows) {
                $summary[$table] = 0;
                continue;
            }

            $summary[$table] = import_catalog_table_rows(
                $table,
                $definitions[$table]['columns'],
                $definitions[$table]['json'],
                $rows
            );

            refresh_catalog_table_sequence($table);
        }

        db()->commit();
    } catch (Throwable $exception) {
        db()->rollBack();
        throw $exception;
    }

    return $summary;
}

function extract_catalog_import_rows(string $scope, string $table, array $tables, array $payload): array
{
    if (isset($payload['data'][$table]) && is_array($payload['data'][$table])) {
        return $payload['data'][$table];
    }

    if (isset($payload['tables'][$table]) && is_array($payload['tables'][$table])) {
        return $payload['tables'][$table];
    }

    if (isset($payload[$table]) && is_array($payload[$table])) {
        return $payload[$table];
    }

    $singleScopeTable = [
        'items' => 'procurement_items',
        'projects' => 'procurement_projects',
        'requesters' => 'requester_units',
        'suppliers' => 'suppliers',
        'categories' => 'categories',
        'unit_types' => 'unit_types',
        'kits' => 'item_kits',
    ];

    if (
        isset($singleScopeTable[$scope]) &&
        $singleScopeTable[$scope] === $table &&
        array_is_list($payload)
    ) {
        return $payload;
    }

    if (count($tables) === 1 && array_is_list($payload)) {
        return $payload;
    }

    return [];
}

function import_catalog_table_rows(string $table, array $columns, array $jsonColumns, array $rows): int
{
    $imported = 0;

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $values = [];

        foreach ($columns as $column) {
            if (!array_key_exists($column, $row)) {
                continue;
            }

            if ($table === 'procurement_items' && $column === 'specification') {
                $rawSpecification = is_string($row[$column])
                    ? $row[$column]
                    : json_encode($row[$column], JSON_UNESCAPED_UNICODE);

                $values[$column] = normalize_item_specification_json((string) $rawSpecification);
                continue;
            }

            if ($table === 'procurement_items' && $column === 'environmental_impacts') {
                $values[$column] = normalize_environmental_impacts_json($row[$column]);
                continue;
            }

            if (
                in_array($table, ['procurement_items', 'procurement_item_versions'], true) &&
                $column === 'package_content_quantity'
            ) {
                $values[$column] = normalize_decimal_db_value($row[$column]);
                continue;
            }

            if ($table === 'suppliers' && $column === 'document') {
                $values[$column] = normalize_supplier_document((string) $row[$column]);
                continue;
            }

            if ($table === 'suppliers' && $column === 'share_capital') {
                $values[$column] = normalize_supplier_share_capital($row[$column]);
                continue;
            }

            if ($table === 'suppliers' && $column === 'special_status_date') {
                $values[$column] = normalize_optional_date($row[$column]);
                continue;
            }

            $values[$column] = normalize_catalog_import_value($row[$column], in_array($column, $jsonColumns, true));
        }

        if (!$values) {
            continue;
        }

        $insertColumns = array_keys($values);
        $placeholders = [];

        foreach ($insertColumns as $column) {
            $placeholders[] = in_array($column, $jsonColumns, true)
                ? ':' . $column . '::jsonb'
                : ':' . $column;
        }

        if (array_key_exists('id', $values) && $values['id']) {
            $updates = [];

            foreach ($insertColumns as $column) {
                if ($column === 'id') {
                    continue;
                }

                $updates[] = $column . ' = EXCLUDED.' . $column;
            }

            $sql = $updates
                ? sprintf(
                    'INSERT INTO %s (%s) VALUES (%s) ON CONFLICT (id) DO UPDATE SET %s',
                    $table,
                    implode(', ', $insertColumns),
                    implode(', ', $placeholders),
                    implode(', ', $updates)
                )
                : sprintf(
                    'INSERT INTO %s (%s) VALUES (%s) ON CONFLICT (id) DO NOTHING',
                    $table,
                    implode(', ', $insertColumns),
                    implode(', ', $placeholders)
                );
        } else {
            unset($values['id']);
            $insertColumns = array_keys($values);
            $placeholders = [];

            foreach ($insertColumns as $column) {
                $placeholders[] = in_array($column, $jsonColumns, true)
                    ? ':' . $column . '::jsonb'
                    : ':' . $column;
            }

            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $table,
                implode(', ', $insertColumns),
                implode(', ', $placeholders)
            );
        }

        $stmt = db()->prepare($sql);
        $stmt->execute($values);
        $imported++;
    }

    return $imported;
}

function normalize_catalog_import_value(mixed $value, bool $isJson): mixed
{
    if ($isJson) {
        if (is_string($value)) {
            return $value === '' ? '{}' : $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    return $value;
}

function refresh_catalog_table_sequence(string $table): void
{
    $sql = "
        SELECT setval(
            pg_get_serial_sequence('{$table}', 'id'),
            GREATEST(COALESCE(MAX(id), 0), 1),
            COALESCE(MAX(id), 0) > 0
        )
        FROM {$table}
    ";

    db()->query($sql);
}
