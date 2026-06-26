SET client_encoding = 'UTF8';

CREATE EXTENSION IF NOT EXISTS pg_trgm;

CREATE TABLE IF NOT EXISTS categories (
    id SERIAL PRIMARY KEY,
    parent_id INTEGER NULL REFERENCES categories(id) ON DELETE SET NULL,
    name VARCHAR(150) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_categories_parent_name
ON categories (COALESCE(parent_id, 0), lower(name));

CREATE TABLE IF NOT EXISTS unit_types (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    abbreviation VARCHAR(20),
    description TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS procurement_items (
    id SERIAL PRIMARY KEY,
    tracking_code VARCHAR(20) UNIQUE,
    category_id INTEGER NULL REFERENCES categories(id) ON DELETE SET NULL,
    subcategory_id INTEGER NULL REFERENCES categories(id) ON DELETE SET NULL,
    unit_type_id INTEGER NULL REFERENCES unit_types(id) ON DELETE SET NULL,
    package_content_quantity NUMERIC(12,2),
    package_content_unit_type_id INTEGER NULL REFERENCES unit_types(id) ON DELETE SET NULL,
    level CHAR(1) NOT NULL DEFAULT 'C' CHECK (level IN ('A', 'B', 'C')),
    status VARCHAR(50) NOT NULL DEFAULT 'draft',
    name VARCHAR(255) NOT NULL,
    specification JSONB NOT NULL DEFAULT '{}'::jsonb,
    justification TEXT NOT NULL DEFAULT '',
    warranty TEXT,
    environmental_impacts TEXT,
    image_path VARCHAR(255),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE procurement_items
ADD COLUMN IF NOT EXISTS tracking_code VARCHAR(20);

ALTER TABLE procurement_items
ADD COLUMN IF NOT EXISTS status VARCHAR(50) NOT NULL DEFAULT 'draft';

ALTER TABLE procurement_items
ADD COLUMN IF NOT EXISTS unit_type_id INTEGER NULL;

ALTER TABLE procurement_items
ADD COLUMN IF NOT EXISTS package_content_quantity NUMERIC(12,2);

ALTER TABLE procurement_items
ADD COLUMN IF NOT EXISTS package_content_unit_type_id INTEGER NULL;

ALTER TABLE procurement_items
ADD COLUMN IF NOT EXISTS image_path VARCHAR(255);

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_procurement_items_category'
    ) THEN
        ALTER TABLE procurement_items
        ADD CONSTRAINT fk_procurement_items_category
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_procurement_items_subcategory'
    ) THEN
        ALTER TABLE procurement_items
        ADD CONSTRAINT fk_procurement_items_subcategory
        FOREIGN KEY (subcategory_id) REFERENCES categories(id) ON DELETE SET NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_procurement_items_unit_type'
    ) THEN
        ALTER TABLE procurement_items
        ADD CONSTRAINT fk_procurement_items_unit_type
        FOREIGN KEY (unit_type_id) REFERENCES unit_types(id) ON DELETE SET NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_procurement_items_package_content_unit_type'
    ) THEN
        ALTER TABLE procurement_items
        ADD CONSTRAINT fk_procurement_items_package_content_unit_type
        FOREIGN KEY (package_content_unit_type_id) REFERENCES unit_types(id) ON DELETE SET NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'ck_procurement_items_level'
    ) THEN
        ALTER TABLE procurement_items
        ADD CONSTRAINT ck_procurement_items_level
        CHECK (level IN ('A', 'B', 'C'));
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'ck_procurement_items_package_content'
    ) THEN
        ALTER TABLE procurement_items
        ADD CONSTRAINT ck_procurement_items_package_content
        CHECK (
            (package_content_quantity IS NULL AND package_content_unit_type_id IS NULL)
            OR (package_content_quantity > 0 AND package_content_unit_type_id IS NOT NULL)
        );
    END IF;
END
$$;

CREATE TABLE IF NOT EXISTS procurement_item_images (
    id SERIAL PRIMARY KEY,
    procurement_item_id INTEGER NOT NULL REFERENCES procurement_items(id) ON DELETE CASCADE,
    image_path VARCHAR(255) NOT NULL,
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_procurement_item_images_primary
ON procurement_item_images (procurement_item_id)
WHERE is_primary = TRUE;

CREATE TABLE IF NOT EXISTS procurement_item_versions (
    id SERIAL PRIMARY KEY,
    procurement_item_id INTEGER NOT NULL REFERENCES procurement_items(id) ON DELETE CASCADE,
    version_number INTEGER NOT NULL,
    name VARCHAR(255) NOT NULL,
    specification JSONB NOT NULL DEFAULT '{}'::jsonb,
    justification TEXT,
    warranty TEXT,
    environmental_impacts TEXT,
    level CHAR(1),
    status VARCHAR(50),
    unit_type_id INTEGER NULL REFERENCES unit_types(id) ON DELETE SET NULL,
    package_content_quantity NUMERIC(12,2),
    package_content_unit_type_id INTEGER NULL REFERENCES unit_types(id) ON DELETE SET NULL,
    notes TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (procurement_item_id, version_number)
);

ALTER TABLE procurement_item_versions
ADD COLUMN IF NOT EXISTS package_content_quantity NUMERIC(12,2);

ALTER TABLE procurement_item_versions
ADD COLUMN IF NOT EXISTS package_content_unit_type_id INTEGER NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_procurement_item_versions_package_content_unit_type'
    ) THEN
        ALTER TABLE procurement_item_versions
        ADD CONSTRAINT fk_procurement_item_versions_package_content_unit_type
        FOREIGN KEY (package_content_unit_type_id) REFERENCES unit_types(id) ON DELETE SET NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'ck_procurement_item_versions_package_content'
    ) THEN
        ALTER TABLE procurement_item_versions
        ADD CONSTRAINT ck_procurement_item_versions_package_content
        CHECK (
            (package_content_quantity IS NULL AND package_content_unit_type_id IS NULL)
            OR (package_content_quantity > 0 AND package_content_unit_type_id IS NOT NULL)
        );
    END IF;
