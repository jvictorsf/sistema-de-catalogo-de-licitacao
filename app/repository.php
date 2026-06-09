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
            ut.abbreviation AS unit_type_abbreviation
        FROM procurement_items i
        LEFT JOIN categories c ON c.id = i.category_id
        LEFT JOIN categories s ON s.id = i.subcategory_id
        LEFT JOIN unit_types ut ON ut.id = i.unit_type_id
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
                s.name ILIKE :q
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
            ut.abbreviation AS unit_type_abbreviation
        FROM procurement_items i
        LEFT JOIN categories c ON c.id = i.category_id
        LEFT JOIN categories s ON s.id = i.subcategory_id
        LEFT JOIN unit_types ut ON ut.id = i.unit_type_id
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

function find_project(int $id): ?array
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

function create_project(array $data): int
{
    $stmt = db()->prepare("
        INSERT INTO procurement_projects (
            name,
            description,
            status
        ) VALUES (
            :name,
            :description,
            :status
        )
        RETURNING id
    ");

    $stmt->execute([
        'name' => $data['name'],
        'description' => $data['description'] ?? null,
        'status' => $data['status'] ?? 'draft',
    ]);

    return (int) $stmt->fetchColumn();
}

function update_project(int $id, array $data): void
{
    $stmt = db()->prepare("
        UPDATE procurement_projects SET
            name = :name,
            description = :description,
            status = :status
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $id,
        'name' => $data['name'],
        'description' => $data['description'] ?? null,
        'status' => $data['status'] ?? 'draft',
    ]);
}

function delete_project(int $id): void
{
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
            s.name AS secretariat_name,
            s.is_active AS secretariat_is_active
        FROM requester_units ru
        LEFT JOIN secretariats s ON s.id = ru.secretariat_id
    ";

    if ($activeOnly) {
        $sql .= " WHERE ru.is_active = TRUE AND COALESCE(s.is_active, TRUE) = TRUE";
    }

    $sql .= " ORDER BY s.name NULLS LAST, ru.name";

    return db()->query($sql)->fetchAll();
}