END
$$;

CREATE TABLE IF NOT EXISTS procurement_projects (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    status VARCHAR(50) NOT NULL DEFAULT 'draft',
    closure_hash CHAR(64),
    closed_at TIMESTAMP NULL,
    cancellation_reason TEXT,
    canceled_at TIMESTAMP NULL,
    reopen_reason TEXT,
    reopened_at TIMESTAMP NULL,
    reopen_mode VARCHAR(30),
    reopen_correction_deadline DATE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS project_status_events (
    id SERIAL PRIMARY KEY,
    project_id INTEGER NOT NULL REFERENCES procurement_projects(id) ON DELETE CASCADE,
    from_status VARCHAR(50),
    to_status VARCHAR(50) NOT NULL,
    reason TEXT NOT NULL,
    reopen_mode VARCHAR(30),
    correction_deadline DATE,
    snapshot JSONB NOT NULL DEFAULT '{}'::jsonb,
    event_hash CHAR(64) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS secretariats (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS requester_units (
    id SERIAL PRIMARY KEY,
    parent_id INTEGER NULL REFERENCES requester_units(id) ON DELETE SET NULL,
    secretariat_id INTEGER NULL REFERENCES secretariats(id) ON DELETE SET NULL,
    name VARCHAR(255) NOT NULL,
    default_responsible_name VARCHAR(255),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (secretariat_id, name)
);

CREATE TABLE IF NOT EXISTS suppliers (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    trade_name VARCHAR(255),
    document VARCHAR(30),
    contact_name VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(50),
    address TEXT,
    city VARCHAR(120),
    state VARCHAR(2),
    postal_code VARCHAR(20),
    state_registration VARCHAR(60),
    municipal_registration VARCHAR(60),
    company_size VARCHAR(120),
    main_cnae JSONB,
    secondary_cnaes JSONB NOT NULL DEFAULT '[]'::jsonb,
    participates_bidding BOOLEAN NOT NULL DEFAULT TRUE,
    website_url VARCHAR(255),
    bank_name VARCHAR(120),
    bank_agency VARCHAR(50),
    bank_account VARCHAR(80),
    owner_cpf VARCHAR(30),
    owner_name VARCHAR(255),
    notes TEXT,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS demand_lists (
    id SERIAL PRIMARY KEY,
    project_id INTEGER NOT NULL REFERENCES procurement_projects(id) ON DELETE CASCADE,
    requester_unit_id INTEGER NULL REFERENCES requester_units(id) ON DELETE SET NULL,
    secretariat_id INTEGER NULL REFERENCES secretariats(id) ON DELETE SET NULL,
    name VARCHAR(255) NOT NULL,
    requester_department VARCHAR(255),
    responsible_name VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS demand_items (
    id SERIAL PRIMARY KEY,
    demand_list_id INTEGER NOT NULL REFERENCES demand_lists(id) ON DELETE CASCADE,
    procurement_item_id INTEGER NOT NULL REFERENCES procurement_items(id),
    quantity NUMERIC(12,2) NOT NULL DEFAULT 1,
    approved_quantity NUMERIC(12,2),
    estimated_unit_price NUMERIC(12,2),
    notes TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (demand_list_id, procurement_item_id)
);

CREATE TABLE IF NOT EXISTS demand_supplier_quotes (
    id SERIAL PRIMARY KEY,
    demand_list_id INTEGER NOT NULL REFERENCES demand_lists(id) ON DELETE CASCADE,
    supplier_id INTEGER NOT NULL REFERENCES suppliers(id),
    quote_number VARCHAR(100),
    quote_date DATE,
    validity_date DATE,
    quoted_by VARCHAR(255),
    collected_by VARCHAR(255),
    attachment_path VARCHAR(255),
    notes TEXT,
    status VARCHAR(50) NOT NULL DEFAULT 'received',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS demand_supplier_quote_items (
    id SERIAL PRIMARY KEY,
    demand_supplier_quote_id INTEGER NOT NULL REFERENCES demand_supplier_quotes(id) ON DELETE CASCADE,
    demand_item_id INTEGER NOT NULL REFERENCES demand_items(id) ON DELETE CASCADE,
    unit_price NUMERIC(12,2),
    notes TEXT,
    reused_from_quote_item_id INTEGER NULL REFERENCES demand_supplier_quote_items(id) ON DELETE SET NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (demand_supplier_quote_id, demand_item_id)
);

CREATE TABLE IF NOT EXISTS demand_price_references (
    id SERIAL PRIMARY KEY,
    demand_item_id INTEGER NOT NULL REFERENCES demand_items(id) ON DELETE CASCADE,
    source_quote_item_id INTEGER NOT NULL REFERENCES demand_supplier_quote_items(id) ON DELETE CASCADE,
    notes TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (demand_item_id, source_quote_item_id)
);

CREATE TABLE IF NOT EXISTS project_licitation_items (
    id SERIAL PRIMARY KEY,
    project_id INTEGER NOT NULL REFERENCES procurement_projects(id) ON DELETE CASCADE,
    procurement_item_id INTEGER NOT NULL REFERENCES procurement_items(id) ON DELETE CASCADE,
    licitation_number INTEGER NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (project_id, procurement_item_id),
    UNIQUE (project_id, licitation_number),
    CHECK (licitation_number > 0)
);

CREATE TABLE IF NOT EXISTS project_annex_versions (
    id SERIAL PRIMARY KEY,
    project_id INTEGER NOT NULL REFERENCES procurement_projects(id) ON DELETE CASCADE,
    annex_type VARCHAR(20) NOT NULL,
    version_number INTEGER NOT NULL,
    content_hash CHAR(64) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'valid',
    item_count INTEGER NOT NULL DEFAULT 0,
    total_value NUMERIC(14,2),
    generated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    invalidated_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (project_id, annex_type, version_number),
    CHECK (annex_type IN ('annex_i', 'annex_ii', 'annex_iii', 'lot_annex_i', 'lot_annex_ii', 'lot_annex_iii', 'lot_annex_iv')),
    CHECK (status IN ('valid', 'invalid'))
);

ALTER TABLE project_annex_versions
DROP CONSTRAINT IF EXISTS project_annex_versions_annex_type_check;

ALTER TABLE project_annex_versions
DROP CONSTRAINT IF EXISTS ck_project_annex_versions_annex_type;

ALTER TABLE project_annex_versions
ADD CONSTRAINT ck_project_annex_versions_annex_type
CHECK (annex_type IN ('annex_i', 'annex_ii', 'annex_iii', 'lot_annex_i', 'lot_annex_ii', 'lot_annex_iii', 'lot_annex_iv'));

CREATE TABLE IF NOT EXISTS project_lot_denominations (
    id SERIAL PRIMARY KEY,
    project_id INTEGER NOT NULL REFERENCES procurement_projects(id) ON DELETE CASCADE,
    lot_number INTEGER NOT NULL,
    name VARCHAR(255) NOT NULL,
    justification TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (project_id, lot_number),
    UNIQUE (project_id, name),
    CHECK (lot_number > 0)
);

CREATE TABLE IF NOT EXISTS project_lot_assignments (
    id SERIAL PRIMARY KEY,
    project_lot_id INTEGER NOT NULL REFERENCES project_lot_denominations(id) ON DELETE CASCADE,
    assignment_type VARCHAR(20) NOT NULL,
    procurement_item_id INTEGER NULL REFERENCES procurement_items(id) ON DELETE CASCADE,
    category_id INTEGER NULL REFERENCES categories(id) ON DELETE CASCADE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (assignment_type IN ('item', 'category')),
    CHECK (
        (assignment_type = 'item' AND procurement_item_id IS NOT NULL AND category_id IS NULL)
        OR (assignment_type = 'category' AND category_id IS NOT NULL AND procurement_item_id IS NULL)
    )
);

INSERT INTO project_licitation_items (project_id, procurement_item_id, licitation_number)
SELECT
    seed.project_id,
    seed.procurement_item_id,
    ROW_NUMBER() OVER (
        PARTITION BY seed.project_id
        ORDER BY seed.category_name NULLS LAST, seed.item_name, seed.procurement_item_id
    ) AS licitation_number
FROM (
    SELECT DISTINCT
        dl.project_id,
        pi.id AS procurement_item_id,
        c.name AS category_name,
        pi.name AS item_name
    FROM demand_items di
    INNER JOIN demand_lists dl ON dl.id = di.demand_list_id
    INNER JOIN procurement_items pi ON pi.id = di.procurement_item_id
    LEFT JOIN categories c ON c.id = pi.category_id
) AS seed
ON CONFLICT DO NOTHING;

ALTER TABLE demand_items
ADD COLUMN IF NOT EXISTS approved_quantity NUMERIC(12,2);

ALTER TABLE demand_items
ADD COLUMN IF NOT EXISTS estimated_unit_price NUMERIC(12,2);

UPDATE demand_items
SET approved_quantity = quantity
WHERE approved_quantity IS NULL;

CREATE TABLE IF NOT EXISTS justification_templates (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL UNIQUE,
    content TEXT NOT NULL,
    category_id INTEGER NULL REFERENCES categories(id) ON DELETE SET NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS environmental_impact_templates (
    id SERIAL PRIMARY KEY,
    code VARCHAR(20),
    title VARCHAR(255) NOT NULL UNIQUE,
    content TEXT NOT NULL,
    category_id INTEGER NULL REFERENCES categories(id) ON DELETE SET NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS item_kits (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS item_kit_items (
    id SERIAL PRIMARY KEY,
    kit_id INTEGER NOT NULL REFERENCES item_kits(id) ON DELETE CASCADE,
    procurement_item_id INTEGER NOT NULL REFERENCES procurement_items(id) ON DELETE CASCADE,
    quantity NUMERIC(12,2) NOT NULL DEFAULT 1,
    notes TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (kit_id, procurement_item_id)
);

ALTER TABLE categories
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE unit_types
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE procurement_projects
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE procurement_projects
ADD COLUMN IF NOT EXISTS closure_hash CHAR(64);

ALTER TABLE procurement_projects
ADD COLUMN IF NOT EXISTS closed_at TIMESTAMP NULL;

ALTER TABLE procurement_projects
ADD COLUMN IF NOT EXISTS cancellation_reason TEXT;

ALTER TABLE procurement_projects
ADD COLUMN IF NOT EXISTS canceled_at TIMESTAMP NULL;

ALTER TABLE procurement_projects
ADD COLUMN IF NOT EXISTS reopen_reason TEXT;

ALTER TABLE procurement_projects
ADD COLUMN IF NOT EXISTS reopened_at TIMESTAMP NULL;

ALTER TABLE procurement_projects
ADD COLUMN IF NOT EXISTS reopen_mode VARCHAR(30);

ALTER TABLE procurement_projects
ADD COLUMN IF NOT EXISTS reopen_correction_deadline DATE;

ALTER TABLE secretariats
ADD COLUMN IF NOT EXISTS is_active BOOLEAN NOT NULL DEFAULT TRUE;

ALTER TABLE secretariats
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE requester_units
ADD COLUMN IF NOT EXISTS parent_id INTEGER NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_requester_units_parent'
    ) THEN
        ALTER TABLE requester_units
        ADD CONSTRAINT fk_requester_units_parent
        FOREIGN KEY (parent_id) REFERENCES requester_units(id) ON DELETE SET NULL;
    END IF;
END
$$;

DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'requester_units_secretariat_id_name_key'
    ) THEN
        ALTER TABLE requester_units
        DROP CONSTRAINT requester_units_secretariat_id_name_key;
    END IF;
END
$$;

ALTER TABLE requester_units
ADD COLUMN IF NOT EXISTS default_responsible_name VARCHAR(255);

ALTER TABLE requester_units
ADD COLUMN IF NOT EXISTS is_active BOOLEAN NOT NULL DEFAULT TRUE;

ALTER TABLE requester_units
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE suppliers
ADD COLUMN IF NOT EXISTS document VARCHAR(30);

ALTER TABLE suppliers
ADD COLUMN IF NOT EXISTS trade_name VARCHAR(255);

ALTER TABLE suppliers
ADD COLUMN IF NOT EXISTS contact_name VARCHAR(255);

ALTER TABLE suppliers
ADD COLUMN IF NOT EXISTS email VARCHAR(255);

ALTER TABLE suppliers
ADD COLUMN IF NOT EXISTS phone VARCHAR(50);

ALTER TABLE suppliers
ADD COLUMN IF NOT EXISTS address TEXT;

ALTER TABLE suppliers
ADD COLUMN IF NOT EXISTS city VARCHAR(120);

ALTER TABLE suppliers
ADD COLUMN IF NOT EXISTS state VARCHAR(2);

ALTER TABLE suppliers
ADD COLUMN IF NOT EXISTS postal_code VARCHAR(20);

ALTER TABLE suppliers
ADD COLUMN IF NOT EXISTS bank_name VARCHAR(120);

ALTER TABLE suppliers
ADD COLUMN IF NOT EXISTS bank_agency VARCHAR(50);

ALTER TABLE suppliers
ADD COLUMN IF NOT EXISTS bank_account VARCHAR(80);

ALTER TABLE suppliers
ADD COLUMN IF NOT EXISTS owner_cpf VARCHAR(30);

ALTER TABLE suppliers
ADD COLUMN IF NOT EXISTS owner_name VARCHAR(255);

ALTER TABLE suppliers
ADD COLUMN IF NOT EXISTS notes TEXT;

ALTER TABLE suppliers
ADD COLUMN IF NOT EXISTS is_active BOOLEAN NOT NULL DEFAULT TRUE;

ALTER TABLE suppliers
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE suppliers
ADD COLUMN IF NOT EXISTS state_registration VARCHAR(60);

ALTER TABLE suppliers
ADD COLUMN IF NOT EXISTS municipal_registration VARCHAR(60);

ALTER TABLE suppliers
ADD COLUMN IF NOT EXISTS company_size VARCHAR(120);

ALTER TABLE suppliers
ADD COLUMN IF NOT EXISTS main_cnae JSONB;

ALTER TABLE suppliers
ADD COLUMN IF NOT EXISTS secondary_cnaes JSONB NOT NULL DEFAULT '[]'::jsonb;

ALTER TABLE suppliers
ADD COLUMN IF NOT EXISTS participates_bidding BOOLEAN NOT NULL DEFAULT TRUE;

ALTER TABLE suppliers
ADD COLUMN IF NOT EXISTS website_url VARCHAR(255);

ALTER TABLE demand_lists
ADD COLUMN IF NOT EXISTS requester_unit_id INTEGER NULL;

ALTER TABLE demand_lists
ADD COLUMN IF NOT EXISTS secretariat_id INTEGER NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_demand_lists_requester_unit'
    ) THEN
        ALTER TABLE demand_lists
        ADD CONSTRAINT fk_demand_lists_requester_unit
        FOREIGN KEY (requester_unit_id) REFERENCES requester_units(id) ON DELETE SET NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_demand_lists_secretariat'
    ) THEN
        ALTER TABLE demand_lists
        ADD CONSTRAINT fk_demand_lists_secretariat
        FOREIGN KEY (secretariat_id) REFERENCES secretariats(id) ON DELETE SET NULL;
    END IF;
END
$$;

ALTER TABLE demand_lists
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE demand_items
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE demand_items
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE demand_supplier_quotes
ADD COLUMN IF NOT EXISTS quote_number VARCHAR(100);

ALTER TABLE demand_supplier_quotes
ADD COLUMN IF NOT EXISTS quote_date DATE;

ALTER TABLE demand_supplier_quotes
ADD COLUMN IF NOT EXISTS validity_date DATE;
ALTER TABLE demand_supplier_quotes
ADD COLUMN IF NOT EXISTS quoted_by VARCHAR(255);

ALTER TABLE demand_supplier_quotes
ADD COLUMN IF NOT EXISTS collected_by VARCHAR(255);

ALTER TABLE demand_supplier_quotes
ADD COLUMN IF NOT EXISTS attachment_path VARCHAR(255);

ALTER TABLE demand_supplier_quotes
ADD COLUMN IF NOT EXISTS notes TEXT;

ALTER TABLE demand_supplier_quotes
ADD COLUMN IF NOT EXISTS status VARCHAR(50) NOT NULL DEFAULT 'received';

ALTER TABLE demand_supplier_quotes
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE demand_supplier_quote_items
ADD COLUMN IF NOT EXISTS unit_price NUMERIC(12,2);

ALTER TABLE demand_supplier_quote_items
ADD COLUMN IF NOT EXISTS notes TEXT;

ALTER TABLE demand_supplier_quote_items
ADD COLUMN IF NOT EXISTS reused_from_quote_item_id INTEGER NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_demand_supplier_quote_items_reused_from'
    ) THEN
        ALTER TABLE demand_supplier_quote_items
        ADD CONSTRAINT fk_demand_supplier_quote_items_reused_from
        FOREIGN KEY (reused_from_quote_item_id) REFERENCES demand_supplier_quote_items(id) ON DELETE SET NULL;
    END IF;
END
$$;

ALTER TABLE demand_supplier_quote_items
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE demand_supplier_quote_items
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE demand_price_references
ADD COLUMN IF NOT EXISTS notes TEXT;

ALTER TABLE demand_price_references
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE demand_price_references
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE justification_templates
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE environmental_impact_templates
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE environmental_impact_templates
ADD COLUMN IF NOT EXISTS code VARCHAR(20);

CREATE UNIQUE INDEX IF NOT EXISTS ux_environmental_impact_templates_code
ON environmental_impact_templates (code)
WHERE code IS NOT NULL AND code <> '';

ALTER TABLE item_kits
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE item_kit_items
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE item_kit_items
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

CREATE OR REPLACE FUNCTION set_tracking_code()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.tracking_code IS NULL OR NEW.tracking_code = '' THEN
        NEW.tracking_code := 'CL' || LPAD(NEW.id::TEXT, 6, '0');
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_set_tracking_code ON procurement_items;

CREATE TRIGGER trg_set_tracking_code
BEFORE INSERT ON procurement_items
FOR EACH ROW
EXECUTE FUNCTION set_tracking_code();

CREATE OR REPLACE FUNCTION touch_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at := CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_touch_updated_at ON procurement_items;
DROP TRIGGER IF EXISTS trg_touch_updated_at_procurement_items ON procurement_items;
CREATE TRIGGER trg_touch_updated_at_procurement_items
BEFORE UPDATE ON procurement_items
FOR EACH ROW
EXECUTE FUNCTION touch_updated_at();

DROP TRIGGER IF EXISTS trg_touch_updated_at_categories ON categories;
CREATE TRIGGER trg_touch_updated_at_categories
BEFORE UPDATE ON categories
FOR EACH ROW
EXECUTE FUNCTION touch_updated_at();

DROP TRIGGER IF EXISTS trg_touch_updated_at_unit_types ON unit_types;
CREATE TRIGGER trg_touch_updated_at_unit_types
BEFORE UPDATE ON unit_types
FOR EACH ROW
EXECUTE FUNCTION touch_updated_at();

DROP TRIGGER IF EXISTS trg_touch_updated_at_projects ON procurement_projects;
CREATE TRIGGER trg_touch_updated_at_projects
BEFORE UPDATE ON procurement_projects
FOR EACH ROW
EXECUTE FUNCTION touch_updated_at();

DROP TRIGGER IF EXISTS trg_touch_updated_at_secretariats ON secretariats;
CREATE TRIGGER trg_touch_updated_at_secretariats
BEFORE UPDATE ON secretariats
FOR EACH ROW
EXECUTE FUNCTION touch_updated_at();

DROP TRIGGER IF EXISTS trg_touch_updated_at_requester_units ON requester_units;
CREATE TRIGGER trg_touch_updated_at_requester_units
BEFORE UPDATE ON requester_units
FOR EACH ROW
EXECUTE FUNCTION touch_updated_at();

DROP TRIGGER IF EXISTS trg_touch_updated_at_demands ON demand_lists;
CREATE TRIGGER trg_touch_updated_at_demands
BEFORE UPDATE ON demand_lists
FOR EACH ROW
EXECUTE FUNCTION touch_updated_at();

DROP TRIGGER IF EXISTS trg_touch_updated_at_demand_items ON demand_items;
CREATE TRIGGER trg_touch_updated_at_demand_items
BEFORE UPDATE ON demand_items
FOR EACH ROW
EXECUTE FUNCTION touch_updated_at();

DROP TRIGGER IF EXISTS trg_touch_updated_at_suppliers ON suppliers;
CREATE TRIGGER trg_touch_updated_at_suppliers
BEFORE UPDATE ON suppliers
FOR EACH ROW
EXECUTE FUNCTION touch_updated_at();

DROP TRIGGER IF EXISTS trg_touch_updated_at_supplier_quotes ON demand_supplier_quotes;
CREATE TRIGGER trg_touch_updated_at_supplier_quotes
BEFORE UPDATE ON demand_supplier_quotes
FOR EACH ROW
EXECUTE FUNCTION touch_updated_at();

DROP TRIGGER IF EXISTS trg_touch_updated_at_supplier_quote_items ON demand_supplier_quote_items;
CREATE TRIGGER trg_touch_updated_at_supplier_quote_items
BEFORE UPDATE ON demand_supplier_quote_items
FOR EACH ROW
EXECUTE FUNCTION touch_updated_at();

DROP TRIGGER IF EXISTS trg_touch_updated_at_price_references ON demand_price_references;
CREATE TRIGGER trg_touch_updated_at_price_references
BEFORE UPDATE ON demand_price_references
FOR EACH ROW
EXECUTE FUNCTION touch_updated_at();

DROP TRIGGER IF EXISTS trg_touch_updated_at_project_licitation_items ON project_licitation_items;
CREATE TRIGGER trg_touch_updated_at_project_licitation_items
BEFORE UPDATE ON project_licitation_items
FOR EACH ROW
EXECUTE FUNCTION touch_updated_at();

DROP TRIGGER IF EXISTS trg_touch_updated_at_project_annex_versions ON project_annex_versions;
CREATE TRIGGER trg_touch_updated_at_project_annex_versions
BEFORE UPDATE ON project_annex_versions
FOR EACH ROW
EXECUTE FUNCTION touch_updated_at();

DROP TRIGGER IF EXISTS trg_touch_updated_at_project_lot_denominations ON project_lot_denominations;
CREATE TRIGGER trg_touch_updated_at_project_lot_denominations
BEFORE UPDATE ON project_lot_denominations
FOR EACH ROW
EXECUTE FUNCTION touch_updated_at();

DROP TRIGGER IF EXISTS trg_touch_updated_at_kits ON item_kits;
CREATE TRIGGER trg_touch_updated_at_kits
BEFORE UPDATE ON item_kits
FOR EACH ROW
EXECUTE FUNCTION touch_updated_at();

DROP TRIGGER IF EXISTS trg_touch_updated_at_kit_items ON item_kit_items;
CREATE TRIGGER trg_touch_updated_at_kit_items
BEFORE UPDATE ON item_kit_items
FOR EACH ROW
EXECUTE FUNCTION touch_updated_at();

INSERT INTO unit_types (name, abbreviation, description) VALUES
('Unidade', 'un', 'Item contado individualmente'),
('Caixa', 'cx', 'Conjunto de itens acondicionados em caixa'),
('Pacote', 'pct', 'Conjunto de itens acondicionados em pacote'),
('Metro', 'm', 'Medida linear em metros'),
('Metro quadrado', 'm2', 'Medida de area'),
('Metro cubico', 'm3', 'Medida de volume'),
('Rolo', 'rl', 'Material fornecido em rolo'),
('Bobina', 'bob', 'Material fornecido em bobina'),
('Par', 'par', 'Conjunto de duas unidades'),
('Kit', 'kit', 'Conjunto de itens agrupados'),
('Servico', 'serv', 'Prestacao de servico'),
('Licenca', 'lic', 'Licenca de software ou direito de uso')
ON CONFLICT (name) DO UPDATE SET
    abbreviation = EXCLUDED.abbreviation,
    description = EXCLUDED.description;

INSERT INTO categories (name)
SELECT seed.name
FROM (
    VALUES
        ('Equipamentos de TI'),
        ('Perifericos'),
        ('Materiais de Consumo'),
        ('Servicos de TI')
) AS seed(name)
WHERE NOT EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.parent_id IS NULL
      AND c.name = seed.name
);

INSERT INTO categories (parent_id, name)
SELECT c.id, seed.name
FROM categories c
CROSS JOIN (
    VALUES
        ('Computadores'),
        ('Impressoras')
) AS seed(name)
WHERE c.name = 'Equipamentos de TI'
  AND c.parent_id IS NULL
  AND NOT EXISTS (
      SELECT 1
      FROM categories child
      WHERE child.parent_id = c.id
        AND child.name = seed.name
  );

INSERT INTO categories (parent_id, name)
SELECT c.id, 'Teclados e Mouses'
FROM categories c
WHERE c.name = 'Perifericos'
  AND c.parent_id IS NULL
  AND NOT EXISTS (
      SELECT 1
      FROM categories child
      WHERE child.parent_id = c.id
        AND child.name = 'Teclados e Mouses'
  );

INSERT INTO secretariats (name)
VALUES ('Secretaria nao informada')
ON CONFLICT (name) DO NOTHING;

WITH legacy_requester_units AS (
    SELECT
        (SELECT id FROM secretariats WHERE name = 'Secretaria nao informada') AS secretariat_id,
        MIN(legacy.requester_department) AS name,
        MAX(NULLIF(legacy.responsible_name, '')) AS default_responsible_name
    FROM demand_lists legacy
    WHERE legacy.requester_department IS NOT NULL
      AND legacy.requester_department <> ''
    GROUP BY lower(legacy.requester_department)
)
UPDATE requester_units ru
SET default_responsible_name = COALESCE(ru.default_responsible_name, legacy.default_responsible_name)
FROM legacy_requester_units legacy
WHERE ru.secretariat_id = legacy.secretariat_id
  AND ru.parent_id IS NULL
  AND lower(ru.name) = lower(legacy.name);

WITH legacy_requester_units AS (
    SELECT
        (SELECT id FROM secretariats WHERE name = 'Secretaria nao informada') AS secretariat_id,
        MIN(legacy.requester_department) AS name,
        MAX(NULLIF(legacy.responsible_name, '')) AS default_responsible_name
    FROM demand_lists legacy
    WHERE legacy.requester_department IS NOT NULL
      AND legacy.requester_department <> ''
    GROUP BY lower(legacy.requester_department)
)
INSERT INTO requester_units (secretariat_id, name, default_responsible_name)
SELECT
    legacy.secretariat_id,
    legacy.name,
    legacy.default_responsible_name
FROM legacy_requester_units legacy
WHERE NOT EXISTS (
    SELECT 1
    FROM requester_units ru
    WHERE ru.secretariat_id = legacy.secretariat_id
      AND ru.parent_id IS NULL
      AND lower(ru.name) = lower(legacy.name)
);

UPDATE demand_lists dl
SET
    requester_unit_id = ru.id,
    secretariat_id = ru.secretariat_id
FROM requester_units ru
WHERE dl.requester_unit_id IS NULL
  AND dl.requester_department = ru.name
  AND ru.secretariat_id = (SELECT id FROM secretariats WHERE name = 'Secretaria nao informada');

UPDATE procurement_items
SET unit_type_id = (SELECT id FROM unit_types WHERE name = 'Unidade')
WHERE unit_type_id IS NULL;

UPDATE procurement_items
SET tracking_code = 'CL' || LPAD(id::TEXT, 6, '0')
WHERE tracking_code IS NULL OR tracking_code = '';

INSERT INTO procurement_item_images (
    procurement_item_id,
    image_path,
    is_primary
)
SELECT
    id,
    image_path,
    TRUE
FROM procurement_items
WHERE image_path IS NOT NULL
  AND image_path <> ''
  AND NOT EXISTS (
      SELECT 1
      FROM procurement_item_images pii
      WHERE pii.procurement_item_id = procurement_items.id
  );

INSERT INTO justification_templates (title, content)
SELECT seed.title, seed.content
FROM (
    VALUES
        ('Substituicao por obsolescencia', 'A aquisicao justifica-se pela necessidade de substituicao de equipamentos obsoletos, com desempenho insuficiente ou incompativeis com as demandas atuais da Administracao Publica.'),
        ('Continuidade dos servicos publicos', 'A aquisicao visa garantir a continuidade dos servicos publicos, evitando interrupcoes nas atividades administrativas e operacionais das unidades demandantes.'),
        ('Ampliacao da capacidade operacional', 'A aquisicao busca ampliar a capacidade operacional da unidade, permitindo melhor atendimento a populacao e maior eficiencia na execucao das atividades institucionais.')
) AS seed(title, content)
WHERE NOT EXISTS (
    SELECT 1
    FROM justification_templates jt
    WHERE jt.title = seed.title
);

INSERT INTO environmental_impact_templates (code, title, content)
SELECT seed.code, seed.title, seed.content
FROM (
    VALUES
        ('IA001', 'Residuos Eletronicos', 'O produto podera gerar residuos eletroeletronicos ao final de sua vida util, devendo receber destinacao ambientalmente adequada conforme legislacao vigente.'),
        ('IA002', 'Consumo de Energia', 'O equipamento consome energia eletrica durante sua operacao, recomendando-se a utilizacao de recursos de economia de energia quando disponiveis.'),
        ('IA003', 'Uso de Baterias', 'O produto utiliza baterias ou acumuladores que devem ser descartados em locais apropriados.'),
        ('IA004', 'Uso de Plasticos', 'O produto possui componentes plasticos cuja reciclagem deve ser incentivada sempre que possivel.'),
        ('IA005', 'Embalagens', 'As embalagens do produto devem ser destinadas a reciclagem ou reaproveitamento quando possivel.'),
        ('IA006', 'Equipamento de Longa Vida Util', 'A contratacao prioriza equipamentos de maior durabilidade visando reduzir a geracao de residuos.'),
        ('IA007', 'Reducao de Papel', 'O equipamento contribui para a digitalizacao de processos e consequente reducao do consumo de papel.'),
        ('IA008', 'Eficiencia Energetica', 'Preferencialmente deverao ser fornecidos equipamentos com mecanismos de eficiencia energetica reconhecidos pelo mercado.')
) AS seed(code, title, content)
WHERE NOT EXISTS (
    SELECT 1
    FROM environmental_impact_templates eit
    WHERE eit.code = seed.code OR eit.title = seed.title
);

CREATE INDEX IF NOT EXISTS idx_procurement_items_name_trgm
ON procurement_items
USING gin (name gin_trgm_ops);

CREATE INDEX IF NOT EXISTS idx_procurement_items_tracking_code
ON procurement_items (tracking_code);

CREATE INDEX IF NOT EXISTS idx_procurement_items_package_content_unit
ON procurement_items (package_content_unit_type_id);

CREATE UNIQUE INDEX IF NOT EXISTS ux_procurement_items_tracking_code
ON procurement_items (tracking_code)
WHERE tracking_code IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_demand_items_demand_list
ON demand_items (demand_list_id);

CREATE INDEX IF NOT EXISTS idx_demand_lists_project
ON demand_lists (project_id);

CREATE INDEX IF NOT EXISTS idx_demand_lists_secretariat
ON demand_lists (secretariat_id);

CREATE INDEX IF NOT EXISTS idx_demand_lists_requester_unit
ON demand_lists (requester_unit_id);

CREATE INDEX IF NOT EXISTS idx_requester_units_secretariat_name
ON requester_units (secretariat_id, lower(name));

CREATE UNIQUE INDEX IF NOT EXISTS ux_requester_units_secretariat_parent_name
ON requester_units (secretariat_id, COALESCE(parent_id, 0), lower(name));

CREATE INDEX IF NOT EXISTS idx_requester_units_parent
ON requester_units (parent_id);

CREATE INDEX IF NOT EXISTS idx_suppliers_name
ON suppliers (lower(name));

CREATE UNIQUE INDEX IF NOT EXISTS ux_suppliers_document
ON suppliers (lower(document))
WHERE document IS NOT NULL AND document <> '';

CREATE UNIQUE INDEX IF NOT EXISTS ux_demand_supplier_quotes_demand_supplier
ON demand_supplier_quotes (demand_list_id, supplier_id);

CREATE INDEX IF NOT EXISTS idx_demand_supplier_quotes_demand
ON demand_supplier_quotes (demand_list_id);

CREATE INDEX IF NOT EXISTS idx_demand_supplier_quote_items_quote
ON demand_supplier_quote_items (demand_supplier_quote_id);

CREATE INDEX IF NOT EXISTS idx_demand_supplier_quote_items_item
ON demand_supplier_quote_items (demand_item_id);

CREATE INDEX IF NOT EXISTS idx_demand_supplier_quote_items_reused_from
ON demand_supplier_quote_items (reused_from_quote_item_id);

CREATE INDEX IF NOT EXISTS idx_demand_price_references_demand_item
ON demand_price_references (demand_item_id);

CREATE INDEX IF NOT EXISTS idx_demand_price_references_source
ON demand_price_references (source_quote_item_id);

CREATE INDEX IF NOT EXISTS idx_project_licitation_items_project
ON project_licitation_items (project_id);

CREATE INDEX IF NOT EXISTS idx_project_licitation_items_item
ON project_licitation_items (procurement_item_id);

CREATE INDEX IF NOT EXISTS idx_procurement_projects_closure_hash
ON procurement_projects (closure_hash);

CREATE INDEX IF NOT EXISTS idx_project_status_events_project
ON project_status_events (project_id, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_project_status_events_hash
ON project_status_events (event_hash);

CREATE UNIQUE INDEX IF NOT EXISTS ux_project_annex_versions_hash
ON project_annex_versions (project_id, annex_type, content_hash);

CREATE INDEX IF NOT EXISTS idx_project_annex_versions_project_type
ON project_annex_versions (project_id, annex_type, version_number DESC);

CREATE INDEX IF NOT EXISTS idx_project_lot_denominations_project
ON project_lot_denominations (project_id, lot_number);

CREATE INDEX IF NOT EXISTS idx_project_lot_assignments_lot
ON project_lot_assignments (project_lot_id);

CREATE UNIQUE INDEX IF NOT EXISTS ux_project_lot_assignments_item
ON project_lot_assignments (project_lot_id, procurement_item_id)
WHERE assignment_type = 'item' AND procurement_item_id IS NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS ux_project_lot_assignments_category
ON project_lot_assignments (project_lot_id, category_id)
WHERE assignment_type = 'category' AND category_id IS NOT NULL;

SELECT setval(pg_get_serial_sequence('categories', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM categories), 0), 1), COALESCE((SELECT MAX(id) FROM categories), 0) > 0);
SELECT setval(pg_get_serial_sequence('unit_types', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM unit_types), 0), 1), COALESCE((SELECT MAX(id) FROM unit_types), 0) > 0);
SELECT setval(pg_get_serial_sequence('procurement_items', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM procurement_items), 0), 1), COALESCE((SELECT MAX(id) FROM procurement_items), 0) > 0);
SELECT setval(pg_get_serial_sequence('procurement_item_images', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM procurement_item_images), 0), 1), COALESCE((SELECT MAX(id) FROM procurement_item_images), 0) > 0);
SELECT setval(pg_get_serial_sequence('procurement_item_versions', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM procurement_item_versions), 0), 1), COALESCE((SELECT MAX(id) FROM procurement_item_versions), 0) > 0);
SELECT setval(pg_get_serial_sequence('procurement_projects', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM procurement_projects), 0), 1), COALESCE((SELECT MAX(id) FROM procurement_projects), 0) > 0);
SELECT setval(pg_get_serial_sequence('secretariats', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM secretariats), 0), 1), COALESCE((SELECT MAX(id) FROM secretariats), 0) > 0);
SELECT setval(pg_get_serial_sequence('requester_units', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM requester_units), 0), 1), COALESCE((SELECT MAX(id) FROM requester_units), 0) > 0);
SELECT setval(pg_get_serial_sequence('suppliers', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM suppliers), 0), 1), COALESCE((SELECT MAX(id) FROM suppliers), 0) > 0);
SELECT setval(pg_get_serial_sequence('demand_lists', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM demand_lists), 0), 1), COALESCE((SELECT MAX(id) FROM demand_lists), 0) > 0);
SELECT setval(pg_get_serial_sequence('demand_items', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM demand_items), 0), 1), COALESCE((SELECT MAX(id) FROM demand_items), 0) > 0);
SELECT setval(pg_get_serial_sequence('demand_supplier_quotes', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM demand_supplier_quotes), 0), 1), COALESCE((SELECT MAX(id) FROM demand_supplier_quotes), 0) > 0);
SELECT setval(pg_get_serial_sequence('demand_supplier_quote_items', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM demand_supplier_quote_items), 0), 1), COALESCE((SELECT MAX(id) FROM demand_supplier_quote_items), 0) > 0);
SELECT setval(pg_get_serial_sequence('demand_price_references', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM demand_price_references), 0), 1), COALESCE((SELECT MAX(id) FROM demand_price_references), 0) > 0);
SELECT setval(pg_get_serial_sequence('project_licitation_items', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM project_licitation_items), 0), 1), COALESCE((SELECT MAX(id) FROM project_licitation_items), 0) > 0);
SELECT setval(pg_get_serial_sequence('project_status_events', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM project_status_events), 0), 1), COALESCE((SELECT MAX(id) FROM project_status_events), 0) > 0);
SELECT setval(pg_get_serial_sequence('project_annex_versions', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM project_annex_versions), 0), 1), COALESCE((SELECT MAX(id) FROM project_annex_versions), 0) > 0);
SELECT setval(pg_get_serial_sequence('project_lot_denominations', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM project_lot_denominations), 0), 1), COALESCE((SELECT MAX(id) FROM project_lot_denominations), 0) > 0);
SELECT setval(pg_get_serial_sequence('project_lot_assignments', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM project_lot_assignments), 0), 1), COALESCE((SELECT MAX(id) FROM project_lot_assignments), 0) > 0);
SELECT setval(pg_get_serial_sequence('justification_templates', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM justification_templates), 0), 1), COALESCE((SELECT MAX(id) FROM justification_templates), 0) > 0);
SELECT setval(pg_get_serial_sequence('environmental_impact_templates', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM environmental_impact_templates), 0), 1), COALESCE((SELECT MAX(id) FROM environmental_impact_templates), 0) > 0);
SELECT setval(pg_get_serial_sequence('item_kits', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM item_kits), 0), 1), COALESCE((SELECT MAX(id) FROM item_kits), 0) > 0);
SELECT setval(pg_get_serial_sequence('item_kit_items', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM item_kit_items), 0), 1), COALESCE((SELECT MAX(id) FROM item_kit_items), 0) > 0);