function find_requester_unit(int $id): ?array
{
    $stmt = db()->prepare("
        SELECT
            ru.*,
            s.name AS secretariat_name
        FROM requester_units ru
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
            secretariat_id,
            name,
            default_responsible_name,
            is_active
        ) VALUES (
            :secretariat_id,
            :name,
            :default_responsible_name,
            :is_active
        )
        RETURNING id
    ");

    $stmt->execute([
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
            secretariat_id = :secretariat_id,
            name = :name,
            default_responsible_name = :default_responsible_name,
            is_active = :is_active
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $id,
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

    return db()->query($sql)->fetchAll();
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

    return $supplier ?: null;
}

function create_supplier(array $data): int
{
    $stmt = db()->prepare("
        INSERT INTO suppliers (
            name,
            document,
            contact_name,
            email,
            phone,
            address,
            notes,
            is_active
        ) VALUES (
            :name,
            :document,
            :contact_name,
            :email,
            :phone,
            :address,
            :notes,
            :is_active
        )
        RETURNING id
    ");

    $stmt->execute([
        'name' => $data['name'],
        'document' => normalize_supplier_document($data['document'] ?? null),
        'contact_name' => $data['contact_name'] ?: null,
        'email' => $data['email'] ?: null,
        'phone' => $data['phone'] ?: null,
        'address' => $data['address'] ?: null,
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
            document = :document,
            contact_name = :contact_name,
            email = :email,
            phone = :phone,
            address = :address,
            notes = :notes,
            is_active = :is_active
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $id,
        'name' => $data['name'],
        'document' => normalize_supplier_document($data['document'] ?? null),
        'contact_name' => $data['contact_name'] ?: null,
        'email' => $data['email'] ?: null,
        'phone' => $data['phone'] ?: null,
        'address' => $data['address'] ?: null,
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
    $data['requester_department'] = $unit['name'];

    if (empty($data['responsible_name']) && !empty($unit['default_responsible_name'])) {
        $data['responsible_name'] = $unit['default_responsible_name'];
    }

    return $data;
}

function get_project_demands(int $projectId): array
{
    $stmt = db()->prepare("
        SELECT
            dl.*,
            ru.name AS requester_unit_name,
            ru.default_responsible_name,
            s.name AS secretariat_name
        FROM demand_lists dl
        LEFT JOIN requester_units ru ON ru.id = dl.requester_unit_id
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
            ru.name AS requester_unit_name,
            ru.default_responsible_name,
            s.name AS secretariat_name
        FROM demand_lists dl
        LEFT JOIN requester_units ru ON ru.id = dl.requester_unit_id
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
    $stmt = db()->prepare("
        DELETE FROM demand_lists
        WHERE id = :id
    ");

    $stmt->execute(['id' => $id]);
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
            ut.abbreviation AS unit_type_abbreviation
        FROM demand_items di
        INNER JOIN procurement_items pi ON pi.id = di.procurement_item_id
        LEFT JOIN unit_types ut ON ut.id = pi.unit_type_id
        WHERE di.demand_list_id = :demand_list_id
        ORDER BY pi.name
    ");

    $stmt->execute(['demand_list_id' => $demandListId]);

    return $stmt->fetchAll();
}

function add_demand_item(array $data): void
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
}

function delete_demand_item(int $id): void
{
    $stmt = db()->prepare("
        DELETE FROM demand_items
        WHERE id = :id
    ");

    $stmt->execute(['id' => $id]);
}

function update_demand_item(int $id, array $data): void
{
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

function get_demand_supplier_quotes(int $demandListId): array
{
    $stmt = db()->prepare("
        SELECT
            q.*,
            s.name AS supplier_name,
            s.document AS supplier_document,
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
        GROUP BY q.id, s.name, s.document
        ORDER BY s.name
    ");

    $stmt->execute(['demand_list_id' => $demandListId]);

    return $stmt->fetchAll();
}

function find_demand_supplier_quote(int $id): ?array
{
    $stmt = db()->prepare("
        SELECT
            q.*,
            s.name AS supplier_name,
            s.document AS supplier_document
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

function create_demand_supplier_quote(array $data): int
{
    $stmt = db()->prepare("
        INSERT INTO demand_supplier_quotes (
            demand_list_id,
            supplier_id,
            quote_number,
            quote_date,
            validity_date,
            attachment_path,
            notes,
            status
        ) VALUES (
            :demand_list_id,
            :supplier_id,
            :quote_number,
            :quote_date,
            :validity_date,
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
        'attachment_path' => $data['attachment_path'] ?: null,
        'notes' => $data['notes'] ?: null,
        'status' => $data['status'] ?? 'received',
    ]);

    return (int) $stmt->fetchColumn();
}

function update_demand_supplier_quote(int $id, array $data): void
{
    $stmt = db()->prepare("
        UPDATE demand_supplier_quotes SET
            supplier_id = :supplier_id,
            quote_number = :quote_number,
            quote_date = :quote_date,
            validity_date = :validity_date,
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
        'attachment_path' => $data['attachment_path'] ?: null,
        'notes' => $data['notes'] ?: null,
        'status' => $data['status'] ?? 'received',
    ]);
}

function delete_demand_supplier_quote(int $id): void
{
    $stmt = db()->prepare("
        DELETE FROM demand_supplier_quotes
        WHERE id = :id
    ");

    $stmt->execute(['id' => $id]);
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
}

function get_selected_demand_price_references(int $demandListId): array
{
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
}

function get_demand_price_bank_candidates(int $demandListId, int $months = 0): array
{
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
}

function save_demand_price_references(int $demandListId, array $selectedReferences): void
{
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

        db()->commit();
    } catch (Throwable $exception) {
        db()->rollBack();
        throw $exception;
    }
}

function save_demand_supplier_quote_items(int $quoteId, array $prices, array $notes = [], array $sourceQuoteItemIds = []): void
{
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

        db()->commit();
    } catch (Throwable $exception) {
        db()->rollBack();
        throw $exception;
    }
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

function get_project_consolidated_items(int $projectId): array
{
    $trackingCodeSql = item_tracking_code_sql('pi');

    $stmt = db()->prepare("
        SELECT
            pi.id AS procurement_item_id,
            {$trackingCodeSql} AS tracking_code,
            pi.name AS item_name,
            pi.justification,
            pi.environmental_impacts,
            ut.name AS unit_type_name,
            ut.abbreviation AS unit_type_abbreviation,
            SUM(di.quantity) AS total_quantity,
            SUM(COALESCE(di.approved_quantity, di.quantity)) AS total_approved_quantity,
            AVG(COALESCE(di.estimated_unit_price, 0)) AS average_unit_price,
            SUM(COALESCE(di.approved_quantity, di.quantity) * COALESCE(di.estimated_unit_price, 0)) AS estimated_total,
            COUNT(DISTINCT dl.id) AS demand_count
        FROM demand_items di
        INNER JOIN demand_lists dl ON dl.id = di.demand_list_id
        INNER JOIN procurement_items pi ON pi.id = di.procurement_item_id
        LEFT JOIN unit_types ut ON ut.id = pi.unit_type_id
        WHERE dl.project_id = :project_id
        GROUP BY
            pi.id,
            {$trackingCodeSql},
            pi.name,
            pi.justification,
            pi.environmental_impacts,
            ut.name,
            ut.abbreviation
        ORDER BY pi.name
    ");

    $stmt->execute(['project_id' => $projectId]);

    return $stmt->fetchAll();
}

function get_project_items_by_demand(int $projectId): array
{
    $trackingCodeSql = item_tracking_code_sql('pi');

    $stmt = db()->prepare("
        SELECT
            dl.id AS demand_id,
            dl.name AS demand_name,
            s.name AS secretariat_name,
            dl.requester_department,
            dl.responsible_name,
            {$trackingCodeSql} AS tracking_code,
            pi.name AS item_name,
            ut.name AS unit_type_name,
            ut.abbreviation AS unit_type_abbreviation,
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
        WHERE dl.project_id = :project_id
        ORDER BY s.name NULLS LAST, dl.name, pi.name
    ");

    $stmt->execute(['project_id' => $projectId]);

    return $stmt->fetchAll();
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

    return $stmt->fetch() ?: [
        'total_requested_quantity' => 0,
        'total_approved_quantity' => 0,
        'total_estimated_value' => 0,
    ];
}

function get_project_secretariat_summary(int $projectId): array
{
    $stmt = db()->prepare("
        SELECT
            COALESCE(s.name, 'Sem secretaria vinculada') AS secretariat_name,
            COUNT(DISTINCT dl.id) AS demand_count,
            SUM(di.quantity) AS total_requested_quantity,
            SUM(COALESCE(di.approved_quantity, di.quantity)) AS total_approved_quantity,
            SUM(COALESCE(di.approved_quantity, di.quantity) * COALESCE(di.estimated_unit_price, 0)) AS total_estimated_value
        FROM demand_lists dl
        LEFT JOIN secretariats s ON s.id = dl.secretariat_id
        LEFT JOIN demand_items di ON di.demand_list_id = dl.id
        WHERE dl.project_id = :project_id
        GROUP BY COALESCE(s.name, 'Sem secretaria vinculada')
        ORDER BY secretariat_name
    ");

    $stmt->execute([
        'project_id' => $projectId,
    ]);

    return $stmt->fetchAll();
}

function duplicate_project(int $projectId): int
{
    $project = find_project($projectId);

    if (!$project) {
        throw new RuntimeException('Projeto não encontrado.');
    }

    db()->beginTransaction();

    try {
        $newProjectId = create_project([
            'name' => $project['name'] . ' - Cópia',
            'description' => $project['description'],
            'status' => 'draft',
        ]);

        $demands = get_project_demands($projectId);

        foreach ($demands as $demand) {
            $newDemandId = create_demand_list([
                'project_id' => $newProjectId,
                'name' => $demand['name'],
                'requester_unit_id' => $demand['requester_unit_id'] ?? null,
                'secretariat_id' => $demand['secretariat_id'] ?? null,
                'requester_department' => $demand['requester_department'],
                'responsible_name' => $demand['responsible_name'],
                'notes' => $demand['notes'],
            ]);

            $items = get_demand_items((int) $demand['id']);

            foreach ($items as $item) {
                add_demand_item([
                    'demand_list_id' => $newDemandId,
                    'procurement_item_id' => $item['procurement_item_id'],
                    'quantity' => $item['quantity'],
                    'approved_quantity' => $item['approved_quantity'] ?? $item['quantity'],
                    'estimated_unit_price' => $item['estimated_unit_price'] ?? null,
                    'notes' => $item['notes'] ?? null,
                ]);
            }
        }

        db()->commit();

        return $newProjectId;
    } catch (Throwable $exception) {
        db()->rollBack();
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

    $budget = get_demand_budget_report($demandListId);

    if (($budget['priced_rows'] ?? 0) > 0) {
        $summary['total_estimated_value'] = $budget['total_average'];
        $summary['uses_supplier_average'] = true;
    } else {
        $summary['uses_supplier_average'] = false;
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
            ut.abbreviation AS unit_type_abbreviation
        FROM procurement_item_versions v
        LEFT JOIN unit_types ut ON ut.id = v.unit_type_id
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
        SELECT *
        FROM procurement_item_versions
        WHERE id = :id
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
            unit_type_id = :unit_type_id
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
                'notes',
                'created_at',
            ],
            'json' => ['specification'],
        ],
        'procurement_projects' => [
            'label' => 'Projetos',
            'columns' => ['id', 'name', 'description', 'status', 'created_at', 'updated_at'],
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
                'document',
                'contact_name',
                'email',
                'phone',
                'address',
                'notes',
                'is_active',
                'created_at',
                'updated_at',
            ],
            'json' => [],
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
            'secretariat_id' => 1,
            'name' => 'Nome da unidade ou setor',
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
            'address' => 'Endereco do fornecedor.',
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

            if ($table === 'suppliers' && $column === 'document') {
                $values[$column] = normalize_supplier_document((string) $row[$column]);
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
