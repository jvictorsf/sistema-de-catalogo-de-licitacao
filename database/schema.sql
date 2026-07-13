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
    process_type VARCHAR(30) NOT NULL DEFAULT 'licitacao',
    direct_purchase_award_criterion VARCHAR(30) NOT NULL DEFAULT 'global_lowest',
    direct_purchase_parameters JSONB NOT NULL DEFAULT '{}'::jsonb,
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

CREATE TABLE IF NOT EXISTS direct_purchase_dod_documents (
    id SERIAL PRIMARY KEY,
    project_id INTEGER NOT NULL UNIQUE REFERENCES procurement_projects(id) ON DELETE CASCADE,
    header JSONB NOT NULL DEFAULT '{}'::jsonb,
    footer JSONB NOT NULL DEFAULT '{}'::jsonb,
    sections JSONB NOT NULL DEFAULT '[]'::jsonb,
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
    address TEXT,
    postal_code VARCHAR(20),
    phone VARCHAR(50),
    branch VARCHAR(30),
    email VARCHAR(255),
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
    share_capital NUMERIC(14,2),
    special_status VARCHAR(255),
    special_status_date DATE,
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

CREATE TABLE IF NOT EXISTS cnae_references (
    code VARCHAR(7) PRIMARY KEY,
    subclass_description TEXT NOT NULL,
    class_code VARCHAR(5),
    class_description TEXT,
    group_code VARCHAR(3),
    group_description TEXT,
    division_code VARCHAR(2),
    division_description TEXT,
    section_code VARCHAR(1),
    section_description TEXT,
    cnae_2_0 BOOLEAN NOT NULL DEFAULT FALSE,
    cnae_2_1 BOOLEAN NOT NULL DEFAULT FALSE,
    cnae_2_2 BOOLEAN NOT NULL DEFAULT FALSE,
    cnae_2_3 BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS app_users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    username VARCHAR(80) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(30) NOT NULL DEFAULT 'operator',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    must_change_password BOOLEAN NOT NULL DEFAULT FALSE,
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT ck_app_users_role CHECK (role IN ('admin', 'manager', 'operator', 'viewer'))
);

ALTER TABLE app_users
DROP CONSTRAINT IF EXISTS ck_app_users_role;

ALTER TABLE app_users
ADD CONSTRAINT ck_app_users_role
CHECK (role IN ('admin', 'manager', 'operator', 'viewer'));
CREATE TABLE IF NOT EXISTS collaborators (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    document_number VARCHAR(50),
    registration_number VARCHAR(100),
    role VARCHAR(255),
    department VARCHAR(255),
    requester_unit_id INTEGER NULL REFERENCES requester_units(id) ON DELETE SET NULL,
    email VARCHAR(255),
    phone VARCHAR(50),
    branch VARCHAR(30),
    whatsapp VARCHAR(50),
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
    quote_collector_name VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS demand_confirmation_requests (
    id SERIAL PRIMARY KEY,
    demand_list_id INTEGER NOT NULL REFERENCES demand_lists(id) ON DELETE CASCADE,
    collaborator_id INTEGER NULL REFERENCES collaborators(id) ON DELETE SET NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    requester_name VARCHAR(255) NOT NULL,
    requester_document VARCHAR(50),
    requester_role VARCHAR(255),
    requester_email VARCHAR(255),
    requester_phone VARCHAR(50),
    statement_text TEXT NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    expires_at TIMESTAMP NULL,
    signed_at TIMESTAMP NULL,
    signature_path VARCHAR(255),
    document_photo_path VARCHAR(255),
    snapshot JSONB NOT NULL DEFAULT '{}'::jsonb,
    content_hash CHAR(64),
    signer_ip VARCHAR(100),
    signer_user_agent TEXT,
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

CREATE TABLE IF NOT EXISTS demand_supplier_quote_attachments (
    id SERIAL PRIMARY KEY,
    demand_supplier_quote_id INTEGER NOT NULL REFERENCES demand_supplier_quotes(id) ON DELETE CASCADE,
    quote_number VARCHAR(100),
    quote_date DATE,
    validity_date DATE,
    attachment_path VARCHAR(255) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (demand_supplier_quote_id, attachment_path)
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
ADD COLUMN IF NOT EXISTS process_type VARCHAR(30) NOT NULL DEFAULT 'licitacao';

ALTER TABLE procurement_projects
ADD COLUMN IF NOT EXISTS direct_purchase_award_criterion VARCHAR(30) NOT NULL DEFAULT 'global_lowest';

ALTER TABLE procurement_projects
ADD COLUMN IF NOT EXISTS direct_purchase_parameters JSONB NOT NULL DEFAULT '{}'::jsonb;

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

ALTER TABLE requester_units
ADD COLUMN IF NOT EXISTS address TEXT;

ALTER TABLE requester_units
ADD COLUMN IF NOT EXISTS postal_code VARCHAR(20);

ALTER TABLE requester_units
ADD COLUMN IF NOT EXISTS phone VARCHAR(50);

ALTER TABLE requester_units
ADD COLUMN IF NOT EXISTS branch VARCHAR(30);

ALTER TABLE requester_units
ADD COLUMN IF NOT EXISTS email VARCHAR(255);

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
ADD COLUMN IF NOT EXISTS share_capital NUMERIC(14,2);

ALTER TABLE suppliers
ADD COLUMN IF NOT EXISTS special_status VARCHAR(255);

ALTER TABLE suppliers
ADD COLUMN IF NOT EXISTS special_status_date DATE;

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
ADD COLUMN IF NOT EXISTS quote_collector_name VARCHAR(255);

ALTER TABLE collaborators
ADD COLUMN IF NOT EXISTS requester_unit_id INTEGER NULL;

ALTER TABLE collaborators
ADD COLUMN IF NOT EXISTS branch VARCHAR(30);

ALTER TABLE collaborators
ADD COLUMN IF NOT EXISTS whatsapp VARCHAR(50);

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_collaborators_requester_unit'
    ) THEN
        ALTER TABLE collaborators
        ADD CONSTRAINT fk_collaborators_requester_unit
        FOREIGN KEY (requester_unit_id) REFERENCES requester_units(id) ON DELETE SET NULL;
    END IF;
END
$$;

ALTER TABLE demand_confirmation_requests
ADD COLUMN IF NOT EXISTS collaborator_id INTEGER NULL REFERENCES collaborators(id) ON DELETE SET NULL;

ALTER TABLE demand_confirmation_requests
ADD COLUMN IF NOT EXISTS requester_email VARCHAR(255);

ALTER TABLE demand_confirmation_requests
ADD COLUMN IF NOT EXISTS requester_phone VARCHAR(50);

ALTER TABLE demand_confirmation_requests
ADD COLUMN IF NOT EXISTS snapshot JSONB NOT NULL DEFAULT '{}'::jsonb;

ALTER TABLE demand_confirmation_requests
ADD COLUMN IF NOT EXISTS content_hash CHAR(64);

ALTER TABLE demand_confirmation_requests
ADD COLUMN IF NOT EXISTS signer_ip VARCHAR(100);

ALTER TABLE demand_confirmation_requests
ADD COLUMN IF NOT EXISTS signer_user_agent TEXT;


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

ALTER TABLE demand_supplier_quote_attachments
ADD COLUMN IF NOT EXISTS quote_number VARCHAR(100);

ALTER TABLE demand_supplier_quote_attachments
ADD COLUMN IF NOT EXISTS quote_date DATE;

ALTER TABLE demand_supplier_quote_attachments
ADD COLUMN IF NOT EXISTS validity_date DATE;

ALTER TABLE demand_supplier_quote_attachments
ADD COLUMN IF NOT EXISTS notes TEXT;

ALTER TABLE demand_supplier_quote_attachments
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

DROP TRIGGER IF EXISTS trg_touch_updated_at_direct_purchase_dod_documents ON direct_purchase_dod_documents;
CREATE TRIGGER trg_touch_updated_at_direct_purchase_dod_documents
BEFORE UPDATE ON direct_purchase_dod_documents
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

DROP TRIGGER IF EXISTS trg_touch_updated_at_app_users ON app_users;
CREATE TRIGGER trg_touch_updated_at_app_users
BEFORE UPDATE ON app_users
FOR EACH ROW
EXECUTE FUNCTION touch_updated_at();
DROP TRIGGER IF EXISTS trg_touch_updated_at_collaborators ON collaborators;
CREATE TRIGGER trg_touch_updated_at_collaborators
BEFORE UPDATE ON collaborators
FOR EACH ROW
EXECUTE FUNCTION touch_updated_at();

DROP TRIGGER IF EXISTS trg_touch_updated_at_demand_confirmation_requests ON demand_confirmation_requests;
CREATE TRIGGER trg_touch_updated_at_demand_confirmation_requests
BEFORE UPDATE ON demand_confirmation_requests
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

DROP TRIGGER IF EXISTS trg_touch_updated_at_cnae_references ON cnae_references;
CREATE TRIGGER trg_touch_updated_at_cnae_references
BEFORE UPDATE ON cnae_references
FOR EACH ROW
EXECUTE FUNCTION touch_updated_at();
DROP TRIGGER IF EXISTS trg_touch_updated_at_supplier_quotes ON demand_supplier_quotes;
CREATE TRIGGER trg_touch_updated_at_supplier_quotes
BEFORE UPDATE ON demand_supplier_quotes
FOR EACH ROW
EXECUTE FUNCTION touch_updated_at();

DROP TRIGGER IF EXISTS trg_touch_updated_at_supplier_quote_attachments ON demand_supplier_quote_attachments;
CREATE TRIGGER trg_touch_updated_at_supplier_quote_attachments
BEFORE UPDATE ON demand_supplier_quote_attachments
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

CREATE INDEX IF NOT EXISTS idx_app_users_username
ON app_users (lower(username));

CREATE INDEX IF NOT EXISTS idx_app_users_email
ON app_users (lower(email));

CREATE INDEX IF NOT EXISTS idx_app_users_role_active
ON app_users (role, is_active);
CREATE INDEX IF NOT EXISTS idx_collaborators_name
ON collaborators (lower(name));

CREATE INDEX IF NOT EXISTS idx_collaborators_document
ON collaborators (lower(document_number));

CREATE INDEX IF NOT EXISTS idx_collaborators_requester_unit
ON collaborators (requester_unit_id);

CREATE INDEX IF NOT EXISTS idx_demand_confirmation_requests_demand
ON demand_confirmation_requests (demand_list_id);

CREATE INDEX IF NOT EXISTS idx_demand_confirmation_requests_collaborator
ON demand_confirmation_requests (collaborator_id);

CREATE INDEX IF NOT EXISTS idx_demand_confirmation_requests_status
ON demand_confirmation_requests (status);

CREATE INDEX IF NOT EXISTS idx_demand_confirmation_requests_hash
ON demand_confirmation_requests (content_hash);
CREATE INDEX IF NOT EXISTS idx_demand_items_demand_list
ON demand_items (demand_list_id);

CREATE INDEX IF NOT EXISTS idx_demand_items_procurement_item
ON demand_items (procurement_item_id);

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

CREATE INDEX IF NOT EXISTS idx_suppliers_special_status
ON suppliers (lower(special_status));

CREATE INDEX IF NOT EXISTS idx_cnae_references_description
ON cnae_references (lower(subclass_description));

CREATE INDEX IF NOT EXISTS idx_cnae_references_class
ON cnae_references (class_code);

CREATE INDEX IF NOT EXISTS idx_cnae_references_section
ON cnae_references (section_code);

CREATE UNIQUE INDEX IF NOT EXISTS ux_demand_supplier_quotes_demand_supplier
ON demand_supplier_quotes (demand_list_id, supplier_id);

CREATE INDEX IF NOT EXISTS idx_demand_supplier_quotes_demand
ON demand_supplier_quotes (demand_list_id);

CREATE INDEX IF NOT EXISTS idx_demand_supplier_quotes_price_history
ON demand_supplier_quotes (quote_date, supplier_id, status);

CREATE INDEX IF NOT EXISTS idx_demand_supplier_quote_attachments_quote
ON demand_supplier_quote_attachments (demand_supplier_quote_id);
CREATE INDEX IF NOT EXISTS idx_demand_supplier_quote_items_quote
ON demand_supplier_quote_items (demand_supplier_quote_id);

CREATE INDEX IF NOT EXISTS idx_demand_supplier_quote_items_item
ON demand_supplier_quote_items (demand_item_id);

CREATE INDEX IF NOT EXISTS idx_demand_supplier_quote_items_reused_from
ON demand_supplier_quote_items (reused_from_quote_item_id);

CREATE INDEX IF NOT EXISTS idx_procurement_items_category
ON procurement_items (category_id);

CREATE INDEX IF NOT EXISTS idx_procurement_items_subcategory
ON procurement_items (subcategory_id);

CREATE INDEX IF NOT EXISTS idx_demand_price_references_demand_item
ON demand_price_references (demand_item_id);

CREATE INDEX IF NOT EXISTS idx_demand_price_references_source
ON demand_price_references (source_quote_item_id);

CREATE INDEX IF NOT EXISTS idx_project_licitation_items_project
ON project_licitation_items (project_id);

CREATE INDEX IF NOT EXISTS idx_project_licitation_items_item
ON project_licitation_items (procurement_item_id);

CREATE INDEX IF NOT EXISTS idx_direct_purchase_dod_documents_project
ON direct_purchase_dod_documents (project_id);

CREATE INDEX IF NOT EXISTS idx_procurement_projects_process_type
ON procurement_projects (process_type);

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

-- Seed: referencias oficiais de CNAE 2.0 a partir de external/br_bd_diretorios_brasil_cnae_2.csv
INSERT INTO cnae_references (
    code, subclass_description, class_code, class_description, group_code, group_description,
    division_code, division_description, section_code, section_description,
    cnae_2_0, cnae_2_1, cnae_2_2, cnae_2_3
) VALUES
('9700500', 'Serviços domésticos', '97005', 'Serviços domésticos', '970', 'Serviços domésticos', '97', 'Serviços Domésticos', 'T', 'Serviços Domésticos', TRUE, TRUE, TRUE, TRUE),
('9900800', 'Organismos internacionais e outras instituições extraterritoriais', '99008', 'Organismos internacionais e outras instituições extraterritoriais', '990', 'Organismos internacionais e outras instituições extraterritoriais', '99', 'Organismos Internacionais E Outras Instituições Extraterritoriais', 'U', 'Organismos Internacionais E Outras Instituições Extraterritoriais', TRUE, TRUE, TRUE, FALSE),
('0111301', 'Cultivo de arroz', '01113', 'Cultivo de cereais', '011', 'Produção de lavouras temporárias', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0111399', 'Cultivo de outros cereais não especificados anteriormente', '01113', 'Cultivo de cereais', '011', 'Produção de lavouras temporárias', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0111302', 'Cultivo de milho', '01113', 'Cultivo de cereais', '011', 'Produção de lavouras temporárias', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0111303', 'Cultivo de trigo', '01113', 'Cultivo de cereais', '011', 'Produção de lavouras temporárias', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0112102', 'Cultivo de juta', '01121', 'Cultivo de algodão herbáceo e de outras fibras de lavoura temporária', '011', 'Produção de lavouras temporárias', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0112101', 'Cultivo de algodão herbáceo', '01121', 'Cultivo de algodão herbáceo e de outras fibras de lavoura temporária', '011', 'Produção de lavouras temporárias', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0112199', 'Cultivo de outras fibras de lavoura temporária não especificadas anteriormente', '01121', 'Cultivo de algodão herbáceo e de outras fibras de lavoura temporária', '011', 'Produção de lavouras temporárias', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0113000', 'Cultivo de cana-de-açúcar', '01130', 'Cultivo de cana-de-açúcar', '011', 'Produção de lavouras temporárias', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0114800', 'Cultivo de fumo', '01148', 'Cultivo de fumo', '011', 'Produção de lavouras temporárias', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0115600', 'Cultivo de soja', '01156', 'Cultivo de soja', '011', 'Produção de lavouras temporárias', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0116402', 'Cultivo de girassol', '01164', 'Cultivo de oleaginosas de lavoura temporária, exceto soja', '011', 'Produção de lavouras temporárias', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0116499', 'Cultivo de outras oleaginosas de lavoura temporária não especificadas anteriormente', '01164', 'Cultivo de oleaginosas de lavoura temporária, exceto soja', '011', 'Produção de lavouras temporárias', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0116403', 'Cultivo de mamona', '01164', 'Cultivo de oleaginosas de lavoura temporária, exceto soja', '011', 'Produção de lavouras temporárias', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0116401', 'Cultivo de amendoim', '01164', 'Cultivo de oleaginosas de lavoura temporária, exceto soja', '011', 'Produção de lavouras temporárias', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0119908', 'Cultivo de melancia', '01199', 'Cultivo de plantas de lavoura temporária não especificadas anteriormente', '011', 'Produção de lavouras temporárias', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0119903', 'Cultivo de batata-inglesa', '01199', 'Cultivo de plantas de lavoura temporária não especificadas anteriormente', '011', 'Produção de lavouras temporárias', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0119904', 'Cultivo de cebola', '01199', 'Cultivo de plantas de lavoura temporária não especificadas anteriormente', '011', 'Produção de lavouras temporárias', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0119901', 'Cultivo de abacaxi', '01199', 'Cultivo de plantas de lavoura temporária não especificadas anteriormente', '011', 'Produção de lavouras temporárias', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0119902', 'Cultivo de alho', '01199', 'Cultivo de plantas de lavoura temporária não especificadas anteriormente', '011', 'Produção de lavouras temporárias', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0119907', 'Cultivo de melão', '01199', 'Cultivo de plantas de lavoura temporária não especificadas anteriormente', '011', 'Produção de lavouras temporárias', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0119906', 'Cultivo de mandioca', '01199', 'Cultivo de plantas de lavoura temporária não especificadas anteriormente', '011', 'Produção de lavouras temporárias', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0119905', 'Cultivo de feijão', '01199', 'Cultivo de plantas de lavoura temporária não especificadas anteriormente', '011', 'Produção de lavouras temporárias', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0119909', 'Cultivo de tomate rasteiro', '01199', 'Cultivo de plantas de lavoura temporária não especificadas anteriormente', '011', 'Produção de lavouras temporárias', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0119999', 'Cultivo de outras plantas de lavoura temporária não especificadas anteriormente', '01199', 'Cultivo de plantas de lavoura temporária não especificadas anteriormente', '011', 'Produção de lavouras temporárias', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0121101', 'Horticultura, exceto morango', '01211', 'Horticultura', '012', 'Horticultura e floricultura', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0121102', 'Cultivo de morango', '01211', 'Horticultura', '012', 'Horticultura e floricultura', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0122900', 'Cultivo de flores e plantas ornamentais', '01229', 'Cultivo de flores e plantas ornamentais', '012', 'Horticultura e floricultura', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0131800', 'Cultivo de laranja', '01318', 'Cultivo de laranja', '013', 'Produção de lavouras permanentes', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0132600', 'Cultivo de uva', '01326', 'Cultivo de uva', '013', 'Produção de lavouras permanentes', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0133401', 'Cultivo de açaí', '01334', 'Cultivo de frutas de lavoura permanente, exceto laranja e uva', '013', 'Produção de lavouras permanentes', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0133499', 'Cultivo de frutas de lavoura permanente não especificadas anteriormente', '01334', 'Cultivo de frutas de lavoura permanente, exceto laranja e uva', '013', 'Produção de lavouras permanentes', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0133408', 'Cultivo de mamão', '01334', 'Cultivo de frutas de lavoura permanente, exceto laranja e uva', '013', 'Produção de lavouras permanentes', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0133410', 'Cultivo de manga', '01334', 'Cultivo de frutas de lavoura permanente, exceto laranja e uva', '013', 'Produção de lavouras permanentes', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0133407', 'Cultivo de maçã', '01334', 'Cultivo de frutas de lavoura permanente, exceto laranja e uva', '013', 'Produção de lavouras permanentes', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0133409', 'Cultivo de maracujá', '01334', 'Cultivo de frutas de lavoura permanente, exceto laranja e uva', '013', 'Produção de lavouras permanentes', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0133402', 'Cultivo de banana', '01334', 'Cultivo de frutas de lavoura permanente, exceto laranja e uva', '013', 'Produção de lavouras permanentes', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0133406', 'Cultivo de guaraná', '01334', 'Cultivo de frutas de lavoura permanente, exceto laranja e uva', '013', 'Produção de lavouras permanentes', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0133403', 'Cultivo de caju', '01334', 'Cultivo de frutas de lavoura permanente, exceto laranja e uva', '013', 'Produção de lavouras permanentes', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0133405', 'Cultivo de coco-da-baía', '01334', 'Cultivo de frutas de lavoura permanente, exceto laranja e uva', '013', 'Produção de lavouras permanentes', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0133404', 'Cultivo de cítricos, exceto laranja', '01334', 'Cultivo de frutas de lavoura permanente, exceto laranja e uva', '013', 'Produção de lavouras permanentes', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0133411', 'Cultivo de pêssego', '01334', 'Cultivo de frutas de lavoura permanente, exceto laranja e uva', '013', 'Produção de lavouras permanentes', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0134200', 'Cultivo de café', '01342', 'Cultivo de café', '013', 'Produção de lavouras permanentes', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0135100', 'Cultivo de cacau', '01351', 'Cultivo de cacau', '013', 'Produção de lavouras permanentes', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0139303', 'Cultivo de pimenta-do-reino', '01393', 'Cultivo de plantas de lavoura permanente não especificadas anteriormente', '013', 'Produção de lavouras permanentes', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0139399', 'Cultivo de outras plantas de lavoura permanente não especificadas anteriormente', '01393', 'Cultivo de plantas de lavoura permanente não especificadas anteriormente', '013', 'Produção de lavouras permanentes', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0139304', 'Cultivo de plantas para condimento, exceto pimenta-do-reino', '01393', 'Cultivo de plantas de lavoura permanente não especificadas anteriormente', '013', 'Produção de lavouras permanentes', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0139302', 'Cultivo de erva-mate', '01393', 'Cultivo de plantas de lavoura permanente não especificadas anteriormente', '013', 'Produção de lavouras permanentes', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0139301', 'Cultivo de chá-da-índia', '01393', 'Cultivo de plantas de lavoura permanente não especificadas anteriormente', '013', 'Produção de lavouras permanentes', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0139305', 'Cultivo de dendê', '01393', 'Cultivo de plantas de lavoura permanente não especificadas anteriormente', '013', 'Produção de lavouras permanentes', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0139306', 'Cultivo de seringueira', '01393', 'Cultivo de plantas de lavoura permanente não especificadas anteriormente', '013', 'Produção de lavouras permanentes', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0141501', 'Produção de sementes certificadas, exceto de forrageiras para pasto', '01415', 'Produção de sementes certificadas', '014', 'Produção de sementes e mudas certificadas', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0141502', 'Produção de sementes certificadas de forrageiras para formação de pasto', '01415', 'Produção de sementes certificadas', '014', 'Produção de sementes e mudas certificadas', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0142300', 'Produção de mudas e outras formas de propagação vegetal, certificadas', '01423', 'Produção de mudas e outras formas de propagação vegetal, certificadas', '014', 'Produção de sementes e mudas certificadas', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0151202', 'Criação de bovinos para leite', '01512', 'Criação de bovinos', '015', 'Pecuária', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0151201', 'Criação de bovinos para corte', '01512', 'Criação de bovinos', '015', 'Pecuária', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0151203', 'Criação de bovinos, exceto para corte e leite', '01512', 'Criação de bovinos', '015', 'Pecuária', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0152102', 'Criação de equinos', '01521', 'Criação de outros animais de grande porte', '015', 'Pecuária', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0152101', 'Criação de bufalinos', '01521', 'Criação de outros animais de grande porte', '015', 'Pecuária', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0152103', 'Criação de asininos e muares', '01521', 'Criação de outros animais de grande porte', '015', 'Pecuária', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0153902', 'Criação de ovinos, inclusive para produção de lã', '01539', 'Criação de caprinos e ovinos', '015', 'Pecuária', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0153901', 'Criação de caprinos', '01539', 'Criação de caprinos e ovinos', '015', 'Pecuária', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0154700', 'Criação de suínos', '01547', 'Criação de suínos', '015', 'Pecuária', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0155503', 'Criação de outros galináceos, exceto para corte', '01555', 'Criação de aves', '015', 'Pecuária', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0155502', 'Produção de pintos de um dia', '01555', 'Criação de aves', '015', 'Pecuária', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0155501', 'Criação de frangos para corte', '01555', 'Criação de aves', '015', 'Pecuária', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0155504', 'Criação de aves, exceto galináceos', '01555', 'Criação de aves', '015', 'Pecuária', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0155505', 'Produção de ovos', '01555', 'Criação de aves', '015', 'Pecuária', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0159804', 'Criação de bicho-da-seda', '01598', 'Criação de animais não especificados anteriormente', '015', 'Pecuária', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0159803', 'Criação de escargô', '01598', 'Criação de animais não especificados anteriormente', '015', 'Pecuária', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0159899', 'Criação de outros animais não especificados anteriormente', '01598', 'Criação de animais não especificados anteriormente', '015', 'Pecuária', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0159802', 'Criação de animais de estimação', '01598', 'Criação de animais não especificados anteriormente', '015', 'Pecuária', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0159801', 'Apicultura', '01598', 'Criação de animais não especificados anteriormente', '015', 'Pecuária', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0161001', 'Serviço de pulverização e controle de pragas agrícolas', '01610', 'Atividades de apoio à agricultura', '016', 'Atividades de apoio à agricultura e à pecuária; atividades de
pós-colheita', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0161002', 'Serviço de poda de árvores para lavouras', '01610', 'Atividades de apoio à agricultura', '016', 'Atividades de apoio à agricultura e à pecuária; atividades de
pós-colheita', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0161099', 'Atividades de apoio à agricultura não especificadas anteriormente', '01610', 'Atividades de apoio à agricultura', '016', 'Atividades de apoio à agricultura e à pecuária; atividades de
pós-colheita', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0161003', 'Serviço de preparação de terreno, cultivo e colheita', '01610', 'Atividades de apoio à agricultura', '016', 'Atividades de apoio à agricultura e à pecuária; atividades de
pós-colheita', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0162899', 'Atividades de apoio à pecuária não especificadas anteriormente', '01628', 'Atividades de apoio à pecuária', '016', 'Atividades de apoio à agricultura e à pecuária; atividades de
pós-colheita', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0162803', 'Serviço de manejo de animais', '01628', 'Atividades de apoio à pecuária', '016', 'Atividades de apoio à agricultura e à pecuária; atividades de
pós-colheita', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0162802', 'Serviço de tosquiamento de ovinos', '01628', 'Atividades de apoio à pecuária', '016', 'Atividades de apoio à agricultura e à pecuária; atividades de
pós-colheita', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0162801', 'Serviço de inseminação artificial em animais', '01628', 'Atividades de apoio à pecuária', '016', 'Atividades de apoio à agricultura e à pecuária; atividades de
pós-colheita', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0163600', 'Atividades de pós-colheita', '01636', 'Atividades de pós-colheita', '016', 'Atividades de apoio à agricultura e à pecuária; atividades de
pós-colheita', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0170900', 'Caça e serviços relacionados', '01709', 'Caça e serviços relacionados', '017', 'Caça e serviços relacionados', '01', 'Agricultura, Pecuária E Serviços Relacionados', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0210199', 'Produção de produtos não madeireiros não especificados anteriormente em florestas plantadas', '02101', 'Produção florestal - florestas plantadas', '021', 'Produção florestal - florestas plantadas', '02', 'Produção Florestal', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0210108', 'Produção de carvão vegetal - florestas plantadas', '02101', 'Produção florestal - florestas plantadas', '021', 'Produção florestal - florestas plantadas', '02', 'Produção Florestal', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0210101', 'Cultivo de eucalipto', '02101', 'Produção florestal - florestas plantadas', '021', 'Produção florestal - florestas plantadas', '02', 'Produção Florestal', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0210104', 'Cultivo de teca', '02101', 'Produção florestal - florestas plantadas', '021', 'Produção florestal - florestas plantadas', '02', 'Produção Florestal', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0210102', 'Cultivo de acácia-negra', '02101', 'Produção florestal - florestas plantadas', '021', 'Produção florestal - florestas plantadas', '02', 'Produção Florestal', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0210103', 'Cultivo de pinus', '02101', 'Produção florestal - florestas plantadas', '021', 'Produção florestal - florestas plantadas', '02', 'Produção Florestal', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0210107', 'Extração de madeira em florestas plantadas', '02101', 'Produção florestal - florestas plantadas', '021', 'Produção florestal - florestas plantadas', '02', 'Produção Florestal', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0210109', 'Produção de casca de acácia-negra - florestas plantadas', '02101', 'Produção florestal - florestas plantadas', '021', 'Produção florestal - florestas plantadas', '02', 'Produção Florestal', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0210106', 'Cultivo de mudas em viveiros florestais', '02101', 'Produção florestal - florestas plantadas', '021', 'Produção florestal - florestas plantadas', '02', 'Produção Florestal', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0210105', 'Cultivo de espécies madeireiras, exceto eucalipto, acácia-negra, pinus e teca', '02101', 'Produção florestal - florestas plantadas', '021', 'Produção florestal - florestas plantadas', '02', 'Produção Florestal', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0220905', 'Coleta de palmito em florestas nativas', '02209', 'Produção florestal - florestas nativas', '022', 'Produção florestal - florestas nativas', '02', 'Produção Florestal', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0220999', 'Coleta de produtos não madeireiros não especificados anteriormente em florestas nativas', '02209', 'Produção florestal - florestas nativas', '022', 'Produção florestal - florestas nativas', '02', 'Produção Florestal', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0220902', 'Produção de carvão vegetal - florestas nativas', '02209', 'Produção florestal - florestas nativas', '022', 'Produção florestal - florestas nativas', '02', 'Produção Florestal', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0220901', 'Extração de madeira em florestas nativas', '02209', 'Produção florestal - florestas nativas', '022', 'Produção florestal - florestas nativas', '02', 'Produção Florestal', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0220906', 'Conservação de florestas nativas', '02209', 'Produção florestal - florestas nativas', '022', 'Produção florestal - florestas nativas', '02', 'Produção Florestal', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0220904', 'Coleta de látex em florestas nativas', '02209', 'Produção florestal - florestas nativas', '022', 'Produção florestal - florestas nativas', '02', 'Produção Florestal', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0220903', 'Coleta de castanha-do-pará em florestas nativas', '02209', 'Produção florestal - florestas nativas', '022', 'Produção florestal - florestas nativas', '02', 'Produção Florestal', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0230600', 'Atividades de apoio à produção florestal', '02306', 'Atividades de apoio à produção florestal', '023', 'Atividades de apoio à produção florestal', '02', 'Produção Florestal', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0311604', 'Atividades de apoio à pesca em água salgada', '03116', 'Pesca em água salgada', '031', 'Pesca', '03', 'Pesca E Aqüicultura', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0311602', 'Pesca de crustáceos e moluscos em água salgada', '03116', 'Pesca em água salgada', '031', 'Pesca', '03', 'Pesca E Aqüicultura', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0311601', 'Pesca de peixes em água salgada', '03116', 'Pesca em água salgada', '031', 'Pesca', '03', 'Pesca E Aqüicultura', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0311603', 'Coleta de outros produtos marinhos', '03116', 'Pesca em água salgada', '031', 'Pesca', '03', 'Pesca E Aqüicultura', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0312401', 'Pesca de peixes em água doce', '03124', 'Pesca em água doce', '031', 'Pesca', '03', 'Pesca E Aqüicultura', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0312402', 'Pesca de crustáceos e moluscos em água doce', '03124', 'Pesca em água doce', '031', 'Pesca', '03', 'Pesca E Aqüicultura', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0312403', 'Coleta de outros produtos aquáticos de água doce', '03124', 'Pesca em água doce', '031', 'Pesca', '03', 'Pesca E Aqüicultura', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0312404', 'Atividades de apoio à pesca em água doce', '03124', 'Pesca em água doce', '031', 'Pesca', '03', 'Pesca E Aqüicultura', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0321303', 'Criação de ostras e mexilhões em água salgada e salobra', '03213', 'Aquicultura em água salgada e salobra', '032', 'Aquicultura', '03', 'Pesca E Aqüicultura', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0321305', 'Atividades de apoio à aquicultura em água salgada e salobra', '03213', 'Aquicultura em água salgada e salobra', '032', 'Aquicultura', '03', 'Pesca E Aqüicultura', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0321304', 'Criação de peixes ornamentais em água salgada e salobra', '03213', 'Aquicultura em água salgada e salobra', '032', 'Aquicultura', '03', 'Pesca E Aqüicultura', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0321399', 'Cultivos e semicultivos da aquicultura em água salgada e salobra não especificados anteriormente', '03213', 'Aquicultura em água salgada e salobra', '032', 'Aquicultura', '03', 'Pesca E Aqüicultura', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0321301', 'Criação de peixes em água salgada e salobra', '03213', 'Aquicultura em água salgada e salobra', '032', 'Aquicultura', '03', 'Pesca E Aqüicultura', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0321302', 'Criação de camarões em água salgada e salobra', '03213', 'Aquicultura em água salgada e salobra', '032', 'Aquicultura', '03', 'Pesca E Aqüicultura', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0322103', 'Criação de ostras e mexilhões em água doce', '03221', 'Aquicultura em água doce', '032', 'Aquicultura', '03', 'Pesca E Aqüicultura', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0322105', 'Ranicultura', '03221', 'Aquicultura em água doce', '032', 'Aquicultura', '03', 'Pesca E Aqüicultura', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0322102', 'Criação de camarões em água doce', '03221', 'Aquicultura em água doce', '032', 'Aquicultura', '03', 'Pesca E Aqüicultura', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0322106', 'Criação de jacaré', '03221', 'Aquicultura em água doce', '032', 'Aquicultura', '03', 'Pesca E Aqüicultura', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0322199', 'Cultivos e semicultivos da aquicultura em água doce não especificados anteriormente', '03221', 'Aquicultura em água doce', '032', 'Aquicultura', '03', 'Pesca E Aqüicultura', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0322101', 'Criação de peixes em água doce', '03221', 'Aquicultura em água doce', '032', 'Aquicultura', '03', 'Pesca E Aqüicultura', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0322104', 'Criação de peixes ornamentais em água doce', '03221', 'Aquicultura em água doce', '032', 'Aquicultura', '03', 'Pesca E Aqüicultura', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0322107', 'Atividades de apoio à aquicultura em água doce', '03221', 'Aquicultura em água doce', '032', 'Aquicultura', '03', 'Pesca E Aqüicultura', 'A', 'Agricultura, Pecuária, Produção Florestal, Pesca E Aquicultura', TRUE, TRUE, TRUE, TRUE),
('0500302', 'Beneficiamento de carvão mineral', '05003', 'Extração de carvão mineral', '050', 'Extração de carvão mineral', '05', 'Extração De Carvão Mineral', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0500301', 'Extração de carvão mineral', '05003', 'Extração de carvão mineral', '050', 'Extração de carvão mineral', '05', 'Extração De Carvão Mineral', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0600003', 'Extração e beneficiamento de areias betuminosas', '06000', 'Extração de petróleo e gás natural', '060', 'Extração de petróleo e gás natural', '06', 'Extração De Petróleo E Gás Natural', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0600002', 'Extração e beneficiamento de xisto', '06000', 'Extração de petróleo e gás natural', '060', 'Extração de petróleo e gás natural', '06', 'Extração De Petróleo E Gás Natural', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0600001', 'Extração de petróleo e gás natural', '06000', 'Extração de petróleo e gás natural', '060', 'Extração de petróleo e gás natural', '06', 'Extração De Petróleo E Gás Natural', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0710302', 'Pelotização, sinterização e outros beneficiamentos de minério de ferro', '07103', 'Extração de minério de ferro', '071', 'Extração de minério de ferro', '07', 'Extração De Minerais Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0710301', 'Extração de minério de ferro', '07103', 'Extração de minério de ferro', '071', 'Extração de minério de ferro', '07', 'Extração De Minerais Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0721902', 'Beneficiamento de minério de alumínio', '07219', 'Extração de minério de alumínio', '072', 'Extração de minerais metálicos não ferrosos', '07', 'Extração De Minerais Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0721901', 'Extração de minério de alumínio', '07219', 'Extração de minério de alumínio', '072', 'Extração de minerais metálicos não ferrosos', '07', 'Extração De Minerais Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0722702', 'Beneficiamento de minério de estanho', '07227', 'Extração de minério de estanho', '072', 'Extração de minerais metálicos não ferrosos', '07', 'Extração De Minerais Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0722701', 'Extração de minério de estanho', '07227', 'Extração de minério de estanho', '072', 'Extração de minerais metálicos não ferrosos', '07', 'Extração De Minerais Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0723501', 'Extração de minério de manganês', '07235', 'Extração de minério de manganês', '072', 'Extração de minerais metálicos não ferrosos', '07', 'Extração De Minerais Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0723502', 'Beneficiamento de minério de manganês', '07235', 'Extração de minério de manganês', '072', 'Extração de minerais metálicos não ferrosos', '07', 'Extração De Minerais Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0724301', 'Extração de minério de metais preciosos', '07243', 'Extração de minério de metais preciosos', '072', 'Extração de minerais metálicos não ferrosos', '07', 'Extração De Minerais Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0724302', 'Beneficiamento de minério de metais preciosos', '07243', 'Extração de minério de metais preciosos', '072', 'Extração de minerais metálicos não ferrosos', '07', 'Extração De Minerais Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0725100', 'Extração de minerais radioativos', '07251', 'Extração de minerais radioativos', '072', 'Extração de minerais metálicos não ferrosos', '07', 'Extração De Minerais Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0729402', 'Extração de minério de tungstênio', '07294', 'Extração de minerais metálicos não ferrosos não especificados anteriormente', '072', 'Extração de minerais metálicos não ferrosos', '07', 'Extração De Minerais Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0729401', 'Extração de minérios de nióbio e titânio', '07294', 'Extração de minerais metálicos não ferrosos não especificados anteriormente', '072', 'Extração de minerais metálicos não ferrosos', '07', 'Extração De Minerais Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0729404', 'Extração de minérios de cobre, chumbo, zinco e outros minerais metálicos não ferrosos não especificados anteriormente', '07294', 'Extração de minerais metálicos não ferrosos não especificados anteriormente', '072', 'Extração de minerais metálicos não ferrosos', '07', 'Extração De Minerais Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0729405', 'Beneficiamento de minérios de cobre, chumbo, zinco e outros minerais metálicos não ferrosos não especificados anteriormente', '07294', 'Extração de minerais metálicos não ferrosos não especificados anteriormente', '072', 'Extração de minerais metálicos não ferrosos', '07', 'Extração De Minerais Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0729403', 'Extração de minério de níquel', '07294', 'Extração de minerais metálicos não ferrosos não especificados anteriormente', '072', 'Extração de minerais metálicos não ferrosos', '07', 'Extração De Minerais Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0810006', 'Extração de areia, cascalho ou pedregulho e beneficiamento associado', '08100', 'Extração de pedra, areia e argila', '081', 'Extração de pedra, areia e argila', '08', 'Extração De Minerais Não Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0810001', 'Extração de ardósia e beneficiamento associado', '08100', 'Extração de pedra, areia e argila', '081', 'Extração de pedra, areia e argila', '08', 'Extração De Minerais Não Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0810004', 'Extração de calcário e dolomita e beneficiamento associado', '08100', 'Extração de pedra, areia e argila', '081', 'Extração de pedra, areia e argila', '08', 'Extração De Minerais Não Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0810007', 'Extração de argila e beneficiamento associado', '08100', 'Extração de pedra, areia e argila', '081', 'Extração de pedra, areia e argila', '08', 'Extração De Minerais Não Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0810009', 'Extração de basalto e beneficiamento associado', '08100', 'Extração de pedra, areia e argila', '081', 'Extração de pedra, areia e argila', '08', 'Extração De Minerais Não Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0810002', 'Extração de granito e beneficiamento associado', '08100', 'Extração de pedra, areia e argila', '081', 'Extração de pedra, areia e argila', '08', 'Extração De Minerais Não Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0810005', 'Extração de gesso e caulim', '08100', 'Extração de pedra, areia e argila', '081', 'Extração de pedra, areia e argila', '08', 'Extração De Minerais Não Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0810003', 'Extração de mármore e beneficiamento associado', '08100', 'Extração de pedra, areia e argila', '081', 'Extração de pedra, areia e argila', '08', 'Extração De Minerais Não Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0810010', 'Beneficiamento de gesso e caulim associado à extração', '08100', 'Extração de pedra, areia e argila', '081', 'Extração de pedra, areia e argila', '08', 'Extração De Minerais Não Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0810099', 'Extração e britamento de pedras e outros materiais para construção e beneficiamento associado', '08100', 'Extração de pedra, areia e argila', '081', 'Extração de pedra, areia e argila', '08', 'Extração De Minerais Não Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0810008', 'Extração de saibro e beneficiamento associado', '08100', 'Extração de pedra, areia e argila', '081', 'Extração de pedra, areia e argila', '08', 'Extração De Minerais Não Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0891600', 'Extração de minerais para fabricação de adubos, fertilizantes e outros produtos químicos', '08916', 'Extração de minerais para fabricação de adubos, fertilizantes e outros produtos químicos', '089', 'Extração de outros minerais não metálicos', '08', 'Extração De Minerais Não Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0892403', 'Refino e outros tratamentos do sal', '08924', 'Extração e refino de sal marinho e sal-gema', '089', 'Extração de outros minerais não metálicos', '08', 'Extração De Minerais Não Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0892401', 'Extração de sal marinho', '08924', 'Extração e refino de sal marinho e sal-gema', '089', 'Extração de outros minerais não metálicos', '08', 'Extração De Minerais Não Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0892402', 'Extração de sal-gema', '08924', 'Extração e refino de sal marinho e sal-gema', '089', 'Extração de outros minerais não metálicos', '08', 'Extração De Minerais Não Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0893200', 'Extração de gemas (pedras preciosas e semipreciosas)', '08932', 'Extração de gemas (pedras preciosas e semipreciosas)', '089', 'Extração de outros minerais não metálicos', '08', 'Extração De Minerais Não Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0899102', 'Extração de quartzo', '08991', 'Extração de minerais não metálicos não especificados anteriormente', '089', 'Extração de outros minerais não metálicos', '08', 'Extração De Minerais Não Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0899103', 'Extração de amianto', '08991', 'Extração de minerais não metálicos não especificados anteriormente', '089', 'Extração de outros minerais não metálicos', '08', 'Extração De Minerais Não Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0899199', 'Extração de outros minerais não metálicos não especificados anteriormente', '08991', 'Extração de minerais não metálicos não especificados anteriormente', '089', 'Extração de outros minerais não metálicos', '08', 'Extração De Minerais Não Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0899101', 'Extração de grafita', '08991', 'Extração de minerais não metálicos não especificados anteriormente', '089', 'Extração de outros minerais não metálicos', '08', 'Extração De Minerais Não Metálicos', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0910600', 'Atividades de apoio à extração de petróleo e gás natural', '09106', 'Atividades de apoio à extração de petróleo e gás natural', '091', 'Atividades de apoio à extração de petróleo e gás natural', '09', 'Atividades De Apoio À Extração De Minerais', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0990401', 'Atividades de apoio à extração de minério de ferro', '09904', 'Atividades de apoio à extração de minerais, exceto petróleo e gás natural', '099', 'Atividades de apoio à extração de minerais, exceto petróleo e gás natural', '09', 'Atividades De Apoio À Extração De Minerais', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0990403', 'Atividades de apoio à extração de minerais não metálicos', '09904', 'Atividades de apoio à extração de minerais, exceto petróleo e gás natural', '099', 'Atividades de apoio à extração de minerais, exceto petróleo e gás natural', '09', 'Atividades De Apoio À Extração De Minerais', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('0990402', 'Atividades de apoio à extração de minerais metálicos não ferrosos', '09904', 'Atividades de apoio à extração de minerais, exceto petróleo e gás natural', '099', 'Atividades de apoio à extração de minerais, exceto petróleo e gás natural', '09', 'Atividades De Apoio À Extração De Minerais', 'B', 'Indústrias Extrativas', TRUE, TRUE, TRUE, TRUE),
('1011202', 'Frigorífico - abate de equinos', '10112', 'Abate de reses, exceto suínos', '101', 'Abate e fabricação de produtos de carne', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1011203', 'Frigorífico - abate de ovinos e caprinos', '10112', 'Abate de reses, exceto suínos', '101', 'Abate e fabricação de produtos de carne', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1011201', 'Frigorífico - abate de bovinos', '10112', 'Abate de reses, exceto suínos', '101', 'Abate e fabricação de produtos de carne', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1011205', 'Matadouro - abate de reses sob contrato, exceto abate de suínos', '10112', 'Abate de reses, exceto suínos', '101', 'Abate e fabricação de produtos de carne', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1011204', 'Frigorífico - abate de bufalinos', '10112', 'Abate de reses, exceto suínos', '101', 'Abate e fabricação de produtos de carne', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1012102', 'Abate de pequenos animais', '10121', 'Abate de suínos, aves e outros pequenos animais', '101', 'Abate e fabricação de produtos de carne', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1012101', 'Abate de aves', '10121', 'Abate de suínos, aves e outros pequenos animais', '101', 'Abate e fabricação de produtos de carne', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1012104', 'Matadouro - abate de suínos sob contrato', '10121', 'Abate de suínos, aves e outros pequenos animais', '101', 'Abate e fabricação de produtos de carne', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1012103', 'Frigorífico - abate de suínos', '10121', 'Abate de suínos, aves e outros pequenos animais', '101', 'Abate e fabricação de produtos de carne', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1013901', 'Fabricação de produtos de carne', '10139', 'Fabricação de produtos de carne', '101', 'Abate e fabricação de produtos de carne', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1013902', 'Preparação de subprodutos do abate', '10139', 'Fabricação de produtos de carne', '101', 'Abate e fabricação de produtos de carne', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1020102', 'Fabricação de conservas de peixes, crustáceos e moluscos', '10201', 'Preservação do pescado e fabricação de produtos do pescado', '102', 'Preservação do pescado e fabricação de produtos do pescado', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1020101', 'Preservação de peixes, crustáceos e moluscos', '10201', 'Preservação do pescado e fabricação de produtos do pescado', '102', 'Preservação do pescado e fabricação de produtos do pescado', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1031700', 'Fabricação de conservas de frutas', '10317', 'Fabricação de conservas de frutas', '103', 'Fabricação de conservas de frutas, legumes e outros vegetais', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1032599', 'Fabricação de conservas de legumes e outros vegetais, exceto palmito', '10325', 'Fabricação de conservas de legumes e outros vegetais', '103', 'Fabricação de conservas de frutas, legumes e outros vegetais', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1032501', 'Fabricação de conservas de palmito', '10325', 'Fabricação de conservas de legumes e outros vegetais', '103', 'Fabricação de conservas de frutas, legumes e outros vegetais', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1033301', 'Fabricação de sucos concentrados de frutas, hortaliças e legumes', '10333', 'Fabricação de sucos de frutas, hortaliças e legumes', '103', 'Fabricação de conservas de frutas, legumes e outros vegetais', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1033302', 'Fabricação de sucos de frutas, hortaliças e legumes, exceto concentrados', '10333', 'Fabricação de sucos de frutas, hortaliças e legumes', '103', 'Fabricação de conservas de frutas, legumes e outros vegetais', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1041400', 'Fabricação de óleos vegetais em bruto, exceto óleo de milho', '10414', 'Fabricação de óleos vegetais em bruto, exceto óleo de milho', '104', 'Fabricação de óleos e gorduras vegetais e animais', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1042200', 'Fabricação de óleos vegetais refinados, exceto óleo de milho', '10422', 'Fabricação de óleos vegetais refinados, exceto óleo de milho', '104', 'Fabricação de óleos e gorduras vegetais e animais', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1043100', 'Fabricação de margarina e outras gorduras vegetais e de óleos não comestíveis de animais', '10431', 'Fabricação de margarina e outras gorduras vegetais e de óleos não comestíveis de animais', '104', 'Fabricação de óleos e gorduras vegetais e animais', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1051100', 'Preparação do leite', '10511', 'Preparação do leite', '105', 'Laticínios', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1052000', 'Fabricação de laticínios', '10520', 'Fabricação de laticínios', '105', 'Laticínios', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1053800', 'Fabricação de sorvetes e outros gelados comestíveis', '10538', 'Fabricação de sorvetes e outros gelados comestíveis', '105', 'Laticínios', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1061901', 'Beneficiamento de arroz', '10619', 'Beneficiamento de arroz e fabricação de produtos do arroz', '106', 'Moagem, fabricação de produtos amiláceos e de alimentos para animais', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1061902', 'Fabricação de produtos do arroz', '10619', 'Beneficiamento de arroz e fabricação de produtos do arroz', '106', 'Moagem, fabricação de produtos amiláceos e de alimentos para animais', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1062700', 'Moagem de trigo e fabricação de derivados', '10627', 'Moagem de trigo e fabricação de derivados', '106', 'Moagem, fabricação de produtos amiláceos e de alimentos para animais', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1063500', 'Fabricação de farinha de mandioca e derivados', '10635', 'Fabricação de farinha de mandioca e derivados', '106', 'Moagem, fabricação de produtos amiláceos e de alimentos para animais', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1064300', 'Fabricação de farinha de milho e derivados, exceto óleos de milho', '10643', 'Fabricação de farinha de milho e derivados, exceto óleos de milho', '106', 'Moagem, fabricação de produtos amiláceos e de alimentos para animais', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1065102', 'Fabricação de óleo de milho em bruto', '10651', 'Fabricação de amidos e féculas de vegetais e de óleos de milho', '106', 'Moagem, fabricação de produtos amiláceos e de alimentos para animais', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1065101', 'Fabricação de amidos e féculas de vegetais', '10651', 'Fabricação de amidos e féculas de vegetais e de óleos de milho', '106', 'Moagem, fabricação de produtos amiláceos e de alimentos para animais', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1065103', 'Fabricação de óleo de milho refinado', '10651', 'Fabricação de amidos e féculas de vegetais e de óleos de milho', '106', 'Moagem, fabricação de produtos amiláceos e de alimentos para animais', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1066000', 'Fabricação de alimentos para animais', '10660', 'Fabricação de alimentos para animais', '106', 'Moagem, fabricação de produtos amiláceos e de alimentos para animais', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1069400', 'Moagem e fabricação de produtos de origem vegetal não especificados anteriormente', '10694', 'Moagem e fabricação de produtos de origem vegetal não especificados anteriormente', '106', 'Moagem, fabricação de produtos amiláceos e de alimentos para animais', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1071600', 'Fabricação de açúcar em bruto', '10716', 'Fabricação de açúcar em bruto', '107', 'Fabricação e refino de açúcar', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1072402', 'Fabricação de açúcar de cereais (dextrose) e de beterraba', '10724', 'Fabricação de açúcar refinado', '107', 'Fabricação e refino de açúcar', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1072401', 'Fabricação de açúcar de cana refinado', '10724', 'Fabricação de açúcar refinado', '107', 'Fabricação e refino de açúcar', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1081301', 'Beneficiamento de café', '10813', 'Torrefação e moagem de café', '108', 'Torrefação e moagem de café', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1081302', 'Torrefação e moagem de café', '10813', 'Torrefação e moagem de café', '108', 'Torrefação e moagem de café', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1082100', 'Fabricação de produtos à base de café', '10821', 'Fabricação de produtos à base de café', '108', 'Torrefação e moagem de café', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1091100', 'Fabricação de produtos de panificação', '10911', 'Fabricação de produtos de panificação', '109', 'Fabricação de outros produtos alimentícios', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, FALSE, FALSE, FALSE),
('1091101', 'Fabricação de produtos de panificação industrial', '10911', 'Fabricação de produtos de panificação', '109', 'Fabricação de outros produtos alimentícios', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', FALSE, TRUE, TRUE, TRUE),
('1091102', 'Fabricação de produtos de padaria e confeitaria com predominância de produção própria', '10911', 'Fabricação de produtos de panificação', '109', 'Fabricação de outros produtos alimentícios', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', FALSE, TRUE, TRUE, TRUE),
('1092900', 'Fabricação de biscoitos e bolachas', '10929', 'Fabricação de biscoitos e bolachas', '109', 'Fabricação de outros produtos alimentícios', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1093701', 'Fabricação de produtos derivados do cacau e de chocolates', '10937', 'Fabricação de produtos derivados do cacau, de chocolates e confeitos', '109', 'Fabricação de outros produtos alimentícios', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1093702', 'Fabricação de frutas cristalizadas, balas e semelhantes', '10937', 'Fabricação de produtos derivados do cacau, de chocolates e confeitos', '109', 'Fabricação de outros produtos alimentícios', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1094500', 'Fabricação de massas alimentícias', '10945', 'Fabricação de massas alimentícias', '109', 'Fabricação de outros produtos alimentícios', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1095300', 'Fabricação de especiarias, molhos, temperos e condimentos', '10953', 'Fabricação de especiarias, molhos, temperos e condimentos', '109', 'Fabricação de outros produtos alimentícios', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1096100', 'Fabricação de alimentos e pratos prontos', '10961', 'Fabricação de alimentos e pratos prontos', '109', 'Fabricação de outros produtos alimentícios', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1099603', 'Fabricação de fermentos e leveduras', '10996', 'Fabricação de produtos alimentícios não especificados anteriormente', '109', 'Fabricação de outros produtos alimentícios', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1099607', 'Fabricação de alimentos dietéticos e complementos alimentares', '10996', 'Fabricação de produtos alimentícios não especificados anteriormente', '109', 'Fabricação de outros produtos alimentícios', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', FALSE, TRUE, TRUE, TRUE),
('1099606', 'Fabricação de adoçantes naturais e artificiais', '10996', 'Fabricação de produtos alimentícios não especificados anteriormente', '109', 'Fabricação de outros produtos alimentícios', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1099601', 'Fabricação de vinagres', '10996', 'Fabricação de produtos alimentícios não especificados anteriormente', '109', 'Fabricação de outros produtos alimentícios', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1099602', 'Fabricação de pós-alimentícios', '10996', 'Fabricação de produtos alimentícios não especificados anteriormente', '109', 'Fabricação de outros produtos alimentícios', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1099699', 'Fabricação de outros produtos alimentícios não especificados anteriormente', '10996', 'Fabricação de produtos alimentícios não especificados anteriormente', '109', 'Fabricação de outros produtos alimentícios', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1099604', 'Fabricação de gelo comum', '10996', 'Fabricação de produtos alimentícios não especificados anteriormente', '109', 'Fabricação de outros produtos alimentícios', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1099605', 'Fabricação de produtos para infusão (chá, mate, etc.)', '10996', 'Fabricação de produtos alimentícios não especificados anteriormente', '109', 'Fabricação de outros produtos alimentícios', '10', 'Fabricação De Produtos Alimentícios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1111902', 'Fabricação de outras aguardentes e bebidas destiladas', '11119', 'Fabricação de aguardentes e outras bebidas destiladas', '111', 'Fabricação de bebidas alcoólicas', '11', 'Fabricação De Bebidas', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1111901', 'Fabricação de aguardente de cana-de-açúcar', '11119', 'Fabricação de aguardentes e outras bebidas destiladas', '111', 'Fabricação de bebidas alcoólicas', '11', 'Fabricação De Bebidas', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1112700', 'Fabricação de vinho', '11127', 'Fabricação de vinho', '111', 'Fabricação de bebidas alcoólicas', '11', 'Fabricação De Bebidas', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1113501', 'Fabricação de malte, inclusive malte uísque', '11135', 'Fabricação de malte, cervejas e chopes', '111', 'Fabricação de bebidas alcoólicas', '11', 'Fabricação De Bebidas', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1113502', 'Fabricação de cervejas e chopes', '11135', 'Fabricação de malte, cervejas e chopes', '111', 'Fabricação de bebidas alcoólicas', '11', 'Fabricação De Bebidas', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1121600', 'Fabricação de águas envasadas', '11216', 'Fabricação de águas envasadas', '112', 'Fabricação de bebidas não alcoólicas', '11', 'Fabricação De Bebidas', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1122403', 'Fabricação de refrescos, xaropes e pós para refrescos, exceto refrescos de frutas', '11224', 'Fabricação de refrigerantes e de outras bebidas não alcoólicas', '112', 'Fabricação de bebidas não alcoólicas', '11', 'Fabricação De Bebidas', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1122401', 'Fabricação de refrigerantes', '11224', 'Fabricação de refrigerantes e de outras bebidas não alcoólicas', '112', 'Fabricação de bebidas não alcoólicas', '11', 'Fabricação De Bebidas', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1122404', 'Fabricação de bebidas isotônicas', '11224', 'Fabricação de refrigerantes e de outras bebidas não alcoólicas', '112', 'Fabricação de bebidas não alcoólicas', '11', 'Fabricação De Bebidas', 'C', 'Indústrias De Transformação', FALSE, TRUE, TRUE, TRUE),
('1122402', 'Fabricação de chá mate e outros chás prontos para consumo', '11224', 'Fabricação de refrigerantes e de outras bebidas não alcoólicas', '112', 'Fabricação de bebidas não alcoólicas', '11', 'Fabricação De Bebidas', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1122499', 'Fabricação de outras bebidas não alcoólicas não especificadas anteriormente', '11224', 'Fabricação de refrigerantes e de outras bebidas não alcoólicas', '112', 'Fabricação de bebidas não alcoólicas', '11', 'Fabricação De Bebidas', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1210700', 'Processamento industrial do fumo', '12107', 'Processamento industrial do fumo', '121', 'Processamento industrial do fumo', '12', 'Fabricação De Produtos Do Fumo', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1220403', 'Fabricação de filtros para cigarros', '12204', 'Fabricação de produtos do fumo', '122', 'Fabricação de produtos do fumo', '12', 'Fabricação De Produtos Do Fumo', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1220499', 'Fabricação de outros produtos do fumo, exceto cigarros, cigarrilhas e charutos', '12204', 'Fabricação de produtos do fumo', '122', 'Fabricação de produtos do fumo', '12', 'Fabricação De Produtos Do Fumo', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1220402', 'Fabricação de cigarrilhas e charutos', '12204', 'Fabricação de produtos do fumo', '122', 'Fabricação de produtos do fumo', '12', 'Fabricação De Produtos Do Fumo', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1220401', 'Fabricação de cigarros', '12204', 'Fabricação de produtos do fumo', '122', 'Fabricação de produtos do fumo', '12', 'Fabricação De Produtos Do Fumo', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1311100', 'Preparação e fiação de fibras de algodão', '13111', 'Preparação e fiação de fibras de algodão', '131', 'Preparação e fiação de fibras têxteis', '13', 'Fabricação De Produtos Têxteis', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1312000', 'Preparação e fiação de fibras têxteis naturais, exceto algodão', '13120', 'Preparação e fiação de fibras têxteis naturais, exceto algodão', '131', 'Preparação e fiação de fibras têxteis', '13', 'Fabricação De Produtos Têxteis', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1313800', 'Fiação de fibras artificiais e sintéticas', '13138', 'Fiação de fibras artificiais e sintéticas', '131', 'Preparação e fiação de fibras têxteis', '13', 'Fabricação De Produtos Têxteis', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1314600', 'Fabricação de linhas para costurar e bordar', '13146', 'Fabricação de linhas para costurar e bordar', '131', 'Preparação e fiação de fibras têxteis', '13', 'Fabricação De Produtos Têxteis', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1321900', 'Tecelagem de fios de algodão', '13219', 'Tecelagem de fios de algodão', '132', 'Tecelagem, exceto malha', '13', 'Fabricação De Produtos Têxteis', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1322700', 'Tecelagem de fios de fibras têxteis naturais, exceto algodão', '13227', 'Tecelagem de fios de fibras têxteis naturais, exceto algodão', '132', 'Tecelagem, exceto malha', '13', 'Fabricação De Produtos Têxteis', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1323500', 'Tecelagem de fios de fibras artificiais e sintéticas', '13235', 'Tecelagem de fios de fibras artificiais e sintéticas', '132', 'Tecelagem, exceto malha', '13', 'Fabricação De Produtos Têxteis', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1330800', 'Fabricação de tecidos de malha', '13308', 'Fabricação de tecidos de malha', '133', 'Fabricação de tecidos de malha', '13', 'Fabricação De Produtos Têxteis', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1340599', 'Outros serviços de acabamento em fios, tecidos, artefatos têxteis e peças do vestuário', '13405', 'Acabamentos em fios, tecidos e artefatos têxteis', '134', 'Acabamentos em fios, tecidos e artefatos têxteis', '13', 'Fabricação De Produtos Têxteis', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1340501', 'Estamparia e texturização em fios, tecidos, artefatos têxteis e peças do vestuário', '13405', 'Acabamentos em fios, tecidos e artefatos têxteis', '134', 'Acabamentos em fios, tecidos e artefatos têxteis', '13', 'Fabricação De Produtos Têxteis', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1340502', 'Alvejamento, tingimento e torção em fios, tecidos, artefatos têxteis e peças do vestuário', '13405', 'Acabamentos em fios, tecidos e artefatos têxteis', '134', 'Acabamentos em fios, tecidos e artefatos têxteis', '13', 'Fabricação De Produtos Têxteis', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1351100', 'Fabricação de artefatos têxteis para uso doméstico', '13511', 'Fabricação de artefatos têxteis para uso doméstico', '135', 'Fabricação de artefatos têxteis, exceto vestuário', '13', 'Fabricação De Produtos Têxteis', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1352900', 'Fabricação de artefatos de tapeçaria', '13529', 'Fabricação de artefatos de tapeçaria', '135', 'Fabricação de artefatos têxteis, exceto vestuário', '13', 'Fabricação De Produtos Têxteis', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1353700', 'Fabricação de artefatos de cordoaria', '13537', 'Fabricação de artefatos de cordoaria', '135', 'Fabricação de artefatos têxteis, exceto vestuário', '13', 'Fabricação De Produtos Têxteis', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1354500', 'Fabricação de tecidos especiais, inclusive artefatos', '13545', 'Fabricação de tecidos especiais, inclusive artefatos', '135', 'Fabricação de artefatos têxteis, exceto vestuário', '13', 'Fabricação De Produtos Têxteis', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1359600', 'Fabricação de outros produtos têxteis não especificados anteriormente', '13596', 'Fabricação de outros produtos têxteis não especificados anteriormente', '135', 'Fabricação de artefatos têxteis, exceto vestuário', '13', 'Fabricação De Produtos Têxteis', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1411802', 'Facção de roupas íntimas', '14118', 'Confecção de roupas íntimas', '141', 'Confecção de artigos do vestuário e acessórios', '14', 'Confecção De Artigos Do Vestuário E Acessórios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1411801', 'Confecção de roupas íntimas', '14118', 'Confecção de roupas íntimas', '141', 'Confecção de artigos do vestuário e acessórios', '14', 'Confecção De Artigos Do Vestuário E Acessórios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1412602', 'Confecção, sob medida, de peças do vestuário, exceto roupas íntimas', '14126', 'Confecção de peças do vestuário, exceto roupas íntimas', '141', 'Confecção de artigos do vestuário e acessórios', '14', 'Confecção De Artigos Do Vestuário E Acessórios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1412603', 'Facção de peças do vestuário, exceto roupas íntimas', '14126', 'Confecção de peças do vestuário, exceto roupas íntimas', '141', 'Confecção de artigos do vestuário e acessórios', '14', 'Confecção De Artigos Do Vestuário E Acessórios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1412601', 'Confecção de peças do vestuário, exceto roupas íntimas e as confeccionadas sob medida', '14126', 'Confecção de peças do vestuário, exceto roupas íntimas', '141', 'Confecção de artigos do vestuário e acessórios', '14', 'Confecção De Artigos Do Vestuário E Acessórios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1413401', 'Confecção de roupas profissionais, exceto sob medida', '14134', 'Confecção de roupas profissionais', '141', 'Confecção de artigos do vestuário e acessórios', '14', 'Confecção De Artigos Do Vestuário E Acessórios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1413403', 'Facção de roupas profissionais', '14134', 'Confecção de roupas profissionais', '141', 'Confecção de artigos do vestuário e acessórios', '14', 'Confecção De Artigos Do Vestuário E Acessórios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1413402', 'Confecção, sob medida, de roupas profissionais', '14134', 'Confecção de roupas profissionais', '141', 'Confecção de artigos do vestuário e acessórios', '14', 'Confecção De Artigos Do Vestuário E Acessórios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1414200', 'Fabricação de acessórios do vestuário, exceto para segurança e proteção', '14142', 'Fabricação de acessórios do vestuário, exceto para segurança e proteção', '141', 'Confecção de artigos do vestuário e acessórios', '14', 'Confecção De Artigos Do Vestuário E Acessórios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1421500', 'Fabricação de meias', '14215', 'Fabricação de meias', '142', 'Fabricação de artigos de malharia e tricotagem', '14', 'Confecção De Artigos Do Vestuário E Acessórios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1422300', 'Fabricação de artigos do vestuário, produzidos em malharias e tricotagens, exceto meias', '14223', 'Fabricação de artigos do vestuário, produzidos em malharias e tricotagens, exceto meias', '142', 'Fabricação de artigos de malharia e tricotagem', '14', 'Confecção De Artigos Do Vestuário E Acessórios', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1510600', 'Curtimento e outras preparações de couro', '15106', 'Curtimento e outras preparações de couro', '151', 'Curtimento e outras preparações de couro', '15', 'Preparação De Couros E Fabricação De Artefatos De Couro, Artigos Para Viagem E Calçados', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1521100', 'Fabricação de artigos para viagem, bolsas e semelhantes de qualquer material', '15211', 'Fabricação de artigos para viagem, bolsas e semelhantes de qualquer material', '152', 'Fabricação de artigos para viagem e de artefatos diversos de couro', '15', 'Preparação De Couros E Fabricação De Artefatos De Couro, Artigos Para Viagem E Calçados', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1529700', 'Fabricação de artefatos de couro não especificados anteriormente', '15297', 'Fabricação de artefatos de couro não especificados anteriormente', '152', 'Fabricação de artigos para viagem e de artefatos diversos de couro', '15', 'Preparação De Couros E Fabricação De Artefatos De Couro, Artigos Para Viagem E Calçados', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1531901', 'Fabricação de calçados de couro', '15319', 'Fabricação de calçados de couro', '153', 'Fabricação de calçados', '15', 'Preparação De Couros E Fabricação De Artefatos De Couro, Artigos Para Viagem E Calçados', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1531902', 'Acabamento de calçados de couro sob contrato', '15319', 'Fabricação de calçados de couro', '153', 'Fabricação de calçados', '15', 'Preparação De Couros E Fabricação De Artefatos De Couro, Artigos Para Viagem E Calçados', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1532700', 'Fabricação de tênis de qualquer material', '15327', 'Fabricação de tênis de qualquer material', '153', 'Fabricação de calçados', '15', 'Preparação De Couros E Fabricação De Artefatos De Couro, Artigos Para Viagem E Calçados', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1533500', 'Fabricação de calçados de material sintético', '15335', 'Fabricação de calçados de material sintético', '153', 'Fabricação de calçados', '15', 'Preparação De Couros E Fabricação De Artefatos De Couro, Artigos Para Viagem E Calçados', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1539400', 'Fabricação de calçados de materiais não especificados
anteriormente', '15394', 'Fabricação de calçados de materiais não especificados anteriormente', '153', 'Fabricação de calçados', '15', 'Preparação De Couros E Fabricação De Artefatos De Couro, Artigos Para Viagem E Calçados', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1540800', 'Fabricação de partes para calçados, de qualquer material', '15408', 'Fabricação de partes para calçados, de qualquer material', '154', 'Fabricação de partes para calçados, de qualquer material', '15', 'Preparação De Couros E Fabricação De Artefatos De Couro, Artigos Para Viagem E Calçados', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1610204', 'Serrarias sem desdobramento de madeira em bruto - Resseragem', '16102', 'Desdobramento de madeira', '161', 'Desdobramento de madeira', '16', 'Fabricação De Produtos De Madeira', 'C', 'Indústrias De Transformação', FALSE, FALSE, FALSE, TRUE),
('1610202', 'Serrarias sem desdobramento de madeira', '16102', 'Desdobramento de madeira', '161', 'Desdobramento de madeira', '16', 'Fabricação De Produtos De Madeira', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, FALSE),
('1610203', 'Serrarias com desdobramento de madeira em bruto', '16102', 'Desdobramento de madeira', '161', 'Desdobramento de madeira', '16', 'Fabricação De Produtos De Madeira', 'C', 'Indústrias De Transformação', FALSE, FALSE, FALSE, TRUE),
('1610205', 'Serviço de tratamento de madeira realizado sob contrato', '16102', 'Desdobramento de madeira', '161', 'Desdobramento de madeira', '16', 'Fabricação De Produtos De Madeira', 'C', 'Indústrias De Transformação', FALSE, FALSE, FALSE, TRUE),
('1610201', 'Serrarias com desdobramento de madeira', '16102', 'Desdobramento de madeira', '161', 'Desdobramento de madeira', '16', 'Fabricação De Produtos De Madeira', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, FALSE),
('1621800', 'Fabricação de madeira laminada e de chapas de madeira compensada, prensada e aglomerada', '16218', 'Fabricação de madeira laminada e de chapas de madeira compensada, prensada e aglomerada', '162', 'Fabricação de produtos de madeira, cortiça e material trançado, exceto móveis', '16', 'Fabricação De Produtos De Madeira', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1622601', 'Fabricação de casas de madeira pré-fabricadas', '16226', 'Fabricação de estruturas de madeira e de artigos de carpintaria para construção', '162', 'Fabricação de produtos de madeira, cortiça e material trançado, exceto móveis', '16', 'Fabricação De Produtos De Madeira', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1622699', 'Fabricação de outros artigos de carpintaria para construção', '16226', 'Fabricação de estruturas de madeira e de artigos de carpintaria para construção', '162', 'Fabricação de produtos de madeira, cortiça e material trançado, exceto móveis', '16', 'Fabricação De Produtos De Madeira', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1622602', 'Fabricação de esquadrias de madeira e de peças de madeira para instalações industriais e comerciais', '16226', 'Fabricação de estruturas de madeira e de artigos de carpintaria para construção', '162', 'Fabricação de produtos de madeira, cortiça e material trançado, exceto móveis', '16', 'Fabricação De Produtos De Madeira', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1623400', 'Fabricação de artefatos de tanoaria e de embalagens de madeira', '16234', 'Fabricação de artefatos de tanoaria e de embalagens de madeira', '162', 'Fabricação de produtos de madeira, cortiça e material trançado, exceto móveis', '16', 'Fabricação De Produtos De Madeira', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1629302', 'Fabricação de artefatos diversos de cortiça, bambu, palha, vime e outros materiais trançados, exceto móveis', '16293', 'Fabricação de artefatos de madeira, palha, cortiça, vime e material trançado não especificados anteriormente, exceto
móveis', '162', 'Fabricação de produtos de madeira, cortiça e material trançado, exceto móveis', '16', 'Fabricação De Produtos De Madeira', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1629301', 'Fabricação de artefatos diversos de madeira, exceto móveis', '16293', 'Fabricação de artefatos de madeira, palha, cortiça, vime e material trançado não especificados anteriormente, exceto
móveis', '162', 'Fabricação de produtos de madeira, cortiça e material trançado, exceto móveis', '16', 'Fabricação De Produtos De Madeira', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1710900', 'Fabricação de celulose e outras pastas para a fabricação de papel', '17109', 'Fabricação de celulose e outras pastas para a fabricação de papel', '171', 'Fabricação de celulose e outras pastas para a fabricação de papel', '17', 'Fabricação De Celulose, Papel E Produtos De Papel', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1721400', 'Fabricação de papel', '17214', 'Fabricação de papel', '172', 'Fabricação de papel, cartolina e papel-cartão', '17', 'Fabricação De Celulose, Papel E Produtos De Papel', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1722200', 'Fabricação de cartolina e papel-cartão', '17222', 'Fabricação de cartolina e papel-cartão', '172', 'Fabricação de papel, cartolina e papel-cartão', '17', 'Fabricação De Celulose, Papel E Produtos De Papel', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1731100', 'Fabricação de embalagens de papel', '17311', 'Fabricação de embalagens de papel', '173', 'Fabricação de embalagens de papel, cartolina, papel-cartão e papelão ondulado', '17', 'Fabricação De Celulose, Papel E Produtos De Papel', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1732000', 'Fabricação de embalagens de cartolina e papel-cartão', '17320', 'Fabricação de embalagens de cartolina e papel-cartão', '173', 'Fabricação de embalagens de papel, cartolina, papel-cartão e papelão ondulado', '17', 'Fabricação De Celulose, Papel E Produtos De Papel', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1733800', 'Fabricação de chapas e de embalagens de papelão ondulado', '17338', 'Fabricação de chapas e de embalagens de papelão ondulado', '173', 'Fabricação de embalagens de papel, cartolina, papel-cartão e papelão ondulado', '17', 'Fabricação De Celulose, Papel E Produtos De Papel', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1741902', 'Fabricação de produtos de papel, cartolina, papel-cartão e papelão ondulado para uso comercial e de escritório', '17419', 'Fabricação de produtos de papel, cartolina, papel-cartão e papelão ondulado para uso comercial e de escritório', '174', 'Fabricação de produtos diversos de papel, cartolina, papel-cartão e papelão ondulado', '17', 'Fabricação De Celulose, Papel E Produtos De Papel', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1741901', 'Fabricação de formulários contínuos', '17419', 'Fabricação de produtos de papel, cartolina, papel-cartão e papelão ondulado para uso comercial e de escritório', '174', 'Fabricação de produtos diversos de papel, cartolina, papel-cartão e papelão ondulado', '17', 'Fabricação De Celulose, Papel E Produtos De Papel', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1742702', 'Fabricação de absorventes higiênicos', '17427', 'Fabricação de produtos de papel para usos doméstico e higiênico-sanitário', '174', 'Fabricação de produtos diversos de papel, cartolina, papel-cartão e papelão ondulado', '17', 'Fabricação De Celulose, Papel E Produtos De Papel', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1742799', 'Fabricação de produtos de papel para uso doméstico e higiênico-sanitário não especificados anteriormente', '17427', 'Fabricação de produtos de papel para usos doméstico e higiênico-sanitário', '174', 'Fabricação de produtos diversos de papel, cartolina, papel-cartão e papelão ondulado', '17', 'Fabricação De Celulose, Papel E Produtos De Papel', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1742701', 'Fabricação de fraldas descartáveis', '17427', 'Fabricação de produtos de papel para usos doméstico e higiênico-sanitário', '174', 'Fabricação de produtos diversos de papel, cartolina, papel-cartão e papelão ondulado', '17', 'Fabricação De Celulose, Papel E Produtos De Papel', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1749400', 'Fabricação de produtos de pastas celulósicas, papel, cartolina, papel-cartão e papelão ondulado não especificados anteriormente', '17494', 'Fabricação de produtos de pastas celulósicas, papel, cartolina, papel-cartão e papelão ondulado não especificados anteriormente', '174', 'Fabricação de produtos diversos de papel, cartolina, papel-cartão e papelão ondulado', '17', 'Fabricação De Celulose, Papel E Produtos De Papel', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1811302', 'Impressão de livros, revistas e outras publicações periódicas', '18113', 'Impressão de jornais, livros, revistas e outras publicações periódicas', '181', 'Atividade de impressão', '18', 'Impressão E Reprodução De Gravações', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1811301', 'Impressão de jornais', '18113', 'Impressão de jornais, livros, revistas e outras publicações periódicas', '181', 'Atividade de impressão', '18', 'Impressão E Reprodução De Gravações', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1812100', 'Impressão de material de segurança', '18121', 'Impressão de material de segurança', '181', 'Atividade de impressão', '18', 'Impressão E Reprodução De Gravações', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1813001', 'Impressão de material para uso publicitário', '18130', 'Impressão de materiais para outros usos', '181', 'Atividade de impressão', '18', 'Impressão E Reprodução De Gravações', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1813099', 'Impressão de material para outros usos', '18130', 'Impressão de materiais para outros usos', '181', 'Atividade de impressão', '18', 'Impressão E Reprodução De Gravações', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1821100', 'Serviços de pré-impressão', '18211', 'Serviços de pré-impressão', '182', 'Serviços de pré-impressão e acabamentos gráficos', '18', 'Impressão E Reprodução De Gravações', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1822900', 'Serviços de acabamentos gráficos', '18229', 'Serviços de acabamentos gráficos', '182', 'Serviços de pré-impressão e acabamentos gráficos', '18', 'Impressão E Reprodução De Gravações', 'C', 'Indústrias De Transformação', TRUE, FALSE, FALSE, FALSE),
('1822999', 'Serviços de acabamentos gráficos, exceto encadernação e plastificação', '18229', 'Serviços de acabamentos gráficos', '182', 'Serviços de pré-impressão e acabamentos gráficos', '18', 'Impressão E Reprodução De Gravações', 'C', 'Indústrias De Transformação', FALSE, TRUE, TRUE, TRUE),
('1822901', 'Serviços de encadernação e plastificação', '18229', 'Serviços de acabamentos gráficos', '182', 'Serviços de pré-impressão e acabamentos gráficos', '18', 'Impressão E Reprodução De Gravações', 'C', 'Indústrias De Transformação', FALSE, TRUE, TRUE, TRUE),
('1830001', 'Reprodução de som em qualquer suporte', '18300', 'Reprodução de materiais gravados em qualquer suporte', '183', 'Reprodução de materiais gravados em qualquer suporte', '18', 'Impressão E Reprodução De Gravações', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1830002', 'Reprodução de vídeo em qualquer suporte', '18300', 'Reprodução de materiais gravados em qualquer suporte', '183', 'Reprodução de materiais gravados em qualquer suporte', '18', 'Impressão E Reprodução De Gravações', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1830003', 'Reprodução de software em qualquer suporte', '18300', 'Reprodução de materiais gravados em qualquer suporte', '183', 'Reprodução de materiais gravados em qualquer suporte', '18', 'Impressão E Reprodução De Gravações', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1910100', 'Coquerias', '19101', 'Coquerias', '191', 'Coquerias', '19', 'Fabricação De Coque, De Produtos Derivados Do Petróleo E De Biocombustíveis', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1921700', 'Fabricação de produtos do refino de petróleo', '19217', 'Fabricação de produtos do refino de petróleo', '192', 'Fabricação de produtos derivados do petróleo', '19', 'Fabricação De Coque, De Produtos Derivados Do Petróleo E De Biocombustíveis', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1922501', 'Formulação de combustíveis', '19225', 'Fabricação de produtos derivados do petróleo, exceto produtos do refino', '192', 'Fabricação de produtos derivados do petróleo', '19', 'Fabricação De Coque, De Produtos Derivados Do Petróleo E De Biocombustíveis', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1922502', 'Rerrefino de óleos lubrificantes', '19225', 'Fabricação de produtos derivados do petróleo, exceto produtos do refino', '192', 'Fabricação de produtos derivados do petróleo', '19', 'Fabricação De Coque, De Produtos Derivados Do Petróleo E De Biocombustíveis', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1922599', 'Fabricação de outros produtos derivados do petróleo, exceto produtos do refino', '19225', 'Fabricação de produtos derivados do petróleo, exceto produtos do refino', '192', 'Fabricação de produtos derivados do petróleo', '19', 'Fabricação De Coque, De Produtos Derivados Do Petróleo E De Biocombustíveis', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1931400', 'Fabricação de álcool', '19314', 'Fabricação de álcool', '193', 'Fabricação de biocombustíveis', '19', 'Fabricação De Coque, De Produtos Derivados Do Petróleo E De Biocombustíveis', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('1932200', 'Fabricação de biocombustíveis, exceto álcool', '19322', 'Fabricação de biocombustíveis, exceto álcool', '193', 'Fabricação de biocombustíveis', '19', 'Fabricação De Coque, De Produtos Derivados Do Petróleo E De Biocombustíveis', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2011800', 'Fabricação de cloro e álcalis', '20118', 'Fabricação de cloro e álcalis', '201', 'Fabricação de produtos químicos inorgânicos', '20', 'Fabricação De Produtos Químicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2012600', 'Fabricação de intermediários para fertilizantes', '20126', 'Fabricação de intermediários para fertilizantes', '201', 'Fabricação de produtos químicos inorgânicos', '20', 'Fabricação De Produtos Químicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2013400', 'Fabricação de adubos e fertilizantes', '20134', 'Fabricação de adubos e fertilizantes', '201', 'Fabricação de produtos químicos inorgânicos', '20', 'Fabricação De Produtos Químicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, FALSE, FALSE),
('2013402', 'Fabricação de adubos e fertilizantes, exceto organo-minerais', '20134', 'Fabricação de adubos e fertilizantes', '201', 'Fabricação de produtos químicos inorgânicos', '20', 'Fabricação De Produtos Químicos', 'C', 'Indústrias De Transformação', FALSE, FALSE, TRUE, TRUE),
('2013401', 'Fabricação de adubos e fertilizantes organo-minerais', '20134', 'Fabricação de adubos e fertilizantes', '201', 'Fabricação de produtos químicos inorgânicos', '20', 'Fabricação De Produtos Químicos', 'C', 'Indústrias De Transformação', FALSE, FALSE, TRUE, TRUE),
('2014200', 'Fabricação de gases industriais', '20142', 'Fabricação de gases industriais', '201', 'Fabricação de produtos químicos inorgânicos', '20', 'Fabricação De Produtos Químicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2019399', 'Fabricação de outros produtos químicos inorgânicos não especificados anteriormente', '20193', 'Fabricação de produtos químicos inorgânicos não especificados anteriormente', '201', 'Fabricação de produtos químicos inorgânicos', '20', 'Fabricação De Produtos Químicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2019301', 'Elaboração de combustíveis nucleares', '20193', 'Fabricação de produtos químicos inorgânicos não especificados anteriormente', '201', 'Fabricação de produtos químicos inorgânicos', '20', 'Fabricação De Produtos Químicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2021500', 'Fabricação de produtos petroquímicos básicos', '20215', 'Fabricação de produtos petroquímicos básicos', '202', 'Fabricação de produtos químicos orgânicos', '20', 'Fabricação De Produtos Químicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2022300', 'Fabricação de intermediários para plastificantes, resinas e fibras', '20223', 'Fabricação de intermediários para plastificantes, resinas e fibras', '202', 'Fabricação de produtos químicos orgânicos', '20', 'Fabricação De Produtos Químicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2029100', 'Fabricação de produtos químicos orgânicos não especificados anteriormente', '20291', 'Fabricação de produtos químicos orgânicos não especificados anteriormente', '202', 'Fabricação de produtos químicos orgânicos', '20', 'Fabricação De Produtos Químicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2031200', 'Fabricação de resinas termoplásticas', '20312', 'Fabricação de resinas termoplásticas', '203', 'Fabricação de resinas e elastômeros', '20', 'Fabricação De Produtos Químicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2032100', 'Fabricação de resinas termofixas', '20321', 'Fabricação de resinas termofixas', '203', 'Fabricação de resinas e elastômeros', '20', 'Fabricação De Produtos Químicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2033900', 'Fabricação de elastômeros', '20339', 'Fabricação de elastômeros', '203', 'Fabricação de resinas e elastômeros', '20', 'Fabricação De Produtos Químicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2040100', 'Fabricação de fibras artificiais e sintéticas', '20401', 'Fabricação de fibras artificiais e sintéticas', '204', 'Fabricação de fibras artificiais e sintéticas', '20', 'Fabricação De Produtos Químicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2051700', 'Fabricação de defensivos agrícolas', '20517', 'Fabricação de defensivos agrícolas', '205', 'Fabricação de defensivos agrícolas e desinfestantes domissanitários', '20', 'Fabricação De Produtos Químicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2052500', 'Fabricação de desinfestantes domissanitários', '20525', 'Fabricação de desinfestantes domissanitários', '205', 'Fabricação de defensivos agrícolas e desinfestantes domissanitários', '20', 'Fabricação De Produtos Químicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2061400', 'Fabricação de sabões e detergentes sintéticos', '20614', 'Fabricação de sabões e detergentes sintéticos', '206', 'Fabricação de sabões, detergentes, produtos de limpeza, cosméticos, produtos de perfumaria e de higiene pessoal', '20', 'Fabricação De Produtos Químicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2062200', 'Fabricação de produtos de limpeza e polimento', '20622', 'Fabricação de produtos de limpeza e polimento', '206', 'Fabricação de sabões, detergentes, produtos de limpeza, cosméticos, produtos de perfumaria e de higiene pessoal', '20', 'Fabricação De Produtos Químicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2063100', 'Fabricação de cosméticos, produtos de perfumaria e de higiene pessoal', '20631', 'Fabricação de cosméticos, produtos de perfumaria e de higiene pessoal', '206', 'Fabricação de sabões, detergentes, produtos de limpeza, cosméticos, produtos de perfumaria e de higiene pessoal', '20', 'Fabricação De Produtos Químicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2071100', 'Fabricação de tintas, vernizes, esmaltes e lacas', '20711', 'Fabricação de tintas, vernizes, esmaltes e lacas', '207', 'Fabricação de tintas, vernizes, esmaltes, lacas e produtos afins', '20', 'Fabricação De Produtos Químicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2072000', 'Fabricação de tintas de impressão', '20720', 'Fabricação de tintas de impressão', '207', 'Fabricação de tintas, vernizes, esmaltes, lacas e produtos afins', '20', 'Fabricação De Produtos Químicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2073800', 'Fabricação de impermeabilizantes, solventes e produtos afins', '20738', 'Fabricação de impermeabilizantes, solventes e produtos afins', '207', 'Fabricação de tintas, vernizes, esmaltes, lacas e produtos afins', '20', 'Fabricação De Produtos Químicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2091600', 'Fabricação de adesivos e selantes', '20916', 'Fabricação de adesivos e selantes', '209', 'Fabricação de produtos e preparados químicos diversos', '20', 'Fabricação De Produtos Químicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2092401', 'Fabricação de pólvoras, explosivos e detonantes', '20924', 'Fabricação de explosivos', '209', 'Fabricação de produtos e preparados químicos diversos', '20', 'Fabricação De Produtos Químicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2092402', 'Fabricação de artigos pirotécnicos', '20924', 'Fabricação de explosivos', '209', 'Fabricação de produtos e preparados químicos diversos', '20', 'Fabricação De Produtos Químicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2092403', 'Fabricação de fósforos de segurança', '20924', 'Fabricação de explosivos', '209', 'Fabricação de produtos e preparados químicos diversos', '20', 'Fabricação De Produtos Químicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2093200', 'Fabricação de aditivos de uso industrial', '20932', 'Fabricação de aditivos de uso industrial', '209', 'Fabricação de produtos e preparados químicos diversos', '20', 'Fabricação De Produtos Químicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2094100', 'Fabricação de catalisadores', '20941', 'Fabricação de catalisadores', '209', 'Fabricação de produtos e preparados químicos diversos', '20', 'Fabricação De Produtos Químicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2099199', 'Fabricação de outros produtos químicos não especificados anteriormente', '20991', 'Fabricação de produtos químicos não especificados anteriormente', '209', 'Fabricação de produtos e preparados químicos diversos', '20', 'Fabricação De Produtos Químicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2099101', 'Fabricação de chapas, filmes, papéis e outros materiais e produtos químicos para fotografia', '20991', 'Fabricação de produtos químicos não especificados anteriormente', '209', 'Fabricação de produtos e preparados químicos diversos', '20', 'Fabricação De Produtos Químicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2110600', 'Fabricação de produtos farmoquímicos', '21106', 'Fabricação de produtos farmoquímicos', '211', 'Fabricação de produtos farmoquímicos', '21', 'Fabricação De Produtos Farmoquímicos E Farmacêuticos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2121103', 'Fabricação de medicamentos fitoterápicos para uso humano', '21211', 'Fabricação de medicamentos para uso humano', '212', 'Fabricação de produtos farmacêuticos', '21', 'Fabricação De Produtos Farmoquímicos E Farmacêuticos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2121102', 'Fabricação de medicamentos homeopáticos para uso humano', '21211', 'Fabricação de medicamentos para uso humano', '212', 'Fabricação de produtos farmacêuticos', '21', 'Fabricação De Produtos Farmoquímicos E Farmacêuticos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2121101', 'Fabricação de medicamentos alopáticos para uso humano', '21211', 'Fabricação de medicamentos para uso humano', '212', 'Fabricação de produtos farmacêuticos', '21', 'Fabricação De Produtos Farmoquímicos E Farmacêuticos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2122000', 'Fabricação de medicamentos para uso veterinário', '21220', 'Fabricação de medicamentos para uso veterinário', '212', 'Fabricação de produtos farmacêuticos', '21', 'Fabricação De Produtos Farmoquímicos E Farmacêuticos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2123800', 'Fabricação de preparações farmacêuticas', '21238', 'Fabricação de preparações farmacêuticas', '212', 'Fabricação de produtos farmacêuticos', '21', 'Fabricação De Produtos Farmoquímicos E Farmacêuticos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2211100', 'Fabricação de pneumáticos e de câmaras-de-ar', '22111', 'Fabricação de pneumáticos e de câmaras-de-ar', '221', 'Fabricação de produtos de borracha', '22', 'Fabricação De Produtos De Borracha E De Material Plástico', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2212900', 'Reforma de pneumáticos usados', '22129', 'Reforma de pneumáticos usados', '221', 'Fabricação de produtos de borracha', '22', 'Fabricação De Produtos De Borracha E De Material Plástico', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2219600', 'Fabricação de artefatos de borracha não especificados
anteriormente', '22196', 'Fabricação de artefatos de borracha não especificados anteriormente', '221', 'Fabricação de produtos de borracha', '22', 'Fabricação De Produtos De Borracha E De Material Plástico', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2221800', 'Fabricação de laminados planos e tubulares de material plástico', '22218', 'Fabricação de laminados planos e tubulares de material plástico', '222', 'Fabricação de produtos de material plástico', '22', 'Fabricação De Produtos De Borracha E De Material Plástico', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2222600', 'Fabricação de embalagens de material plástico', '22226', 'Fabricação de embalagens de material plástico', '222', 'Fabricação de produtos de material plástico', '22', 'Fabricação De Produtos De Borracha E De Material Plástico', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2223400', 'Fabricação de tubos e acessórios de material plástico para uso na construção', '22234', 'Fabricação de tubos e acessórios de material plástico para uso na construção', '222', 'Fabricação de produtos de material plástico', '22', 'Fabricação De Produtos De Borracha E De Material Plástico', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2229302', 'Fabricação de artefatos de material plástico para usos industriais', '22293', 'Fabricação de artefatos de material plástico não especificados anteriormente', '222', 'Fabricação de produtos de material plástico', '22', 'Fabricação De Produtos De Borracha E De Material Plástico', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2229301', 'Fabricação de artefatos de material plástico para uso pessoal e doméstico', '22293', 'Fabricação de artefatos de material plástico não especificados anteriormente', '222', 'Fabricação de produtos de material plástico', '22', 'Fabricação De Produtos De Borracha E De Material Plástico', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2229399', 'Fabricação de artefatos de material plástico para outros usos não especificados anteriormente', '22293', 'Fabricação de artefatos de material plástico não especificados anteriormente', '222', 'Fabricação de produtos de material plástico', '22', 'Fabricação De Produtos De Borracha E De Material Plástico', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2229303', 'Fabricação de artefatos de material plástico para uso na construção, exceto tubos e acessórios', '22293', 'Fabricação de artefatos de material plástico não especificados anteriormente', '222', 'Fabricação de produtos de material plástico', '22', 'Fabricação De Produtos De Borracha E De Material Plástico', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2311700', 'Fabricação de vidro plano e de segurança', '23117', 'Fabricação de vidro plano e de segurança', '231', 'Fabricação de vidro e de produtos do vidro', '23', 'Fabricação De Produtos De Minerais Não Metálicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2312500', 'Fabricação de embalagens de vidro', '23125', 'Fabricação de embalagens de vidro', '231', 'Fabricação de vidro e de produtos do vidro', '23', 'Fabricação De Produtos De Minerais Não Metálicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2319200', 'Fabricação de artigos de vidro', '23192', 'Fabricação de artigos de vidro', '231', 'Fabricação de vidro e de produtos do vidro', '23', 'Fabricação De Produtos De Minerais Não Metálicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2320600', 'Fabricação de cimento', '23206', 'Fabricação de cimento', '232', 'Fabricação de cimento', '23', 'Fabricação De Produtos De Minerais Não Metálicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2330399', 'Fabricação de outros artefatos e produtos de concreto, cimento, fibrocimento, gesso e materiais semelhantes', '23303', 'Fabricação de artefatos de concreto, cimento, fibrocimento, gesso e materiais semelhantes', '233', 'Fabricação de artefatos de concreto, cimento, fibrocimento, gesso e materiais semelhantes', '23', 'Fabricação De Produtos De Minerais Não Metálicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2330305', 'Preparação de massa de concreto e argamassa para construção', '23303', 'Fabricação de artefatos de concreto, cimento, fibrocimento, gesso e materiais semelhantes', '233', 'Fabricação de artefatos de concreto, cimento, fibrocimento, gesso e materiais semelhantes', '23', 'Fabricação De Produtos De Minerais Não Metálicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2330302', 'Fabricação de artefatos de cimento para uso na construção', '23303', 'Fabricação de artefatos de concreto, cimento, fibrocimento, gesso e materiais semelhantes', '233', 'Fabricação de artefatos de concreto, cimento, fibrocimento, gesso e materiais semelhantes', '23', 'Fabricação De Produtos De Minerais Não Metálicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2330301', 'Fabricação de estruturas pré-moldadas de concreto armado, em série e sob encomenda', '23303', 'Fabricação de artefatos de concreto, cimento, fibrocimento, gesso e materiais semelhantes', '233', 'Fabricação de artefatos de concreto, cimento, fibrocimento, gesso e materiais semelhantes', '23', 'Fabricação De Produtos De Minerais Não Metálicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2330303', 'Fabricação de artefatos de fibrocimento para uso na construção', '23303', 'Fabricação de artefatos de concreto, cimento, fibrocimento, gesso e materiais semelhantes', '233', 'Fabricação de artefatos de concreto, cimento, fibrocimento, gesso e materiais semelhantes', '23', 'Fabricação De Produtos De Minerais Não Metálicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2330304', 'Fabricação de casas pré-moldadas de concreto', '23303', 'Fabricação de artefatos de concreto, cimento, fibrocimento, gesso e materiais semelhantes', '233', 'Fabricação de artefatos de concreto, cimento, fibrocimento, gesso e materiais semelhantes', '23', 'Fabricação De Produtos De Minerais Não Metálicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2341900', 'Fabricação de produtos cerâmicos refratários', '23419', 'Fabricação de produtos cerâmicos refratários', '234', 'Fabricação de produtos cerâmicos', '23', 'Fabricação De Produtos De Minerais Não Metálicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2342701', 'Fabricação de azulejos e pisos', '23427', 'Fabricação de produtos cerâmicos não refratários para uso estrutural na construção', '234', 'Fabricação de produtos cerâmicos', '23', 'Fabricação De Produtos De Minerais Não Metálicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2342702', 'Fabricação de artefatos de cerâmica e barro cozido para uso na construção, exceto azulejos e pisos', '23427', 'Fabricação de produtos cerâmicos não refratários para uso estrutural na construção', '234', 'Fabricação de produtos cerâmicos', '23', 'Fabricação De Produtos De Minerais Não Metálicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2349401', 'Fabricação de material sanitário de cerâmica', '23494', 'Fabricação de produtos cerâmicos não refratários não especificados anteriormente', '234', 'Fabricação de produtos cerâmicos', '23', 'Fabricação De Produtos De Minerais Não Metálicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2349499', 'Fabricação de produtos cerâmicos não refratários não especificados anteriormente', '23494', 'Fabricação de produtos cerâmicos não refratários não especificados anteriormente', '234', 'Fabricação de produtos cerâmicos', '23', 'Fabricação De Produtos De Minerais Não Metálicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2391502', 'Aparelhamento de pedras para construção, exceto associado à extração', '23915', 'Aparelhamento e outros trabalhos em pedras', '239', 'Aparelhamento de pedras e fabricação de outros produtos de minerais não metálicos', '23', 'Fabricação De Produtos De Minerais Não Metálicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2391503', 'Aparelhamento de placas e execução de trabalhos em mármore, granito, ardósia e outras pedras', '23915', 'Aparelhamento e outros trabalhos em pedras', '239', 'Aparelhamento de pedras e fabricação de outros produtos de minerais não metálicos', '23', 'Fabricação De Produtos De Minerais Não Metálicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2391501', 'Britamento de pedras, exceto associado à extração', '23915', 'Aparelhamento e outros trabalhos em pedras', '239', 'Aparelhamento de pedras e fabricação de outros produtos de minerais não metálicos', '23', 'Fabricação De Produtos De Minerais Não Metálicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2392300', 'Fabricação de cal e gesso', '23923', 'Fabricação de cal e gesso', '239', 'Aparelhamento de pedras e fabricação de outros produtos de minerais não metálicos', '23', 'Fabricação De Produtos De Minerais Não Metálicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2399101', 'Decoração, lapidação, gravação, vitrificação e outros trabalhos em cerâmica, louça, vidro e cristal', '23991', 'Fabricação de produtos de minerais não metálicos não especificados anteriormente', '239', 'Aparelhamento de pedras e fabricação de outros produtos de minerais não metálicos', '23', 'Fabricação De Produtos De Minerais Não Metálicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2399199', 'Fabricação de outros produtos de minerais não metálicos não especificados anteriormente', '23991', 'Fabricação de produtos de minerais não metálicos não especificados anteriormente', '239', 'Aparelhamento de pedras e fabricação de outros produtos de minerais não metálicos', '23', 'Fabricação De Produtos De Minerais Não Metálicos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2399102', 'Fabricação de abrasivos', '23991', 'Fabricação de produtos de minerais não metálicos não especificados anteriormente', '239', 'Aparelhamento de pedras e fabricação de outros produtos de minerais não metálicos', '23', 'Fabricação De Produtos De Minerais Não Metálicos', 'C', 'Indústrias De Transformação', FALSE, TRUE, TRUE, TRUE),
('2411300', 'Produção de ferro-gusa', '24113', 'Produção de ferro-gusa', '241', 'Produção de ferro-gusa e de ferroligas', '24', 'Metalurgia', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2412100', 'Produção de ferroligas', '24121', 'Produção de ferroligas', '241', 'Produção de ferro-gusa e de ferroligas', '24', 'Metalurgia', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2421100', 'Produção de semiacabados de aço', '24211', 'Produção de semiacabados de aço', '242', 'Siderurgia', '24', 'Metalurgia', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2422901', 'Produção de laminados planos de aço ao carbono, revestidos ou não', '24229', 'Produção de laminados planos de aço', '242', 'Siderurgia', '24', 'Metalurgia', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2422902', 'Produção de laminados planos de aços especiais', '24229', 'Produção de laminados planos de aço', '242', 'Siderurgia', '24', 'Metalurgia', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2423701', 'Produção de tubos de aço sem costura', '24237', 'Produção de laminados longos de aço', '242', 'Siderurgia', '24', 'Metalurgia', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2423702', 'Produção de laminados longos de aço, exceto tubos', '24237', 'Produção de laminados longos de aço', '242', 'Siderurgia', '24', 'Metalurgia', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2424501', 'Produção de arames de aço', '24245', 'Produção de relaminados, trefilados e perfilados de aço', '242', 'Siderurgia', '24', 'Metalurgia', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2424502', 'Produção de relaminados, trefilados e perfilados de aço, exceto arames', '24245', 'Produção de relaminados, trefilados e perfilados de aço', '242', 'Siderurgia', '24', 'Metalurgia', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2431800', 'Produção de tubos de aço com costura', '24318', 'Produção de tubos de aço com costura', '243', 'Produção de tubos de aço, exceto tubos sem costura', '24', 'Metalurgia', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2439300', 'Produção de outros tubos de ferro e aço', '24393', 'Produção de outros tubos de ferro e aço', '243', 'Produção de tubos de aço, exceto tubos sem costura', '24', 'Metalurgia', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2441502', 'Produção de laminados de alumínio', '24415', 'Metalurgia do alumínio e suas ligas', '244', 'Metalurgia dos metais não ferrosos', '24', 'Metalurgia', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2441501', 'Produção de alumínio e suas ligas em formas primárias', '24415', 'Metalurgia do alumínio e suas ligas', '244', 'Metalurgia dos metais não ferrosos', '24', 'Metalurgia', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2442300', 'Metalurgia dos metais preciosos', '24423', 'Metalurgia dos metais preciosos', '244', 'Metalurgia dos metais não ferrosos', '24', 'Metalurgia', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2443100', 'Metalurgia do cobre', '24431', 'Metalurgia do cobre', '244', 'Metalurgia dos metais não ferrosos', '24', 'Metalurgia', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2449103', 'Fabricação de ânodos para galvanoplastia', '24491', 'Metalurgia dos metais não ferrosos e suas ligas não especificados anteriormente', '244', 'Metalurgia dos metais não ferrosos', '24', 'Metalurgia', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2449199', 'Metalurgia de outros metais não ferrosos e suas ligas não especificados anteriormente', '24491', 'Metalurgia dos metais não ferrosos e suas ligas não especificados anteriormente', '244', 'Metalurgia dos metais não ferrosos', '24', 'Metalurgia', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2449102', 'Produção de laminados de zinco', '24491', 'Metalurgia dos metais não ferrosos e suas ligas não especificados anteriormente', '244', 'Metalurgia dos metais não ferrosos', '24', 'Metalurgia', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2449101', 'Produção de zinco em formas primárias', '24491', 'Metalurgia dos metais não ferrosos e suas ligas não especificados anteriormente', '244', 'Metalurgia dos metais não ferrosos', '24', 'Metalurgia', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2451200', 'Fundição de ferro e aço', '24512', 'Fundição de ferro e aço', '245', 'Fundição', '24', 'Metalurgia', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2452100', 'Fundição de metais não ferrosos e suas ligas', '24521', 'Fundição de metais não ferrosos e suas ligas', '245', 'Fundição', '24', 'Metalurgia', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2511000', 'Fabricação de estruturas metálicas', '25110', 'Fabricação de estruturas metálicas', '251', 'Fabricação de estruturas metálicas e obras de caldeiraria
pesada', '25', 'Fabricação De Produtos De Metal, Exceto Máquinas
E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2512800', 'Fabricação de esquadrias de metal', '25128', 'Fabricação de esquadrias de metal', '251', 'Fabricação de estruturas metálicas e obras de caldeiraria
pesada', '25', 'Fabricação De Produtos De Metal, Exceto Máquinas
E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2513600', 'Fabricação de obras de caldeiraria pesada', '25136', 'Fabricação de obras de caldeiraria pesada', '251', 'Fabricação de estruturas metálicas e obras de caldeiraria
pesada', '25', 'Fabricação De Produtos De Metal, Exceto Máquinas
E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2521700', 'Fabricação de tanques, reservatórios metálicos e caldeiras para aquecimento central', '25217', 'Fabricação de tanques, reservatórios metálicos e caldeiras para aquecimento central', '252', 'Fabricação de tanques, reservatórios metálicos e caldeiras', '25', 'Fabricação De Produtos De Metal, Exceto Máquinas
E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2522500', 'Fabricação de caldeiras geradoras de vapor, exceto para aquecimento central e para veículos', '25225', 'Fabricação de caldeiras geradoras de vapor, exceto para aquecimento central e para veículos', '252', 'Fabricação de tanques, reservatórios metálicos e caldeiras', '25', 'Fabricação De Produtos De Metal, Exceto Máquinas
E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2531402', 'Produção de forjados de metais não ferrosos e suas ligas', '25314', 'Produção de forjados de aço e de metais não ferrosos e suas ligas', '253', 'Forjaria, estamparia, metalurgia do pó e serviços de tratamento de metais', '25', 'Fabricação De Produtos De Metal, Exceto Máquinas
E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2531401', 'Produção de forjados de aço', '25314', 'Produção de forjados de aço e de metais não ferrosos e suas ligas', '253', 'Forjaria, estamparia, metalurgia do pó e serviços de tratamento de metais', '25', 'Fabricação De Produtos De Metal, Exceto Máquinas
E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2532202', 'Metalurgia do pó', '25322', 'Produção de artefatos estampados de metal; metalurgia do pó', '253', 'Forjaria, estamparia, metalurgia do pó e serviços de tratamento de metais', '25', 'Fabricação De Produtos De Metal, Exceto Máquinas
E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2532201', 'Produção de artefatos estampados de metal', '25322', 'Produção de artefatos estampados de metal; metalurgia do pó', '253', 'Forjaria, estamparia, metalurgia do pó e serviços de tratamento de metais', '25', 'Fabricação De Produtos De Metal, Exceto Máquinas
E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2539000', 'Serviços de usinagem, solda, tratamento e revestimento em metais', '25390', 'Serviços de usinagem, solda, tratamento e revestimento em metais', '253', 'Forjaria, estamparia, metalurgia do pó e serviços de tratamento de metais', '25', 'Fabricação De Produtos De Metal, Exceto Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, FALSE, FALSE, FALSE),
('2539001', 'Serviços de usinagem, torneiria e solda', '25390', 'Serviços de usinagem, solda, tratamento e revestimento em metais', '253', 'Forjaria, estamparia, metalurgia do pó e serviços de tratamento de metais', '25', 'Fabricação De Produtos De Metal, Exceto Máquinas
E Equipamentos', 'C', 'Indústrias De Transformação', FALSE, TRUE, FALSE, TRUE),
('2539002', 'Serviços de tratamento e revestimento em metais', '25390', 'Serviços de usinagem, solda, tratamento e revestimento em metais', '253', 'Forjaria, estamparia, metalurgia do pó e serviços de tratamento de metais', '25', 'Fabricação De Produtos De Metal, Exceto Máquinas
E Equipamentos', 'C', 'Indústrias De Transformação', FALSE, TRUE, FALSE, TRUE),
('2541100', 'Fabricação de artigos de cutelaria', '25411', 'Fabricação de artigos de cutelaria', '254', 'Fabricação de artigos de cutelaria, de serralheria e ferramentas', '25', 'Fabricação De Produtos De Metal, Exceto Máquinas
E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2542000', 'Fabricação de artigos de serralheria, exceto esquadrias', '25420', 'Fabricação de artigos de serralheria, exceto esquadrias', '254', 'Fabricação de artigos de cutelaria, de serralheria e ferramentas', '25', 'Fabricação De Produtos De Metal, Exceto Máquinas
E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2543800', 'Fabricação de ferramentas', '25438', 'Fabricação de ferramentas', '254', 'Fabricação de artigos de cutelaria, de serralheria e ferramentas', '25', 'Fabricação De Produtos De Metal, Exceto Máquinas
E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2550101', 'Fabricação de equipamento bélico pesado, exceto veículos militares de combate', '25501', 'Fabricação de equipamento bélico pesado, armas de fogo e munições', '255', 'Fabricação de equipamento bélico pesado, armas e munições', '25', 'Fabricação De Produtos De Metal, Exceto Máquinas
E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2550102', 'Fabricação de armas de fogo, outras armas e munições', '25501', 'Fabricação de equipamento bélico pesado, armas de fogo e munições', '255', 'Fabricação de equipamento bélico pesado, armas e munições', '25', 'Fabricação De Produtos De Metal, Exceto Máquinas
E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2591800', 'Fabricação de embalagens metálicas', '25918', 'Fabricação de embalagens metálicas', '259', 'Fabricação de produtos de metal não especificados anteriormente', '25', 'Fabricação De Produtos De Metal, Exceto Máquinas
E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2592602', 'Fabricação de produtos de trefilados de metal, exceto padronizados', '25926', 'Fabricação de produtos de trefilados de metal', '259', 'Fabricação de produtos de metal não especificados anteriormente', '25', 'Fabricação De Produtos De Metal, Exceto Máquinas
E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2592601', 'Fabricação de produtos de trefilados de metal padronizados', '25926', 'Fabricação de produtos de trefilados de metal', '259', 'Fabricação de produtos de metal não especificados anteriormente', '25', 'Fabricação De Produtos De Metal, Exceto Máquinas
E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2593400', 'Fabricação de artigos de metal para uso doméstico e pessoal', '25934', 'Fabricação de artigos de metal para uso doméstico e pessoal', '259', 'Fabricação de produtos de metal não especificados anteriormente', '25', 'Fabricação De Produtos De Metal, Exceto Máquinas
E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2599399', 'Fabricação de outros produtos de metal não especificados anteriormente', '25993', 'Fabricação de produtos de metal não especificados anteriormente', '259', 'Fabricação de produtos de metal não especificados anteriormente', '25', 'Fabricação De Produtos De Metal, Exceto Máquinas
E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2599302', 'Serviço de corte e dobra de metais', '25993', 'Fabricação de produtos de metal não especificados anteriormente', '259', 'Fabricação de produtos de metal não especificados anteriormente', '25', 'Fabricação De Produtos De Metal, Exceto Máquinas
E Equipamentos', 'C', 'Indústrias De Transformação', FALSE, TRUE, TRUE, TRUE),
('2599301', 'Serviços de confecção de armações metálicas para a construção', '25993', 'Fabricação de produtos de metal não especificados anteriormente', '259', 'Fabricação de produtos de metal não especificados anteriormente', '25', 'Fabricação De Produtos De Metal, Exceto Máquinas
E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2610800', 'Fabricação de componentes eletrônicos', '26108', 'Fabricação de componentes eletrônicos', '261', 'Fabricação de componentes eletrônicos', '26', 'Fabricação De Equipamentos De Informática, Produtos Eletrônicos E Ópticos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2621300', 'Fabricação de equipamentos de informática', '26213', 'Fabricação de equipamentos de informática', '262', 'Fabricação de equipamentos de informática e periféricos', '26', 'Fabricação De Equipamentos De Informática, Produtos Eletrônicos E Ópticos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2622100', 'Fabricação de periféricos para equipamentos de informática', '26221', 'Fabricação de periféricos para equipamentos de informática', '262', 'Fabricação de equipamentos de informática e periféricos', '26', 'Fabricação De Equipamentos De Informática, Produtos Eletrônicos E Ópticos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2631100', 'Fabricação de equipamentos transmissores de comunicação, peças e acessórios', '26311', 'Fabricação de equipamentos transmissores de comunicação', '263', 'Fabricação de equipamentos de comunicação', '26', 'Fabricação De Equipamentos De Informática, Produtos Eletrônicos E Ópticos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2632900', 'Fabricação de aparelhos telefônicos e de outros equipamentos de comunicação, peças e acessórios', '26329', 'Fabricação de aparelhos telefônicos e de outros equipamentos de comunicação', '263', 'Fabricação de equipamentos de comunicação', '26', 'Fabricação De Equipamentos De Informática, Produtos Eletrônicos E Ópticos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2640000', 'Fabricação de aparelhos de recepção, reprodução, gravação e amplificação de áudio e vídeo', '26400', 'Fabricação de aparelhos de recepção, reprodução, gravação e amplificação de áudio e vídeo', '264', 'Fabricação de aparelhos de recepção, reprodução, gravação e amplificação de áudio e vídeo', '26', 'Fabricação De Equipamentos De Informática, Produtos Eletrônicos E Ópticos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2651500', 'Fabricação de aparelhos e equipamentos de medida, teste e controle', '26515', 'Fabricação de aparelhos e equipamentos de medida, teste e
controle', '265', 'Fabricação de aparelhos e instrumentos de medida, teste e controle; cronômetros e relógios', '26', 'Fabricação De Equipamentos De Informática, Produtos Eletrônicos E Ópticos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2652300', 'Fabricação de cronômetros e relógios', '26523', 'Fabricação de cronômetros e relógios', '265', 'Fabricação de aparelhos e instrumentos de medida, teste e controle; cronômetros e relógios', '26', 'Fabricação De Equipamentos De Informática, Produtos Eletrônicos E Ópticos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2660400', 'Fabricação de aparelhos eletromédicos e eletroterapêuticos e equipamentos de irradiação', '26604', 'Fabricação de aparelhos eletromédicos e eletroterapêuticos e equipamentos de irradiação', '266', 'Fabricação de aparelhos eletromédicos e eletroterapêuticos e equipamentos de irradiação', '26', 'Fabricação De Equipamentos De Informática, Produtos Eletrônicos E Ópticos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2670101', 'Fabricação de equipamentos e instrumentos ópticos, peças e acessórios', '26701', 'Fabricação de equipamentos e instrumentos ópticos, fotográficos e cinematográficos', '267', 'Fabricação de equipamentos e instrumentos ópticos, fotográficos e cinematográficos', '26', 'Fabricação De Equipamentos De Informática, Produtos Eletrônicos E Ópticos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2670102', 'Fabricação de aparelhos fotográficos e cinematográficos, peças e acessórios', '26701', 'Fabricação de equipamentos e instrumentos ópticos, fotográficos e cinematográficos', '267', 'Fabricação de equipamentos e instrumentos ópticos, fotográficos e cinematográficos', '26', 'Fabricação De Equipamentos De Informática, Produtos Eletrônicos E Ópticos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2680900', 'Fabricação de mídias virgens, magnéticas e ópticas', '26809', 'Fabricação de mídias virgens, magnéticas e ópticas', '268', 'Fabricação de mídias virgens, magnéticas e ópticas', '26', 'Fabricação De Equipamentos De Informática, Produtos Eletrônicos E Ópticos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2710402', 'Fabricação de transformadores, indutores, conversores, sincronizadores e semelhantes, peças e acessórios', '27104', 'Fabricação de geradores, transformadores e motores elétricos', '271', 'Fabricação de geradores, transformadores e motores elétricos', '27', 'Fabricação De Máquinas, Aparelhos E Materiais Elétricos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2710403', 'Fabricação de motores elétricos, peças e acessórios', '27104', 'Fabricação de geradores, transformadores e motores elétricos', '271', 'Fabricação de geradores, transformadores e motores elétricos', '27', 'Fabricação De Máquinas, Aparelhos E Materiais Elétricos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2710401', 'Fabricação de geradores de corrente contínua e alternada, peças e acessórios', '27104', 'Fabricação de geradores, transformadores e motores elétricos', '271', 'Fabricação de geradores, transformadores e motores elétricos', '27', 'Fabricação De Máquinas, Aparelhos E Materiais Elétricos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2721000', 'Fabricação de pilhas, baterias e acumuladores elétricos, exceto para veículos automotores', '27210', 'Fabricação de pilhas, baterias e acumuladores elétricos, exceto para veículos automotores', '272', 'Fabricação de pilhas, baterias e acumuladores elétricos', '27', 'Fabricação De Máquinas, Aparelhos E Materiais Elétricos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2722802', 'Recondicionamento de baterias e acumuladores para veículos automotores', '27228', 'Fabricação de baterias e acumuladores para veículos automotores', '272', 'Fabricação de pilhas, baterias e acumuladores elétricos', '27', 'Fabricação De Máquinas, Aparelhos E Materiais Elétricos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2722801', 'Fabricação de baterias e acumuladores para veículos automotores', '27228', 'Fabricação de baterias e acumuladores para veículos automotores', '272', 'Fabricação de pilhas, baterias e acumuladores elétricos', '27', 'Fabricação De Máquinas, Aparelhos E Materiais Elétricos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2731700', 'Fabricação de aparelhos e equipamentos para distribuição e controle de energia elétrica', '27317', 'Fabricação de aparelhos e equipamentos para distribuição e controle de energia elétrica', '273', 'Fabricação de equipamentos para distribuição e controle de energia elétrica', '27', 'Fabricação De Máquinas, Aparelhos E Materiais Elétricos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2732500', 'Fabricação de material elétrico para instalações em circuito de consumo', '27325', 'Fabricação de material elétrico para instalações em circuito de consumo', '273', 'Fabricação de equipamentos para distribuição e controle de energia elétrica', '27', 'Fabricação De Máquinas, Aparelhos E Materiais Elétricos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2733300', 'Fabricação de fios, cabos e condutores elétricos isolados', '27333', 'Fabricação de fios, cabos e condutores elétricos isolados', '273', 'Fabricação de equipamentos para distribuição e controle de energia elétrica', '27', 'Fabricação De Máquinas, Aparelhos E Materiais Elétricos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2740601', 'Fabricação de lâmpadas', '27406', 'Fabricação de lâmpadas e outros equipamentos de iluminação', '274', 'Fabricação de lâmpadas e outros equipamentos de iluminação', '27', 'Fabricação De Máquinas, Aparelhos E Materiais Elétricos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2740602', 'Fabricação de luminárias e outros equipamentos de iluminação', '27406', 'Fabricação de lâmpadas e outros equipamentos de iluminação', '274', 'Fabricação de lâmpadas e outros equipamentos de iluminação', '27', 'Fabricação De Máquinas, Aparelhos E Materiais Elétricos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2751100', 'Fabricação de fogões, refrigeradores e máquinas de lavar e secar para uso doméstico, peças e acessórios', '27511', 'Fabricação de fogões, refrigeradores e máquinas de lavar e secar para uso doméstico', '275', 'Fabricação de eletrodomésticos', '27', 'Fabricação De Máquinas, Aparelhos E Materiais Elétricos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2759799', 'Fabricação de outros aparelhos eletrodomésticos não especificados anteriormente, peças e acessórios', '27597', 'Fabricação de aparelhos eletrodomésticos não especificados anteriormente', '275', 'Fabricação de eletrodomésticos', '27', 'Fabricação De Máquinas, Aparelhos E Materiais Elétricos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2759701', 'Fabricação de aparelhos elétricos de uso pessoal, peças e acessórios', '27597', 'Fabricação de aparelhos eletrodomésticos não especificados anteriormente', '275', 'Fabricação de eletrodomésticos', '27', 'Fabricação De Máquinas, Aparelhos E Materiais Elétricos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2790202', 'Fabricação de equipamentos para sinalização e alarme', '27902', 'Fabricação de equipamentos e aparelhos elétricos não especificados anteriormente', '279', 'Fabricação de equipamentos e aparelhos elétricos não especificados anteriormente', '27', 'Fabricação De Máquinas, Aparelhos E Materiais Elétricos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2790299', 'Fabricação de outros equipamentos e aparelhos elétricos não especificados anteriormente', '27902', 'Fabricação de equipamentos e aparelhos elétricos não especificados anteriormente', '279', 'Fabricação de equipamentos e aparelhos elétricos não especificados anteriormente', '27', 'Fabricação De Máquinas, Aparelhos E Materiais Elétricos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2790201', 'Fabricação de eletrodos, contatos e outros artigos de carvão e grafita para uso elétrico, eletroímãs e isoladores', '27902', 'Fabricação de equipamentos e aparelhos elétricos não especificados anteriormente', '279', 'Fabricação de equipamentos e aparelhos elétricos não especificados anteriormente', '27', 'Fabricação De Máquinas, Aparelhos E Materiais Elétricos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2811900', 'Fabricação de motores e turbinas, peças e acessórios, exceto para aviões e veículos rodoviários', '28119', 'Fabricação de motores e turbinas, exceto para aviões e veículos rodoviários', '281', 'Fabricação de motores, bombas, compressores e equipamentos de transmissão', '28', 'Fabricação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2812700', 'Fabricação de equipamentos hidráulicos e pneumáticos, peças e acessórios, exceto válvulas', '28127', 'Fabricação de equipamentos hidráulicos e pneumáticos, exceto válvulas', '281', 'Fabricação de motores, bombas, compressores e equipamentos de transmissão', '28', 'Fabricação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2813500', 'Fabricação de válvulas, registros e dispositivos semelhantes, peças e acessórios', '28135', 'Fabricação de válvulas, registros e dispositivos semelhantes', '281', 'Fabricação de motores, bombas, compressores e equipamentos de transmissão', '28', 'Fabricação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2814301', 'Fabricação de compressores para uso industrial, peças e acessórios', '28143', 'Fabricação de compressores', '281', 'Fabricação de motores, bombas, compressores e equipamentos de transmissão', '28', 'Fabricação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2814302', 'Fabricação de compressores para uso não industrial, peças e acessórios', '28143', 'Fabricação de compressores', '281', 'Fabricação de motores, bombas, compressores e equipamentos de transmissão', '28', 'Fabricação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2815102', 'Fabricação de equipamentos de transmissão para fins industriais, exceto rolamentos', '28151', 'Fabricação de equipamentos de transmissão para fins industriais', '281', 'Fabricação de motores, bombas, compressores e equipamentos de transmissão', '28', 'Fabricação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2815101', 'Fabricação de rolamentos para fins industriais', '28151', 'Fabricação de equipamentos de transmissão para fins industriais', '281', 'Fabricação de motores, bombas, compressores e equipamentos de transmissão', '28', 'Fabricação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2821601', 'Fabricação de fornos industriais, aparelhos e equipamentos não elétricos para instalações térmicas, peças e acessórios', '28216', 'Fabricação de aparelhos e equipamentos para instalações térmicas', '282', 'Fabricação de máquinas e equipamentos de uso geral', '28', 'Fabricação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2821602', 'Fabricação de estufas e fornos elétricos para fins industriais, peças e acessórios', '28216', 'Fabricação de aparelhos e equipamentos para instalações térmicas', '282', 'Fabricação de máquinas e equipamentos de uso geral', '28', 'Fabricação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2822402', 'Fabricação de máquinas, equipamentos e aparelhos para transporte e elevação de cargas, peças e acessórios', '28224', 'Fabricação de máquinas, equipamentos e aparelhos para transporte e elevação de cargas e pessoas', '282', 'Fabricação de máquinas e equipamentos de uso geral', '28', 'Fabricação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2822401', 'Fabricação de máquinas, equipamentos e aparelhos para transporte e elevação de pessoas, peças e acessórios', '28224', 'Fabricação de máquinas, equipamentos e aparelhos para transporte e elevação de cargas e pessoas', '282', 'Fabricação de máquinas e equipamentos de uso geral', '28', 'Fabricação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2823200', 'Fabricação de máquinas e aparelhos de refrigeração e ventilação para uso industrial e comercial, peças e acessórios', '28232', 'Fabricação de máquinas e aparelhos de refrigeração e ventilação para uso industrial e comercial', '282', 'Fabricação de máquinas e equipamentos de uso geral', '28', 'Fabricação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2824101', 'Fabricação de aparelhos e equipamentos de ar condicionado para uso industrial', '28241', 'Fabricação de aparelhos e equipamentos de ar condicionado', '282', 'Fabricação de máquinas e equipamentos de uso geral', '28', 'Fabricação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2824102', 'Fabricação de aparelhos e equipamentos de ar condicionado para uso não industrial', '28241', 'Fabricação de aparelhos e equipamentos de ar condicionado', '282', 'Fabricação de máquinas e equipamentos de uso geral', '28', 'Fabricação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2825900', 'Fabricação de máquinas e equipamentos para saneamento básico e ambiental, peças e acessórios', '28259', 'Fabricação de máquinas e equipamentos para saneamento básico e ambiental', '282', 'Fabricação de máquinas e equipamentos de uso geral', '28', 'Fabricação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2829101', 'Fabricação de máquinas de escrever, calcular e outros equipamentos não eletrônicos para escritório, peças e acessórios', '28291', 'Fabricação de máquinas e equipamentos de uso geral não especificados anteriormente', '282', 'Fabricação de máquinas e equipamentos de uso geral', '28', 'Fabricação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2829199', 'Fabricação de outras máquinas e equipamentos de uso geral não especificados anteriormente, peças e acessórios', '28291', 'Fabricação de máquinas e equipamentos de uso geral não especificados anteriormente', '282', 'Fabricação de máquinas e equipamentos de uso geral', '28', 'Fabricação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2831300', 'Fabricação de tratores agrícolas, peças e acessórios', '28313', 'Fabricação de tratores agrícolas', '283', 'Fabricação de tratores e de máquinas e equipamentos para a agricultura e pecuária', '28', 'Fabricação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2832100', 'Fabricação de equipamentos para irrigação agrícola, peças e acessórios', '28321', 'Fabricação de equipamentos para irrigação agrícola', '283', 'Fabricação de tratores e de máquinas e equipamentos para a agricultura e pecuária', '28', 'Fabricação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2833000', 'Fabricação de máquinas e equipamentos para a agricultura e pecuária, peças e acessórios, exceto para irrigação', '28330', 'Fabricação de máquinas e equipamentos para a agricultura e pecuária, exceto para irrigação', '283', 'Fabricação de tratores e de máquinas e equipamentos para a agricultura e pecuária', '28', 'Fabricação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2840200', 'Fabricação de máquinas-ferramenta, peças e acessórios', '28402', 'Fabricação de máquinas-ferramenta', '284', 'Fabricação de máquinas-ferramenta', '28', 'Fabricação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2851800', 'Fabricação de máquinas e equipamentos para a prospecção e extração de petróleo, peças e acessórios', '28518', 'Fabricação de máquinas e equipamentos para a prospecção e extração de petróleo', '285', 'Fabricação de máquinas e equipamentos de uso na extração mineral e na construção', '28', 'Fabricação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2852600', 'Fabricação de outras máquinas e equipamentos para uso na extração mineral, peças e acessórios, exceto na extração de petróleo', '28526', 'Fabricação de outras máquinas e equipamentos para uso na extração mineral, exceto na extração de petróleo', '285', 'Fabricação de máquinas e equipamentos de uso na extração mineral e na construção', '28', 'Fabricação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2853400', 'Fabricação de tratores, peças e acessórios, exceto agrícolas', '28534', 'Fabricação de tratores, exceto agrícolas', '285', 'Fabricação de máquinas e equipamentos de uso na extração mineral e na construção', '28', 'Fabricação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2854200', 'Fabricação de máquinas e equipamentos para terraplenagem, pavimentação e construção, peças e acessórios, exceto tratores', '28542', 'Fabricação de máquinas e equipamentos para terraplenagem, pavimentação e construção, exceto tratores', '285', 'Fabricação de máquinas e equipamentos de uso na extração mineral e na construção', '28', 'Fabricação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2861500', 'Fabricação de máquinas para a indústria metalúrgica, peças e acessórios, exceto máquinas-ferramenta', '28615', 'Fabricação de máquinas para a indústria metalúrgica, exceto máquinas-ferramenta', '286', 'Fabricação de máquinas e equipamentos de uso industrial específico', '28', 'Fabricação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2862300', 'Fabricação de máquinas e equipamentos para as indústrias de alimentos, bebidas e fumo, peças e acessórios', '28623', 'Fabricação de máquinas e equipamentos para as indústrias de alimentos, bebidas e fumo', '286', 'Fabricação de máquinas e equipamentos de uso industrial específico', '28', 'Fabricação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2863100', 'Fabricação de máquinas e equipamentos para a indústria têxtil, peças e acessórios', '28631', 'Fabricação de máquinas e equipamentos para a indústria têxtil', '286', 'Fabricação de máquinas e equipamentos de uso industrial específico', '28', 'Fabricação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2864000', 'Fabricação de máquinas e equipamentos para as indústrias do vestuário, do couro e de calçados, peças e acessórios', '28640', 'Fabricação de máquinas e equipamentos para as indústrias do vestuário, do couro e de calçados', '286', 'Fabricação de máquinas e equipamentos de uso industrial específico', '28', 'Fabricação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2865800', 'Fabricação de máquinas e equipamentos para as indústrias de celulose, papel e papelão e artefatos, peças e acessórios', '28658', 'Fabricação de máquinas e equipamentos para as indústrias de celulose, papel e papelão e artefatos', '286', 'Fabricação de máquinas e equipamentos de uso industrial específico', '28', 'Fabricação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2866600', 'Fabricação de máquinas e equipamentos para a indústria do plástico, peças e acessórios', '28666', 'Fabricação de máquinas e equipamentos para a indústria do plástico', '286', 'Fabricação de máquinas e equipamentos de uso industrial específico', '28', 'Fabricação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2869100', 'Fabricação de máquinas e equipamentos para uso industrial específico não especificados anteriormente, peças e acessórios', '28691', 'Fabricação de máquinas e equipamentos para uso industrial específico não especificados anteriormente', '286', 'Fabricação de máquinas e equipamentos de uso industrial específico', '28', 'Fabricação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2910701', 'Fabricação de automóveis, camionetas e utilitários', '29107', 'Fabricação de automóveis, camionetas e utilitários', '291', 'Fabricação de automóveis, camionetas e utilitários', '29', 'Fabricação De Veículos Automotores, Reboques E Carrocerias', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2910702', 'Fabricação de chassis com motor para automóveis, camionetas e utilitários', '29107', 'Fabricação de automóveis, camionetas e utilitários', '291', 'Fabricação de automóveis, camionetas e utilitários', '29', 'Fabricação De Veículos Automotores, Reboques E Carrocerias', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2910703', 'Fabricação de motores para automóveis, camionetas e utilitários', '29107', 'Fabricação de automóveis, camionetas e utilitários', '291', 'Fabricação de automóveis, camionetas e utilitários', '29', 'Fabricação De Veículos Automotores, Reboques E Carrocerias', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2920401', 'Fabricação de caminhões e ônibus', '29204', 'Fabricação de caminhões e ônibus', '292', 'Fabricação de caminhões e ônibus', '29', 'Fabricação De Veículos Automotores, Reboques E Carrocerias', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2920402', 'Fabricação de motores para caminhões e ônibus', '29204', 'Fabricação de caminhões e ônibus', '292', 'Fabricação de caminhões e ônibus', '29', 'Fabricação De Veículos Automotores, Reboques E Carrocerias', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2930102', 'Fabricação de carrocerias para ônibus', '29301', 'Fabricação de cabines, carrocerias e reboques para veículos automotores', '293', 'Fabricação de cabines, carrocerias e reboques para veículos automotores', '29', 'Fabricação De Veículos Automotores, Reboques E Carrocerias', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2930103', 'Fabricação de cabines, carrocerias e reboques para outros veículos automotores, exceto caminhões e ônibus', '29301', 'Fabricação de cabines, carrocerias e reboques para veículos automotores', '293', 'Fabricação de cabines, carrocerias e reboques para veículos automotores', '29', 'Fabricação De Veículos Automotores, Reboques E Carrocerias', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2930101', 'Fabricação de cabines, carrocerias e reboques para caminhões', '29301', 'Fabricação de cabines, carrocerias e reboques para veículos automotores', '293', 'Fabricação de cabines, carrocerias e reboques para veículos automotores', '29', 'Fabricação De Veículos Automotores, Reboques E Carrocerias', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2941700', 'Fabricação de peças e acessórios para o sistema motor de veículos automotores', '29417', 'Fabricação de peças e acessórios para o sistema motor de veículos automotores', '294', 'Fabricação de peças e acessórios para veículos automotores', '29', 'Fabricação De Veículos Automotores, Reboques E Carrocerias', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2942500', 'Fabricação de peças e acessórios para os sistemas de marcha e transmissão de veículos automotores', '29425', 'Fabricação de peças e acessórios para os sistemas de marcha e transmissão de veículos automotores', '294', 'Fabricação de peças e acessórios para veículos automotores', '29', 'Fabricação De Veículos Automotores, Reboques E Carrocerias', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2943300', 'Fabricação de peças e acessórios para o sistema de freios de veículos automotores', '29433', 'Fabricação de peças e acessórios para o sistema de freios de veículos automotores', '294', 'Fabricação de peças e acessórios para veículos automotores', '29', 'Fabricação De Veículos Automotores, Reboques E Carrocerias', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2944100', 'Fabricação de peças e acessórios para o sistema de direção e suspensão de veículos automotores', '29441', 'Fabricação de peças e acessórios para o sistema de direção e suspensão de veículos automotores', '294', 'Fabricação de peças e acessórios para veículos automotores', '29', 'Fabricação De Veículos Automotores, Reboques E Carrocerias', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2945000', 'Fabricação de material elétrico e eletrônico para veículos automotores, exceto baterias', '29450', 'Fabricação de material elétrico e eletrônico para veículos automotores, exceto baterias', '294', 'Fabricação de peças e acessórios para veículos automotores', '29', 'Fabricação De Veículos Automotores, Reboques E Carrocerias', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2949299', 'Fabricação de outras peças e acessórios para veículos automotores não especificadas anteriormente', '29492', 'Fabricação de peças e acessórios para veículos automotores não especificados anteriormente', '294', 'Fabricação de peças e acessórios para veículos automotores', '29', 'Fabricação De Veículos Automotores, Reboques E Carrocerias', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2949201', 'Fabricação de bancos e estofados para veículos automotores', '29492', 'Fabricação de peças e acessórios para veículos automotores não especificados anteriormente', '294', 'Fabricação de peças e acessórios para veículos automotores', '29', 'Fabricação De Veículos Automotores, Reboques E Carrocerias', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('2950600', 'Recondicionamento e recuperação de motores para veículos automotores', '29506', 'Recondicionamento e recuperação de motores para veículos automotores', '295', 'Recondicionamento e recuperação de motores para veículos automotores', '29', 'Fabricação De Veículos Automotores, Reboques E Carrocerias', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3011301', 'Construção de embarcações de grande porte', '30113', 'Construção de embarcações e estruturas flutuantes', '301', 'Construção de embarcações', '30', 'Fabricação De Outros Equipamentos De Transporte, Exceto Veículos Automotores', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3011302', 'Construção de embarcações para uso comercial e para usos especiais, exceto de grande porte', '30113', 'Construção de embarcações e estruturas flutuantes', '301', 'Construção de embarcações', '30', 'Fabricação De Outros Equipamentos De Transporte, Exceto Veículos Automotores', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3012100', 'Construção de embarcações para esporte e lazer', '30121', 'Construção de embarcações para esporte e lazer', '301', 'Construção de embarcações', '30', 'Fabricação De Outros Equipamentos De Transporte, Exceto Veículos Automotores', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3031800', 'Fabricação de locomotivas, vagões e outros materiais rodantes', '30318', 'Fabricação de locomotivas, vagões e outros materiais rodantes', '303', 'Fabricação de veículos ferroviários', '30', 'Fabricação De Outros Equipamentos De Transporte, Exceto Veículos Automotores', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3032600', 'Fabricação de peças e acessórios para veículos ferroviários', '30326', 'Fabricação de peças e acessórios para veículos ferroviários', '303', 'Fabricação de veículos ferroviários', '30', 'Fabricação De Outros Equipamentos De Transporte, Exceto Veículos Automotores', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3041500', 'Fabricação de aeronaves', '30415', 'Fabricação de aeronaves', '304', 'Fabricação de aeronaves', '30', 'Fabricação De Outros Equipamentos De Transporte, Exceto Veículos Automotores', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3042300', 'Fabricação de turbinas, motores e outros componentes e peças para aeronaves', '30423', 'Fabricação de turbinas, motores e outros componentes e peças para aeronaves', '304', 'Fabricação de aeronaves', '30', 'Fabricação De Outros Equipamentos De Transporte, Exceto Veículos Automotores', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3050400', 'Fabricação de veículos militares de combate', '30504', 'Fabricação de veículos militares de combate', '305', 'Fabricação de veículos militares de combate', '30', 'Fabricação De Outros Equipamentos De Transporte, Exceto Veículos Automotores', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3091100', 'Fabricação de motocicletas, peças e acessórios', '30911', 'Fabricação de motocicletas', '309', 'Fabricação de equipamentos de transporte não especificados anteriormente', '30', 'Fabricação De Outros Equipamentos De Transporte, Exceto Veículos Automotores', 'C', 'Indústrias De Transformação', TRUE, FALSE, FALSE, FALSE),
('3091102', 'Fabricação de peças e acessórios para motocicletas', '30911', 'Fabricação de motocicletas', '309', 'Fabricação de equipamentos de transporte não especificados anteriormente', '30', 'Fabricação De Outros Equipamentos De Transporte, Exceto Veículos Automotores', 'C', 'Indústrias De Transformação', FALSE, TRUE, TRUE, TRUE),
('3091101', 'Fabricação de motocicletas', '30911', 'Fabricação de motocicletas', '309', 'Fabricação de equipamentos de transporte não especificados anteriormente', '30', 'Fabricação De Outros Equipamentos De Transporte, Exceto Veículos Automotores', 'C', 'Indústrias De Transformação', FALSE, TRUE, TRUE, TRUE),
('3092000', 'Fabricação de bicicletas e triciclos não motorizados, peças e acessórios', '30920', 'Fabricação de bicicletas e triciclos não motorizados', '309', 'Fabricação de equipamentos de transporte não especificados anteriormente', '30', 'Fabricação De Outros Equipamentos De Transporte, Exceto Veículos Automotores', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3099700', 'Fabricação de equipamentos de transporte não especificados anteriormente', '30997', 'Fabricação de equipamentos de transporte não especificados anteriormente', '309', 'Fabricação de equipamentos de transporte não especificados anteriormente', '30', 'Fabricação De Outros Equipamentos De Transporte, Exceto Veículos Automotores', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3101200', 'Fabricação de móveis com predominância de madeira', '31012', 'Fabricação de móveis com predominância de madeira', '310', 'Fabricação de móveis', '31', 'Fabricação De Móveis', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3102100', 'Fabricação de móveis com predominância de metal', '31021', 'Fabricação de móveis com predominância de metal', '310', 'Fabricação de móveis', '31', 'Fabricação De Móveis', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3103900', 'Fabricação de móveis de outros materiais, exceto madeira e metal', '31039', 'Fabricação de móveis de outros materiais, exceto madeira e metal', '310', 'Fabricação de móveis', '31', 'Fabricação De Móveis', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3104700', 'Fabricação de colchões', '31047', 'Fabricação de colchões', '310', 'Fabricação de móveis', '31', 'Fabricação De Móveis', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3211602', 'Fabricação de artefatos de joalheria e ourivesaria', '32116', 'Lapidação de gemas e fabricação de artefatos de ourivesaria e joalheria', '321', 'Fabricação de artigos de joalheria, bijuteria e semelhantes', '32', 'Fabricação De Produtos Diversos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3211603', 'Cunhagem de moedas e medalhas', '32116', 'Lapidação de gemas e fabricação de artefatos de ourivesaria e joalheria', '321', 'Fabricação de artigos de joalheria, bijuteria e semelhantes', '32', 'Fabricação De Produtos Diversos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3211601', 'Lapidação de gemas', '32116', 'Lapidação de gemas e fabricação de artefatos de ourivesaria e joalheria', '321', 'Fabricação de artigos de joalheria, bijuteria e semelhantes', '32', 'Fabricação De Produtos Diversos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3212400', 'Fabricação de bijuterias e artefatos semelhantes', '32124', 'Fabricação de bijuterias e artefatos semelhantes', '321', 'Fabricação de artigos de joalheria, bijuteria e semelhantes', '32', 'Fabricação De Produtos Diversos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3220500', 'Fabricação de instrumentos musicais, peças e acessórios', '32205', 'Fabricação de instrumentos musicais', '322', 'Fabricação de instrumentos musicais', '32', 'Fabricação De Produtos Diversos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3230200', 'Fabricação de artefatos para pesca e esporte', '32302', 'Fabricação de artefatos para pesca e esporte', '323', 'Fabricação de artefatos para pesca e esporte', '32', 'Fabricação De Produtos Diversos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3240002', 'Fabricação de mesas de bilhar, de sinuca e acessórios não associada à locação', '32400', 'Fabricação de brinquedos e jogos recreativos', '324', 'Fabricação de brinquedos e jogos recreativos', '32', 'Fabricação De Produtos Diversos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3240003', 'Fabricação de mesas de bilhar, de sinuca e acessórios associada à locação', '32400', 'Fabricação de brinquedos e jogos recreativos', '324', 'Fabricação de brinquedos e jogos recreativos', '32', 'Fabricação De Produtos Diversos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3240099', 'Fabricação de outros brinquedos e jogos recreativos não especificados anteriormente', '32400', 'Fabricação de brinquedos e jogos recreativos', '324', 'Fabricação de brinquedos e jogos recreativos', '32', 'Fabricação De Produtos Diversos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3240001', 'Fabricação de jogos eletrônicos', '32400', 'Fabricação de brinquedos e jogos recreativos', '324', 'Fabricação de brinquedos e jogos recreativos', '32', 'Fabricação De Produtos Diversos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3250701', 'Fabricação de instrumentos não eletrônicos e utensílios para uso médico, cirúrgico, odontológico e de laboratório', '32507', 'Fabricação de instrumentos e materiais para uso médico e odontológico e de artigos ópticos', '325', 'Fabricação de instrumentos e materiais para uso médico e odontológico e de artigos ópticos', '32', 'Fabricação De Produtos Diversos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3250704', 'Fabricação de aparelhos e utensílios para correção de defeitos físicos e aparelhos ortopédicos em geral, exceto sob encomenda', '32507', 'Fabricação de instrumentos e materiais para uso médico e odontológico e de artigos ópticos', '325', 'Fabricação de instrumentos e materiais para uso médico e odontológico e de artigos ópticos', '32', 'Fabricação De Produtos Diversos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3250702', 'Fabricação de mobiliário para uso médico, cirúrgico, odontológico e de laboratório', '32507', 'Fabricação de instrumentos e materiais para uso médico e odontológico e de artigos ópticos', '325', 'Fabricação de instrumentos e materiais para uso médico e odontológico e de artigos ópticos', '32', 'Fabricação De Produtos Diversos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3250706', 'Serviços de prótese dentária', '32507', 'Fabricação de instrumentos e materiais para uso médico e odontológico e de artigos ópticos', '325', 'Fabricação de instrumentos e materiais para uso médico e odontológico e de artigos ópticos', '32', 'Fabricação De Produtos Diversos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3250705', 'Fabricação de materiais para medicina e odontologia', '32507', 'Fabricação de instrumentos e materiais para uso médico e odontológico e de artigos ópticos', '325', 'Fabricação de instrumentos e materiais para uso médico e odontológico e de artigos ópticos', '32', 'Fabricação De Produtos Diversos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3250707', 'Fabricação de artigos ópticos', '32507', 'Fabricação de instrumentos e materiais para uso médico e odontológico e de artigos ópticos', '325', 'Fabricação de instrumentos e materiais para uso médico e odontológico e de artigos ópticos', '32', 'Fabricação De Produtos Diversos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3250709', 'Serviço de laboratório óptico', '32507', 'Fabricação de instrumentos e materiais para uso médico e odontológico e de artigos ópticos', '325', 'Fabricação de instrumentos e materiais para uso médico e odontológico e de artigos ópticos', '32', 'Fabricação De Produtos Diversos', 'C', 'Indústrias De Transformação', FALSE, TRUE, TRUE, TRUE),
('3250708', 'Fabricação de artefatos de tecido não tecido para uso odonto-médico-hospitalar', '32507', 'Fabricação de instrumentos e materiais para uso médico e odontológico e de artigos ópticos', '325', 'Fabricação de instrumentos e materiais para uso médico e odontológico e de artigos ópticos', '32', 'Fabricação De Produtos Diversos', 'C', 'Indústrias De Transformação', TRUE, FALSE, FALSE, FALSE),
('3250703', 'Fabricação de aparelhos e utensílios para correção de defeitos físicos e aparelhos ortopédicos em geral sob encomenda', '32507', 'Fabricação de instrumentos e materiais para uso médico e odontológico e de artigos ópticos', '325', 'Fabricação de instrumentos e materiais para uso médico e odontológico e de artigos ópticos', '32', 'Fabricação De Produtos Diversos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3291400', 'Fabricação de escovas, pincéis e vassouras', '32914', 'Fabricação de escovas, pincéis e vassouras', '329', 'Fabricação de produtos diversos', '32', 'Fabricação De Produtos Diversos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3292202', 'Fabricação de equipamentos e acessórios para segurança pessoal e profissional', '32922', 'Fabricação de equipamentos e acessórios para segurança e proteção pessoal e profissional', '329', 'Fabricação de produtos diversos', '32', 'Fabricação De Produtos Diversos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3292201', 'Fabricação de roupas de proteção e segurança e resistentes a fogo', '32922', 'Fabricação de equipamentos e acessórios para segurança e proteção pessoal e profissional', '329', 'Fabricação de produtos diversos', '32', 'Fabricação De Produtos Diversos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3299004', 'Fabricação de painéis e letreiros luminosos', '32990', 'Fabricação de produtos diversos não especificados anteriormente', '329', 'Fabricação de produtos diversos', '32', 'Fabricação De Produtos Diversos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3299003', 'Fabricação de letras, letreiros e placas de qualquer material, exceto luminosos', '32990', 'Fabricação de produtos diversos não especificados anteriormente', '329', 'Fabricação de produtos diversos', '32', 'Fabricação De Produtos Diversos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3299006', 'Fabricação de velas, inclusive decorativas', '32990', 'Fabricação de produtos diversos não especificados anteriormente', '329', 'Fabricação de produtos diversos', '32', 'Fabricação De Produtos Diversos', 'C', 'Indústrias De Transformação', FALSE, TRUE, TRUE, TRUE),
('3299001', 'Fabricação de guarda-chuvas e similares', '32990', 'Fabricação de produtos diversos não especificados anteriormente', '329', 'Fabricação de produtos diversos', '32', 'Fabricação De Produtos Diversos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3299005', 'Fabricação de aviamentos para costura', '32990', 'Fabricação de produtos diversos não especificados anteriormente', '329', 'Fabricação de produtos diversos', '32', 'Fabricação De Produtos Diversos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3299099', 'Fabricação de produtos diversos não especificados anteriormente', '32990', 'Fabricação de produtos diversos não especificados anteriormente', '329', 'Fabricação de produtos diversos', '32', 'Fabricação De Produtos Diversos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3299002', 'Fabricação de canetas, lápis e outros artigos para escritório', '32990', 'Fabricação de produtos diversos não especificados anteriormente', '329', 'Fabricação de produtos diversos', '32', 'Fabricação De Produtos Diversos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3311200', 'Manutenção e reparação de tanques, reservatórios metálicos e caldeiras, exceto para veículos', '33112', 'Manutenção e reparação de tanques, reservatórios metálicos e caldeiras, exceto para veículos', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3312102', 'Manutenção e reparação de aparelhos e instrumentos de medida, teste e controle', '33121', 'Manutenção e reparação de equipamentos eletrônicos e ópticos', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3312103', 'Manutenção e reparação de aparelhos eletromédicos e eletroterapêuticos e equipamentos de irradiação', '33121', 'Manutenção e reparação de equipamentos eletrônicos e ópticos', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3312104', 'Manutenção e reparação de equipamentos e instrumentos ópticos', '33121', 'Manutenção e reparação de equipamentos eletrônicos e ópticos', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3313901', 'Manutenção e reparação de geradores, transformadores e motores elétricos', '33139', 'Manutenção e reparação de máquinas e equipamentos elétricos', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3313999', 'Manutenção e reparação de máquinas, aparelhos e materiais elétricos não especificados anteriormente', '33139', 'Manutenção e reparação de máquinas e equipamentos elétricos', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3313902', 'Manutenção e reparação de baterias e acumuladores elétricos, exceto para veículos', '33139', 'Manutenção e reparação de máquinas e equipamentos elétricos', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3314711', 'Manutenção e reparação de máquinas e equipamentos para agricultura e pecuária', '33147', 'Manutenção e reparação de máquinas e equipamentos da indústria mecânica', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3314708', 'Manutenção e reparação de máquinas, equipamentos e aparelhos para transporte e elevação de cargas', '33147', 'Manutenção e reparação de máquinas e equipamentos da indústria mecânica', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3314721', 'Manutenção e reparação de máquinas e aparelhos para a indústria de celulose, papel e papelão e artefatos', '33147', 'Manutenção e reparação de máquinas e equipamentos da indústria mecânica', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3314719', 'Manutenção e reparação de máquinas e equipamentos para as indústrias de alimentos, bebidas e fumo', '33147', 'Manutenção e reparação de máquinas e equipamentos da indústria mecânica', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3314720', 'Manutenção e reparação de máquinas e equipamentos para a indústria têxtil, do vestuário, do couro e calçados', '33147', 'Manutenção e reparação de máquinas e equipamentos da indústria mecânica', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3314702', 'Manutenção e reparação de equipamentos hidráulicos e pneumáticos, exceto válvulas', '33147', 'Manutenção e reparação de máquinas e equipamentos da indústria mecânica', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3314716', 'Manutenção e reparação de tratores, exceto agrícolas', '33147', 'Manutenção e reparação de máquinas e equipamentos da indústria mecânica', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3314701', 'Manutenção e reparação de máquinas motrizes não elétricas', '33147', 'Manutenção e reparação de máquinas e equipamentos da indústria mecânica', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3314713', 'Manutenção e reparação de máquinas-ferramenta', '33147', 'Manutenção e reparação de máquinas e equipamentos da indústria mecânica', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3314715', 'Manutenção e reparação de máquinas e equipamentos para uso na extração mineral, exceto na extração de petróleo', '33147', 'Manutenção e reparação de máquinas e equipamentos da indústria mecânica', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3314717', 'Manutenção e reparação de máquinas e equipamentos de terraplenagem, pavimentação e construção, exceto tratores', '33147', 'Manutenção e reparação de máquinas e equipamentos da indústria mecânica', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3314707', 'Manutenção e reparação de máquinas e aparelhos de refrigeração e ventilação para uso industrial e comercial', '33147', 'Manutenção e reparação de máquinas e equipamentos da indústria mecânica', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3314706', 'Manutenção e reparação de máquinas, aparelhos e equipamentos para instalações térmicas', '33147', 'Manutenção e reparação de máquinas e equipamentos da indústria mecânica', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3314710', 'Manutenção e reparação de máquinas e equipamentos para uso geral não especificados anteriormente', '33147', 'Manutenção e reparação de máquinas e equipamentos da indústria mecânica', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3314714', 'Manutenção e reparação de máquinas e equipamentos para a prospecção e extração de petróleo', '33147', 'Manutenção e reparação de máquinas e equipamentos da indústria mecânica', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3314705', 'Manutenção e reparação de equipamentos de transmissão para fins industriais', '33147', 'Manutenção e reparação de máquinas e equipamentos da indústria mecânica', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3314704', 'Manutenção e reparação de compressores', '33147', 'Manutenção e reparação de máquinas e equipamentos da indústria mecânica', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3314799', 'Manutenção e reparação de outras máquinas e equipamentos para usos industriais não especificados anteriormente', '33147', 'Manutenção e reparação de máquinas e equipamentos da indústria mecânica', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3314703', 'Manutenção e reparação de válvulas industriais', '33147', 'Manutenção e reparação de máquinas e equipamentos da indústria mecânica', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3314712', 'Manutenção e reparação de tratores agrícolas', '33147', 'Manutenção e reparação de máquinas e equipamentos da indústria mecânica', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3314718', 'Manutenção e reparação de máquinas para a indústria metalúrgica, exceto máquinas-ferramenta', '33147', 'Manutenção e reparação de máquinas e equipamentos da indústria mecânica', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3314722', 'Manutenção e reparação de máquinas e aparelhos para a indústria do plástico', '33147', 'Manutenção e reparação de máquinas e equipamentos da indústria mecânica', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3314709', 'Manutenção e reparação de máquinas de escrever, calcular e de outros equipamentos não eletrônicos para escritório', '33147', 'Manutenção e reparação de máquinas e equipamentos da indústria mecânica', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3315500', 'Manutenção e reparação de veículos ferroviários', '33155', 'Manutenção e reparação de veículos ferroviários', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3316301', 'Manutenção e reparação de aeronaves, exceto a manutenção na pista', '33163', 'Manutenção e reparação de aeronaves', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3316302', 'Manutenção de aeronaves na pista', '33163', 'Manutenção e reparação de aeronaves', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3317101', 'Manutenção e reparação de embarcações e estruturas flutuantes', '33171', 'Manutenção e reparação de embarcações', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3317102', 'Manutenção e reparação de embarcações para esporte e lazer', '33171', 'Manutenção e reparação de embarcações', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3319800', 'Manutenção e reparação de equipamentos e produtos não especificados anteriormente', '33198', 'Manutenção e reparação de equipamentos e produtos não especificados anteriormente', '331', 'Manutenção e reparação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3321000', 'Instalação de máquinas e equipamentos industriais', '33210', 'Instalação de máquinas e equipamentos industriais', '332', 'Instalação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3329599', 'Instalação de outros equipamentos não especificados anteriormente', '33295', 'Instalação de equipamentos não especificados anteriormente', '332', 'Instalação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3329501', 'Serviços de montagem de móveis de qualquer material', '33295', 'Instalação de equipamentos não especificados anteriormente', '332', 'Instalação de máquinas e equipamentos', '33', 'Manutenção, Reparação E Instalação De Máquinas E Equipamentos', 'C', 'Indústrias De Transformação', TRUE, TRUE, TRUE, TRUE),
('3511502', 'Atividades de coordenação e controle da operação da geração e transmissão de energia elétrica', '35115', 'Geração de energia elétrica', '351', 'Geração, transmissão e distribuição de energia elétrica', '35', 'Eletricidade, Gás E Outras Utilidades', 'D', 'Eletricidade E Gás', FALSE, TRUE, TRUE, TRUE),
('3511501', 'Geração de energia elétrica', '35115', 'Geração de energia elétrica', '351', 'Geração, transmissão e distribuição de energia elétrica', '35', 'Eletricidade, Gás E Outras Utilidades', 'D', 'Eletricidade E Gás', FALSE, TRUE, TRUE, TRUE),
('3511500', 'Geração de energia elétrica', '35115', 'Geração de energia elétrica', '351', 'Geração, transmissão e distribuição de energia elétrica', '35', 'Eletricidade, Gás E Outras Utilidades', 'D', 'Eletricidade E Gás', TRUE, FALSE, FALSE, FALSE),
('3512300', 'Transmissão de energia elétrica', '35123', 'Transmissão de energia elétrica', '351', 'Geração, transmissão e distribuição de energia elétrica', '35', 'Eletricidade, Gás E Outras Utilidades', 'D', 'Eletricidade E Gás', TRUE, TRUE, TRUE, TRUE),
('3513100', 'Comércio atacadista de energia elétrica', '35131', 'Comércio atacadista de energia elétrica', '351', 'Geração, transmissão e distribuição de energia elétrica', '35', 'Eletricidade, Gás E Outras Utilidades', 'D', 'Eletricidade E Gás', TRUE, TRUE, TRUE, TRUE),
('3514000', 'Distribuição de energia elétrica', '35140', 'Distribuição de energia elétrica', '351', 'Geração, transmissão e distribuição de energia elétrica', '35', 'Eletricidade, Gás E Outras Utilidades', 'D', 'Eletricidade E Gás', TRUE, TRUE, TRUE, TRUE),
('3520401', 'Produção de gás; processamento de gás natural', '35204', 'Produção de gás; processamento de gás natural; distribuição de combustíveis gasosos por redes urbanas', '352', 'Produção e distribuição de combustíveis gasosos por redes urbanas', '35', 'Eletricidade, Gás E Outras Utilidades', 'D', 'Eletricidade E Gás', TRUE, TRUE, TRUE, TRUE),
('3520402', 'Distribuição de combustíveis gasosos por redes urbanas', '35204', 'Produção de gás; processamento de gás natural; distribuição de combustíveis gasosos por redes urbanas', '352', 'Produção e distribuição de combustíveis gasosos por redes urbanas', '35', 'Eletricidade, Gás E Outras Utilidades', 'D', 'Eletricidade E Gás', TRUE, TRUE, TRUE, TRUE),
('3530100', 'Produção e distribuição de vapor, água quente e ar condicionado', '35301', 'Produção e distribuição de vapor, água quente e ar
condicionado', '353', 'Produção e distribuição de vapor, água quente e ar
condicionado', '35', 'Eletricidade, Gás E Outras Utilidades', 'D', 'Eletricidade E Gás', TRUE, TRUE, TRUE, TRUE),
('3600602', 'Distribuição de água por caminhões', '36006', 'Captação, tratamento e distribuição de água', '360', 'Captação, tratamento e distribuição de água', '36', 'Captação, Tratamento E Distribuição De Água', 'E', 'Água, Esgoto, Atividades De Gestão De Resíduos E Descontaminação', TRUE, TRUE, TRUE, TRUE),
('3600601', 'Captação, tratamento e distribuição de água', '36006', 'Captação, tratamento e distribuição de água', '360', 'Captação, tratamento e distribuição de água', '36', 'Captação, Tratamento E Distribuição De Água', 'E', 'Água, Esgoto, Atividades De Gestão De Resíduos E Descontaminação', TRUE, TRUE, TRUE, TRUE),
('3701100', 'Gestão de redes de esgoto', '37011', 'Gestão de redes de esgoto', '370', 'Esgoto e atividades relacionadas', '37', 'Esgoto E Atividades Relacionadas', 'E', 'Água, Esgoto, Atividades De Gestão De Resíduos E Descontaminação', TRUE, TRUE, TRUE, TRUE),
('3702900', 'Atividades relacionadas a esgoto, exceto a gestão de redes', '37029', 'Atividades relacionadas a esgoto, exceto a gestão de redes', '370', 'Esgoto e atividades relacionadas', '37', 'Esgoto E Atividades Relacionadas', 'E', 'Água, Esgoto, Atividades De Gestão De Resíduos E Descontaminação', TRUE, TRUE, TRUE, TRUE),
('3811400', 'Coleta de resíduos não perigosos', '38114', 'Coleta de resíduos não perigosos', '381', 'Coleta de resíduos', '38', 'Coleta, Tratamento E Disposição De Resíduos; Recuperação De Materiais', 'E', 'Água, Esgoto, Atividades De Gestão De Resíduos E Descontaminação', TRUE, TRUE, TRUE, TRUE),
('3812200', 'Coleta de resíduos perigosos', '38122', 'Coleta de resíduos perigosos', '381', 'Coleta de resíduos', '38', 'Coleta, Tratamento E Disposição De Resíduos; Recuperação De Materiais', 'E', 'Água, Esgoto, Atividades De Gestão De Resíduos E Descontaminação', TRUE, TRUE, TRUE, TRUE),
('3821100', 'Tratamento e disposição de resíduos não perigosos', '38211', 'Tratamento e disposição de resíduos não perigosos', '382', 'Tratamento e disposição de resíduos', '38', 'Coleta, Tratamento E Disposição De Resíduos; Recuperação De Materiais', 'E', 'Água, Esgoto, Atividades De Gestão De Resíduos E Descontaminação', TRUE, TRUE, TRUE, TRUE),
('3822000', 'Tratamento e disposição de resíduos perigosos', '38220', 'Tratamento e disposição de resíduos perigosos', '382', 'Tratamento e disposição de resíduos', '38', 'Coleta, Tratamento E Disposição De Resíduos; Recuperação De Materiais', 'E', 'Água, Esgoto, Atividades De Gestão De Resíduos E Descontaminação', TRUE, TRUE, TRUE, TRUE),
('3831901', 'Recuperação de sucatas de alumínio', '38319', 'Recuperação de materiais metálicos', '383', 'Recuperação de materiais', '38', 'Coleta, Tratamento E Disposição De Resíduos; Recuperação De Materiais', 'E', 'Água, Esgoto, Atividades De Gestão De Resíduos E Descontaminação', TRUE, TRUE, TRUE, TRUE),
('3831999', 'Recuperação de materiais metálicos, exceto alumínio', '38319', 'Recuperação de materiais metálicos', '383', 'Recuperação de materiais', '38', 'Coleta, Tratamento E Disposição De Resíduos; Recuperação De Materiais', 'E', 'Água, Esgoto, Atividades De Gestão De Resíduos E Descontaminação', TRUE, TRUE, TRUE, TRUE),
('3832700', 'Recuperação de materiais plásticos', '38327', 'Recuperação de materiais plásticos', '383', 'Recuperação de materiais', '38', 'Coleta, Tratamento E Disposição De Resíduos; Recuperação De Materiais', 'E', 'Água, Esgoto, Atividades De Gestão De Resíduos E Descontaminação', TRUE, TRUE, TRUE, TRUE),
('3839499', 'Recuperação de materiais não especificados anteriormente', '38394', 'Recuperação de materiais não especificados anteriormente', '383', 'Recuperação de materiais', '38', 'Coleta, Tratamento E Disposição De Resíduos; Recuperação De Materiais', 'E', 'Água, Esgoto, Atividades De Gestão De Resíduos E Descontaminação', TRUE, TRUE, TRUE, TRUE),
('3839401', 'Usinas de compostagem', '38394', 'Recuperação de materiais não especificados anteriormente', '383', 'Recuperação de materiais', '38', 'Coleta, Tratamento E Disposição De Resíduos; Recuperação De Materiais', 'E', 'Água, Esgoto, Atividades De Gestão De Resíduos E Descontaminação', TRUE, TRUE, TRUE, TRUE),
('3900500', 'Descontaminação e outros serviços de gestão de resíduos', '39005', 'Descontaminação e outros serviços de gestão de resíduos', '390', 'Descontaminação e outros serviços de gestão de resíduos', '39', 'Descontaminação E Outros Serviços De Gestão De Resíduos', 'E', 'Água, Esgoto, Atividades De Gestão De Resíduos E Descontaminação', TRUE, TRUE, TRUE, TRUE),
('4110700', 'Incorporação de empreendimentos imobiliários', '41107', 'Incorporação de empreendimentos imobiliários', '411', 'Incorporação de empreendimentos imobiliários', '41', 'Construção De Edifícios', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4120400', 'Construção de edifícios', '41204', 'Construção de edifícios', '412', 'Construção de edifícios', '41', 'Construção De Edifícios', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4211101', 'Construção de rodovias e ferrovias', '42111', 'Construção de rodovias e ferrovias', '421', 'Construção de rodovias, ferrovias, obras urbanas e obras-de-arte especiais', '42', 'Obras De Infraestrutura', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4211102', 'Pintura para sinalização em pistas rodoviárias e aeroportos', '42111', 'Construção de rodovias e ferrovias', '421', 'Construção de rodovias, ferrovias, obras urbanas e obras-de-arte especiais', '42', 'Obras De Infraestrutura', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4212000', 'Construção de obras de arte especiais', '42120', 'Construção de obras de arte especiais', '421', 'Construção de rodovias, ferrovias, obras urbanas e obras-de-arte especiais', '42', 'Obras De Infraestrutura', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4213800', 'Obras de urbanização - ruas, praças e calçadas', '42138', 'Obras de urbanização - ruas, praças e calçadas', '421', 'Construção de rodovias, ferrovias, obras urbanas e obras-de-arte especiais', '42', 'Obras De Infraestrutura', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4221901', 'Construção de barragens e represas para geração de energia
elétrica', '42219', 'Obras para geração e distribuição de energia elétrica e para telecomunicações', '422', 'Obras de infraestrutura para energia elétrica, telecomunicações, água, esgoto e transporte por dutos', '42', 'Obras De Infraestrutura', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4221904', 'Construção de estações e redes de telecomunicações', '42219', 'Obras para geração e distribuição de energia elétrica e para telecomunicações', '422', 'Obras de infraestrutura para energia elétrica, telecomunicações, água, esgoto e transporte por dutos', '42', 'Obras De Infraestrutura', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4221905', 'Manutenção de estações e redes de telecomunicações', '42219', 'Obras para geração e distribuição de energia elétrica e para telecomunicações', '422', 'Obras de infraestrutura para energia elétrica, telecomunicações, água, esgoto e transporte por dutos', '42', 'Obras De Infraestrutura', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4221902', 'Construção de estações e redes de distribuição de energia elétrica', '42219', 'Obras para geração e distribuição de energia elétrica e para telecomunicações', '422', 'Obras de infraestrutura para energia elétrica, telecomunicações, água, esgoto e transporte por dutos', '42', 'Obras De Infraestrutura', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4221903', 'Manutenção de redes de distribuição de energia elétrica', '42219', 'Obras para geração e distribuição de energia elétrica e para telecomunicações', '422', 'Obras de infraestrutura para energia elétrica, telecomunicações, água, esgoto e transporte por dutos', '42', 'Obras De Infraestrutura', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4222702', 'Obras de irrigação', '42227', 'Construção de redes de abastecimento de água, coleta de esgoto e construções correlatas', '422', 'Obras de infraestrutura para energia elétrica, telecomunicações, água, esgoto e transporte por dutos', '42', 'Obras De Infraestrutura', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4222701', 'Construção de redes de abastecimento de água, coleta de esgoto e construções correlatas, exceto obras de irrigação', '42227', 'Construção de redes de abastecimento de água, coleta de esgoto e construções correlatas', '422', 'Obras de infraestrutura para energia elétrica, telecomunicações, água, esgoto e transporte por dutos', '42', 'Obras De Infraestrutura', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4223500', 'Construção de redes de transportes por dutos, exceto para água e esgoto', '42235', 'Construção de redes de transportes por dutos, exceto para água e esgoto', '422', 'Obras de infraestrutura para energia elétrica, telecomunicações, água, esgoto e transporte por dutos', '42', 'Obras De Infraestrutura', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4291000', 'Obras portuárias, marítimas e fluviais', '42910', 'Obras portuárias, marítimas e fluviais', '429', 'Construção de outras obras de infraestrutura', '42', 'Obras De Infraestrutura', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4292802', 'Obras de montagem industrial', '42928', 'Montagem de instalações industriais e de estruturas metálicas', '429', 'Construção de outras obras de infraestrutura', '42', 'Obras De Infraestrutura', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4292801', 'Montagem de estruturas metálicas', '42928', 'Montagem de instalações industriais e de estruturas metálicas', '429', 'Construção de outras obras de infraestrutura', '42', 'Obras De Infraestrutura', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4299599', 'Outras obras de engenharia civil não especificadas anteriormente', '42995', 'Obras de engenharia civil não especificadas anteriormente', '429', 'Construção de outras obras de infraestrutura', '42', 'Obras De Infraestrutura', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4299501', 'Construção de instalações esportivas e recreativas', '42995', 'Obras de engenharia civil não especificadas anteriormente', '429', 'Construção de outras obras de infraestrutura', '42', 'Obras De Infraestrutura', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4311802', 'Preparação de canteiro e limpeza de terreno', '43118', 'Demolição e preparação de canteiros de obras', '431', 'Demolição e preparação do terreno', '43', 'Serviços Especializados Para Construção', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4311801', 'Demolição de edifícios e outras estruturas', '43118', 'Demolição e preparação de canteiros de obras', '431', 'Demolição e preparação do terreno', '43', 'Serviços Especializados Para Construção', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4312600', 'Perfurações e sondagens', '43126', 'Perfurações e sondagens', '431', 'Demolição e preparação do terreno', '43', 'Serviços Especializados Para Construção', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4313400', 'Obras de terraplenagem', '43134', 'Obras de terraplenagem', '431', 'Demolição e preparação do terreno', '43', 'Serviços Especializados Para Construção', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4319300', 'Serviços de preparação do terreno não especificados anteriormente', '43193', 'Serviços de preparação do terreno não especificados anteriormente', '431', 'Demolição e preparação do terreno', '43', 'Serviços Especializados Para Construção', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4321500', 'Instalação e manutenção elétrica', '43215', 'Instalações elétricas', '432', 'Instalações elétricas, hidráulicas e outras instalações em construções', '43', 'Serviços Especializados Para Construção', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4322301', 'Instalações hidráulicas, sanitárias e de gás', '43223', 'Instalações hidráulicas, de sistemas de ventilação e
refrigeração', '432', 'Instalações elétricas, hidráulicas e outras instalações em construções', '43', 'Serviços Especializados Para Construção', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4322302', 'Instalação e manutenção de sistemas centrais de ar condicionado, de ventilação e refrigeração', '43223', 'Instalações hidráulicas, de sistemas de ventilação e
refrigeração', '432', 'Instalações elétricas, hidráulicas e outras instalações em construções', '43', 'Serviços Especializados Para Construção', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4322303', 'Instalações de sistema de prevenção contra incêndio', '43223', 'Instalações hidráulicas, de sistemas de ventilação e
refrigeração', '432', 'Instalações elétricas, hidráulicas e outras instalações em construções', '43', 'Serviços Especializados Para Construção', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4329101', 'Instalação de painéis publicitários', '43291', 'Obras de instalações em construções não especificadas anteriormente', '432', 'Instalações elétricas, hidráulicas e outras instalações em construções', '43', 'Serviços Especializados Para Construção', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4329104', 'Montagem e instalação de sistemas e equipamentos de iluminação e sinalização em vias públicas, portos e aeroportos', '43291', 'Obras de instalações em construções não especificadas anteriormente', '432', 'Instalações elétricas, hidráulicas e outras instalações em construções', '43', 'Serviços Especializados Para Construção', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4329199', 'Outras obras de instalações em construções não especificadas anteriormente', '43291', 'Obras de instalações em construções não especificadas anteriormente', '432', 'Instalações elétricas, hidráulicas e outras instalações em construções', '43', 'Serviços Especializados Para Construção', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4329102', 'Instalação de equipamentos para orientação à navegação marítima, fluvial e lacustre', '43291', 'Obras de instalações em construções não especificadas anteriormente', '432', 'Instalações elétricas, hidráulicas e outras instalações em construções', '43', 'Serviços Especializados Para Construção', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4329105', 'Tratamentos térmicos, acústicos ou de vibração', '43291', 'Obras de instalações em construções não especificadas anteriormente', '432', 'Instalações elétricas, hidráulicas e outras instalações em construções', '43', 'Serviços Especializados Para Construção', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4329103', 'Instalação, manutenção e reparação de elevadores, escadas e esteiras rolantes', '43291', 'Obras de instalações em construções não especificadas anteriormente', '432', 'Instalações elétricas, hidráulicas e outras instalações em construções', '43', 'Serviços Especializados Para Construção', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4330402', 'Instalação de portas, janelas, tetos, divisórias e armários embutidos de qualquer material', '43304', 'Obras de acabamento', '433', 'Obras de acabamento', '43', 'Serviços Especializados Para Construção', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4330499', 'Outras obras de acabamento da construção', '43304', 'Obras de acabamento', '433', 'Obras de acabamento', '43', 'Serviços Especializados Para Construção', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4330404', 'Serviços de pintura de edifícios em geral', '43304', 'Obras de acabamento', '433', 'Obras de acabamento', '43', 'Serviços Especializados Para Construção', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4330401', 'Impermeabilização em obras de engenharia civil', '43304', 'Obras de acabamento', '433', 'Obras de acabamento', '43', 'Serviços Especializados Para Construção', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4330405', 'Aplicação de revestimentos e de resinas em interiores e exteriores', '43304', 'Obras de acabamento', '433', 'Obras de acabamento', '43', 'Serviços Especializados Para Construção', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4330403', 'Obras de acabamento em gesso e estuque', '43304', 'Obras de acabamento', '433', 'Obras de acabamento', '43', 'Serviços Especializados Para Construção', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4391600', 'Obras de fundações', '43916', 'Obras de fundações', '439', 'Outros serviços especializados para construção', '43', 'Serviços Especializados Para Construção', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4399103', 'Obras de alvenaria', '43991', 'Serviços especializados para construção não especificados anteriormente', '439', 'Outros serviços especializados para construção', '43', 'Serviços Especializados Para Construção', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4399101', 'Administração de obras', '43991', 'Serviços especializados para construção não especificados anteriormente', '439', 'Outros serviços especializados para construção', '43', 'Serviços Especializados Para Construção', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4399104', 'Serviços de operação e fornecimento de equipamentos para transporte e elevação de cargas e pessoas para uso em obras', '43991', 'Serviços especializados para construção não especificados anteriormente', '439', 'Outros serviços especializados para construção', '43', 'Serviços Especializados Para Construção', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4399102', 'Montagem e desmontagem de andaimes e outras estruturas temporárias', '43991', 'Serviços especializados para construção não especificados anteriormente', '439', 'Outros serviços especializados para construção', '43', 'Serviços Especializados Para Construção', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4399199', 'Serviços especializados para construção não especificados anteriormente', '43991', 'Serviços especializados para construção não especificados anteriormente', '439', 'Outros serviços especializados para construção', '43', 'Serviços Especializados Para Construção', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4399105', 'Perfuração e construção de poços de água', '43991', 'Serviços especializados para construção não especificados anteriormente', '439', 'Outros serviços especializados para construção', '43', 'Serviços Especializados Para Construção', 'F', 'Construção', TRUE, TRUE, TRUE, TRUE),
('4511102', 'Comércio a varejo de automóveis, camionetas e utilitários usados', '45111', 'Comércio a varejo e por atacado de veículos automotores', '451', 'Comércio de veículos automotores', '45', 'Comércio E Reparação De Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4511103', 'Comércio por atacado de automóveis, camionetas e utilitários novos e usados', '45111', 'Comércio a varejo e por atacado de veículos automotores', '451', 'Comércio de veículos automotores', '45', 'Comércio E Reparação De Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4511105', 'Comércio por atacado de reboques e semireboques novos e usados', '45111', 'Comércio a varejo e por atacado de veículos automotores', '451', 'Comércio de veículos automotores', '45', 'Comércio E Reparação De Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4511101', 'Comércio a varejo de automóveis, camionetas e utilitários novos', '45111', 'Comércio a varejo e por atacado de veículos automotores', '451', 'Comércio de veículos automotores', '45', 'Comércio E Reparação De Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4511104', 'Comércio por atacado de caminhões novos e usados', '45111', 'Comércio a varejo e por atacado de veículos automotores', '451', 'Comércio de veículos automotores', '45', 'Comércio E Reparação De Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4511106', 'Comércio por atacado de ônibus e micro-ônibus novos e usados', '45111', 'Comércio a varejo e por atacado de veículos automotores', '451', 'Comércio de veículos automotores', '45', 'Comércio E Reparação De Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4512902', 'Comércio sob consignação de veículos automotores', '45129', 'Representantes comerciais e agentes do comércio de veículos automotores', '451', 'Comércio de veículos automotores', '45', 'Comércio E Reparação De Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4512901', 'Representantes comerciais e agentes do comércio de veículos automotores', '45129', 'Representantes comerciais e agentes do comércio de veículos automotores', '451', 'Comércio de veículos automotores', '45', 'Comércio E Reparação De Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4520003', 'Serviços de manutenção e reparação elétrica de veículos automotores', '45200', 'Manutenção e reparação de veículos automotores', '452', 'Manutenção e reparação de veículos automotores', '45', 'Comércio E Reparação De Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4520002', 'Serviços de lanternagem ou funilaria e pintura de veículos automotores', '45200', 'Manutenção e reparação de veículos automotores', '452', 'Manutenção e reparação de veículos automotores', '45', 'Comércio E Reparação De Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4520001', 'Serviços de manutenção e reparação mecânica de veículos automotores', '45200', 'Manutenção e reparação de veículos automotores', '452', 'Manutenção e reparação de veículos automotores', '45', 'Comércio E Reparação De Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4520007', 'Serviços de instalação, manutenção e reparação de acessórios para veículos automotores', '45200', 'Manutenção e reparação de veículos automotores', '452', 'Manutenção e reparação de veículos automotores', '45', 'Comércio E Reparação De Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4520004', 'Serviços de alinhamento e balanceamento de veículos automotores', '45200', 'Manutenção e reparação de veículos automotores', '452', 'Manutenção e reparação de veículos automotores', '45', 'Comércio E Reparação De Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4520008', 'Serviços de capotaria', '45200', 'Manutenção e reparação de veículos automotores', '452', 'Manutenção e reparação de veículos automotores', '45', 'Comércio E Reparação De Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', FALSE, TRUE, TRUE, TRUE),
('4520005', 'Serviços de lavagem, lubrificação e polimento de veículos automotores', '45200', 'Manutenção e reparação de veículos automotores', '452', 'Manutenção e reparação de veículos automotores', '45', 'Comércio E Reparação De Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4520006', 'Serviços de borracharia para veículos automotores', '45200', 'Manutenção e reparação de veículos automotores', '452', 'Manutenção e reparação de veículos automotores', '45', 'Comércio E Reparação De Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4530703', 'Comércio a varejo de peças e acessórios novos para veículos automotores', '45307', 'Comércio de peças e acessórios para veículos automotores', '453', 'Comércio de peças e acessórios para veículos automotores', '45', 'Comércio E Reparação De Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4530702', 'Comércio por atacado de pneumáticos e câmaras-de-ar', '45307', 'Comércio de peças e acessórios para veículos automotores', '453', 'Comércio de peças e acessórios para veículos automotores', '45', 'Comércio E Reparação De Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4530705', 'Comércio a varejo de pneumáticos e câmaras-de-ar', '45307', 'Comércio de peças e acessórios para veículos automotores', '453', 'Comércio de peças e acessórios para veículos automotores', '45', 'Comércio E Reparação De Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4530701', 'Comércio por atacado de peças e acessórios novos para veículos automotores', '45307', 'Comércio de peças e acessórios para veículos automotores', '453', 'Comércio de peças e acessórios para veículos automotores', '45', 'Comércio E Reparação De Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4530706', 'Representantes comerciais e agentes do comércio de peças e acessórios novos e usados para veículos automotores', '45307', 'Comércio de peças e acessórios para veículos automotores', '453', 'Comércio de peças e acessórios para veículos automotores', '45', 'Comércio E Reparação De Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4530704', 'Comércio a varejo de peças e acessórios usados para veículos automotores', '45307', 'Comércio de peças e acessórios para veículos automotores', '453', 'Comércio de peças e acessórios para veículos automotores', '45', 'Comércio E Reparação De Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4541207', 'Comércio a varejo de peças e acessórios usados para motocicletas e motonetas', '45412', 'Comércio por atacado e a varejo de motocicletas, peças e acessórios', '454', 'Comércio, manutenção e reparação de motocicletas, peças e acessórios', '45', 'Comércio E Reparação De Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', FALSE, FALSE, FALSE, TRUE),
('4541202', 'Comércio por atacado de peças e acessórios para motocicletas e motonetas', '45412', 'Comércio por atacado e a varejo de motocicletas, peças e acessórios', '454', 'Comércio, manutenção e reparação de motocicletas, peças e acessórios', '45', 'Comércio E Reparação De Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4541203', 'Comércio a varejo de motocicletas e motonetas novas', '45412', 'Comércio por atacado e a varejo de motocicletas, peças e acessórios', '454', 'Comércio, manutenção e reparação de motocicletas, peças e acessórios', '45', 'Comércio E Reparação De Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4541201', 'Comércio por atacado de motocicletas e motonetas', '45412', 'Comércio por atacado e a varejo de motocicletas, peças e acessórios', '454', 'Comércio, manutenção e reparação de motocicletas, peças e acessórios', '45', 'Comércio E Reparação De Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4541206', 'Comércio a varejo de peças e acessórios novos para motocicletas e motonetas', '45412', 'Comércio por atacado e a varejo de motocicletas, peças e acessórios', '454', 'Comércio, manutenção e reparação de motocicletas, peças e acessórios', '45', 'Comércio E Reparação De Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', FALSE, FALSE, FALSE, TRUE),
('4541204', 'Comércio a varejo de motocicletas e motonetas usadas', '45412', 'Comércio por atacado e a varejo de motocicletas, peças e acessórios', '454', 'Comércio, manutenção e reparação de motocicletas, peças e acessórios', '45', 'Comércio E Reparação De Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4541205', 'Comércio a varejo de peças e acessórios para motocicletas e motonetas', '45412', 'Comércio por atacado e a varejo de motocicletas, peças e acessórios', '454', 'Comércio, manutenção e reparação de motocicletas, peças e acessórios', '45', 'Comércio E Reparação De Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, FALSE),
('4542102', 'Comércio sob consignação de motocicletas e motonetas', '45421', 'Representantes comerciais e agentes do comércio de motocicletas, peças e acessórios', '454', 'Comércio, manutenção e reparação de motocicletas, peças e acessórios', '45', 'Comércio E Reparação De Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4542101', 'Representantes comerciais e agentes do comércio de motocicletas e motonetas, peças e acessórios', '45421', 'Representantes comerciais e agentes do comércio de motocicletas, peças e acessórios', '454', 'Comércio, manutenção e reparação de motocicletas, peças e acessórios', '45', 'Comércio E Reparação De Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4543900', 'Manutenção e reparação de motocicletas e motonetas', '45439', 'Manutenção e reparação de motocicletas', '454', 'Comércio, manutenção e reparação de motocicletas, peças e acessórios', '45', 'Comércio E Reparação De Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4611700', 'Representantes comerciais e agentes do comércio de matérias-primas agrícolas e animais vivos', '46117', 'Representantes comerciais e agentes do comércio de matérias-primas agrícolas e animais vivos', '461', 'Representantes comerciais e agentes do comércio, exceto de veículos automotores e motocicletas', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4612500', 'Representantes comerciais e agentes do comércio de combustíveis, minerais, produtos siderúrgicos e químicos', '46125', 'Representantes comerciais e agentes do comércio de combustíveis, minerais, produtos siderúrgicos e químicos', '461', 'Representantes comerciais e agentes do comércio, exceto de veículos automotores e motocicletas', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4613300', 'Representantes comerciais e agentes do comércio de madeira, material de construção e ferragens', '46133', 'Representantes comerciais e agentes do comércio de madeira, material de construção e ferragens', '461', 'Representantes comerciais e agentes do comércio, exceto de veículos automotores e motocicletas', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4614100', 'Representantes comerciais e agentes do comércio de máquinas, equipamentos, embarcações e aeronaves', '46141', 'Representantes comerciais e agentes do comércio de máquinas, equipamentos, embarcações e aeronaves', '461', 'Representantes comerciais e agentes do comércio, exceto de veículos automotores e motocicletas', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4615000', 'Representantes comerciais e agentes do comércio de eletrodomésticos, móveis e artigos de uso doméstico', '46150', 'Representantes comerciais e agentes do comércio de eletrodomésticos, móveis e artigos de uso doméstico', '461', 'Representantes comerciais e agentes do comércio, exceto de veículos automotores e motocicletas', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4616800', 'Representantes comerciais e agentes do comércio de têxteis, vestuário, calçados e artigos de viagem', '46168', 'Representantes comerciais e agentes do comércio de têxteis, vestuário, calçados e artigos de viagem', '461', 'Representantes comerciais e agentes do comércio, exceto de veículos automotores e motocicletas', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4617600', 'Representantes comerciais e agentes do comércio de produtos alimentícios, bebidas e fumo', '46176', 'Representantes comerciais e agentes do comércio de produtos alimentícios, bebidas e fumo', '461', 'Representantes comerciais e agentes do comércio, exceto de veículos automotores e motocicletas', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4618402', 'Representantes comerciais e agentes do comércio de instrumentos e materiais odonto-médico-hospitalares', '46184', 'Representantes comerciais e agentes do comércio especializado em produtos não especificados anteriormente', '461', 'Representantes comerciais e agentes do comércio, exceto de veículos automotores e motocicletas', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4618403', 'Representantes comerciais e agentes do comércio de jornais, revistas e outras publicações', '46184', 'Representantes comerciais e agentes do comércio especializado em produtos não especificados anteriormente', '461', 'Representantes comerciais e agentes do comércio, exceto de veículos automotores e motocicletas', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4618499', 'Outros representantes comerciais e agentes do comércio especializado em produtos não especificados anteriormente', '46184', 'Representantes comerciais e agentes do comércio especializado em produtos não especificados anteriormente', '461', 'Representantes comerciais e agentes do comércio, exceto de veículos automotores e motocicletas', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4618401', 'Representantes comerciais e agentes do comércio de medicamentos, cosméticos e produtos de perfumaria', '46184', 'Representantes comerciais e agentes do comércio especializado em produtos não especificados anteriormente', '461', 'Representantes comerciais e agentes do comércio, exceto de veículos automotores e motocicletas', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4619200', 'Representantes comerciais e agentes do comércio de mercadorias em geral não especializado', '46192', 'Representantes comerciais e agentes do comércio de mercadorias em geral não especializado', '461', 'Representantes comerciais e agentes do comércio, exceto de veículos automotores e motocicletas', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4621400', 'Comércio atacadista de café em grão', '46214', 'Comércio atacadista de café em grão', '462', 'Comércio atacadista de matérias-primas agrícolas e animais vivos', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4622200', 'Comércio atacadista de soja', '46222', 'Comércio atacadista de soja', '462', 'Comércio atacadista de matérias-primas agrícolas e animais vivos', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4623101', 'Comércio atacadista de animais vivos', '46231', 'Comércio atacadista de animais vivos, alimentos para animais e matérias-primas agrícolas, exceto café e soja', '462', 'Comércio atacadista de matérias-primas agrícolas e animais vivos', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4623103', 'Comércio atacadista de algodão', '46231', 'Comércio atacadista de animais vivos, alimentos para animais e matérias-primas agrícolas, exceto café e soja', '462', 'Comércio atacadista de matérias-primas agrícolas e animais vivos', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4623106', 'Comércio atacadista de sementes, flores, plantas e gramas', '46231', 'Comércio atacadista de animais vivos, alimentos para animais e matérias-primas agrícolas, exceto café e soja', '462', 'Comércio atacadista de matérias-primas agrícolas e animais vivos', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4623102', 'Comércio atacadista de couros, lãs, peles e outros subprodutos não comestíveis de origem animal', '46231', 'Comércio atacadista de animais vivos, alimentos para animais e matérias-primas agrícolas, exceto café e soja', '462', 'Comércio atacadista de matérias-primas agrícolas e animais vivos', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4623108', 'Comércio atacadista de matérias-primas agrícolas com atividade de fracionamento e acondicionamento associada', '46231', 'Comércio atacadista de animais vivos, alimentos para animais e matérias-primas agrícolas, exceto café e soja', '462', 'Comércio atacadista de matérias-primas agrícolas e animais vivos', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4623105', 'Comércio atacadista de cacau', '46231', 'Comércio atacadista de animais vivos, alimentos para animais e matérias-primas agrícolas, exceto café e soja', '462', 'Comércio atacadista de matérias-primas agrícolas e animais vivos', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4623107', 'Comércio atacadista de sisal', '46231', 'Comércio atacadista de animais vivos, alimentos para animais e matérias-primas agrícolas, exceto café e soja', '462', 'Comércio atacadista de matérias-primas agrícolas e animais vivos', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4623104', 'Comércio atacadista de fumo em folha não beneficiado', '46231', 'Comércio atacadista de animais vivos, alimentos para animais e matérias-primas agrícolas, exceto café e soja', '462', 'Comércio atacadista de matérias-primas agrícolas e animais vivos', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4623199', 'Comércio atacadista de matérias-primas agrícolas não especificadas anteriormente', '46231', 'Comércio atacadista de animais vivos, alimentos para animais e matérias-primas agrícolas, exceto café e soja', '462', 'Comércio atacadista de matérias-primas agrícolas e animais vivos', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4623109', 'Comércio atacadista de alimentos para animais', '46231', 'Comércio atacadista de animais vivos, alimentos para animais e matérias-primas agrícolas, exceto café e soja', '462', 'Comércio atacadista de matérias-primas agrícolas e animais vivos', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4631100', 'Comércio atacadista de leite e laticínios', '46311', 'Comércio atacadista de leite e laticínios', '463', 'Comércio atacadista especializado em produtos alimentícios, bebidas e fumo', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4632003', 'Comércio atacadista de cereais e leguminosas beneficiados, farinhas, amidos e féculas, com atividade de fracionamento e acondicionamento associada', '46320', 'Comércio atacadista de cereais e leguminosas beneficiados, farinhas, amidos e féculas', '463', 'Comércio atacadista especializado em produtos alimentícios, bebidas e fumo', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4632002', 'Comércio atacadista de farinhas, amidos e féculas', '46320', 'Comércio atacadista de cereais e leguminosas beneficiados, farinhas, amidos e féculas', '463', 'Comércio atacadista especializado em produtos alimentícios, bebidas e fumo', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4632001', 'Comércio atacadista de cereais e leguminosas beneficiados', '46320', 'Comércio atacadista de cereais e leguminosas beneficiados, farinhas, amidos e féculas', '463', 'Comércio atacadista especializado em produtos alimentícios, bebidas e fumo', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4633803', 'Comércio atacadista de coelhos e outros pequenos animais vivos para alimentação', '46338', 'Comércio atacadista de hortifrutigranjeiros', '463', 'Comércio atacadista especializado em produtos alimentícios, bebidas e fumo', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4633801', 'Comércio atacadista de frutas, verduras, raízes, tubérculos, hortaliças e legumes frescos', '46338', 'Comércio atacadista de hortifrutigranjeiros', '463', 'Comércio atacadista especializado em produtos alimentícios, bebidas e fumo', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4633802', 'Comércio atacadista de aves vivas e ovos', '46338', 'Comércio atacadista de hortifrutigranjeiros', '463', 'Comércio atacadista especializado em produtos alimentícios, bebidas e fumo', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4634601', 'Comércio atacadista de carnes bovinas e suínas e derivados', '46346', 'Comércio atacadista de carnes, produtos da carne e pescado', '463', 'Comércio atacadista especializado em produtos alimentícios, bebidas e fumo', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4634603', 'Comércio atacadista de pescados e frutos do mar', '46346', 'Comércio atacadista de carnes, produtos da carne e pescado', '463', 'Comércio atacadista especializado em produtos alimentícios, bebidas e fumo', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4634699', 'Comércio atacadista de carnes e derivados de outros animais', '46346', 'Comércio atacadista de carnes, produtos da carne e pescado', '463', 'Comércio atacadista especializado em produtos alimentícios, bebidas e fumo', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4634602', 'Comércio atacadista de aves abatidas e derivados', '46346', 'Comércio atacadista de carnes, produtos da carne e pescado', '463', 'Comércio atacadista especializado em produtos alimentícios, bebidas e fumo', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4635499', 'Comércio atacadista de bebidas não especificadas anteriormente', '46354', 'Comércio atacadista de bebidas', '463', 'Comércio atacadista especializado em produtos alimentícios, bebidas e fumo', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4635401', 'Comércio atacadista de água mineral', '46354', 'Comércio atacadista de bebidas', '463', 'Comércio atacadista especializado em produtos alimentícios, bebidas e fumo', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4635403', 'Comércio atacadista de bebidas com atividade de fracionamento e acondicionamento associada', '46354', 'Comércio atacadista de bebidas', '463', 'Comércio atacadista especializado em produtos alimentícios, bebidas e fumo', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4635402', 'Comércio atacadista de cerveja, chope e refrigerante', '46354', 'Comércio atacadista de bebidas', '463', 'Comércio atacadista especializado em produtos alimentícios, bebidas e fumo', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4636201', 'Comércio atacadista de fumo beneficiado', '46362', 'Comércio atacadista de produtos do fumo', '463', 'Comércio atacadista especializado em produtos alimentícios, bebidas e fumo', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4636202', 'Comércio atacadista de cigarros, cigarrilhas e charutos', '46362', 'Comércio atacadista de produtos do fumo', '463', 'Comércio atacadista especializado em produtos alimentícios, bebidas e fumo', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4637106', 'Comércio atacadista de sorvetes', '46371', 'Comércio atacadista especializado em produtos alimentícios não especificados anteriormente', '463', 'Comércio atacadista especializado em produtos alimentícios, bebidas e fumo', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4637105', 'Comércio atacadista de massas alimentícias', '46371', 'Comércio atacadista especializado em produtos alimentícios não especificados anteriormente', '463', 'Comércio atacadista especializado em produtos alimentícios, bebidas e fumo', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4637102', 'Comércio atacadista de açúcar', '46371', 'Comércio atacadista especializado em produtos alimentícios não especificados anteriormente', '463', 'Comércio atacadista especializado em produtos alimentícios, bebidas e fumo', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4637107', 'Comércio atacadista de chocolates, confeitos, balas, bombons e semelhantes', '46371', 'Comércio atacadista especializado em produtos alimentícios não especificados anteriormente', '463', 'Comércio atacadista especializado em produtos alimentícios, bebidas e fumo', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4637103', 'Comércio atacadista de óleos e gorduras', '46371', 'Comércio atacadista especializado em produtos alimentícios não especificados anteriormente', '463', 'Comércio atacadista especializado em produtos alimentícios, bebidas e fumo', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4637104', 'Comércio atacadista de pães, bolos, biscoitos e similares', '46371', 'Comércio atacadista especializado em produtos alimentícios não especificados anteriormente', '463', 'Comércio atacadista especializado em produtos alimentícios, bebidas e fumo', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4637101', 'Comércio atacadista de café torrado, moído e solúvel', '46371', 'Comércio atacadista especializado em produtos alimentícios não especificados anteriormente', '463', 'Comércio atacadista especializado em produtos alimentícios, bebidas e fumo', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4637199', 'Comércio atacadista especializado em outros produtos alimentícios não especificados anteriormente', '46371', 'Comércio atacadista especializado em produtos alimentícios não especificados anteriormente', '463', 'Comércio atacadista especializado em produtos alimentícios, bebidas e fumo', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4639702', 'Comércio atacadista de produtos alimentícios em geral, com atividade de fracionamento e acondicionamento associada', '46397', 'Comércio atacadista de produtos alimentícios em geral', '463', 'Comércio atacadista especializado em produtos alimentícios, bebidas e fumo', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4639701', 'Comércio atacadista de produtos alimentícios em geral', '46397', 'Comércio atacadista de produtos alimentícios em geral', '463', 'Comércio atacadista especializado em produtos alimentícios, bebidas e fumo', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4641903', 'Comércio atacadista de artigos de armarinho', '46419', 'Comércio atacadista de tecidos, artefatos de tecidos e de armarinho', '464', 'Comércio atacadista de produtos de consumo não alimentar', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4641902', 'Comércio atacadista de artigos de cama, mesa e banho', '46419', 'Comércio atacadista de tecidos, artefatos de tecidos e de armarinho', '464', 'Comércio atacadista de produtos de consumo não alimentar', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4641901', 'Comércio atacadista de tecidos', '46419', 'Comércio atacadista de tecidos, artefatos de tecidos e de armarinho', '464', 'Comércio atacadista de produtos de consumo não alimentar', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4642702', 'Comércio atacadista de roupas e acessórios para uso profissional e de segurança do trabalho', '46427', 'Comércio atacadista de artigos do vestuário e acessórios', '464', 'Comércio atacadista de produtos de consumo não alimentar', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4642701', 'Comércio atacadista de artigos do vestuário e acessórios, exceto profissionais e de segurança', '46427', 'Comércio atacadista de artigos do vestuário e acessórios', '464', 'Comércio atacadista de produtos de consumo não alimentar', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4643502', 'Comércio atacadista de bolsas, malas e artigos de viagem', '46435', 'Comércio atacadista de calçados e artigos de viagem', '464', 'Comércio atacadista de produtos de consumo não alimentar', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4643501', 'Comércio atacadista de calçados', '46435', 'Comércio atacadista de calçados e artigos de viagem', '464', 'Comércio atacadista de produtos de consumo não alimentar', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4644301', 'Comércio atacadista de medicamentos e drogas de uso humano', '46443', 'Comércio atacadista de produtos farmacêuticos para uso humano e veterinário', '464', 'Comércio atacadista de produtos de consumo não alimentar', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4644302', 'Comércio atacadista de medicamentos e drogas de uso veterinário', '46443', 'Comércio atacadista de produtos farmacêuticos para uso humano e veterinário', '464', 'Comércio atacadista de produtos de consumo não alimentar', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4645102', 'Comércio atacadista de próteses e artigos de ortopedia', '46451', 'Comércio atacadista de instrumentos e materiais para uso médico, cirúrgico, ortopédico e odontológico', '464', 'Comércio atacadista de produtos de consumo não alimentar', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4645103', 'Comércio atacadista de produtos odontológicos', '46451', 'Comércio atacadista de instrumentos e materiais para uso médico, cirúrgico, ortopédico e odontológico', '464', 'Comércio atacadista de produtos de consumo não alimentar', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4645101', 'Comércio atacadista de instrumentos e materiais para uso médico, cirúrgico, hospitalar e de laboratórios', '46451', 'Comércio atacadista de instrumentos e materiais para uso médico, cirúrgico, ortopédico e odontológico', '464', 'Comércio atacadista de produtos de consumo não alimentar', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4646001', 'Comércio atacadista de cosméticos e produtos de perfumaria', '46460', 'Comércio atacadista de cosméticos, produtos de perfumaria e de higiene pessoal', '464', 'Comércio atacadista de produtos de consumo não alimentar', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4646002', 'Comércio atacadista de produtos de higiene pessoal', '46460', 'Comércio atacadista de cosméticos, produtos de perfumaria e de higiene pessoal', '464', 'Comércio atacadista de produtos de consumo não alimentar', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4647801', 'Comércio atacadista de artigos de escritório e de papelaria', '46478', 'Comércio atacadista de artigos de escritório e de papelaria; livros, jornais e outras publicações', '464', 'Comércio atacadista de produtos de consumo não alimentar', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4647802', 'Comércio atacadista de livros, jornais e outras publicações', '46478', 'Comércio atacadista de artigos de escritório e de papelaria; livros, jornais e outras publicações', '464', 'Comércio atacadista de produtos de consumo não alimentar', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4649410', 'Comércio atacadista de jóias, relógios e bijuterias, inclusive pedras preciosas e semipreciosas lapidadas', '46494', 'Comércio atacadista de equipamentos e artigos de uso pessoal e doméstico não especificados anteriormente', '464', 'Comércio atacadista de produtos de consumo não alimentar', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4649403', 'Comércio atacadista de bicicletas, triciclos e outros veículos recreativos', '46494', 'Comércio atacadista de equipamentos e artigos de uso pessoal e doméstico não especificados anteriormente', '464', 'Comércio atacadista de produtos de consumo não alimentar', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4649402', 'Comércio atacadista de aparelhos eletrônicos de uso pessoal e doméstico', '46494', 'Comércio atacadista de equipamentos e artigos de uso pessoal e doméstico não especificados anteriormente', '464', 'Comércio atacadista de produtos de consumo não alimentar', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4649406', 'Comércio atacadista de lustres, luminárias e abajures', '46494', 'Comércio atacadista de equipamentos e artigos de uso pessoal e doméstico não especificados anteriormente', '464', 'Comércio atacadista de produtos de consumo não alimentar', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4649401', 'Comércio atacadista de equipamentos elétricos de uso pessoal e doméstico', '46494', 'Comércio atacadista de equipamentos e artigos de uso pessoal e doméstico não especificados anteriormente', '464', 'Comércio atacadista de produtos de consumo não alimentar', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4649409', 'Comércio atacadista de produtos de higiene, limpeza e conservação domiciliar, com atividade de fracionamento e acondicionamento associada', '46494', 'Comércio atacadista de equipamentos e artigos de uso pessoal e doméstico não especificados anteriormente', '464', 'Comércio atacadista de produtos de consumo não alimentar', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4649408', 'Comércio atacadista de produtos de higiene, limpeza e conservação domiciliar', '46494', 'Comércio atacadista de equipamentos e artigos de uso pessoal e doméstico não especificados anteriormente', '464', 'Comércio atacadista de produtos de consumo não alimentar', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4649407', 'Comércio atacadista de filmes, CDs, DVDs, fitas e discos', '46494', 'Comércio atacadista de equipamentos e artigos de uso pessoal e doméstico não especificados anteriormente', '464', 'Comércio atacadista de produtos de consumo não alimentar', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4649499', 'Comércio atacadista de outros equipamentos e artigos de uso pessoal e doméstico não especificados anteriormente', '46494', 'Comércio atacadista de equipamentos e artigos de uso pessoal e doméstico não especificados anteriormente', '464', 'Comércio atacadista de produtos de consumo não alimentar', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4649404', 'Comércio atacadista de móveis e artigos de colchoaria', '46494', 'Comércio atacadista de equipamentos e artigos de uso pessoal e doméstico não especificados anteriormente', '464', 'Comércio atacadista de produtos de consumo não alimentar', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4649405', 'Comércio atacadista de artigos de tapeçaria; persianas e cortinas', '46494', 'Comércio atacadista de equipamentos e artigos de uso pessoal e doméstico não especificados anteriormente', '464', 'Comércio atacadista de produtos de consumo não alimentar', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4651602', 'Comércio atacadista de suprimentos para informática', '46516', 'Comércio atacadista de computadores, periféricos e suprimentos de informática', '465', 'Comércio atacadista de equipamentos e produtos de tecnologias de informação e comunicação', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4651601', 'Comércio atacadista de equipamentos de informática', '46516', 'Comércio atacadista de computadores, periféricos e suprimentos de informática', '465', 'Comércio atacadista de equipamentos e produtos de tecnologias de informação e comunicação', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4652400', 'Comércio atacadista de componentes eletrônicos e equipamentos de telefonia e comunicação', '46524', 'Comércio atacadista de componentes eletrônicos e equipamentos de telefonia e comunicação', '465', 'Comércio atacadista de equipamentos e produtos de tecnologias de informação e comunicação', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4661300', 'Comércio atacadista de máquinas, aparelhos e equipamentos para uso agropecuário; partes e peças', '46613', 'Comércio atacadista de máquinas, aparelhos e equipamentos para uso agropecuário; partes e peças', '466', 'Comércio atacadista de máquinas, aparelhos e equipamentos, exceto de tecnologias de informação e comunicação', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4662100', 'Comércio atacadista de máquinas, equipamentos para terraplenagem, mineração e construção; partes e peças', '46621', 'Comércio atacadista de máquinas, equipamentos para terraplenagem, mineração e construção; partes e peças', '466', 'Comércio atacadista de máquinas, aparelhos e equipamentos, exceto de tecnologias de informação e comunicação', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4663000', 'Comércio atacadista de máquinas e equipamentos para uso industrial; partes e peças', '46630', 'Comércio atacadista de máquinas e equipamentos para uso industrial; partes e peças', '466', 'Comércio atacadista de máquinas, aparelhos e equipamentos, exceto de tecnologias de informação e comunicação', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4664800', 'Comércio atacadista de máquinas, aparelhos e equipamentos para uso odonto-médico-hospitalar; partes e peças', '46648', 'Comércio atacadista de máquinas, aparelhos e equipamentos para uso odonto-médico-hospitalar; partes e peças', '466', 'Comércio atacadista de máquinas, aparelhos e equipamentos, exceto de tecnologias de informação e comunicação', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4665600', 'Comércio atacadista de máquinas e equipamentos para uso comercial; partes e peças', '46656', 'Comércio atacadista de máquinas e equipamentos para uso comercial; partes e peças', '466', 'Comércio atacadista de máquinas, aparelhos e equipamentos, exceto de tecnologias de informação e comunicação', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4669999', 'Comércio atacadista de outras máquinas e equipamentos não especificados anteriormente; partes e peças', '46699', 'Comércio atacadista de máquinas, aparelhos e equipamentos não especificados anteriormente; partes e peças', '466', 'Comércio atacadista de máquinas, aparelhos e equipamentos, exceto de tecnologias de informação e comunicação', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4669901', 'Comércio atacadista de bombas e compressores; partes e peças', '46699', 'Comércio atacadista de máquinas, aparelhos e equipamentos não especificados anteriormente; partes e peças', '466', 'Comércio atacadista de máquinas, aparelhos e equipamentos, exceto de tecnologias de informação e comunicação', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4671100', 'Comércio atacadista de madeira e produtos derivados', '46711', 'Comércio atacadista de madeira e produtos derivados', '467', 'Comércio atacadista de madeira, ferragens, ferramentas, material elétrico e material de construção', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4672900', 'Comércio atacadista de ferragens e ferramentas', '46729', 'Comércio atacadista de ferragens e ferramentas', '467', 'Comércio atacadista de madeira, ferragens, ferramentas, material elétrico e material de construção', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4673700', 'Comércio atacadista de material elétrico', '46737', 'Comércio atacadista de material elétrico', '467', 'Comércio atacadista de madeira, ferragens, ferramentas, material elétrico e material de construção', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4674500', 'Comércio atacadista de cimento', '46745', 'Comércio atacadista de cimento', '467', 'Comércio atacadista de madeira, ferragens, ferramentas, material elétrico e material de construção', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4679601', 'Comércio atacadista de tintas, vernizes e similares', '46796', 'Comércio atacadista especializado de materiais de construção não especificados anteriormente e de materiais de construção em geral', '467', 'Comércio atacadista de madeira, ferragens, ferramentas, material elétrico e material de construção', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4679604', 'Comércio atacadista especializado de materiais de construção não especificados anteriormente', '46796', 'Comércio atacadista especializado de materiais de construção não especificados anteriormente e de materiais de construção em geral', '467', 'Comércio atacadista de madeira, ferragens, ferramentas, material elétrico e material de construção', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4679602', 'Comércio atacadista de mármores e granitos', '46796', 'Comércio atacadista especializado de materiais de construção não especificados anteriormente e de materiais de construção em geral', '467', 'Comércio atacadista de madeira, ferragens, ferramentas, material elétrico e material de construção', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4679699', 'Comércio atacadista de materiais de construção em geral', '46796', 'Comércio atacadista especializado de materiais de construção não especificados anteriormente e de materiais de construção em geral', '467', 'Comércio atacadista de madeira, ferragens, ferramentas, material elétrico e material de construção', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4679603', 'Comércio atacadista de vidros, espelhos e vitrais', '46796', 'Comércio atacadista especializado de materiais de construção não especificados anteriormente e de materiais de construção em geral', '467', 'Comércio atacadista de madeira, ferragens, ferramentas, material elétrico e material de construção', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4681802', 'Comércio atacadista de combustíveis realizado por transportador retalhista (TRR)', '46818', 'Comércio atacadista de combustíveis sólidos, líquidos e gasosos, exceto gás natural e GLP', '468', 'Comércio atacadista especializado em outros produtos', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4681804', 'Comércio atacadista de combustíveis de origem mineral em bruto', '46818', 'Comércio atacadista de combustíveis sólidos, líquidos e gasosos, exceto gás natural e GLP', '468', 'Comércio atacadista especializado em outros produtos', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4681801', 'Comércio atacadista de álcool carburante, biodiesel, gasolina e demais derivados de petróleo, exceto lubrificantes, não realizado por transportador retalhista (TRR)', '46818', 'Comércio atacadista de combustíveis sólidos, líquidos e gasosos, exceto gás natural e GLP', '468', 'Comércio atacadista especializado em outros produtos', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4681803', 'Comércio atacadista de combustíveis de origem vegetal, exceto álcool carburante', '46818', 'Comércio atacadista de combustíveis sólidos, líquidos e gasosos, exceto gás natural e GLP', '468', 'Comércio atacadista especializado em outros produtos', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4681805', 'Comércio atacadista de lubrificantes', '46818', 'Comércio atacadista de combustíveis sólidos, líquidos e gasosos, exceto gás natural e GLP', '468', 'Comércio atacadista especializado em outros produtos', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4682600', 'Comércio atacadista de gás liquefeito de petróleo (GLP)', '46826', 'Comércio atacadista de gás liquefeito de petróleo (GLP)', '468', 'Comércio atacadista especializado em outros produtos', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4683400', 'Comércio atacadista de defensivos agrícolas, adubos, fertilizantes e corretivos do solo', '46834', 'Comércio atacadista de defensivos agrícolas, adubos, fertilizantes e corretivos do solo', '468', 'Comércio atacadista especializado em outros produtos', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4684299', 'Comércio atacadista de outros produtos químicos e petroquímicos não especificados anteriormente', '46842', 'Comércio atacadista de produtos químicos e petroquímicos, exceto agroquímicos', '468', 'Comércio atacadista especializado em outros produtos', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4684201', 'Comércio atacadista de resinas e elastômeros', '46842', 'Comércio atacadista de produtos químicos e petroquímicos, exceto agroquímicos', '468', 'Comércio atacadista especializado em outros produtos', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4684202', 'Comércio atacadista de solventes', '46842', 'Comércio atacadista de produtos químicos e petroquímicos, exceto agroquímicos', '468', 'Comércio atacadista especializado em outros produtos', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4685100', 'Comércio atacadista de produtos siderúrgicos e metalúrgicos, exceto para construção', '46851', 'Comércio atacadista de produtos siderúrgicos e metalúrgicos, exceto para construção', '468', 'Comércio atacadista especializado em outros produtos', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4686901', 'Comércio atacadista de papel e papelão em bruto', '46869', 'Comércio atacadista de papel e papelão em bruto e de embalagens', '468', 'Comércio atacadista especializado em outros produtos', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4686902', 'Comércio atacadista de embalagens', '46869', 'Comércio atacadista de papel e papelão em bruto e de embalagens', '468', 'Comércio atacadista especializado em outros produtos', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4687703', 'Comércio atacadista de resíduos e sucatas metálicos', '46877', 'Comércio atacadista de resíduos e sucatas', '468', 'Comércio atacadista especializado em outros produtos', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4687702', 'Comércio atacadista de resíduos e sucatas não metálicos, exceto de papel e papelão', '46877', 'Comércio atacadista de resíduos e sucatas', '468', 'Comércio atacadista especializado em outros produtos', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4687701', 'Comércio atacadista de resíduos de papel e papelão', '46877', 'Comércio atacadista de resíduos e sucatas', '468', 'Comércio atacadista especializado em outros produtos', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4689302', 'Comércio atacadista de fios e fibras beneficiados', '46893', 'Comércio atacadista especializado de outros produtos intermediários não especificados anteriormente', '468', 'Comércio atacadista especializado em outros produtos', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4689301', 'Comércio atacadista de produtos da extração mineral, exceto combustíveis', '46893', 'Comércio atacadista especializado de outros produtos intermediários não especificados anteriormente', '468', 'Comércio atacadista especializado em outros produtos', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4689399', 'Comércio atacadista especializado em outros produtos intermediários não especificados anteriormente', '46893', 'Comércio atacadista especializado de outros produtos intermediários não especificados anteriormente', '468', 'Comércio atacadista especializado em outros produtos', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4691500', 'Comércio atacadista de mercadorias em geral, com predominância de produtos alimentícios', '46915', 'Comércio atacadista de mercadorias em geral, com predominância de produtos alimentícios', '469', 'Comércio atacadista não especializado', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4692300', 'Comércio atacadista de mercadorias em geral, com predominância de insumos agropecuários', '46923', 'Comércio atacadista de mercadorias em geral, com predominância de insumos agropecuários', '469', 'Comércio atacadista não especializado', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4693100', 'Comércio atacadista de mercadorias em geral, sem predominância de alimentos ou de insumos agropecuários', '46931', 'Comércio atacadista de mercadorias em geral, sem predominância de alimentos ou de insumos agropecuários', '469', 'Comércio atacadista não especializado', '46', 'Comércio Por Atacado, Exceto Veículos Automotores E Motocicletas', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4711301', 'Comércio varejista de mercadorias em geral, com predominância de produtos alimentícios - hipermercados', '47113', 'Comércio varejista de mercadorias em geral, com predominância de produtos alimentícios - hipermercados e supermercados', '471', 'Comércio varejista não especializado', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4711302', 'Comércio varejista de mercadorias em geral, com predominância de produtos alimentícios - supermercados', '47113', 'Comércio varejista de mercadorias em geral, com predominância de produtos alimentícios - hipermercados e supermercados', '471', 'Comércio varejista não especializado', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4712100', 'Comércio varejista de mercadorias em geral, com predominância de produtos alimentícios - minimercados, mercearias e armazéns', '47121', 'Comércio varejista de mercadorias em geral, com
predominância de produtos alimentícios - minimercados,
mercearias e armazéns', '471', 'Comércio varejista não especializado', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4713003', 'Lojas duty free de aeroportos internacionais', '47130', 'Comércio varejista de mercadorias em geral, sem predominância de produtos alimentícios', '471', 'Comércio varejista não-especializado', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, FALSE),
('4713004', 'Lojas de departamentos ou magazines, exceto lojas francas (Duty free)', '47130', 'Comércio varejista de mercadorias em geral, sem predominância de produtos alimentícios', '471', 'Comércio varejista não especializado', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', FALSE, FALSE, FALSE, TRUE),
('4713001', 'Lojas de departamentos ou magazines', '47130', 'Comércio varejista de mercadorias em geral, sem predominância de produtos alimentícios', '471', 'Comércio varejista não-especializado', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, FALSE),
('4713002', 'Lojas de variedades, exceto lojas de departamentos ou magazines', '47130', 'Comércio varejista de mercadorias em geral, sem predominância de produtos alimentícios', '471', 'Comércio varejista não especializado', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4713005', 'Lojas francas (Duty Free) de aeroportos, portos e em fronteiras terrestres', '47130', 'Comércio varejista de mercadorias em geral, sem predominância de produtos alimentícios', '471', 'Comércio varejista não especializado', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', FALSE, FALSE, FALSE, TRUE),
('4721102', 'Padaria e confeitaria com predominância de revenda', '47211', 'Comércio varejista de produtos de padaria, laticínio, doces, balas e semelhantes', '472', 'Comércio varejista de produtos alimentícios, bebidas e fumo', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4721103', 'Comércio varejista de laticínios e frios', '47211', 'Comércio varejista de produtos de padaria, laticínio, doces, balas e semelhantes', '472', 'Comércio varejista de produtos alimentícios, bebidas e fumo', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4721104', 'Comércio varejista de doces, balas, bombons e semelhantes', '47211', 'Comércio varejista de produtos de padaria, laticínio, doces, balas e semelhantes', '472', 'Comércio varejista de produtos alimentícios, bebidas e fumo', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4721101', 'Padaria e confeitaria com predominância de produção própria', '47211', 'Comércio varejista de produtos de padaria, laticínio, doces, balas e semelhantes', '472', 'Comércio varejista de produtos alimentícios, bebidas e fumo', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, FALSE, FALSE, FALSE),
('4722901', 'Comércio varejista de carnes - açougues', '47229', 'Comércio varejista de carnes e pescados - açougues e peixarias', '472', 'Comércio varejista de produtos alimentícios, bebidas e fumo', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4722902', 'Peixaria', '47229', 'Comércio varejista de carnes e pescados - açougues e peixarias', '472', 'Comércio varejista de produtos alimentícios, bebidas e fumo', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4723700', 'Comércio varejista de bebidas', '47237', 'Comércio varejista de bebidas', '472', 'Comércio varejista de produtos alimentícios, bebidas e fumo', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4724500', 'Comércio varejista de hortifrutigranjeiros', '47245', 'Comércio varejista de hortifrutigranjeiros', '472', 'Comércio varejista de produtos alimentícios, bebidas e fumo', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4729601', 'Tabacaria', '47296', 'Comércio varejista de produtos alimentícios em geral ou especializado em produtos alimentícios não especificados anteriormente; produtos do fumo', '472', 'Comércio varejista de produtos alimentícios, bebidas e fumo', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4729699', 'Comércio varejista de produtos alimentícios em geral ou especializado em produtos alimentícios não especificados anteriormente', '47296', 'Comércio varejista de produtos alimentícios em geral ou especializado em produtos alimentícios não especificados anteriormente; produtos do fumo', '472', 'Comércio varejista de produtos alimentícios, bebidas e fumo', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4729602', 'Comércio varejista de mercadorias em lojas de conveniência', '47296', 'Comércio varejista de produtos alimentícios em geral ou especializado em produtos alimentícios não especificados anteriormente; produtos do fumo', '472', 'Comércio varejista de produtos alimentícios, bebidas e fumo', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', FALSE, TRUE, TRUE, TRUE),
('4731800', 'Comércio varejista de combustíveis para veículos automotores', '47318', 'Comércio varejista de combustíveis para veículos automotores', '473', 'Comércio varejista de combustíveis para veículos automotores', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4732600', 'Comércio varejista de lubrificantes', '47326', 'Comércio varejista de lubrificantes', '473', 'Comércio varejista de combustíveis para veículos automotores', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4741500', 'Comércio varejista de tintas e materiais para pintura', '47415', 'Comércio varejista de tintas e materiais para pintura', '474', 'Comércio varejista de material de construção', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4742300', 'Comércio varejista de material elétrico', '47423', 'Comércio varejista de material elétrico', '474', 'Comércio varejista de material de construção', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4743100', 'Comércio varejista de vidros', '47431', 'Comércio varejista de vidros', '474', 'Comércio varejista de material de construção', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4744099', 'Comércio varejista de materiais de construção em geral', '47440', 'Comércio varejista de ferragens, madeira e materiais de construção', '474', 'Comércio varejista de material de construção', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4744002', 'Comércio varejista de madeira e artefatos', '47440', 'Comércio varejista de ferragens, madeira e materiais de construção', '474', 'Comércio varejista de material de construção', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4744004', 'Comércio varejista de cal, areia, pedra britada, tijolos e telhas', '47440', 'Comércio varejista de ferragens, madeira e materiais de construção', '474', 'Comércio varejista de material de construção', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4744003', 'Comércio varejista de materiais hidráulicos', '47440', 'Comércio varejista de ferragens, madeira e materiais de construção', '474', 'Comércio varejista de material de construção', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4744001', 'Comércio varejista de ferragens e ferramentas', '47440', 'Comércio varejista de ferragens, madeira e materiais de construção', '474', 'Comércio varejista de material de construção', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4744005', 'Comércio varejista de materiais de construção não especificados anteriormente', '47440', 'Comércio varejista de ferragens, madeira e materiais de construção', '474', 'Comércio varejista de material de construção', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4744006', 'Comércio varejista de pedras para revestimento', '47440', 'Comércio varejista de ferragens, madeira e materiais de construção', '474', 'Comércio varejista de material de construção', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', FALSE, TRUE, TRUE, TRUE),
('4751201', 'Comércio varejista especializado de equipamentos e suprimentos de informática', '47512', 'Comércio varejista especializado de equipamentos e suprimentos de informática', '475', 'Comércio varejista de equipamentos de informática e comunicação; equipamentos e artigos de uso doméstico', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', FALSE, TRUE, TRUE, TRUE),
('4751200', 'Comércio varejista especializado de equipamentos e suprimentos de informática', '47512', 'Comércio varejista especializado de equipamentos e suprimentos de informática', '475', 'Comércio varejista de equipamentos de informática e comunicação; equipamentos e artigos de uso doméstico', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, FALSE, FALSE, FALSE),
('4751202', 'Recarga de cartuchos para equipamentos de informática', '47512', 'Comércio varejista especializado de equipamentos e suprimentos de informática', '475', 'Comércio varejista de equipamentos de informática e comunicação; equipamentos e artigos de uso doméstico', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', FALSE, TRUE, TRUE, TRUE),
('4752100', 'Comércio varejista especializado de equipamentos de telefonia e comunicação', '47521', 'Comércio varejista especializado de equipamentos de telefonia e comunicação', '475', 'Comércio varejista de equipamentos de informática e comunicação; equipamentos e artigos de uso doméstico', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4753900', 'Comércio varejista especializado de eletrodomésticos e equipamentos de áudio e vídeo', '47539', 'Comércio varejista especializado de eletrodomésticos e equipamentos de áudio e vídeo', '475', 'Comércio varejista de equipamentos de informática e comunicação; equipamentos e artigos de uso doméstico', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4754702', 'Comércio varejista de artigos de colchoaria', '47547', 'Comércio varejista especializado de móveis, colchoaria e artigos de iluminação', '475', 'Comércio varejista de equipamentos de informática e comunicação; equipamentos e artigos de uso doméstico', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4754701', 'Comércio varejista de móveis', '47547', 'Comércio varejista especializado de móveis, colchoaria e artigos de iluminação', '475', 'Comércio varejista de equipamentos de informática e comunicação; equipamentos e artigos de uso doméstico', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4754703', 'Comércio varejista de artigos de iluminação', '47547', 'Comércio varejista especializado de móveis, colchoaria e artigos de iluminação', '475', 'Comércio varejista de equipamentos de informática e comunicação; equipamentos e artigos de uso doméstico', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4755503', 'Comercio varejista de artigos de cama, mesa e banho', '47555', 'Comércio varejista especializado de tecidos e artigos de cama, mesa e banho', '475', 'Comércio varejista de equipamentos de informática e comunicação; equipamentos e artigos de uso doméstico', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4755502', 'Comercio varejista de artigos de armarinho', '47555', 'Comércio varejista especializado de tecidos e artigos de cama, mesa e banho', '475', 'Comércio varejista de equipamentos de informática e comunicação; equipamentos e artigos de uso doméstico', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4755501', 'Comércio varejista de tecidos', '47555', 'Comércio varejista especializado de tecidos e artigos de cama, mesa e banho', '475', 'Comércio varejista de equipamentos de informática e comunicação; equipamentos e artigos de uso doméstico', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4756300', 'Comércio varejista especializado de instrumentos musicais e acessórios', '47563', 'Comércio varejista especializado de instrumentos musicais e acessórios', '475', 'Comércio varejista de equipamentos de informática e comunicação; equipamentos e artigos de uso doméstico', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4757100', 'Comércio varejista especializado de peças e acessórios para aparelhos eletroeletrônicos para uso doméstico, exceto informática e comunicação', '47571', 'Comércio varejista especializado de peças e acessórios para aparelhos eletroeletrônicos para uso doméstico, exceto informática e comunicação', '475', 'Comércio varejista de equipamentos de informática e comunicação; equipamentos e artigos de uso doméstico', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4759801', 'Comércio varejista de artigos de tapeçaria, cortinas e persianas', '47598', 'Comércio varejista de artigos de uso doméstico não especificados anteriormente', '475', 'Comércio varejista de equipamentos de informática e comunicação; equipamentos e artigos de uso doméstico', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4759899', 'Comércio varejista de outros artigos de uso doméstico não especificados anteriormente', '47598', 'Comércio varejista de artigos de uso doméstico não especificados anteriormente', '475', 'Comércio varejista de equipamentos de informática e comunicação; equipamentos e artigos de uso doméstico', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4761001', 'Comércio varejista de livros', '47610', 'Comércio varejista de livros, jornais, revistas e papelaria', '476', 'Comércio varejista de artigos culturais, recreativos e esportivos', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4761003', 'Comércio varejista de artigos de papelaria', '47610', 'Comércio varejista de livros, jornais, revistas e papelaria', '476', 'Comércio varejista de artigos culturais, recreativos e esportivos', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4761002', 'Comércio varejista de jornais e revistas', '47610', 'Comércio varejista de livros, jornais, revistas e papelaria', '476', 'Comércio varejista de artigos culturais, recreativos e esportivos', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4762800', 'Comércio varejista de discos, CDs, DVDs e fitas', '47628', 'Comércio varejista de discos, CDs, DVDs e fitas', '476', 'Comércio varejista de artigos culturais, recreativos e esportivos', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4763603', 'Comércio varejista de bicicletas e triciclos; peças e acessórios', '47636', 'Comércio varejista de artigos recreativos e esportivos', '476', 'Comércio varejista de artigos culturais, recreativos e esportivos', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4763604', 'Comércio varejista de artigos de caça, pesca e camping', '47636', 'Comércio varejista de artigos recreativos e esportivos', '476', 'Comércio varejista de artigos culturais, recreativos e esportivos', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4763602', 'Comércio varejista de artigos esportivos', '47636', 'Comércio varejista de artigos recreativos e esportivos', '476', 'Comércio varejista de artigos culturais, recreativos e esportivos', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4763601', 'Comércio varejista de brinquedos e artigos recreativos', '47636', 'Comércio varejista de artigos recreativos e esportivos', '476', 'Comércio varejista de artigos culturais, recreativos e esportivos', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4763605', 'Comércio varejista de embarcações e outros veículos recreativos; peças e acessórios', '47636', 'Comércio varejista de artigos recreativos e esportivos', '476', 'Comércio varejista de artigos culturais, recreativos e esportivos', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4771701', 'Comércio varejista de produtos farmacêuticos, sem manipulação de fórmulas', '47717', 'Comércio varejista de produtos farmacêuticos para uso humano e veterinário', '477', 'Comércio varejista de produtos farmacêuticos, perfumaria e cosméticos e artigos médicos, ópticos e ortopédicos', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4771703', 'Comércio varejista de produtos farmacêuticos homeopáticos', '47717', 'Comércio varejista de produtos farmacêuticos para uso humano e veterinário', '477', 'Comércio varejista de produtos farmacêuticos, perfumaria e cosméticos e artigos médicos, ópticos e ortopédicos', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4771704', 'Comércio varejista de medicamentos veterinários', '47717', 'Comércio varejista de produtos farmacêuticos para uso humano e veterinário', '477', 'Comércio varejista de produtos farmacêuticos, perfumaria e cosméticos e artigos médicos, ópticos e ortopédicos', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4771702', 'Comércio varejista de produtos farmacêuticos, com manipulação de fórmulas', '47717', 'Comércio varejista de produtos farmacêuticos para uso humano e veterinário', '477', 'Comércio varejista de produtos farmacêuticos, perfumaria e cosméticos e artigos médicos, ópticos e ortopédicos', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4772500', 'Comércio varejista de cosméticos, produtos de perfumaria e de higiene pessoal', '47725', 'Comércio varejista de cosméticos, produtos de perfumaria e de higiene pessoal', '477', 'Comércio varejista de produtos farmacêuticos, perfumaria e cosméticos e artigos médicos, ópticos e ortopédicos', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4773300', 'Comércio varejista de artigos médicos e ortopédicos', '47733', 'Comércio varejista de artigos médicos e ortopédicos', '477', 'Comércio varejista de produtos farmacêuticos, perfumaria e cosméticos e artigos médicos, ópticos e ortopédicos', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4774100', 'Comércio varejista de artigos de óptica', '47741', 'Comércio varejista de artigos de óptica', '477', 'Comércio varejista de produtos farmacêuticos, perfumaria e cosméticos e artigos médicos, ópticos e ortopédicos', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4781400', 'Comércio varejista de artigos do vestuário e acessórios', '47814', 'Comércio varejista de artigos do vestuário e acessórios', '478', 'Comércio varejista de produtos novos não especificados anteriormente e de produtos usados', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4782202', 'Comércio varejista de artigos de viagem', '47822', 'Comércio varejista de calçados e artigos de viagem', '478', 'Comércio varejista de produtos novos não especificados anteriormente e de produtos usados', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4782201', 'Comércio varejista de calçados', '47822', 'Comércio varejista de calçados e artigos de viagem', '478', 'Comércio varejista de produtos novos não especificados anteriormente e de produtos usados', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4783101', 'Comércio varejista de artigos de joalheria', '47831', 'Comércio varejista de jóias e relógios', '478', 'Comércio varejista de produtos novos não especificados anteriormente e de produtos usados', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4783102', 'Comércio varejista de artigos de relojoaria', '47831', 'Comércio varejista de jóias e relógios', '478', 'Comércio varejista de produtos novos não especificados anteriormente e de produtos usados', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4784900', 'Comércio varejista de gás liqüefeito de petróleo (GLP)', '47849', 'Comércio varejista de gás liqüefeito de petróleo (GLP)', '478', 'Comércio varejista de produtos novos não especificados anteriormente e de produtos usados', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4785701', 'Comércio varejista de antiguidades', '47857', 'Comércio varejista de artigos usados', '478', 'Comércio varejista de produtos novos não especificados anteriormente e de produtos usados', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4785799', 'Comércio varejista de outros artigos usados', '47857', 'Comércio varejista de artigos usados', '478', 'Comércio varejista de produtos novos não especificados anteriormente e de produtos usados', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4789004', 'Comércio varejista de animais vivos e de artigos e alimentos para animais de estimação', '47890', 'Comércio varejista de outros produtos novos não especificados anteriormente', '478', 'Comércio varejista de produtos novos não especificados anteriormente e de produtos usados', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4789005', 'Comércio varejista de produtos saneantes domissanitários', '47890', 'Comércio varejista de outros produtos novos não especificados anteriormente', '478', 'Comércio varejista de produtos novos não especificados anteriormente e de produtos usados', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4789008', 'Comércio varejista de artigos fotográficos e para filmagem', '47890', 'Comércio varejista de outros produtos novos não especificados anteriormente', '478', 'Comércio varejista de produtos novos não especificados anteriormente e de produtos usados', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4789007', 'Comércio varejista de equipamentos para escritório', '47890', 'Comércio varejista de outros produtos novos não especificados anteriormente', '478', 'Comércio varejista de produtos novos não especificados anteriormente e de produtos usados', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4789099', 'Comércio varejista de outros produtos não especificados anteriormente', '47890', 'Comércio varejista de outros produtos novos não especificados anteriormente', '478', 'Comércio varejista de produtos novos não especificados anteriormente e de produtos usados', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4789009', 'Comércio varejista de armas e munições', '47890', 'Comércio varejista de outros produtos novos não especificados anteriormente', '478', 'Comércio varejista de produtos novos não especificados anteriormente e de produtos usados', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4789001', 'Comércio varejista de suvenires, bijuterias e artesanatos', '47890', 'Comércio varejista de outros produtos novos não especificados anteriormente', '478', 'Comércio varejista de produtos novos não especificados anteriormente e de produtos usados', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4789002', 'Comércio varejista de plantas e flores naturais', '47890', 'Comércio varejista de outros produtos novos não especificados anteriormente', '478', 'Comércio varejista de produtos novos não especificados anteriormente e de produtos usados', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4789003', 'Comércio varejista de objetos de arte', '47890', 'Comércio varejista de outros produtos novos não especificados anteriormente', '478', 'Comércio varejista de produtos novos não especificados anteriormente e de produtos usados', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4789006', 'Comércio varejista de fogos de artifício e artigos pirotécnicos', '47890', 'Comércio varejista de outros produtos novos não especificados anteriormente', '478', 'Comércio varejista de produtos novos não especificados anteriormente e de produtos usados', '47', 'Comércio Varejista', 'G', 'Comércio; Reparação De Veículos Automotores E Motocicletas', TRUE, TRUE, TRUE, TRUE),
('4911600', 'Transporte ferroviário de carga', '49116', 'Transporte ferroviário de carga', '491', 'Transporte ferroviário e metroferroviário', '49', 'Transporte Terrestre', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('4912401', 'Transporte ferroviário de passageiros intermunicipal e interestadual', '49124', 'Transporte metroferroviário de passageiros', '491', 'Transporte ferroviário e metroferroviário', '49', 'Transporte Terrestre', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('4912402', 'Transporte ferroviário de passageiros municipal e em região metropolitana', '49124', 'Transporte metroferroviário de passageiros', '491', 'Transporte ferroviário e metroferroviário', '49', 'Transporte Terrestre', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('4912403', 'Transporte metroviário', '49124', 'Transporte metroferroviário de passageiros', '491', 'Transporte ferroviário e metroferroviário', '49', 'Transporte Terrestre', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('4921301', 'Transporte rodoviário coletivo de passageiros, com itinerário fixo, municipal', '49213', 'Transporte rodoviário coletivo de passageiros, com itinerário fixo, municipal e em região metropolitana', '492', 'Transporte rodoviário de passageiros', '49', 'Transporte Terrestre', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('4921302', 'Transporte rodoviário coletivo de passageiros, com itinerário fixo, intermunicipal em região metropolitana', '49213', 'Transporte rodoviário coletivo de passageiros, com itinerário fixo, municipal e em região metropolitana', '492', 'Transporte rodoviário de passageiros', '49', 'Transporte Terrestre', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('4922102', 'Transporte rodoviário coletivo de passageiros, com itinerário fixo, interestadual', '49221', 'Transporte rodoviário coletivo de passageiros, com itinerário fixo, intermunicipal, interestadual e internacional', '492', 'Transporte rodoviário de passageiros', '49', 'Transporte Terrestre', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('4922101', 'Transporte rodoviário coletivo de passageiros, com itinerário fixo, intermunicipal, exceto em região metropolitana', '49221', 'Transporte rodoviário coletivo de passageiros, com itinerário fixo, intermunicipal, interestadual e internacional', '492', 'Transporte rodoviário de passageiros', '49', 'Transporte Terrestre', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('4922103', 'Transporte rodoviário coletivo de passageiros, com itinerário fixo, internacional', '49221', 'Transporte rodoviário coletivo de passageiros, com itinerário fixo, intermunicipal, interestadual e internacional', '492', 'Transporte rodoviário de passageiros', '49', 'Transporte Terrestre', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('4923001', 'Serviço de táxi', '49230', 'Transporte rodoviário de táxi', '492', 'Transporte rodoviário de passageiros', '49', 'Transporte Terrestre', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('4923002', 'Serviço de transporte de passageiros - locação de automóveis com motorista', '49230', 'Transporte rodoviário de táxi', '492', 'Transporte rodoviário de passageiros', '49', 'Transporte Terrestre', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('4924800', 'Transporte escolar', '49248', 'Transporte escolar', '492', 'Transporte rodoviário de passageiros', '49', 'Transporte Terrestre', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('4929901', 'Transporte rodoviário coletivo de passageiros, sob regime de fretamento, municipal', '49299', 'Transporte rodoviário coletivo de passageiros, sob regime de fretamento, e outros transportes rodoviários não especificados anteriormente', '492', 'Transporte rodoviário de passageiros', '49', 'Transporte Terrestre', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('4929903', 'Organização de excursões em veículos rodoviários próprios, municipal', '49299', 'Transporte rodoviário coletivo de passageiros, sob regime de fretamento, e outros transportes rodoviários não especificados anteriormente', '492', 'Transporte rodoviário de passageiros', '49', 'Transporte Terrestre', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('4929904', 'Organização de excursões em veículos rodoviários próprios, intermunicipal, interestadual e internacional', '49299', 'Transporte rodoviário coletivo de passageiros, sob regime de fretamento, e outros transportes rodoviários não especificados anteriormente', '492', 'Transporte rodoviário de passageiros', '49', 'Transporte Terrestre', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('4929902', 'Transporte rodoviário coletivo de passageiros, sob regime de fretamento, intermunicipal, interestadual e internacional', '49299', 'Transporte rodoviário coletivo de passageiros, sob regime de fretamento, e outros transportes rodoviários não especificados anteriormente', '492', 'Transporte rodoviário de passageiros', '49', 'Transporte Terrestre', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('4929999', 'Outros transportes rodoviários de passageiros não especificados anteriormente', '49299', 'Transporte rodoviário coletivo de passageiros, sob regime de fretamento, e outros transportes rodoviários não especificados anteriormente', '492', 'Transporte rodoviário de passageiros', '49', 'Transporte Terrestre', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('4930201', 'Transporte rodoviário de carga, exceto produtos perigosos e mudanças, municipal', '49302', 'Transporte rodoviário de carga', '493', 'Transporte rodoviário de carga', '49', 'Transporte Terrestre', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('4930202', 'Transporte rodoviário de carga, exceto produtos perigosos e mudanças, intermunicipal, interestadual e internacional', '49302', 'Transporte rodoviário de carga', '493', 'Transporte rodoviário de carga', '49', 'Transporte Terrestre', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('4930204', 'Transporte rodoviário de mudanças', '49302', 'Transporte rodoviário de carga', '493', 'Transporte rodoviário de carga', '49', 'Transporte Terrestre', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('4930203', 'Transporte rodoviário de produtos perigosos', '49302', 'Transporte rodoviário de carga', '493', 'Transporte rodoviário de carga', '49', 'Transporte Terrestre', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('4940000', 'Transporte dutoviário', '49400', 'Transporte dutoviário', '494', 'Transporte dutoviário', '49', 'Transporte Terrestre', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('4950700', 'Trens turísticos, teleféricos e similares', '49507', 'Trens turísticos, teleféricos e similares', '495', 'Trens turísticos, teleféricos e similares', '49', 'Transporte Terrestre', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5011402', 'Transporte marítimo de cabotagem - Passageiros', '50114', 'Transporte marítimo de cabotagem', '501', 'Transporte marítimo de cabotagem e longo curso', '50', 'Transporte Aquaviário', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5011401', 'Transporte marítimo de cabotagem - Carga', '50114', 'Transporte marítimo de cabotagem', '501', 'Transporte marítimo de cabotagem e longo curso', '50', 'Transporte Aquaviário', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5012202', 'Transporte marítimo de longo curso - Passageiros', '50122', 'Transporte marítimo de longo curso', '501', 'Transporte marítimo de cabotagem e longo curso', '50', 'Transporte Aquaviário', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5012201', 'Transporte marítimo de longo curso - Carga', '50122', 'Transporte marítimo de longo curso', '501', 'Transporte marítimo de cabotagem e longo curso', '50', 'Transporte Aquaviário', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5021101', 'Transporte por navegação interior de carga, municipal, exceto travessia', '50211', 'Transporte por navegação interior de carga', '502', 'Transporte por navegação interior', '50', 'Transporte Aquaviário', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5021102', 'Transporte por navegação interior de carga, intermunicipal, interestadual e internacional, exceto travessia', '50211', 'Transporte por navegação interior de carga', '502', 'Transporte por navegação interior', '50', 'Transporte Aquaviário', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5022001', 'Transporte por navegação interior de passageiros em linhas regulares, municipal, exceto travessia', '50220', 'Transporte por navegação interior de passageiros em linhas regulares', '502', 'Transporte por navegação interior', '50', 'Transporte Aquaviário', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5022002', 'Transporte por navegação interior de passageiros em linhas regulares, intermunicipal, interestadual e internacional, exceto travessia', '50220', 'Transporte por navegação interior de passageiros em linhas regulares', '502', 'Transporte por navegação interior', '50', 'Transporte Aquaviário', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5030102', 'Navegação de apoio portuário', '50301', 'Navegação de apoio', '503', 'Navegação de apoio', '50', 'Transporte Aquaviário', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5030101', 'Navegação de apoio marítimo', '50301', 'Navegação de apoio', '503', 'Navegação de apoio', '50', 'Transporte Aquaviário', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5030103', 'Serviço de rebocadores e empurradores', '50301', 'Navegação de apoio', '503', 'Navegação de apoio', '50', 'Transporte Aquaviário', 'H', 'Transporte, Armazenagem E Correio', FALSE, FALSE, TRUE, TRUE),
('5091201', 'Transporte por navegação de travessia, municipal', '50912', 'Transporte por navegação de travessia', '509', 'Outros transportes aquaviários', '50', 'Transporte Aquaviário', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5091202', 'Transporte por navegação de travessia, intermunicipal, interestadual e internacional', '50912', 'Transporte por navegação de travessia', '509', 'Outros transportes aquaviários', '50', 'Transporte Aquaviário', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5099899', 'Outros transportes aquaviários não especificados anteriormente', '50998', 'Transportes aquaviários não especificados anteriormente', '509', 'Outros transportes aquaviários', '50', 'Transporte Aquaviário', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5099801', 'Transporte aquaviário para passeios turísticos', '50998', 'Transportes aquaviários não especificados anteriormente', '509', 'Outros transportes aquaviários', '50', 'Transporte Aquaviário', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5111100', 'Transporte aéreo de passageiros regular', '51111', 'Transporte aéreo de passageiros regular', '511', 'Transporte aéreo de passageiros', '51', 'Transporte Aéreo', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5112901', 'Serviço de táxi aéreo e locação de aeronaves com tripulação', '51129', 'Transporte aéreo de passageiros não regular', '511', 'Transporte aéreo de passageiros', '51', 'Transporte Aéreo', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5112999', 'Outros serviços de transporte aéreo de passageiros não regular', '51129', 'Transporte aéreo de passageiros não regular', '511', 'Transporte aéreo de passageiros', '51', 'Transporte Aéreo', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5120000', 'Transporte aéreo de carga', '51200', 'Transporte aéreo de carga', '512', 'Transporte aéreo de carga', '51', 'Transporte Aéreo', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5130700', 'Transporte espacial', '51307', 'Transporte espacial', '513', 'Transporte espacial', '51', 'Transporte Aéreo', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5211701', 'Armazéns gerais - emissão de warrant', '52117', 'Armazenamento', '521', 'Armazenamento, carga e descarga', '52', 'Armazenamento E Atividades Auxiliares Dos Transportes', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5211702', 'Guarda-móveis', '52117', 'Armazenamento', '521', 'Armazenamento, carga e descarga', '52', 'Armazenamento E Atividades Auxiliares Dos Transportes', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5211799', 'Depósitos de mercadorias para terceiros, exceto armazéns gerais e guarda-móveis', '52117', 'Armazenamento', '521', 'Armazenamento, carga e descarga', '52', 'Armazenamento E Atividades Auxiliares Dos Transportes', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5212500', 'Carga e descarga', '52125', 'Carga e descarga', '521', 'Armazenamento, carga e descarga', '52', 'Armazenamento E Atividades Auxiliares Dos Transportes', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5221400', 'Concessionárias de rodovias, pontes, túneis e serviços relacionados', '52214', 'Concessionárias de rodovias, pontes, túneis e serviços relacionados', '522', 'Atividades auxiliares dos transportes terrestres', '52', 'Armazenamento E Atividades Auxiliares Dos Transportes', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5222200', 'Terminais rodoviários e ferroviários', '52222', 'Terminais rodoviários e ferroviários', '522', 'Atividades auxiliares dos transportes terrestres', '52', 'Armazenamento E Atividades Auxiliares Dos Transportes', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5223100', 'Estacionamento de veículos', '52231', 'Estacionamento de veículos', '522', 'Atividades auxiliares dos transportes terrestres', '52', 'Armazenamento E Atividades Auxiliares Dos Transportes', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5229001', 'Serviços de apoio ao transporte por táxi, inclusive centrais de chamada', '52290', 'Atividades auxiliares dos transportes terrestres não especificadas anteriormente', '522', 'Atividades auxiliares dos transportes terrestres', '52', 'Armazenamento E Atividades Auxiliares Dos Transportes', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5229099', 'Outras atividades auxiliares dos transportes terrestres não especificadas anteriormente', '52290', 'Atividades auxiliares dos transportes terrestres não especificadas anteriormente', '522', 'Atividades auxiliares dos transportes terrestres', '52', 'Armazenamento E Atividades Auxiliares Dos Transportes', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5229002', 'Serviços de reboque de veículos', '52290', 'Atividades auxiliares dos transportes terrestres não especificadas anteriormente', '522', 'Atividades auxiliares dos transportes terrestres', '52', 'Armazenamento E Atividades Auxiliares Dos Transportes', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5231102', 'Atividades do Operador Portuário', '52311', 'Gestão de portos e terminais', '523', 'Atividades auxiliares dos transportes aquaviários', '52', 'Armazenamento E Atividades Auxiliares Dos Transportes', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5231101', 'Administração da infraestrutura portuária', '52311', 'Gestão de portos e terminais', '523', 'Atividades auxiliares dos transportes aquaviários', '52', 'Armazenamento E Atividades Auxiliares Dos Transportes', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5231103', 'Gestão de terminais aquaviários', '52311', 'Gestão de portos e terminais', '523', 'Atividades auxiliares dos transportes aquaviários', '52', 'Armazenamento E Atividades Auxiliares Dos Transportes', 'H', 'Transporte, Armazenagem E Correio', FALSE, FALSE, TRUE, TRUE),
('5232000', 'Atividades de agenciamento marítimo', '52320', 'Atividades de agenciamento marítimo', '523', 'Atividades auxiliares dos transportes aquaviários', '52', 'Armazenamento E Atividades Auxiliares Dos Transportes', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5239799', 'Atividades auxiliares dos transportes aquaviários não especificadas anteriormente', '52397', 'Atividades auxiliares dos transportes aquaviários não especificadas anteriormente', '523', 'Atividades auxiliares dos transportes aquaviários', '52', 'Armazenamento E Atividades Auxiliares Dos Transportes', 'H', 'Transporte, Armazenagem E Correio', FALSE, FALSE, TRUE, TRUE),
('5239700', 'Atividades auxiliares dos transportes aquaviários não especificadas anteriormente', '52397', 'Atividades auxiliares dos transportes aquaviários não especificadas anteriormente', '523', 'Atividades auxiliares dos transportes aquaviários', '52', 'Armazenamento E Atividades Auxiliares Dos Transportes', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, FALSE, FALSE),
('5239701', 'Serviços de praticagem', '52397', 'Atividades auxiliares dos transportes aquaviários não especificadas anteriormente', '523', 'Atividades auxiliares dos transportes aquaviários', '52', 'Armazenamento E Atividades Auxiliares Dos Transportes', 'H', 'Transporte, Armazenagem E Correio', FALSE, FALSE, TRUE, TRUE),
('5240199', 'Atividades auxiliares dos transportes aéreos, exceto operação dos aeroportos e campos de aterrissagem', '52401', 'Atividades auxiliares dos transportes aéreos', '524', 'Atividades auxiliares dos transportes aéreos', '52', 'Armazenamento E Atividades Auxiliares Dos Transportes', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5240101', 'Operação dos aeroportos e campos de aterrissagem', '52401', 'Atividades auxiliares dos transportes aéreos', '524', 'Atividades auxiliares dos transportes aéreos', '52', 'Armazenamento E Atividades Auxiliares Dos Transportes', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5250801', 'Comissaria de despachos', '52508', 'Atividades relacionadas à organização do transporte de carga', '525', 'Atividades relacionadas à organização do transporte de carga', '52', 'Armazenamento E Atividades Auxiliares Dos Transportes', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5250805', 'Operador de transporte multimodal - OTM', '52508', 'Atividades relacionadas à organização do transporte de carga', '525', 'Atividades relacionadas à organização do transporte de carga', '52', 'Armazenamento E Atividades Auxiliares Dos Transportes', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5250802', 'Atividades de despachantes aduaneiros', '52508', 'Atividades relacionadas à organização do transporte de carga', '525', 'Atividades relacionadas à organização do transporte de carga', '52', 'Armazenamento E Atividades Auxiliares Dos Transportes', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5250803', 'Agenciamento de cargas, exceto para o transporte marítimo', '52508', 'Atividades relacionadas à organização do transporte de carga', '525', 'Atividades relacionadas à organização do transporte de carga', '52', 'Armazenamento E Atividades Auxiliares Dos Transportes', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5250804', 'Organização logística do transporte de carga', '52508', 'Atividades relacionadas à organização do transporte de carga', '525', 'Atividades relacionadas à organização do transporte de carga', '52', 'Armazenamento E Atividades Auxiliares Dos Transportes', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5310501', 'Atividades do Correio Nacional', '53105', 'Atividades de Correio', '531', 'Atividades de Correio', '53', 'Correio E Outras Atividades De Entrega', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5310502', 'Atividades de franqueadas e permissionárias do Correio Nacional', '53105', 'Atividades de Correio', '531', 'Atividades de Correio', '53', 'Correio E Outras Atividades De Entrega', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5320201', 'Serviços de malote não realizados pelo Correio Nacional', '53202', 'Atividades de malote e de entrega', '532', 'Atividades de malote e de entrega', '53', 'Correio E Outras Atividades De Entrega', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5320202', 'Serviços de entrega rápida', '53202', 'Atividades de malote e de entrega', '532', 'Atividades de malote e de entrega', '53', 'Correio E Outras Atividades De Entrega', 'H', 'Transporte, Armazenagem E Correio', TRUE, TRUE, TRUE, TRUE),
('5510801', 'Hotéis', '55108', 'Hotéis e similares', '551', 'Hotéis e similares', '55', 'Alojamento', 'I', 'Alojamento E Alimentação', TRUE, TRUE, TRUE, TRUE),
('5510802', 'Apart-hotéis', '55108', 'Hotéis e similares', '551', 'Hotéis e similares', '55', 'Alojamento', 'I', 'Alojamento E Alimentação', TRUE, TRUE, TRUE, TRUE),
('5510803', 'Motéis', '55108', 'Hotéis e similares', '551', 'Hotéis e similares', '55', 'Alojamento', 'I', 'Alojamento E Alimentação', TRUE, TRUE, TRUE, TRUE),
('5590601', 'Albergues, exceto assistenciais', '55906', 'Outros tipos de alojamento não especificados anteriormente', '559', 'Outros tipos de alojamento não especificados anteriormente', '55', 'Alojamento', 'I', 'Alojamento E Alimentação', TRUE, TRUE, TRUE, TRUE),
('5590602', 'Campings', '55906', 'Outros tipos de alojamento não especificados anteriormente', '559', 'Outros tipos de alojamento não especificados anteriormente', '55', 'Alojamento', 'I', 'Alojamento E Alimentação', TRUE, TRUE, TRUE, TRUE),
('5590603', 'Pensões (alojamento)', '55906', 'Outros tipos de alojamento não especificados anteriormente', '559', 'Outros tipos de alojamento não especificados anteriormente', '55', 'Alojamento', 'I', 'Alojamento E Alimentação', TRUE, TRUE, TRUE, TRUE),
('5590699', 'Outros alojamentos não especificados anteriormente', '55906', 'Outros tipos de alojamento não especificados anteriormente', '559', 'Outros tipos de alojamento não especificados anteriormente', '55', 'Alojamento', 'I', 'Alojamento E Alimentação', TRUE, TRUE, TRUE, TRUE),
('5611201', 'Restaurantes e similares', '56112', 'Restaurantes e outros estabelecimentos de serviços de alimentação e bebidas', '561', 'Restaurantes e outros serviços de alimentação e bebidas', '56', 'Alimentação', 'I', 'Alojamento E Alimentação', TRUE, TRUE, TRUE, TRUE),
('5611203', 'Lanchonetes, casas de chá, de sucos e similares', '56112', 'Restaurantes e outros estabelecimentos de serviços de alimentação e bebidas', '561', 'Restaurantes e outros serviços de alimentação e bebidas', '56', 'Alimentação', 'I', 'Alojamento E Alimentação', TRUE, TRUE, TRUE, TRUE),
('5611204', 'Bares e outros estabelecimentos especializados em servir bebidas, sem entretenimento', '56112', 'Restaurantes e outros estabelecimentos de serviços de alimentação e bebidas', '561', 'Restaurantes e outros serviços de alimentação e bebidas', '56', 'Alimentação', 'I', 'Alojamento E Alimentação', FALSE, FALSE, FALSE, TRUE),
('5611205', 'Bares e outros estabelecimentos especializados em servir bebidas, com entretenimento', '56112', 'Restaurantes e outros estabelecimentos de serviços de alimentação e bebidas', '561', 'Restaurantes e outros serviços de alimentação e bebidas', '56', 'Alimentação', 'I', 'Alojamento E Alimentação', FALSE, FALSE, FALSE, TRUE),
('5611202', 'Bares e outros estabelecimentos especializados em servir bebidas', '56112', 'Restaurantes e outros estabelecimentos de serviços de alimentação e bebidas', '561', 'Restaurantes e outros serviços de alimentação e bebidas', '56', 'Alimentação', 'I', 'Alojamento E Alimentação', TRUE, TRUE, TRUE, FALSE),
('5612100', 'Serviços ambulantes de alimentação', '56121', 'Serviços ambulantes de alimentação', '561', 'Restaurantes e outros serviços de alimentação e bebidas', '56', 'Alimentação', 'I', 'Alojamento E Alimentação', TRUE, TRUE, TRUE, TRUE),
('5620104', 'Fornecimento de alimentos preparados preponderantemente para consumo domiciliar', '56201', 'Serviços de catering, bufê e outros serviços de comida preparada', '562', 'Serviços de catering, bufê e outros serviços de comida preparada', '56', 'Alimentação', 'I', 'Alojamento E Alimentação', TRUE, TRUE, TRUE, TRUE),
('5620103', 'Cantinas - serviços de alimentação privativos', '56201', 'Serviços de catering, bufê e outros serviços de comida preparada', '562', 'Serviços de catering, bufê e outros serviços de comida preparada', '56', 'Alimentação', 'I', 'Alojamento E Alimentação', TRUE, TRUE, TRUE, TRUE),
('5620102', 'Serviços de alimentação para eventos e recepções - bufê', '56201', 'Serviços de catering, bufê e outros serviços de comida preparada', '562', 'Serviços de catering, bufê e outros serviços de comida preparada', '56', 'Alimentação', 'I', 'Alojamento E Alimentação', TRUE, TRUE, TRUE, TRUE),
('5620101', 'Fornecimento de alimentos preparados preponderantemente para empresas', '56201', 'Serviços de catering, bufê e outros serviços de comida preparada', '562', 'Serviços de catering, bufê e outros serviços de comida preparada', '56', 'Alimentação', 'I', 'Alojamento E Alimentação', TRUE, TRUE, TRUE, TRUE),
('5811500', 'Edição de livros', '58115', 'Edição de livros', '581', 'Edição de livros, jornais, revistas e outras atividades de edição', '58', 'Edição E Edição Integrada À Impressão', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('5812301', 'Edição de jornais diários', '58123', 'Edição de jornais', '581', 'Edição de livros, jornais, revistas e outras atividades de edição', '58', 'Edição E Edição Integrada À Impressão', 'J', 'Informação E Comunicação', FALSE, FALSE, TRUE, TRUE),
('5812300', 'Edição de jornais', '58123', 'Edição de jornais', '581', 'Edição de livros, jornais, revistas e outras atividades de edição', '58', 'Edição E Edição Integrada À Impressão', 'J', 'Informação E Comunicação', TRUE, TRUE, FALSE, FALSE),
('5812302', 'Edição de jornais não diários', '58123', 'Edição de jornais', '581', 'Edição de livros, jornais, revistas e outras atividades de edição', '58', 'Edição E Edição Integrada À Impressão', 'J', 'Informação E Comunicação', FALSE, FALSE, TRUE, TRUE),
('5813100', 'Edição de revistas', '58131', 'Edição de revistas', '581', 'Edição de livros, jornais, revistas e outras atividades de edição', '58', 'Edição E Edição Integrada À Impressão', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('5819100', 'Edição de cadastros, listas e outros produtos gráficos', '58191', 'Edição de cadastros, listas e outros produtos gráficos', '581', 'Edição de livros, jornais, revistas e outras atividades de edição', '58', 'Edição E Edição Integrada À Impressão', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('5821200', 'Edição integrada à impressão de livros', '58212', 'Edição integrada à impressão de livros', '582', 'Edição integrada à impressão de livros, jornais, revistas e outras publicações', '58', 'Edição E Edição Integrada À Impressão', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('5822100', 'Edição integrada à impressão de jornais', '58221', 'Edição integrada à impressão de jornais', '582', 'Edição integrada à impressão de livros, jornais, revistas e outras publicações', '58', 'Edição E Edição Integrada À Impressão', 'J', 'Informação E Comunicação', TRUE, TRUE, FALSE, FALSE),
('5822101', 'Edição integrada à impressão de jornais diários', '58221', 'Edição integrada à impressão de jornais', '582', 'Edição integrada à impressão de livros, jornais, revistas e outras publicações', '58', 'Edição E Edição Integrada À Impressão', 'J', 'Informação E Comunicação', FALSE, FALSE, TRUE, TRUE),
('5822102', 'Edição integrada à impressão de jornais não diários', '58221', 'Edição integrada à impressão de jornais', '582', 'Edição integrada à impressão de livros, jornais, revistas e outras publicações', '58', 'Edição E Edição Integrada À Impressão', 'J', 'Informação E Comunicação', FALSE, FALSE, TRUE, TRUE),
('5823900', 'Edição integrada à impressão de revistas', '58239', 'Edição integrada à impressão de revistas', '582', 'Edição integrada à impressão de livros, jornais, revistas e outras publicações', '58', 'Edição E Edição Integrada À Impressão', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('5829800', 'Edição integrada à impressão de cadastros, listas e outros produtos gráficos', '58298', 'Edição integrada à impressão de cadastros, listas e outros produtos gráficos', '582', 'Edição integrada à impressão de livros, jornais, revistas e outras publicações', '58', 'Edição E Edição Integrada À Impressão', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('5911101', 'Estúdios cinematográficos', '59111', 'Atividades de produção cinematográfica, de vídeos e de programas de televisão', '591', 'Atividades cinematográficas, produção de vídeos e de programas de televisão', '59', 'Atividades Cinematográficas, Produção De Vídeos E De Programas De Televisão; Gravação De Som E Edição De Música', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('5911199', 'Atividades de produção cinematográfica, de vídeos e de programas de televisão não especificadas anteriormente', '59111', 'Atividades de produção cinematográfica, de vídeos e de programas de televisão', '591', 'Atividades cinematográficas, produção de vídeos e de programas de televisão', '59', 'Atividades Cinematográficas, Produção De Vídeos E De Programas De Televisão; Gravação De Som E Edição De Música', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('5911102', 'Produção de filmes para publicidade', '59111', 'Atividades de produção cinematográfica, de vídeos e de programas de televisão', '591', 'Atividades cinematográficas, produção de vídeos e de programas de televisão', '59', 'Atividades Cinematográficas, Produção De Vídeos E De Programas De Televisão; Gravação De Som E Edição De Música', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('5912099', 'Atividades de pós-produção cinematográfica, de vídeos e de programas de televisão não especificadas anteriormente', '59120', 'Atividades de pós-produção cinematográfica, de vídeos e de programas de televisão', '591', 'Atividades cinematográficas, produção de vídeos e de programas de televisão', '59', 'Atividades Cinematográficas, Produção De Vídeos E De Programas De Televisão; Gravação De Som E Edição De Música', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('5912001', 'Serviços de dublagem', '59120', 'Atividades de pós-produção cinematográfica, de vídeos e de programas de televisão', '591', 'Atividades cinematográficas, produção de vídeos e de programas de televisão', '59', 'Atividades Cinematográficas, Produção De Vídeos E De Programas De Televisão; Gravação De Som E Edição De Música', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('5912002', 'Serviços de mixagem sonora em produção audiovisual', '59120', 'Atividades de pós-produção cinematográfica, de vídeos e de programas de televisão', '591', 'Atividades cinematográficas, produção de vídeos e de programas de televisão', '59', 'Atividades Cinematográficas, Produção De Vídeos E De Programas De Televisão; Gravação De Som E Edição De Música', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('5913800', 'Distribuição cinematográfica, de vídeo e de programas de televisão', '59138', 'Distribuição cinematográfica, de vídeo e de programas de televisão', '591', 'Atividades cinematográficas, produção de vídeos e de programas de televisão', '59', 'Atividades Cinematográficas, Produção De Vídeos E De Programas De Televisão; Gravação De Som E Edição De Música', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('5914600', 'Atividades de exibição cinematográfica', '59146', 'Atividades de exibição cinematográfica', '591', 'Atividades cinematográficas, produção de vídeos e de programas de televisão', '59', 'Atividades Cinematográficas, Produção De Vídeos E De Programas De Televisão; Gravação De Som E Edição De Música', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('5920100', 'Atividades de gravação de som e de edição de música', '59201', 'Atividades de gravação de som e de edição de música', '592', 'Atividades de gravação de som e de edição de música', '59', 'Atividades Cinematográficas, Produção De Vídeos E De Programas De Televisão; Gravação De Som E Edição De Música', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('6010100', 'Atividades de rádio', '60101', 'Atividades de rádio', '601', 'Atividades de rádio', '60', 'Atividades De Rádio E De Televisão', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('6021700', 'Atividades de televisão aberta', '60217', 'Atividades de televisão aberta', '602', 'Atividades de televisão', '60', 'Atividades De Rádio E De Televisão', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('6022501', 'Programadoras', '60225', 'Programadoras e atividades relacionadas à televisão por assinatura', '602', 'Atividades de televisão', '60', 'Atividades De Rádio E De Televisão', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('6022502', 'Atividades relacionadas à televisão por assinatura, exceto programadoras', '60225', 'Programadoras e atividades relacionadas à televisão por assinatura', '602', 'Atividades de televisão', '60', 'Atividades De Rádio E De Televisão', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('6110802', 'Serviços de redes de transporte de telecomunicações - SRTT', '61108', 'Telecomunicações por fio', '611', 'Telecomunicações por fio', '61', 'Telecomunicações', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('6110803', 'Serviços de comunicação multimídia - SCM', '61108', 'Telecomunicações por fio', '611', 'Telecomunicações por fio', '61', 'Telecomunicações', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('6110899', 'Serviços de telecomunicações por fio não especificados anteriormente', '61108', 'Telecomunicações por fio', '611', 'Telecomunicações por fio', '61', 'Telecomunicações', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('6110801', 'Serviços de telefonia fixa comutada - STFC', '61108', 'Telecomunicações por fio', '611', 'Telecomunicações por fio', '61', 'Telecomunicações', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('6120599', 'Serviços de telecomunicações sem fio não especificados anteriormente', '61205', 'Telecomunicações sem fio', '612', 'Telecomunicações sem fio', '61', 'Telecomunicações', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('6120502', 'Serviço móvel especializado - SME', '61205', 'Telecomunicações sem fio', '612', 'Telecomunicações sem fio', '61', 'Telecomunicações', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('6120501', 'Telefonia móvel celular', '61205', 'Telecomunicações sem fio', '612', 'Telecomunicações sem fio', '61', 'Telecomunicações', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('6130200', 'Telecomunicações por satélite', '61302', 'Telecomunicações por satélite', '613', 'Telecomunicações por satélite', '61', 'Telecomunicações', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('6141800', 'Operadoras de televisão por assinatura por cabo', '61418', 'Operadoras de televisão por assinatura por cabo', '614', 'Operadoras de televisão por assinatura', '61', 'Telecomunicações', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('6142600', 'Operadoras de televisão por assinatura por micro-ondas', '61426', 'Operadoras de televisão por assinatura por micro-ondas', '614', 'Operadoras de televisão por assinatura', '61', 'Telecomunicações', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('6143400', 'Operadoras de televisão por assinatura por satélite', '61434', 'Operadoras de televisão por assinatura por satélite', '614', 'Operadoras de televisão por assinatura', '61', 'Telecomunicações', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('6190601', 'Provedores de acesso às redes de comunicações', '61906', 'Outras atividades de telecomunicações', '619', 'Outras atividades de telecomunicações', '61', 'Telecomunicações', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('6190602', 'Provedores de voz sobre protocolo Internet - VOIP', '61906', 'Outras atividades de telecomunicações', '619', 'Outras atividades de telecomunicações', '61', 'Telecomunicações', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('6190699', 'Outras atividades de telecomunicações não especificadas anteriormente', '61906', 'Outras atividades de telecomunicações', '619', 'Outras atividades de telecomunicações', '61', 'Telecomunicações', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('6201502', 'Web desing', '62015', 'Desenvolvimento de programas de computador sob encomenda', '620', 'Atividades dos serviços de tecnologia da informação', '62', 'Atividades Dos Serviços De Tecnologia Da Informação', 'J', 'Informação E Comunicação', FALSE, FALSE, TRUE, TRUE),
('6201501', 'Desenvolvimento de programas de computador sob encomenda', '62015', 'Desenvolvimento de programas de computador sob encomenda', '620', 'Atividades dos serviços de tecnologia da informação', '62', 'Atividades Dos Serviços De Tecnologia Da Informação', 'J', 'Informação E Comunicação', FALSE, FALSE, TRUE, TRUE),
('6201500', 'Desenvolvimento de programas de computador sob encomenda', '62015', 'Desenvolvimento de programas de computador sob encomenda', '620', 'Atividades dos serviços de tecnologia da informação', '62', 'Atividades Dos Serviços De Tecnologia Da Informação', 'J', 'Informação E Comunicação', TRUE, TRUE, FALSE, FALSE),
('6202300', 'Desenvolvimento e licenciamento de programas de computador customizáveis', '62023', 'Desenvolvimento e licenciamento de programas de computador customizáveis', '620', 'Atividades dos serviços de tecnologia da informação', '62', 'Atividades Dos Serviços De Tecnologia Da Informação', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('6203100', 'Desenvolvimento e licenciamento de programas de computador não customizáveis', '62031', 'Desenvolvimento e licenciamento de programas de computador não customizáveis', '620', 'Atividades dos serviços de tecnologia da informação', '62', 'Atividades Dos Serviços De Tecnologia Da Informação', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('6204000', 'Consultoria em tecnologia da informação', '62040', 'Consultoria em tecnologia da informação', '620', 'Atividades dos serviços de tecnologia da informação', '62', 'Atividades Dos Serviços De Tecnologia Da Informação', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('6209100', 'Suporte técnico, manutenção e outros serviços em tecnologia da informação', '62091', 'Suporte técnico, manutenção e outros serviços em tecnologia da informação', '620', 'Atividades dos serviços de tecnologia da informação', '62', 'Atividades Dos Serviços De Tecnologia Da Informação', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('6311900', 'Tratamento de dados, provedores de serviços de aplicação e serviços de hospedagem na Internet', '63119', 'Tratamento de dados, provedores de serviços de aplicação e serviços de hospedagem na Internet', '631', 'Tratamento de dados, hospedagem na Internet e outras atividades relacionadas', '63', 'Atividades De Prestação De Serviços De Informação', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('6319400', 'Portais, provedores de conteúdo e outros serviços de informação na Internet', '63194', 'Portais, provedores de conteúdo e outros serviços de informação na Internet', '631', 'Tratamento de dados, hospedagem na Internet e outras atividades relacionadas', '63', 'Atividades De Prestação De Serviços De Informação', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('6391700', 'Agências de notícias', '63917', 'Agências de notícias', '639', 'Outras atividades de prestação de serviços de informação', '63', 'Atividades De Prestação De Serviços De Informação', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('6399200', 'Outras atividades de prestação de serviços de informação não especificadas anteriormente', '63992', 'Outras atividades de prestação de serviços de informação não especificadas anteriormente', '639', 'Outras atividades de prestação de serviços de informação', '63', 'Atividades De Prestação De Serviços De Informação', 'J', 'Informação E Comunicação', TRUE, TRUE, TRUE, TRUE),
('6410700', 'Banco Central', '64107', 'Banco Central', '641', 'Banco Central', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6421200', 'Bancos comerciais', '64212', 'Bancos comerciais', '642', 'Intermediação monetária - depósitos à vista', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6422100', 'Bancos múltiplos, com carteira comercial', '64221', 'Bancos múltiplos, com carteira comercial', '642', 'Intermediação monetária - depósitos à vista', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6423900', 'Caixas econômicas', '64239', 'Caixas econômicas', '642', 'Intermediação monetária - depósitos à vista', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6424701', 'Bancos cooperativos', '64247', 'Crédito cooperativo', '642', 'Intermediação monetária - depósitos à vista', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6424702', 'Cooperativas centrais de crédito', '64247', 'Crédito cooperativo', '642', 'Intermediação monetária - depósitos à vista', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6424704', 'Cooperativas de crédito rural', '64247', 'Crédito cooperativo', '642', 'Intermediação monetária - depósitos à vista', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6424703', 'Cooperativas de crédito mútuo', '64247', 'Crédito cooperativo', '642', 'Intermediação monetária - depósitos à vista', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6431000', 'Bancos múltiplos, sem carteira comercial', '64310', 'Bancos múltiplos, sem carteira comercial', '643', 'Intermediação não monetária - outros instrumentos de captação', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6432800', 'Bancos de investimento', '64328', 'Bancos de investimento', '643', 'Intermediação não monetária - outros instrumentos de captação', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6433600', 'Bancos de desenvolvimento', '64336', 'Bancos de desenvolvimento', '643', 'Intermediação não monetária - outros instrumentos de captação', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6434400', 'Agências de fomento', '64344', 'Agências de fomento', '643', 'Intermediação não monetária - outros instrumentos de captação', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6435203', 'Companhias hipotecárias', '64352', 'Crédito imobiliário', '643', 'Intermediação não monetária - outros instrumentos de captação', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6435202', 'Associações de poupança e empréstimo', '64352', 'Crédito imobiliário', '643', 'Intermediação não monetária - outros instrumentos de captação', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6435201', 'Sociedades de crédito imobiliário', '64352', 'Crédito imobiliário', '643', 'Intermediação não monetária - outros instrumentos de captação', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6436100', 'Sociedades de crédito, financiamento e investimento - financeiras', '64361', 'Sociedades de crédito, financiamento e investimento - financeiras', '643', 'Intermediação não monetária - outros instrumentos de captação', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6437900', 'Sociedades de crédito ao microempreendedor', '64379', 'Sociedades de crédito ao microempreendedor', '643', 'Intermediação não monetária - outros instrumentos de captação', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6438799', 'Outras instituições de intermediação não monetária não especificadas anteriormente', '64387', 'Bancos de câmbio e outras instituições de intermediação não monetária', '643', 'Intermediação não monetária - outros instrumentos de captação', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6438701', 'Bancos de câmbio', '64387', 'Bancos de câmbio e outras instituições de intermediação não monetária', '643', 'Intermediação não monetária - outros instrumentos de captação', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6440900', 'Arrendamento mercantil', '64409', 'Arrendamento mercantil', '644', 'Arrendamento mercantil', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6450600', 'Sociedades de capitalização', '64506', 'Sociedades de capitalização', '645', 'Sociedades de capitalização', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6461100', 'Holdings de instituições financeiras', '64611', 'Holdings de instituições financeiras', '646', 'Atividades de sociedades de participação', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6462000', 'Holdings de instituições não financeiras', '64620', 'Holdings de instituições não financeiras', '646', 'Atividades de sociedades de participação', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6463800', 'Outras sociedades de participação, exceto holdings', '64638', 'Outras sociedades de participação, exceto holdings', '646', 'Atividades de sociedades de participação', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6470103', 'Fundos de investimento imobiliários', '64701', 'Fundos de investimento', '647', 'Fundos de investimento', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6470102', 'Fundos de investimento previdenciários', '64701', 'Fundos de investimento', '647', 'Fundos de investimento', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6470101', 'Fundos de investimento, exceto previdenciários e imobiliários', '64701', 'Fundos de investimento', '647', 'Fundos de investimento', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6491300', 'Sociedades de fomento mercantil - factoring', '64913', 'Sociedades de fomento mercantil - factoring', '649', 'Atividades de serviços financeiros não especificadas anteriormente', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6492100', 'Securitização de créditos', '64921', 'Securitização de créditos', '649', 'Atividades de serviços financeiros não especificadas anteriormente', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6493000', 'Administração de consórcios para aquisição de bens e direitos', '64930', 'Administração de consórcios para aquisição de bens e direitos', '649', 'Atividades de serviços financeiros não especificadas anteriormente', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6499902', 'Sociedades de investimento', '64999', 'Outras atividades de serviços financeiros não especificadas anteriormente', '649', 'Atividades de serviços financeiros não especificadas anteriormente', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6499904', 'Caixas de financiamento de corporações', '64999', 'Outras atividades de serviços financeiros não especificadas anteriormente', '649', 'Atividades de serviços financeiros não especificadas anteriormente', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6499901', 'Clubes de investimento', '64999', 'Outras atividades de serviços financeiros não especificadas anteriormente', '649', 'Atividades de serviços financeiros não especificadas anteriormente', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6499999', 'Outras atividades de serviços financeiros não especificadas anteriormente', '64999', 'Outras atividades de serviços financeiros não especificadas anteriormente', '649', 'Atividades de serviços financeiros não especificadas anteriormente', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6499903', 'Fundo garantidor de crédito', '64999', 'Outras atividades de serviços financeiros não especificadas anteriormente', '649', 'Atividades de serviços financeiros não especificadas anteriormente', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6499905', 'Concessão de crédito pelas OSCIP', '64999', 'Outras atividades de serviços financeiros não especificadas anteriormente', '649', 'Atividades de serviços financeiros não especificadas anteriormente', '64', 'Atividades De Serviços Financeiros', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6511102', 'Planos de auxílio-funeral', '65111', 'Seguros de vida', '651', 'Seguros de vida e não vida', '65', 'Seguros, Resseguros, Previdência Complementar E Planos De Saúde', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6511101', 'Sociedade seguradora de seguros vida', '65111', 'Seguros de vida', '651', 'Seguros de vida e não vida', '65', 'Seguros, Resseguros, Previdência Complementar E Planos De Saúde', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6512000', 'Sociedade seguradora de seguros não vida', '65120', 'Seguros não vida', '651', 'Seguros de vida e não vida', '65', 'Seguros, Resseguros, Previdência Complementar E Planos De Saúde', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6520100', 'Sociedade seguradora de seguros-saúde', '65201', 'Seguros-saúde', '652', 'Seguros-saúde', '65', 'Seguros, Resseguros, Previdência Complementar E Planos De Saúde', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6530800', 'Resseguros', '65308', 'Resseguros', '653', 'Resseguros', '65', 'Seguros, Resseguros, Previdência Complementar E Planos De Saúde', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6541300', 'Previdência complementar fechada', '65413', 'Previdência complementar fechada', '654', 'Previdência complementar', '65', 'Seguros, Resseguros, Previdência Complementar E Planos De Saúde', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6542100', 'Previdência complementar aberta', '65421', 'Previdência complementar aberta', '654', 'Previdência complementar', '65', 'Seguros, Resseguros, Previdência Complementar E Planos De Saúde', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6550200', 'Planos de saúde', '65502', 'Planos de saúde', '655', 'Planos de saúde', '65', 'Seguros, Resseguros, Previdência Complementar E Planos De Saúde', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6611801', 'Bolsa de valores', '66118', 'Administração de bolsas e mercados de balcão organizados', '661', 'Atividades auxiliares dos serviços financeiros', '66', 'Atividades Auxiliares Dos Serviços Financeiros, Seguros, Previdência Complementar E Planos De Saúde', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6611802', 'Bolsa de mercadorias', '66118', 'Administração de bolsas e mercados de balcão organizados', '661', 'Atividades auxiliares dos serviços financeiros', '66', 'Atividades Auxiliares Dos Serviços Financeiros, Seguros, Previdência Complementar E Planos De Saúde', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6611804', 'Administração de mercados de balcão organizados', '66118', 'Administração de bolsas e mercados de balcão organizados', '661', 'Atividades auxiliares dos serviços financeiros', '66', 'Atividades Auxiliares Dos Serviços Financeiros, Seguros, Previdência Complementar E Planos De Saúde', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6611803', 'Bolsa de mercadorias e futuros', '66118', 'Administração de bolsas e mercados de balcão organizados', '661', 'Atividades auxiliares dos serviços financeiros', '66', 'Atividades Auxiliares Dos Serviços Financeiros, Seguros, Previdência Complementar E Planos De Saúde', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6612605', 'Agentes de investimentos em aplicações financeiras', '66126', 'Atividades de intermediários em transações de títulos, valores mobiliários e mercadorias', '661', 'Atividades auxiliares dos serviços financeiros', '66', 'Atividades Auxiliares Dos Serviços Financeiros, Seguros, Previdência Complementar E Planos De Saúde', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6612604', 'Corretoras de contratos de mercadorias', '66126', 'Atividades de intermediários em transações de títulos, valores mobiliários e mercadorias', '661', 'Atividades auxiliares dos serviços financeiros', '66', 'Atividades Auxiliares Dos Serviços Financeiros, Seguros, Previdência Complementar E Planos De Saúde', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6612603', 'Corretoras de câmbio', '66126', 'Atividades de intermediários em transações de títulos, valores mobiliários e mercadorias', '661', 'Atividades auxiliares dos serviços financeiros', '66', 'Atividades Auxiliares Dos Serviços Financeiros, Seguros, Previdência Complementar E Planos De Saúde', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6612601', 'Corretoras de títulos e valores mobiliários', '66126', 'Atividades de intermediários em transações de títulos, valores mobiliários e mercadorias', '661', 'Atividades auxiliares dos serviços financeiros', '66', 'Atividades Auxiliares Dos Serviços Financeiros, Seguros, Previdência Complementar E Planos De Saúde', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6612602', 'Distribuidoras de títulos e valores mobiliários', '66126', 'Atividades de intermediários em transações de títulos, valores mobiliários e mercadorias', '661', 'Atividades auxiliares dos serviços financeiros', '66', 'Atividades Auxiliares Dos Serviços Financeiros, Seguros, Previdência Complementar E Planos De Saúde', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6613400', 'Administração de cartões de crédito', '66134', 'Administração de cartões de crédito', '661', 'Atividades auxiliares dos serviços financeiros', '66', 'Atividades Auxiliares Dos Serviços Financeiros, Seguros, Previdência Complementar E Planos De Saúde', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6619302', 'Correspondentes de instituições financeiras', '66193', 'Atividades auxiliares dos serviços financeiros não especificadas anteriormente', '661', 'Atividades auxiliares dos serviços financeiros', '66', 'Atividades Auxiliares Dos Serviços Financeiros, Seguros, Previdência Complementar E Planos De Saúde', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6619399', 'Outras atividades auxiliares dos serviços financeiros não especificadas anteriormente', '66193', 'Atividades auxiliares dos serviços financeiros não especificadas anteriormente', '661', 'Atividades auxiliares dos serviços financeiros', '66', 'Atividades Auxiliares Dos Serviços Financeiros, Seguros, Previdência Complementar E Planos De Saúde', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6619304', 'Caixas eletrônicos', '66193', 'Atividades auxiliares dos serviços financeiros não especificadas anteriormente', '661', 'Atividades auxiliares dos serviços financeiros', '66', 'Atividades Auxiliares Dos Serviços Financeiros, Seguros, Previdência Complementar E Planos De Saúde', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6619301', 'Serviços de liquidação e custódia', '66193', 'Atividades auxiliares dos serviços financeiros não especificadas anteriormente', '661', 'Atividades auxiliares dos serviços financeiros', '66', 'Atividades Auxiliares Dos Serviços Financeiros, Seguros, Previdência Complementar E Planos De Saúde', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6619305', 'Operadoras de cartões de débito', '66193', 'Atividades auxiliares dos serviços financeiros não especificadas anteriormente', '661', 'Atividades auxiliares dos serviços financeiros', '66', 'Atividades Auxiliares Dos Serviços Financeiros, Seguros, Previdência Complementar E Planos De Saúde', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6619303', 'Representações de bancos estrangeiros', '66193', 'Atividades auxiliares dos serviços financeiros não especificadas anteriormente', '661', 'Atividades auxiliares dos serviços financeiros', '66', 'Atividades Auxiliares Dos Serviços Financeiros, Seguros, Previdência Complementar E Planos De Saúde', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6621502', 'Auditoria e consultoria atuarial', '66215', 'Avaliação de riscos e perdas', '662', 'Atividades auxiliares dos seguros, da previdência complementar e dos planos de saúde', '66', 'Atividades Auxiliares Dos Serviços Financeiros, Seguros, Previdência Complementar E Planos De Saúde', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6621501', 'Peritos e avaliadores de seguros', '66215', 'Avaliação de riscos e perdas', '662', 'Atividades auxiliares dos seguros, da previdência complementar e dos planos de saúde', '66', 'Atividades Auxiliares Dos Serviços Financeiros, Seguros, Previdência Complementar E Planos De Saúde', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6622300', 'Corretores e agentes de seguros, de planos de previdência complementar e de saúde', '66223', 'Corretores e agentes de seguros, de planos de previdência complementar e de saúde', '662', 'Atividades auxiliares dos seguros, da previdência complementar e dos planos de saúde', '66', 'Atividades Auxiliares Dos Serviços Financeiros, Seguros, Previdência Complementar E Planos De Saúde', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6629100', 'Atividades auxiliares dos seguros, da previdência complementar e dos planos de saúde não especificadas anteriormente', '66291', 'Atividades auxiliares dos seguros, da previdência complementar e dos planos de saúde não especificadas anteriormente', '662', 'Atividades auxiliares dos seguros, da previdência complementar e dos planos de saúde', '66', 'Atividades Auxiliares Dos Serviços Financeiros, Seguros, Previdência Complementar E Planos De Saúde', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6630400', 'Atividades de administração de fundos por contrato ou comissão', '66304', 'Atividades de administração de fundos por contrato ou
comissão', '663', 'Atividades de administração de fundos por contrato ou
comissão', '66', 'Atividades Auxiliares Dos Serviços Financeiros, Seguros, Previdência Complementar E Planos De Saúde', 'K', 'Atividades Financeiras, De Seguros E Serviços Relacionados', TRUE, TRUE, TRUE, TRUE),
('6810203', 'Loteamento de imóveis próprios', '68102', 'Atividades imobiliárias de imóveis próprios', '681', 'Atividades imobiliárias de imóveis próprios', '68', 'Atividades Imobiliárias', 'L', 'Atividades Imobiliárias', FALSE, TRUE, TRUE, TRUE),
('6810202', 'Aluguel de imóveis próprios', '68102', 'Atividades imobiliárias de imóveis próprios', '681', 'Atividades imobiliárias de imóveis próprios', '68', 'Atividades Imobiliárias', 'L', 'Atividades Imobiliárias', TRUE, TRUE, TRUE, TRUE),
('6810201', 'Compra e venda de imóveis próprios', '68102', 'Atividades imobiliárias de imóveis próprios', '681', 'Atividades imobiliárias de imóveis próprios', '68', 'Atividades Imobiliárias', 'L', 'Atividades Imobiliárias', TRUE, TRUE, TRUE, TRUE),
('6821801', 'Corretagem na compra e venda e avaliação de imóveis', '68218', 'Intermediação na compra, venda e aluguel de imóveis', '682', 'Atividades imobiliárias por contrato ou comissão', '68', 'Atividades Imobiliárias', 'L', 'Atividades Imobiliárias', TRUE, TRUE, TRUE, TRUE),
('6821802', 'Corretagem no aluguel de imóveis', '68218', 'Intermediação na compra, venda e aluguel de imóveis', '682', 'Atividades imobiliárias por contrato ou comissão', '68', 'Atividades Imobiliárias', 'L', 'Atividades Imobiliárias', TRUE, TRUE, TRUE, TRUE),
('6822600', 'Gestão e administração da propriedade imobiliária', '68226', 'Gestão e administração da propriedade imobiliária', '682', 'Atividades imobiliárias por contrato ou comissão', '68', 'Atividades Imobiliárias', 'L', 'Atividades Imobiliárias', TRUE, TRUE, TRUE, TRUE),
('6911703', 'Agente de propriedade industrial', '69117', 'Atividades jurídicas, exceto cartórios', '691', 'Atividades jurídicas', '69', 'Atividades Jurídicas, De Contabilidade E De Auditoria', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('6911701', 'Serviços advocatícios', '69117', 'Atividades jurídicas, exceto cartórios', '691', 'Atividades jurídicas', '69', 'Atividades Jurídicas, De Contabilidade E De Auditoria', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('6911702', 'Atividades auxiliares da justiça', '69117', 'Atividades jurídicas, exceto cartórios', '691', 'Atividades jurídicas', '69', 'Atividades Jurídicas, De Contabilidade E De Auditoria', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('6912500', 'Cartórios', '69125', 'Cartórios', '691', 'Atividades jurídicas', '69', 'Atividades Jurídicas, De Contabilidade E De Auditoria', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('6920602', 'Atividades de consultoria e auditoria contábil e tributária', '69206', 'Atividades de contabilidade, consultoria e auditoria contábil e tributária', '692', 'Atividades de contabilidade, consultoria e auditoria contábil e tributária', '69', 'Atividades Jurídicas, De Contabilidade E De Auditoria', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('6920601', 'Atividades de contabilidade', '69206', 'Atividades de contabilidade, consultoria e auditoria contábil e tributária', '692', 'Atividades de contabilidade, consultoria e auditoria contábil e tributária', '69', 'Atividades Jurídicas, De Contabilidade E De Auditoria', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('7020400', 'Atividades de consultoria em gestão empresarial, exceto consultoria técnica específica', '70204', 'Atividades de consultoria em gestão empresarial', '702', 'Atividades de consultoria em gestão empresarial', '70', 'Atividades De Sedes De Empresas E De Consultoria Em Gestão Empresarial', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('7111100', 'Serviços de arquitetura', '71111', 'Serviços de arquitetura', '711', 'Serviços de arquitetura e engenharia e atividades técnicas relacionadas', '71', 'Serviços De Arquitetura E Engenharia; Testes E Análises Técnicas', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('7112000', 'Serviços de engenharia', '71120', 'Serviços de engenharia', '711', 'Serviços de arquitetura e engenharia e atividades técnicas relacionadas', '71', 'Serviços De Arquitetura E Engenharia; Testes E Análises Técnicas', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('7119704', 'Serviços de perícia técnica relacionados à segurança do trabalho', '71197', 'Atividades técnicas relacionadas à arquitetura e engenharia', '711', 'Serviços de arquitetura e engenharia e atividades técnicas relacionadas', '71', 'Serviços De Arquitetura E Engenharia; Testes E Análises Técnicas', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('7119701', 'Serviços de cartografia, topografia e geodésia', '71197', 'Atividades técnicas relacionadas à arquitetura e engenharia', '711', 'Serviços de arquitetura e engenharia e atividades técnicas relacionadas', '71', 'Serviços De Arquitetura E Engenharia; Testes E Análises Técnicas', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('7119703', 'Serviços de desenho técnico relacionados à arquitetura e engenharia', '71197', 'Atividades técnicas relacionadas à arquitetura e engenharia', '711', 'Serviços de arquitetura e engenharia e atividades técnicas relacionadas', '71', 'Serviços De Arquitetura E Engenharia; Testes E Análises Técnicas', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('7119799', 'Atividades técnicas relacionadas à engenharia e arquitetura não especificadas anteriormente', '71197', 'Atividades técnicas relacionadas à arquitetura e engenharia', '711', 'Serviços de arquitetura e engenharia e atividades técnicas relacionadas', '71', 'Serviços De Arquitetura E Engenharia; Testes E Análises Técnicas', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('7119702', 'Atividades de estudos geológicos', '71197', 'Atividades técnicas relacionadas à arquitetura e engenharia', '711', 'Serviços de arquitetura e engenharia e atividades técnicas relacionadas', '71', 'Serviços De Arquitetura E Engenharia; Testes E Análises Técnicas', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('7120100', 'Testes e análises técnicas', '71201', 'Testes e análises técnicas', '712', 'Testes e análises técnicas', '71', 'Serviços De Arquitetura E Engenharia; Testes E Análises Técnicas', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('7210000', 'Pesquisa e desenvolvimento experimental em ciências físicas e naturais', '72100', 'Pesquisa e desenvolvimento experimental em ciências físicas e naturais', '721', 'Pesquisa e desenvolvimento experimental em ciências físicas e naturais', '72', 'Pesquisa E Desenvolvimento Científico', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('7220700', 'Pesquisa e desenvolvimento experimental em ciências sociais e humanas', '72207', 'Pesquisa e desenvolvimento experimental em ciências sociais e humanas', '722', 'Pesquisa e desenvolvimento experimental em ciências sociais e humanas', '72', 'Pesquisa E Desenvolvimento Científico', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('7311400', 'Agências de publicidade', '73114', 'Agências de publicidade', '731', 'Publicidade', '73', 'Publicidade E Pesquisa De Mercado', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('7312200', 'Agenciamento de espaços para publicidade, exceto em veículos de comunicação', '73122', 'Agenciamento de espaços para publicidade, exceto em veículos de comunicação', '731', 'Publicidade', '73', 'Publicidade E Pesquisa De Mercado', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('7319099', 'Outras atividades de publicidade não especificadas anteriormente', '73190', 'Atividades de publicidade não especificadas anteriormente', '731', 'Publicidade', '73', 'Publicidade E Pesquisa De Mercado', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('7319003', 'Marketing direto', '73190', 'Atividades de publicidade não especificadas anteriormente', '731', 'Publicidade', '73', 'Publicidade E Pesquisa De Mercado', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('7319001', 'Criação de estandes para feiras e exposições', '73190', 'Atividades de publicidade não especificadas anteriormente', '731', 'Publicidade', '73', 'Publicidade E Pesquisa De Mercado', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('7319002', 'Promoção de vendas', '73190', 'Atividades de publicidade não especificadas anteriormente', '731', 'Publicidade', '73', 'Publicidade E Pesquisa De Mercado', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('7319004', 'Consultoria em publicidade', '73190', 'Atividades de publicidade não especificadas anteriormente', '731', 'Publicidade', '73', 'Publicidade E Pesquisa De Mercado', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('7320300', 'Pesquisas de mercado e de opinião pública', '73203', 'Pesquisas de mercado e de opinião pública', '732', 'Pesquisas de mercado e de opinião pública', '73', 'Publicidade E Pesquisa De Mercado', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('7410203', 'Desing de produto', '74102', 'Design e decoração de interiores', '741', 'Design e decoração de interiores', '74', 'Outras Atividades Profissionais, Científicas E Técnicas', 'M', 'Atividades Profissionais, Científicas E Técnicas', FALSE, FALSE, TRUE, TRUE),
('7410202', 'Design de interiores', '74102', 'Design e decoração de interiores', '741', 'Design e decoração de interiores', '74', 'Outras Atividades Profissionais, Científicas E Técnicas', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('7410201', 'Design', '74102', 'Design e decoração de interiores', '741', 'Design e decoração de interiores', '74', 'Outras Atividades Profissionais, Científicas E Técnicas', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, FALSE, FALSE),
('7410299', 'Atividades de desing não especificadas anteriormente', '74102', 'Design e decoração de interiores', '741', 'Design e decoração de interiores', '74', 'Outras Atividades Profissionais, Científicas E Técnicas', 'M', 'Atividades Profissionais, Científicas E Técnicas', FALSE, FALSE, TRUE, TRUE),
('7420004', 'Filmagem de festas e eventos', '74200', 'Atividades fotográficas e similares', '742', 'Atividades fotográficas e similares', '74', 'Outras Atividades Profissionais, Científicas E Técnicas', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('7420003', 'Laboratórios fotográficos', '74200', 'Atividades fotográficas e similares', '742', 'Atividades fotográficas e similares', '74', 'Outras Atividades Profissionais, Científicas E Técnicas', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('7420001', 'Atividades de produção de fotografias, exceto aérea e submarina', '74200', 'Atividades fotográficas e similares', '742', 'Atividades fotográficas e similares', '74', 'Outras Atividades Profissionais, Científicas E Técnicas', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('7420002', 'Atividades de produção de fotografias aéreas e submarinas', '74200', 'Atividades fotográficas e similares', '742', 'Atividades fotográficas e similares', '74', 'Outras Atividades Profissionais, Científicas E Técnicas', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('7420005', 'Serviços de microfilmagem', '74200', 'Atividades fotográficas e similares', '742', 'Atividades fotográficas e similares', '74', 'Outras Atividades Profissionais, Científicas E Técnicas', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('7490104', 'Atividades de intermediação e agenciamento de serviços e negócios em geral, exceto imobiliários', '74901', 'Atividades profissionais, científicas e técnicas não especificadas anteriormente', '749', 'Atividades profissionais, científicas e técnicas não especificadas anteriormente', '74', 'Outras Atividades Profissionais, Científicas E Técnicas', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('7490199', 'Outras atividades profissionais, científicas e técnicas não especificadas anteriormente', '74901', 'Atividades profissionais, científicas e técnicas não especificadas anteriormente', '749', 'Atividades profissionais, científicas e técnicas não especificadas anteriormente', '74', 'Outras Atividades Profissionais, Científicas E Técnicas', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('7490105', 'Agenciamento de profissionais para atividades esportivas, culturais e artísticas', '74901', 'Atividades profissionais, científicas e técnicas não especificadas anteriormente', '749', 'Atividades profissionais, científicas e técnicas não especificadas anteriormente', '74', 'Outras Atividades Profissionais, Científicas E Técnicas', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('7490101', 'Serviços de tradução, interpretação e similares', '74901', 'Atividades profissionais, científicas e técnicas não especificadas anteriormente', '749', 'Atividades profissionais, científicas e técnicas não especificadas anteriormente', '74', 'Outras Atividades Profissionais, Científicas E Técnicas', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('7490102', 'Escafandria e mergulho', '74901', 'Atividades profissionais, científicas e técnicas não especificadas anteriormente', '749', 'Atividades profissionais, científicas e técnicas não especificadas anteriormente', '74', 'Outras Atividades Profissionais, Científicas E Técnicas', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('7490103', 'Serviços de agronomia e de consultoria às atividades agrícolas e pecuárias', '74901', 'Atividades profissionais, científicas e técnicas não especificadas anteriormente', '749', 'Atividades profissionais, científicas e técnicas não especificadas anteriormente', '74', 'Outras Atividades Profissionais, Científicas E Técnicas', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('7500100', 'Atividades veterinárias', '75001', 'Atividades veterinárias', '750', 'Atividades veterinárias', '75', 'Atividades Veterinárias', 'M', 'Atividades Profissionais, Científicas E Técnicas', TRUE, TRUE, TRUE, TRUE),
('7711000', 'Locação de automóveis sem condutor', '77110', 'Locação de automóveis sem condutor', '771', 'Locação de meios de transporte sem condutor', '77', 'Aluguéis Não Imobiliários E Gestão De Ativos Intangíveis Não Financeiros', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('7719599', 'Locação de outros meios de transporte não especificados anteriormente, sem condutor', '77195', 'Locação de meios de transporte, exceto automóveis, sem condutor', '771', 'Locação de meios de transporte sem condutor', '77', 'Aluguéis Não Imobiliários E Gestão De Ativos Intangíveis Não Financeiros', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('7719502', 'Locação de aeronaves sem tripulação', '77195', 'Locação de meios de transporte, exceto automóveis, sem condutor', '771', 'Locação de meios de transporte sem condutor', '77', 'Aluguéis Não Imobiliários E Gestão De Ativos Intangíveis Não Financeiros', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('7719501', 'Locação de embarcações sem tripulação, exceto para fins
recreativos', '77195', 'Locação de meios de transporte, exceto automóveis, sem condutor', '771', 'Locação de meios de transporte sem condutor', '77', 'Aluguéis Não Imobiliários E Gestão De Ativos Intangíveis Não Financeiros', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('7721700', 'Aluguel de equipamentos recreativos e esportivos', '77217', 'Aluguel de equipamentos recreativos e esportivos', '772', 'Aluguel de objetos pessoais e domésticos', '77', 'Aluguéis Não Imobiliários E Gestão De Ativos Intangíveis Não Financeiros', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('7722500', 'Aluguel de fitas de vídeo, DVDs e similares', '77225', 'Aluguel de fitas de vídeo, DVDs e similares', '772', 'Aluguel de objetos pessoais e domésticos', '77', 'Aluguéis Não Imobiliários E Gestão De Ativos Intangíveis Não Financeiros', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('7723300', 'Aluguel de objetos do vestuário, jóias e acessórios', '77233', 'Aluguel de objetos do vestuário, jóias e acessórios', '772', 'Aluguel de objetos pessoais e domésticos', '77', 'Aluguéis Não Imobiliários E Gestão De Ativos Intangíveis Não Financeiros', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('7729202', 'Aluguel de móveis, utensílios e aparelhos de uso doméstico e pessoal; instrumentos musicais', '77292', 'Aluguel de objetos pessoais e domésticos não especificados anteriormente', '772', 'Aluguel de objetos pessoais e domésticos', '77', 'Aluguéis Não Imobiliários E Gestão De Ativos Intangíveis Não Financeiros', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('7729299', 'Aluguel de outros objetos pessoais e domésticos não especificados anteriormente', '77292', 'Aluguel de objetos pessoais e domésticos não especificados anteriormente', '772', 'Aluguel de objetos pessoais e domésticos', '77', 'Aluguéis Não Imobiliários E Gestão De Ativos Intangíveis Não Financeiros', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('7729203', 'Aluguel de material médico', '77292', 'Aluguel de objetos pessoais e domésticos não especificados anteriormente', '772', 'Aluguel de objetos pessoais e domésticos', '77', 'Aluguéis Não Imobiliários E Gestão De Ativos Intangíveis Não Financeiros', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('7729201', 'Aluguel de aparelhos de jogos eletrônicos', '77292', 'Aluguel de objetos pessoais e domésticos não especificados anteriormente', '772', 'Aluguel de objetos pessoais e domésticos', '77', 'Aluguéis Não Imobiliários E Gestão De Ativos Intangíveis Não Financeiros', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('7731400', 'Aluguel de máquinas e equipamentos agrícolas sem operador', '77314', 'Aluguel de máquinas e equipamentos agrícolas sem operador', '773', 'Aluguel de máquinas e equipamentos sem operador', '77', 'Aluguéis Não Imobiliários E Gestão De Ativos Intangíveis Não Financeiros', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('7732201', 'Aluguel de máquinas e equipamentos para construção sem operador, exceto andaimes', '77322', 'Aluguel de máquinas e equipamentos para construção sem operador', '773', 'Aluguel de máquinas e equipamentos sem operador', '77', 'Aluguéis Não Imobiliários E Gestão De Ativos Intangíveis Não Financeiros', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('7732202', 'Aluguel de andaimes', '77322', 'Aluguel de máquinas e equipamentos para construção sem operador', '773', 'Aluguel de máquinas e equipamentos sem operador', '77', 'Aluguéis Não Imobiliários E Gestão De Ativos Intangíveis Não Financeiros', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('7733100', 'Aluguel de máquinas e equipamentos para escritório', '77331', 'Aluguel de máquinas e equipamentos para escritório', '773', 'Aluguel de máquinas e equipamentos sem operador', '77', 'Aluguéis Não Imobiliários E Gestão De Ativos Intangíveis Não Financeiros', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('7739002', 'Aluguel de equipamentos científicos, médicos e hospitalares, sem operador', '77390', 'Aluguel de máquinas e equipamentos não especificados anteriormente', '773', 'Aluguel de máquinas e equipamentos sem operador', '77', 'Aluguéis Não Imobiliários E Gestão De Ativos Intangíveis Não Financeiros', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('7739099', 'Aluguel de outras máquinas e equipamentos comerciais e industriais não especificados anteriormente, sem operador', '77390', 'Aluguel de máquinas e equipamentos não especificados anteriormente', '773', 'Aluguel de máquinas e equipamentos sem operador', '77', 'Aluguéis Não Imobiliários E Gestão De Ativos Intangíveis Não Financeiros', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('7739003', 'Aluguel de palcos, coberturas e outras estruturas de uso temporário, exceto andaimes', '77390', 'Aluguel de máquinas e equipamentos não especificados anteriormente', '773', 'Aluguel de máquinas e equipamentos sem operador', '77', 'Aluguéis Não Imobiliários E Gestão De Ativos Intangíveis Não Financeiros', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('7739001', 'Aluguel de máquinas e equipamentos para extração de minérios e petróleo, sem operador', '77390', 'Aluguel de máquinas e equipamentos não especificados anteriormente', '773', 'Aluguel de máquinas e equipamentos sem operador', '77', 'Aluguéis Não Imobiliários E Gestão De Ativos Intangíveis Não Financeiros', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('7740300', 'Gestão de ativos intangíveis não financeiros', '77403', 'Gestão de ativos intangíveis não financeiros', '774', 'Gestão de ativos intangíveis não financeiros', '77', 'Aluguéis Não Imobiliários E Gestão De Ativos Intangíveis Não Financeiros', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('7810800', 'Seleção e agenciamento de mão de obra', '78108', 'Seleção e agenciamento de mão de obra', '781', 'Seleção e agenciamento de mão de obra', '78', 'Seleção, Agenciamento E Locação De Mão De Obra', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('7820500', 'Locação de mão de obra temporária', '78205', 'Locação de mão de obra temporária', '782', 'Locação de mão de obra temporária', '78', 'Seleção, Agenciamento E Locação De Mão De Obra', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('7830200', 'Fornecimento e gestão de recursos humanos para terceiros', '78302', 'Fornecimento e gestão de recursos humanos para terceiros', '783', 'Fornecimento e gestão de recursos humanos para terceiros', '78', 'Seleção, Agenciamento E Locação De Mão De Obra', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('7911200', 'Agências de viagens', '79112', 'Agências de viagens', '791', 'Agências de viagens e operadores turísticos', '79', 'Agências De Viagens, Operadores Turísticos E Serviços De Reservas', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('7912100', 'Operadores turísticos', '79121', 'Operadores turísticos', '791', 'Agências de viagens e operadores turísticos', '79', 'Agências De Viagens, Operadores Turísticos E Serviços De Reservas', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('7990200', 'Serviços de reservas e outros serviços de turismo não especificados anteriormente', '79902', 'Serviços de reservas e outros serviços de turismo não especificados anteriormente', '799', 'Serviços de reservas e outros serviços de turismo não especificados anteriormente', '79', 'Agências De Viagens, Operadores Turísticos E Serviços De Reservas', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('8011102', 'Serviços de adestramento de cães de guarda', '80111', 'Atividades de vigilância e segurança privada', '801', 'Atividades de vigilância, segurança privada e transporte de valores', '80', 'Atividades De Vigilância, Segurança E Investigação', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('8011101', 'Atividades de vigilância e segurança privada', '80111', 'Atividades de vigilância e segurança privada', '801', 'Atividades de vigilância, segurança privada e transporte de valores', '80', 'Atividades De Vigilância, Segurança E Investigação', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('8012900', 'Atividades de transporte de valores', '80129', 'Atividades de transporte de valores', '801', 'Atividades de vigilância, segurança privada e transporte de valores', '80', 'Atividades De Vigilância, Segurança E Investigação', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('8020001', 'Atividades de monitoramento de sistemas de segurança eletrônico', '80200', 'Atividades de monitoramento de sistemas de segurança', '802', 'Atividades de monitoramento de sistemas de segurança', '80', 'Atividades De Vigilância, Segurança E Investigação', 'N', 'Atividades Administrativas E Serviços Complementares', FALSE, FALSE, TRUE, TRUE),
('8020000', 'Atividades de monitoramento de sistemas de segurança', '80200', 'Atividades de monitoramento de sistemas de segurança', '802', 'Atividades de monitoramento de sistemas de segurança', '80', 'Atividades De Vigilância, Segurança E Investigação', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, FALSE, FALSE),
('8020002', 'Outras atividades de serviços de segurança', '80200', 'Atividades de monitoramento de sistemas de segurança', '802', 'Atividades de monitoramento de sistemas de segurança', '80', 'Atividades De Vigilância, Segurança E Investigação', 'N', 'Atividades Administrativas E Serviços Complementares', FALSE, FALSE, TRUE, TRUE),
('8030700', 'Atividades de investigação particular', '80307', 'Atividades de investigação particular', '803', 'Atividades de investigação particular', '80', 'Atividades De Vigilância, Segurança E Investigação', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('8111700', 'Serviços combinados para apoio a edifícios, exceto condomínios prediais', '81117', 'Serviços combinados para apoio a edifícios, exceto condomínios prediais', '811', 'Serviços combinados para apoio a edifícios', '81', 'Serviços Para Edifícios E Atividades Paisagísticas', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('8112500', 'Condomínios prediais', '81125', 'Condomínios prediais', '811', 'Serviços combinados para apoio a edifícios', '81', 'Serviços Para Edifícios E Atividades Paisagísticas', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('8121400', 'Limpeza em prédios e em domicílios', '81214', 'Limpeza em prédios e em domicílios', '812', 'Atividades de limpeza', '81', 'Serviços Para Edifícios E Atividades Paisagísticas', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('8122200', 'Imunização e controle de pragas urbanas', '81222', 'Imunização e controle de pragas urbanas', '812', 'Atividades de limpeza', '81', 'Serviços Para Edifícios E Atividades Paisagísticas', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('8129000', 'Atividades de limpeza não especificadas anteriormente', '81290', 'Atividades de limpeza não especificadas anteriormente', '812', 'Atividades de limpeza', '81', 'Serviços Para Edifícios E Atividades Paisagísticas', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('8130300', 'Atividades paisagísticas', '81303', 'Atividades paisagísticas', '813', 'Atividades paisagísticas', '81', 'Serviços Para Edifícios E Atividades Paisagísticas', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('8211300', 'Serviços combinados de escritório e apoio administrativo', '82113', 'Serviços combinados de escritório e apoio administrativo', '821', 'Serviços de escritório e apoio administrativo', '82', 'Serviços De Escritório, De Apoio Administrativo E Outros Serviços Prestados Principalmente Às
Empresas', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('8219901', 'Fotocópias', '82199', 'Fotocópias, preparação de documentos e outros serviços especializados de apoio administrativo', '821', 'Serviços de escritório e apoio administrativo', '82', 'Serviços De Escritório, De Apoio Administrativo E Outros Serviços Prestados Principalmente Às
Empresas', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('8219999', 'Preparação de documentos e serviços especializados de apoio administrativo não especificados anteriormente', '82199', 'Fotocópias, preparação de documentos e outros serviços especializados de apoio administrativo', '821', 'Serviços de escritório e apoio administrativo', '82', 'Serviços De Escritório, De Apoio Administrativo E Outros Serviços Prestados Principalmente Às
Empresas', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('8220200', 'Atividades de teleatendimento', '82202', 'Atividades de teleatendimento', '822', 'Atividades de teleatendimento', '82', 'Serviços De Escritório, De Apoio Administrativo E Outros Serviços Prestados Principalmente Às
Empresas', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('8230002', 'Casas de festas e eventos', '82300', 'Atividades de organização de eventos, exceto culturais e esportivos', '823', 'Atividades de organização de eventos, exceto culturais e esportivos', '82', 'Serviços De Escritório, De Apoio Administrativo E Outros Serviços Prestados Principalmente Às
Empresas', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('8230001', 'Serviços de organização de feiras, congressos, exposições e festas', '82300', 'Atividades de organização de eventos, exceto culturais e esportivos', '823', 'Atividades de organização de eventos, exceto culturais e esportivos', '82', 'Serviços De Escritório, De Apoio Administrativo E Outros Serviços Prestados Principalmente Às
Empresas', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('8291100', 'Atividades de cobrança e informações cadastrais', '82911', 'Atividades de cobrança e informações cadastrais', '829', 'Outras atividades de serviços prestados principalmente às empresas', '82', 'Serviços De Escritório, De Apoio Administrativo E Outros Serviços Prestados Principalmente Às
Empresas', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('8292000', 'Envasamento e empacotamento sob contrato', '82920', 'Envasamento e empacotamento sob contrato', '829', 'Outras atividades de serviços prestados principalmente às empresas', '82', 'Serviços De Escritório, De Apoio Administrativo E Outros Serviços Prestados Principalmente Às
Empresas', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('8299703', 'Serviços de gravação de carimbos, exceto confecção', '82997', 'Atividades de serviços prestados principalmente às empresas não especificadas anteriormente', '829', 'Outras atividades de serviços prestados principalmente às empresas', '82', 'Serviços De Escritório, De Apoio Administrativo E Outros Serviços Prestados Principalmente Às
Empresas', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('8299706', 'Casas lotéricas', '82997', 'Atividades de serviços prestados principalmente às empresas não especificadas anteriormente', '829', 'Outras atividades de serviços prestados principalmente às empresas', '82', 'Serviços De Escritório, De Apoio Administrativo E Outros Serviços Prestados Principalmente Às
Empresas', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('8299702', 'Emissão de vales-alimentação, vales-transporte e similares', '82997', 'Atividades de serviços prestados principalmente às empresas não especificadas anteriormente', '829', 'Outras atividades de serviços prestados principalmente às empresas', '82', 'Serviços De Escritório, De Apoio Administrativo E Outros Serviços Prestados Principalmente Às
Empresas', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('8299707', 'Salas de acesso à Internet', '82997', 'Atividades de serviços prestados principalmente às empresas não especificadas anteriormente', '829', 'Outras atividades de serviços prestados principalmente às empresas', '82', 'Serviços De Escritório, De Apoio Administrativo E Outros Serviços Prestados Principalmente Às
Empresas', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('8299799', 'Outras atividades de serviços prestados principalmente às empresas não especificadas anteriormente', '82997', 'Atividades de serviços prestados principalmente às empresas não especificadas anteriormente', '829', 'Outras atividades de serviços prestados principalmente às empresas', '82', 'Serviços De Escritório, De Apoio Administrativo E Outros Serviços Prestados Principalmente Às
Empresas', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('8299704', 'Leiloeiros independentes', '82997', 'Atividades de serviços prestados principalmente às empresas não especificadas anteriormente', '829', 'Outras atividades de serviços prestados principalmente às empresas', '82', 'Serviços De Escritório, De Apoio Administrativo E Outros Serviços Prestados Principalmente Às
Empresas', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('8299701', 'Medição de consumo de energia elétrica, gás e água', '82997', 'Atividades de serviços prestados principalmente às empresas não especificadas anteriormente', '829', 'Outras atividades de serviços prestados principalmente às empresas', '82', 'Serviços De Escritório, De Apoio Administrativo E Outros Serviços Prestados Principalmente Às
Empresas', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('8299705', 'Serviços de levantamento de fundos sob contrato', '82997', 'Atividades de serviços prestados principalmente às empresas não especificadas anteriormente', '829', 'Outras atividades de serviços prestados principalmente às empresas', '82', 'Serviços De Escritório, De Apoio Administrativo E Outros Serviços Prestados Principalmente Às
Empresas', 'N', 'Atividades Administrativas E Serviços Complementares', TRUE, TRUE, TRUE, TRUE),
('8411600', 'Administração pública em geral', '84116', 'Administração pública em geral', '841', 'Administração do estado e da política econômica e social', '84', 'Administração Pública, Defesa E Seguridade Social', 'O', 'Administração Pública, Defesa E Seguridade Social', TRUE, TRUE, TRUE, TRUE),
('8412400', 'Regulação das atividades de saúde, educação, serviços culturais e outros serviços sociais', '84124', 'Regulação das atividades de saúde, educação, serviços culturais e outros serviços sociais', '841', 'Administração do estado e da política econômica e social', '84', 'Administração Pública, Defesa E Seguridade Social', 'O', 'Administração Pública, Defesa E Seguridade Social', TRUE, TRUE, TRUE, TRUE),
('8413200', 'Regulação das atividades econômicas', '84132', 'Regulação das atividades econômicas', '841', 'Administração do estado e da política econômica e social', '84', 'Administração Pública, Defesa E Seguridade Social', 'O', 'Administração Pública, Defesa E Seguridade Social', TRUE, TRUE, TRUE, TRUE),
('8421300', 'Relações exteriores', '84213', 'Relações exteriores', '842', 'Serviços coletivos prestados pela administração pública', '84', 'Administração Pública, Defesa E Seguridade Social', 'O', 'Administração Pública, Defesa E Seguridade Social', TRUE, TRUE, TRUE, TRUE),
('8422100', 'Defesa', '84221', 'Defesa', '842', 'Serviços coletivos prestados pela administração pública', '84', 'Administração Pública, Defesa E Seguridade Social', 'O', 'Administração Pública, Defesa E Seguridade Social', TRUE, TRUE, TRUE, TRUE),
('8423000', 'Justiça', '84230', 'Justiça', '842', 'Serviços coletivos prestados pela administração pública', '84', 'Administração Pública, Defesa E Seguridade Social', 'O', 'Administração Pública, Defesa E Seguridade Social', TRUE, TRUE, TRUE, TRUE),
('8424800', 'Segurança e ordem pública', '84248', 'Segurança e ordem pública', '842', 'Serviços coletivos prestados pela administração pública', '84', 'Administração Pública, Defesa E Seguridade Social', 'O', 'Administração Pública, Defesa E Seguridade Social', TRUE, TRUE, TRUE, TRUE),
('8425600', 'Defesa Civil', '84256', 'Defesa Civil', '842', 'Serviços coletivos prestados pela administração pública', '84', 'Administração Pública, Defesa E Seguridade Social', 'O', 'Administração Pública, Defesa E Seguridade Social', TRUE, TRUE, TRUE, TRUE),
('8430200', 'Seguridade social obrigatória', '84302', 'Seguridade social obrigatória', '843', 'Seguridade social obrigatória', '84', 'Administração Pública, Defesa E Seguridade Social', 'O', 'Administração Pública, Defesa E Seguridade Social', TRUE, TRUE, TRUE, TRUE),
('8511200', 'Educação infantil - creche', '85112', 'Educação infantil - creche', '851', 'Educação infantil e ensino fundamental', '85', 'Educação', 'P', 'Educação', TRUE, TRUE, TRUE, TRUE),
('8512100', 'Educação infantil - pré-escola', '85121', 'Educação infantil - pré-escola', '851', 'Educação infantil e ensino fundamental', '85', 'Educação', 'P', 'Educação', TRUE, TRUE, TRUE, TRUE),
('8513900', 'Ensino fundamental', '85139', 'Ensino fundamental', '851', 'Educação infantil e ensino fundamental', '85', 'Educação', 'P', 'Educação', TRUE, TRUE, TRUE, TRUE),
('8520100', 'Ensino médio', '85201', 'Ensino médio', '852', 'Ensino médio', '85', 'Educação', 'P', 'Educação', TRUE, TRUE, TRUE, TRUE),
('8531700', 'Educação superior - graduação', '85317', 'Educação superior - graduação', '853', 'Educação superior', '85', 'Educação', 'P', 'Educação', TRUE, TRUE, TRUE, TRUE),
('8532500', 'Educação superior - graduação e pós-graduação', '85325', 'Educação superior - graduação e pós-graduação', '853', 'Educação superior', '85', 'Educação', 'P', 'Educação', TRUE, TRUE, TRUE, TRUE),
('8533300', 'Educação superior - pós-graduação e extensão', '85333', 'Educação superior - pós-graduação e extensão', '853', 'Educação superior', '85', 'Educação', 'P', 'Educação', TRUE, TRUE, TRUE, TRUE),
('8541400', 'Educação profissional de nível técnico', '85414', 'Educação profissional de nível técnico', '854', 'Educação profissional de nível técnico e tecnológico', '85', 'Educação', 'P', 'Educação', TRUE, TRUE, TRUE, TRUE),
('8542200', 'Educação profissional de nível tecnológico', '85422', 'Educação profissional de nível tecnológico', '854', 'Educação profissional de nível técnico e tecnológico', '85', 'Educação', 'P', 'Educação', TRUE, TRUE, TRUE, TRUE),
('8550301', 'Administração de caixas escolares', '85503', 'Atividades de apoio à educação', '855', 'Atividades de apoio à educação', '85', 'Educação', 'P', 'Educação', TRUE, TRUE, TRUE, TRUE),
('8550302', 'Atividades de apoio à educação, exceto caixas escolares', '85503', 'Atividades de apoio à educação', '855', 'Atividades de apoio à educação', '85', 'Educação', 'P', 'Educação', TRUE, TRUE, TRUE, TRUE),
('8591100', 'Ensino de esportes', '85911', 'Ensino de esportes', '859', 'Outras atividades de ensino', '85', 'Educação', 'P', 'Educação', TRUE, TRUE, TRUE, TRUE),
('8592903', 'Ensino de música', '85929', 'Ensino de arte e cultura', '859', 'Outras atividades de ensino', '85', 'Educação', 'P', 'Educação', TRUE, TRUE, TRUE, TRUE),
('8592901', 'Ensino de dança', '85929', 'Ensino de arte e cultura', '859', 'Outras atividades de ensino', '85', 'Educação', 'P', 'Educação', TRUE, TRUE, TRUE, TRUE),
('8592902', 'Ensino de artes cênicas, exceto dança', '85929', 'Ensino de arte e cultura', '859', 'Outras atividades de ensino', '85', 'Educação', 'P', 'Educação', TRUE, TRUE, TRUE, TRUE),
('8592999', 'Ensino de arte e cultura não especificado anteriormente', '85929', 'Ensino de arte e cultura', '859', 'Outras atividades de ensino', '85', 'Educação', 'P', 'Educação', TRUE, TRUE, TRUE, TRUE),
('8593700', 'Ensino de idiomas', '85937', 'Ensino de idiomas', '859', 'Outras atividades de ensino', '85', 'Educação', 'P', 'Educação', TRUE, TRUE, TRUE, TRUE),
('8599601', 'Formação de condutores', '85996', 'Atividades de ensino não especificadas anteriormente', '859', 'Outras atividades de ensino', '85', 'Educação', 'P', 'Educação', TRUE, TRUE, TRUE, TRUE),
('8599699', 'Outras atividades de ensino não especificadas anteriormente', '85996', 'Atividades de ensino não especificadas anteriormente', '859', 'Outras atividades de ensino', '85', 'Educação', 'P', 'Educação', TRUE, TRUE, TRUE, TRUE),
('8599603', 'Treinamento em informática', '85996', 'Atividades de ensino não especificadas anteriormente', '859', 'Outras atividades de ensino', '85', 'Educação', 'P', 'Educação', TRUE, TRUE, TRUE, TRUE),
('8599604', 'Treinamento em desenvolvimento profissional e gerencial', '85996', 'Atividades de ensino não especificadas anteriormente', '859', 'Outras atividades de ensino', '85', 'Educação', 'P', 'Educação', TRUE, TRUE, TRUE, TRUE),
('8599602', 'Cursos de pilotagem', '85996', 'Atividades de ensino não especificadas anteriormente', '859', 'Outras atividades de ensino', '85', 'Educação', 'P', 'Educação', TRUE, TRUE, TRUE, TRUE),
('8599605', 'Cursos preparatórios para concursos', '85996', 'Atividades de ensino não especificadas anteriormente', '859', 'Outras atividades de ensino', '85', 'Educação', 'P', 'Educação', TRUE, TRUE, TRUE, TRUE),
('8610102', 'Atividades de atendimento em pronto-socorro e unidades hospitalares para atendimento a urgências', '86101', 'Atividades de atendimento hospitalar', '861', 'Atividades de atendimento hospitalar', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8610101', 'Atividades de atendimento hospitalar, exceto pronto-socorro e unidades para atendimento a urgências', '86101', 'Atividades de atendimento hospitalar', '861', 'Atividades de atendimento hospitalar', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8621602', 'Serviços móveis de atendimento a urgências, exceto por UTI móvel', '86216', 'Serviços móveis de atendimento a urgências', '862', 'Serviços móveis de atendimento a urgências e de remoção de pacientes', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8621601', 'UTI móvel', '86216', 'Serviços móveis de atendimento a urgências', '862', 'Serviços móveis de atendimento a urgências e de remoção de pacientes', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8622400', 'Serviços de remoção de pacientes, exceto os serviços móveis de atendimento a urgências', '86224', 'Serviços de remoção de pacientes, exceto os serviços móveis de atendimento a urgências', '862', 'Serviços móveis de atendimento a urgências e de remoção de pacientes', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8630503', 'Atividade médica ambulatorial restrita a consultas', '86305', 'Atividades de atenção ambulatorial executadas por médicos e odontólogos', '863', 'Atividades de atenção ambulatorial executadas por médicos e odontólogos', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8630506', 'Serviços de vacinação e imunização humana', '86305', 'Atividades de atenção ambulatorial executadas por médicos e odontólogos', '863', 'Atividades de atenção ambulatorial executadas por médicos e odontólogos', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8630507', 'Atividades de reprodução humana assistida', '86305', 'Atividades de atenção ambulatorial executadas por médicos e odontólogos', '863', 'Atividades de atenção ambulatorial executadas por médicos e odontólogos', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8630502', 'Atividade médica ambulatorial com recursos para realização de exames complementares', '86305', 'Atividades de atenção ambulatorial executadas por médicos e odontólogos', '863', 'Atividades de atenção ambulatorial executadas por médicos e odontólogos', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8630501', 'Atividade médica ambulatorial com recursos para realização de procedimentos cirúrgicos', '86305', 'Atividades de atenção ambulatorial executadas por médicos e odontólogos', '863', 'Atividades de atenção ambulatorial executadas por médicos e odontólogos', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8630599', 'Atividades de atenção ambulatorial não especificadas anteriormente', '86305', 'Atividades de atenção ambulatorial executadas por médicos e odontólogos', '863', 'Atividades de atenção ambulatorial executadas por médicos e odontólogos', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8630504', 'Atividade odontológica', '86305', 'Atividades de atenção ambulatorial executadas por médicos e odontólogos', '863', 'Atividades de atenção ambulatorial executadas por médicos e odontólogos', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8640207', 'Serviços de diagnóstico por imagem sem uso de radiação ionizante, exceto ressonância magnética', '86402', 'Atividades de serviços de complementação diagnóstica e terapêutica', '864', 'Atividades de serviços de complementação diagnóstica e terapêutica', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8640205', 'Serviços de diagnóstico por imagem com uso de radiação ionizante, exceto tomografia', '86402', 'Atividades de serviços de complementação diagnóstica e terapêutica', '864', 'Atividades de serviços de complementação diagnóstica e terapêutica', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8640203', 'Serviços de diálise e nefrologia', '86402', 'Atividades de serviços de complementação diagnóstica e terapêutica', '864', 'Atividades de serviços de complementação diagnóstica e terapêutica', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8640210', 'Serviços de quimioterapia', '86402', 'Atividades de serviços de complementação diagnóstica e terapêutica', '864', 'Atividades de serviços de complementação diagnóstica e terapêutica', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8640213', 'Serviços de litotripsia', '86402', 'Atividades de serviços de complementação diagnóstica e terapêutica', '864', 'Atividades de serviços de complementação diagnóstica e terapêutica', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8640204', 'Serviços de tomografia', '86402', 'Atividades de serviços de complementação diagnóstica e terapêutica', '864', 'Atividades de serviços de complementação diagnóstica e terapêutica', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8640208', 'Serviços de diagnóstico por registro gráfico - ECG, EEG e outros exames análogos', '86402', 'Atividades de serviços de complementação diagnóstica e terapêutica', '864', 'Atividades de serviços de complementação diagnóstica e terapêutica', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8640212', 'Serviços de hemoterapia', '86402', 'Atividades de serviços de complementação diagnóstica e terapêutica', '864', 'Atividades de serviços de complementação diagnóstica e terapêutica', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8640209', 'Serviços de diagnóstico por métodos ópticos - endoscopia e outros exames análogos', '86402', 'Atividades de serviços de complementação diagnóstica e terapêutica', '864', 'Atividades de serviços de complementação diagnóstica e terapêutica', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8640211', 'Serviços de radioterapia', '86402', 'Atividades de serviços de complementação diagnóstica e terapêutica', '864', 'Atividades de serviços de complementação diagnóstica e terapêutica', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8640202', 'Laboratórios clínicos', '86402', 'Atividades de serviços de complementação diagnóstica e terapêutica', '864', 'Atividades de serviços de complementação diagnóstica e terapêutica', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8640201', 'Laboratórios de anatomia patológica e citológica', '86402', 'Atividades de serviços de complementação diagnóstica e terapêutica', '864', 'Atividades de serviços de complementação diagnóstica e terapêutica', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8640206', 'Serviços de ressonância magnética', '86402', 'Atividades de serviços de complementação diagnóstica e terapêutica', '864', 'Atividades de serviços de complementação diagnóstica e terapêutica', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8640299', 'Atividades de serviços de complementação diagnóstica e terapêutica não especificadas anteriormente', '86402', 'Atividades de serviços de complementação diagnóstica e terapêutica', '864', 'Atividades de serviços de complementação diagnóstica e terapêutica', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8640214', 'Serviços de bancos de células e tecidos humanos', '86402', 'Atividades de serviços de complementação diagnóstica e terapêutica', '864', 'Atividades de serviços de complementação diagnóstica e terapêutica', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8650001', 'Atividades de enfermagem', '86500', 'Atividades de profissionais da área de saúde, exceto médicos e odontólogos', '865', 'Atividades de profissionais da área de saúde, exceto médicos e odontólogos', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8650004', 'Atividades de fisioterapia', '86500', 'Atividades de profissionais da área de saúde, exceto médicos e odontólogos', '865', 'Atividades de profissionais da área de saúde, exceto médicos e odontólogos', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8650099', 'Atividades de profissionais da área de saúde não especificadas anteriormente', '86500', 'Atividades de profissionais da área de saúde, exceto médicos e odontólogos', '865', 'Atividades de profissionais da área de saúde, exceto médicos e odontólogos', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8650007', 'Atividades de terapia de nutrição enteral e parenteral', '86500', 'Atividades de profissionais da área de saúde, exceto médicos e odontólogos', '865', 'Atividades de profissionais da área de saúde, exceto médicos e odontólogos', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8650003', 'Atividades de psicologia e psicanálise', '86500', 'Atividades de profissionais da área de saúde, exceto médicos e odontólogos', '865', 'Atividades de profissionais da área de saúde, exceto médicos e odontólogos', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8650006', 'Atividades de fonoaudiologia', '86500', 'Atividades de profissionais da área de saúde, exceto médicos e odontólogos', '865', 'Atividades de profissionais da área de saúde, exceto médicos e odontólogos', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8650005', 'Atividades de terapia ocupacional', '86500', 'Atividades de profissionais da área de saúde, exceto médicos e odontólogos', '865', 'Atividades de profissionais da área de saúde, exceto médicos e odontólogos', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8650002', 'Atividades de profissionais da nutrição', '86500', 'Atividades de profissionais da área de saúde, exceto médicos e odontólogos', '865', 'Atividades de profissionais da área de saúde, exceto médicos e odontólogos', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8660700', 'Atividades de apoio à gestão de saúde', '86607', 'Atividades de apoio à gestão de saúde', '866', 'Atividades de apoio à gestão de saúde', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8690904', 'Atividades de podologia', '86909', 'Atividades de atenção à saúde humana não especificadas anteriormente', '869', 'Atividades de atenção à saúde humana não especificadas anteriormente', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', FALSE, TRUE, TRUE, TRUE),
('8690902', 'Atividades de bancos de leite humano', '86909', 'Atividades de atenção à saúde humana não especificadas anteriormente', '869', 'Atividades de atenção à saúde humana não especificadas anteriormente', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8690903', 'Atividades de acupuntura', '86909', 'Atividades de atenção à saúde humana não especificadas anteriormente', '869', 'Atividades de atenção à saúde humana não especificadas anteriormente', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', FALSE, TRUE, TRUE, TRUE),
('8690999', 'Outras atividades de atenção à saúde humana não especificadas anteriormente', '86909', 'Atividades de atenção à saúde humana não especificadas anteriormente', '869', 'Atividades de atenção à saúde humana não especificadas anteriormente', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8690901', 'Atividades de práticas integrativas e complementares em saúde humana', '86909', 'Atividades de atenção à saúde humana não especificadas anteriormente', '869', 'Atividades de atenção à saúde humana não especificadas anteriormente', '86', 'Atividades De Atenção À Saúde Humana', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8711504', 'Centros de apoio a pacientes com câncer e com AIDS', '87115', 'Atividades de assistência a idosos, deficientes físicos, imunodeprimidos e convalescentes prestadas em residências coletivas e particulares', '871', 'Atividades de assistência a idosos, deficientes físicos, imunodeprimidos e convalescentes, e de infraestrutura e apoio a pacientes prestadas em residências coletivas e particulares', '87', 'Atividades De Atenção À Saúde Humana Integradas Com Assistência Social, Prestadas Em Residências Coletivas E Particulares', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8711502', 'Instituições de longa permanência para idosos', '87115', 'Atividades de assistência a idosos, deficientes físicos, imunodeprimidos e convalescentes prestadas em residências coletivas e particulares', '871', 'Atividades de assistência a idosos, deficientes físicos, imunodeprimidos e convalescentes, e de infraestrutura e apoio a pacientes prestadas em residências coletivas e particulares', '87', 'Atividades De Atenção À Saúde Humana Integradas Com Assistência Social, Prestadas Em Residências Coletivas E Particulares', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8711501', 'Clínicas e residências geriátricas', '87115', 'Atividades de assistência a idosos, deficientes físicos, imunodeprimidos e convalescentes prestadas em residências coletivas e particulares', '871', 'Atividades de assistência a idosos, deficientes físicos, imunodeprimidos e convalescentes, e de infraestrutura e apoio a pacientes prestadas em residências coletivas e particulares', '87', 'Atividades De Atenção À Saúde Humana Integradas Com Assistência Social, Prestadas Em Residências Coletivas E Particulares', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8711503', 'Atividades de assistência a deficientes físicos, imunodeprimidos e convalescentes', '87115', 'Atividades de assistência a idosos, deficientes físicos, imunodeprimidos e convalescentes prestadas em residências coletivas e particulares', '871', 'Atividades de assistência a idosos, deficientes físicos, imunodeprimidos e convalescentes, e de infraestrutura e apoio a pacientes prestadas em residências coletivas e particulares', '87', 'Atividades De Atenção À Saúde Humana Integradas Com Assistência Social, Prestadas Em Residências Coletivas E Particulares', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8711505', 'Condomínios residenciais para idosos', '87115', 'Atividades de assistência a idosos, deficientes físicos, imunodeprimidos e convalescentes prestadas em residências coletivas e particulares', '871', 'Atividades de assistência a idosos, deficientes físicos, imunodeprimidos e convalescentes, e de infraestrutura e apoio a pacientes prestadas em residências coletivas e particulares', '87', 'Atividades De Atenção À Saúde Humana Integradas Com Assistência Social, Prestadas Em Residências Coletivas E Particulares', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8712300', 'Atividades de fornecimento de infraestrutura de apoio e assistência a paciente no domicílio', '87123', 'Atividades de fornecimento de infraestrutura de apoio e assistência a paciente no domicílio', '871', 'Atividades de assistência a idosos, deficientes físicos, imunodeprimidos e convalescentes, e de infraestrutura e apoio a pacientes prestadas em residências coletivas e particulares', '87', 'Atividades De Atenção À Saúde Humana Integradas Com Assistência Social, Prestadas Em Residências Coletivas E Particulares', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8720401', 'Atividades de centros de assistência psicossocial', '87204', 'Atividades de assistência psicossocial e à saúde a portadores de distúrbios psíquicos, deficiência mental e dependência
química', '872', 'Atividades de assistência psicossocial e à saúde a portadores de distúrbios psíquicos, deficiência mental e dependência
química', '87', 'Atividades De Atenção À Saúde Humana Integradas Com Assistência Social, Prestadas Em Residências Coletivas E Particulares', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8720499', 'Atividades de assistência psicossocial e à saúde a portadores de distúrbios psíquicos, deficiência mental e dependência química e grupos similares não especificadas anteriormente', '87204', 'Atividades de assistência psicossocial e à saúde a portadores de distúrbios psíquicos, deficiência mental e dependência
química', '872', 'Atividades de assistência psicossocial e à saúde a portadores de distúrbios psíquicos, deficiência mental e dependência
química', '87', 'Atividades De Atenção À Saúde Humana Integradas Com Assistência Social, Prestadas Em Residências Coletivas E Particulares', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8730199', 'Atividades de assistência social prestadas em residências coletivas e particulares não especificadas anteriormente', '87301', 'Atividades de assistência social prestadas em residências coletivas e particulares', '873', 'Atividades de assistência social prestadas em residências coletivas e particulares', '87', 'Atividades De Atenção À Saúde Humana Integradas Com Assistência Social, Prestadas Em Residências Coletivas E Particulares', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8730101', 'Orfanatos', '87301', 'Atividades de assistência social prestadas em residências coletivas e particulares', '873', 'Atividades de assistência social prestadas em residências coletivas e particulares', '87', 'Atividades De Atenção À Saúde Humana Integradas Com Assistência Social, Prestadas Em Residências Coletivas E Particulares', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8730102', 'Albergues assistenciais', '87301', 'Atividades de assistência social prestadas em residências coletivas e particulares', '873', 'Atividades de assistência social prestadas em residências coletivas e particulares', '87', 'Atividades De Atenção À Saúde Humana Integradas Com Assistência Social, Prestadas Em Residências Coletivas E Particulares', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('8800600', 'Serviços de assistência social sem alojamento', '88006', 'Serviços de assistência social sem alojamento', '880', 'Serviços de assistência social sem alojamento', '88', 'Serviços De Assistência Social Sem Alojamento', 'Q', 'Saúde Humana E Serviços Sociais', TRUE, TRUE, TRUE, TRUE),
('9001901', 'Produção teatral', '90019', 'Artes cênicas, espetáculos e atividades complementares', '900', 'Atividades artísticas, criativas e de espetáculos', '90', 'Atividades Artísticas, Criativas E De Espetáculos', 'R', 'Artes, Cultura, Esporte E Recreação', TRUE, TRUE, TRUE, TRUE),
('9001999', 'Artes cênicas, espetáculos e atividades complementares não especificados anteriormente', '90019', 'Artes cênicas, espetáculos e atividades complementares', '900', 'Atividades artísticas, criativas e de espetáculos', '90', 'Atividades Artísticas, Criativas E De Espetáculos', 'R', 'Artes, Cultura, Esporte E Recreação', TRUE, TRUE, TRUE, TRUE),
('9001902', 'Produção musical', '90019', 'Artes cênicas, espetáculos e atividades complementares', '900', 'Atividades artísticas, criativas e de espetáculos', '90', 'Atividades Artísticas, Criativas E De Espetáculos', 'R', 'Artes, Cultura, Esporte E Recreação', TRUE, TRUE, TRUE, TRUE),
('9001903', 'Produção de espetáculos de dança', '90019', 'Artes cênicas, espetáculos e atividades complementares', '900', 'Atividades artísticas, criativas e de espetáculos', '90', 'Atividades Artísticas, Criativas E De Espetáculos', 'R', 'Artes, Cultura, Esporte E Recreação', TRUE, TRUE, TRUE, TRUE),
('9001905', 'Produção de espetáculos de rodeios, vaquejadas e similares', '90019', 'Artes cênicas, espetáculos e atividades complementares', '900', 'Atividades artísticas, criativas e de espetáculos', '90', 'Atividades Artísticas, Criativas E De Espetáculos', 'R', 'Artes, Cultura, Esporte E Recreação', TRUE, TRUE, TRUE, TRUE),
('9001906', 'Atividades de sonorização e de iluminação', '90019', 'Artes cênicas, espetáculos e atividades complementares', '900', 'Atividades artísticas, criativas e de espetáculos', '90', 'Atividades Artísticas, Criativas E De Espetáculos', 'R', 'Artes, Cultura, Esporte E Recreação', TRUE, TRUE, TRUE, TRUE),
('9001904', 'Produção de espetáculos circenses, de marionetes e similares', '90019', 'Artes cênicas, espetáculos e atividades complementares', '900', 'Atividades artísticas, criativas e de espetáculos', '90', 'Atividades Artísticas, Criativas E De Espetáculos', 'R', 'Artes, Cultura, Esporte E Recreação', TRUE, TRUE, TRUE, TRUE),
('9002702', 'Restauração de obras de arte', '90027', 'Criação artística', '900', 'Atividades artísticas, criativas e de espetáculos', '90', 'Atividades Artísticas, Criativas E De Espetáculos', 'R', 'Artes, Cultura, Esporte E Recreação', TRUE, TRUE, TRUE, TRUE),
('9002701', 'Atividades de artistas plásticos, jornalistas independentes e
escritores', '90027', 'Criação artística', '900', 'Atividades artísticas, criativas e de espetáculos', '90', 'Atividades Artísticas, Criativas E De Espetáculos', 'R', 'Artes, Cultura, Esporte E Recreação', TRUE, TRUE, TRUE, TRUE),
('9003500', 'Gestão de espaços para artes cênicas, espetáculos e outras atividades artísticas', '90035', 'Gestão de espaços para artes cênicas, espetáculos e outras atividades artísticas', '900', 'Atividades artísticas, criativas e de espetáculos', '90', 'Atividades Artísticas, Criativas E De Espetáculos', 'R', 'Artes, Cultura, Esporte E Recreação', TRUE, TRUE, TRUE, TRUE),
('9101500', 'Atividades de bibliotecas e arquivos', '91015', 'Atividades de bibliotecas e arquivos', '910', 'Atividades ligadas ao patrimônio cultural e ambiental', '91', 'Atividades Ligadas Ao Patrimônio Cultural E Ambiental', 'R', 'Artes, Cultura, Esporte E Recreação', TRUE, TRUE, TRUE, TRUE),
('9102301', 'Atividades de museus e de exploração de lugares e prédios históricos e atrações similares', '91023', 'Atividades de museus e de exploração, restauração artística
e conservação de lugares e prédios históricos e atrações
similares', '910', 'Atividades ligadas ao patrimônio cultural e ambiental', '91', 'Atividades Ligadas Ao Patrimônio Cultural E Ambiental', 'R', 'Artes, Cultura, Esporte E Recreação', TRUE, TRUE, TRUE, TRUE),
('9102302', 'Restauração e conservação de lugares e prédios históricos', '91023', 'Atividades de museus e de exploração, restauração artística
e conservação de lugares e prédios históricos e atrações
similares', '910', 'Atividades ligadas ao patrimônio cultural e ambiental', '91', 'Atividades Ligadas Ao Patrimônio Cultural E Ambiental', 'R', 'Artes, Cultura, Esporte E Recreação', TRUE, TRUE, TRUE, TRUE),
('9103100', 'Atividades de jardins botânicos, zoológicos, parques nacionais, reservas ecológicas e áreas de proteção ambiental', '91031', 'Atividades de jardins botânicos, zoológicos, parques nacionais, reservas ecológicas e áreas de proteção ambiental', '910', 'Atividades ligadas ao patrimônio cultural e ambiental', '91', 'Atividades Ligadas Ao Patrimônio Cultural E Ambiental', 'R', 'Artes, Cultura, Esporte E Recreação', TRUE, TRUE, TRUE, TRUE),
('9200302', 'Exploração de apostas em corridas de cavalos', '92003', 'Atividades de exploração de jogos de azar e apostas', '920', 'Atividades de exploração de jogos de azar e apostas', '92', 'Atividades De Exploração De Jogos De Azar E
Apostas', 'R', 'Artes, Cultura, Esporte E Recreação', TRUE, TRUE, TRUE, TRUE),
('9200399', 'Exploração de jogos de azar e apostas não especificados anteriormente', '92003', 'Atividades de exploração de jogos de azar e apostas', '920', 'Atividades de exploração de jogos de azar e apostas', '92', 'Atividades De Exploração De Jogos De Azar E
Apostas', 'R', 'Artes, Cultura, Esporte E Recreação', TRUE, TRUE, TRUE, TRUE),
('9200301', 'Casas de bingo', '92003', 'Atividades de exploração de jogos de azar e apostas', '920', 'Atividades de exploração de jogos de azar e apostas', '92', 'Atividades De Exploração De Jogos De Azar E
Apostas', 'R', 'Artes, Cultura, Esporte E Recreação', TRUE, TRUE, TRUE, TRUE),
('9311500', 'Gestão de instalações de esportes', '93115', 'Gestão de instalações de esportes', '931', 'Atividades esportivas', '93', 'Atividades Esportivas E De Recreação E Lazer', 'R', 'Artes, Cultura, Esporte E Recreação', TRUE, TRUE, TRUE, TRUE),
('9312300', 'Clubes sociais, esportivos e similares', '93123', 'Clubes sociais, esportivos e similares', '931', 'Atividades esportivas', '93', 'Atividades Esportivas E De Recreação E Lazer', 'R', 'Artes, Cultura, Esporte E Recreação', TRUE, TRUE, TRUE, TRUE),
('9313100', 'Atividades de condicionamento físico', '93131', 'Atividades de condicionamento físico', '931', 'Atividades esportivas', '93', 'Atividades Esportivas E De Recreação E Lazer', 'R', 'Artes, Cultura, Esporte E Recreação', TRUE, TRUE, TRUE, TRUE),
('9319199', 'Outras atividades esportivas não especificadas anteriormente', '93191', 'Atividades esportivas não especificadas anteriormente', '931', 'Atividades esportivas', '93', 'Atividades Esportivas E De Recreação E Lazer', 'R', 'Artes, Cultura, Esporte E Recreação', TRUE, TRUE, TRUE, TRUE),
('9319101', 'Produção e promoção de eventos esportivos', '93191', 'Atividades esportivas não especificadas anteriormente', '931', 'Atividades esportivas', '93', 'Atividades Esportivas E De Recreação E Lazer', 'R', 'Artes, Cultura, Esporte E Recreação', TRUE, TRUE, TRUE, TRUE),
('9321200', 'Parques de diversão e parques temáticos', '93212', 'Parques de diversão e parques temáticos', '932', 'Atividades de recreação e lazer', '93', 'Atividades Esportivas E De Recreação E Lazer', 'R', 'Artes, Cultura, Esporte E Recreação', TRUE, TRUE, TRUE, TRUE),
('9329803', 'Exploração de jogos de sinuca, bilhar e similares', '93298', 'Atividades de recreação e lazer não especificadas
anteriormente', '932', 'Atividades de recreação e lazer', '93', 'Atividades Esportivas E De Recreação E Lazer', 'R', 'Artes, Cultura, Esporte E Recreação', TRUE, TRUE, TRUE, TRUE),
('9329801', 'Discotecas, danceterias, salões de dança e similares', '93298', 'Atividades de recreação e lazer não especificadas
anteriormente', '932', 'Atividades de recreação e lazer', '93', 'Atividades Esportivas E De Recreação E Lazer', 'R', 'Artes, Cultura, Esporte E Recreação', TRUE, TRUE, TRUE, TRUE),
('9329802', 'Exploração de boliches', '93298', 'Atividades de recreação e lazer não especificadas
anteriormente', '932', 'Atividades de recreação e lazer', '93', 'Atividades Esportivas E De Recreação E Lazer', 'R', 'Artes, Cultura, Esporte E Recreação', TRUE, TRUE, TRUE, TRUE),
('9329804', 'Exploração de jogos eletrônicos recreativos', '93298', 'Atividades de recreação e lazer não especificadas
anteriormente', '932', 'Atividades de recreação e lazer', '93', 'Atividades Esportivas E De Recreação E Lazer', 'R', 'Artes, Cultura, Esporte E Recreação', TRUE, TRUE, TRUE, TRUE),
('9329899', 'Outras atividades de recreação e lazer não especificadas anteriormente', '93298', 'Atividades de recreação e lazer não especificadas
anteriormente', '932', 'Atividades de recreação e lazer', '93', 'Atividades Esportivas E De Recreação E Lazer', 'R', 'Artes, Cultura, Esporte E Recreação', TRUE, TRUE, TRUE, TRUE),
('9411100', 'Atividades de organizações associativas patronais e empresariais', '94111', 'Atividades de organizações associativas patronais e empresariais', '941', 'Atividades de organizações associativas patronais, empresariais e profissionais', '94', 'Atividades De Organizações Associativas', 'S', 'Outras Atividades De Serviços', TRUE, TRUE, TRUE, TRUE),
('9412001', 'Atividades de fiscalização profissional', '94120', 'Atividades de organizações associativas profissionais', '941', 'Atividades de organizações associativas patronais, empresariais e profissionais', '94', 'Atividades De Organizações Associativas', 'S', 'Outras Atividades De Serviços', FALSE, FALSE, TRUE, TRUE),
('9412000', 'Atividades de organizações associativas profissionais', '94120', 'Atividades de organizações associativas profissionais', '941', 'Atividades de organizações associativas patronais, empresariais e profissionais', '94', 'Atividades De Organizações Associativas', 'S', 'Outras Atividades De Serviços', TRUE, TRUE, FALSE, FALSE),
('9412099', 'Outras atividades associativas profissionais', '94120', 'Atividades de organizações associativas profissionais', '941', 'Atividades de organizações associativas patronais, empresariais e profissionais', '94', 'Atividades De Organizações Associativas', 'S', 'Outras Atividades De Serviços', FALSE, FALSE, TRUE, TRUE),
('9420100', 'Atividades de organizações sindicais', '94201', 'Atividades de organizações sindicais', '942', 'Atividades de organizações sindicais', '94', 'Atividades De Organizações Associativas', 'S', 'Outras Atividades De Serviços', TRUE, TRUE, TRUE, TRUE),
('9430800', 'Atividades de associações de defesa de direitos sociais', '94308', 'Atividades de associações de defesa de direitos sociais', '943', 'Atividades de associações de defesa de direitos sociais', '94', 'Atividades De Organizações Associativas', 'S', 'Outras Atividades De Serviços', TRUE, TRUE, TRUE, TRUE),
('9491000', 'Atividades de organizações religiosas ou filosóficas', '94910', 'Atividades de organizações religiosas', '949', 'Atividades de organizações associativas não especificadas anteriormente', '94', 'Atividades De Organizações Associativas', 'S', 'Outras Atividades De Serviços', TRUE, TRUE, TRUE, TRUE),
('9492800', 'Atividades de organizações políticas', '94928', 'Atividades de organizações políticas', '949', 'Atividades de organizações associativas não especificadas anteriormente', '94', 'Atividades De Organizações Associativas', 'S', 'Outras Atividades De Serviços', TRUE, TRUE, TRUE, TRUE),
('9493600', 'Atividades de organizações associativas ligadas à cultura e à arte', '94936', 'Atividades de organizações associativas ligadas à cultura
e à arte', '949', 'Atividades de organizações associativas não especificadas anteriormente', '94', 'Atividades De Organizações Associativas', 'S', 'Outras Atividades De Serviços', TRUE, TRUE, TRUE, TRUE),
('9499500', 'Atividades associativas não especificadas anteriormente', '94995', 'Atividades associativas não especificadas anteriormente', '949', 'Atividades de organizações associativas não especificadas anteriormente', '94', 'Atividades De Organizações Associativas', 'S', 'Outras Atividades De Serviços', TRUE, TRUE, TRUE, TRUE),
('9511800', 'Reparação e manutenção de computadores e de equipamentos periféricos', '95118', 'Reparação e manutenção de computadores e de equipamentos periféricos', '951', 'Reparação e manutenção de equipamentos de informática e comunicação', '95', 'Reparação E Manutenção De Equipamentos De Informática E Comunicação E De Objetos Pessoais E Domésticos', 'S', 'Outras Atividades De Serviços', TRUE, TRUE, TRUE, TRUE),
('9512600', 'Reparação e manutenção de equipamentos de comunicação', '95126', 'Reparação e manutenção de equipamentos de comunicação', '951', 'Reparação e manutenção de equipamentos de informática e comunicação', '95', 'Reparação E Manutenção De Equipamentos De Informática E Comunicação E De Objetos Pessoais E Domésticos', 'S', 'Outras Atividades De Serviços', TRUE, TRUE, TRUE, TRUE),
('9521500', 'Reparação e manutenção de equipamentos eletroeletrônicos de uso pessoal e doméstico', '95215', 'Reparação e manutenção de equipamentos eletroeletrônicos de uso pessoal e doméstico', '952', 'Reparação e manutenção de objetos e equipamentos pessoais e domésticos', '95', 'Reparação E Manutenção De Equipamentos De Informática E Comunicação E De Objetos Pessoais E Domésticos', 'S', 'Outras Atividades De Serviços', TRUE, TRUE, TRUE, TRUE),
('9529102', 'Chaveiros', '95291', 'Reparação e manutenção de objetos e equipamentos pessoais e domésticos não especificados anteriormente', '952', 'Reparação e manutenção de objetos e equipamentos pessoais e domésticos', '95', 'Reparação E Manutenção De Equipamentos De Informática E Comunicação E De Objetos Pessoais E Domésticos', 'S', 'Outras Atividades De Serviços', TRUE, TRUE, TRUE, TRUE),
('9529101', 'Reparação de calçados, bolsas e artigos de viagem', '95291', 'Reparação e manutenção de objetos e equipamentos pessoais e domésticos não especificados anteriormente', '952', 'Reparação e manutenção de objetos e equipamentos pessoais e domésticos', '95', 'Reparação E Manutenção De Equipamentos De Informática E Comunicação E De Objetos Pessoais E Domésticos', 'S', 'Outras Atividades De Serviços', TRUE, TRUE, TRUE, TRUE),
('9529199', 'Reparação e manutenção de outros objetos e equipamentos pessoais e domésticos não especificados anteriormente', '95291', 'Reparação e manutenção de objetos e equipamentos pessoais e domésticos não especificados anteriormente', '952', 'Reparação e manutenção de objetos e equipamentos pessoais e domésticos', '95', 'Reparação E Manutenção De Equipamentos De Informática E Comunicação E De Objetos Pessoais E Domésticos', 'S', 'Outras Atividades De Serviços', TRUE, TRUE, TRUE, TRUE),
('9529105', 'Reparação de artigos do mobiliário', '95291', 'Reparação e manutenção de objetos e equipamentos pessoais e domésticos não especificados anteriormente', '952', 'Reparação e manutenção de objetos e equipamentos pessoais e domésticos', '95', 'Reparação E Manutenção De Equipamentos De Informática E Comunicação E De Objetos Pessoais E Domésticos', 'S', 'Outras Atividades De Serviços', TRUE, TRUE, TRUE, TRUE),
('9529103', 'Reparação de relógios', '95291', 'Reparação e manutenção de objetos e equipamentos pessoais e domésticos não especificados anteriormente', '952', 'Reparação e manutenção de objetos e equipamentos pessoais e domésticos', '95', 'Reparação E Manutenção De Equipamentos De Informática E Comunicação E De Objetos Pessoais E Domésticos', 'S', 'Outras Atividades De Serviços', TRUE, TRUE, TRUE, TRUE),
('9529104', 'Reparação de bicicletas, triciclos e outros veículos não motorizados', '95291', 'Reparação e manutenção de objetos e equipamentos pessoais e domésticos não especificados anteriormente', '952', 'Reparação e manutenção de objetos e equipamentos pessoais e domésticos', '95', 'Reparação E Manutenção De Equipamentos De Informática E Comunicação E De Objetos Pessoais E Domésticos', 'S', 'Outras Atividades De Serviços', TRUE, TRUE, TRUE, TRUE),
('9529106', 'Reparação de jóias', '95291', 'Reparação e manutenção de objetos e equipamentos pessoais e domésticos não especificados anteriormente', '952', 'Reparação e manutenção de objetos e equipamentos pessoais e domésticos', '95', 'Reparação E Manutenção De Equipamentos De Informática E Comunicação E De Objetos Pessoais E Domésticos', 'S', 'Outras Atividades De Serviços', TRUE, TRUE, TRUE, TRUE),
('9601703', 'Toalheiros', '96017', 'Lavanderias, tinturarias e toalheiros', '960', 'Outras atividades de serviços pessoais', '96', 'Outras Atividades De Serviços Pessoais', 'S', 'Outras Atividades De Serviços', TRUE, TRUE, TRUE, TRUE),
('9601701', 'Lavanderias', '96017', 'Lavanderias, tinturarias e toalheiros', '960', 'Outras atividades de serviços pessoais', '96', 'Outras Atividades De Serviços Pessoais', 'S', 'Outras Atividades De Serviços', TRUE, TRUE, TRUE, TRUE),
('9601702', 'Tinturarias', '96017', 'Lavanderias, tinturarias e toalheiros', '960', 'Outras atividades de serviços pessoais', '96', 'Outras Atividades De Serviços Pessoais', 'S', 'Outras Atividades De Serviços', TRUE, TRUE, TRUE, TRUE),
('9602502', 'Atividades de estética e outros serviços de cuidados com a beleza', '96025', 'Cabeleireiros e outras atividades de tratamento de beleza', '960', 'Outras atividades de serviços pessoais', '96', 'Outras Atividades De Serviços Pessoais', 'S', 'Outras Atividades De Serviços', TRUE, TRUE, TRUE, TRUE),
('9602501', 'Cabeleireiros, manicure e pedicure', '96025', 'Cabeleireiros e outras atividades de tratamento de beleza', '960', 'Outras atividades de serviços pessoais', '96', 'Outras Atividades De Serviços Pessoais', 'S', 'Outras Atividades De Serviços', TRUE, TRUE, TRUE, TRUE),
('9603399', 'Atividades funerárias e serviços relacionados não especificados anteriormente', '96033', 'Atividades funerárias e serviços relacionados', '960', 'Outras atividades de serviços pessoais', '96', 'Outras Atividades De Serviços Pessoais', 'S', 'Outras Atividades De Serviços', TRUE, TRUE, TRUE, TRUE),
('9603301', 'Gestão e manutenção de cemitérios', '96033', 'Atividades funerárias e serviços relacionados', '960', 'Outras atividades de serviços pessoais', '96', 'Outras Atividades De Serviços Pessoais', 'S', 'Outras Atividades De Serviços', TRUE, TRUE, TRUE, TRUE),
('9603305', 'Serviços de somatoconservação', '96033', 'Atividades funerárias e serviços relacionados', '960', 'Outras atividades de serviços pessoais', '96', 'Outras Atividades De Serviços Pessoais', 'S', 'Outras Atividades De Serviços', TRUE, TRUE, TRUE, TRUE),
('9603303', 'Serviços de sepultamento', '96033', 'Atividades funerárias e serviços relacionados', '960', 'Outras atividades de serviços pessoais', '96', 'Outras Atividades De Serviços Pessoais', 'S', 'Outras Atividades De Serviços', TRUE, TRUE, TRUE, TRUE),
('9603304', 'Serviços de funerárias', '96033', 'Atividades funerárias e serviços relacionados', '960', 'Outras atividades de serviços pessoais', '96', 'Outras Atividades De Serviços Pessoais', 'S', 'Outras Atividades De Serviços', TRUE, TRUE, TRUE, TRUE),
('9603302', 'Serviços de cremação', '96033', 'Atividades funerárias e serviços relacionados', '960', 'Outras atividades de serviços pessoais', '96', 'Outras Atividades De Serviços Pessoais', 'S', 'Outras Atividades De Serviços', TRUE, TRUE, TRUE, TRUE),
('9609299', 'Outras atividades de serviços pessoais não especificadas anteriormente', '96092', 'Atividades de serviços pessoais não especificadas anteriormente', '960', 'Outras atividades de serviços pessoais', '96', 'Outras Atividades De Serviços Pessoais', 'S', 'Outras Atividades De Serviços', TRUE, TRUE, TRUE, TRUE),
('9609205', 'Atividades de sauna e banhos', '96092', 'Atividades de serviços pessoais não especificadas anteriormente', '960', 'Outras atividades de serviços pessoais', '96', 'Outras Atividades De Serviços Pessoais', 'S', 'Outras Atividades De Serviços', FALSE, TRUE, TRUE, TRUE),
('9609204', 'Exploração de máquinas de serviços pessoais acionadas por moeda', '96092', 'Atividades de serviços pessoais não especificadas anteriormente', '960', 'Outras atividades de serviços pessoais', '96', 'Outras Atividades De Serviços Pessoais', 'S', 'Outras Atividades De Serviços', TRUE, TRUE, TRUE, TRUE),
('9609206', 'Serviços de tatuagem e colocação de piercing', '96092', 'Atividades de serviços pessoais não especificadas anteriormente', '960', 'Outras atividades de serviços pessoais', '96', 'Outras Atividades De Serviços Pessoais', 'S', 'Outras Atividades De Serviços', FALSE, TRUE, TRUE, TRUE),
('9609203', 'Alojamento, higiene e embelezamento de animais', '96092', 'Atividades de serviços pessoais não especificadas anteriormente', '960', 'Outras atividades de serviços pessoais', '96', 'Outras Atividades De Serviços Pessoais', 'S', 'Outras Atividades De Serviços', TRUE, TRUE, FALSE, FALSE),
('9609207', 'Alojamento de animais domésticos', '96092', 'Atividades de serviços pessoais não especificadas anteriormente', '960', 'Outras atividades de serviços pessoais', '96', 'Outras Atividades De Serviços Pessoais', 'S', 'Outras Atividades De Serviços', FALSE, FALSE, TRUE, TRUE),
('9609202', 'Agências matrimoniais', '96092', 'Atividades de serviços pessoais não especificadas anteriormente', '960', 'Outras atividades de serviços pessoais', '96', 'Outras Atividades De Serviços Pessoais', 'S', 'Outras Atividades De Serviços', TRUE, TRUE, TRUE, TRUE),
('9609201', 'Clínicas de estética e similares', '96092', 'Atividades de serviços pessoais não especificadas anteriormente', '960', 'Outras atividades de serviços pessoais', '96', 'Outras Atividades De Serviços Pessoais', 'S', 'Outras Atividades De Serviços', TRUE, FALSE, FALSE, FALSE),
('9609208', 'Higiene e embelezamento de animais domésticos', '96092', 'Atividades de serviços pessoais não especificadas anteriormente', '960', 'Outras atividades de serviços pessoais', '96', 'Outras Atividades De Serviços Pessoais', 'S', 'Outras Atividades De Serviços', FALSE, FALSE, TRUE, TRUE)
ON CONFLICT (code) DO UPDATE SET
    subclass_description = EXCLUDED.subclass_description,
    class_code = EXCLUDED.class_code,
    class_description = EXCLUDED.class_description,
    group_code = EXCLUDED.group_code,
    group_description = EXCLUDED.group_description,
    division_code = EXCLUDED.division_code,
    division_description = EXCLUDED.division_description,
    section_code = EXCLUDED.section_code,
    section_description = EXCLUDED.section_description,
    cnae_2_0 = EXCLUDED.cnae_2_0,
    cnae_2_1 = EXCLUDED.cnae_2_1,
    cnae_2_2 = EXCLUDED.cnae_2_2,
    cnae_2_3 = EXCLUDED.cnae_2_3,
    updated_at = CURRENT_TIMESTAMP;

-- Backfill: anexos antigos dos orcamentos para a tabela de documentos multiplos.
INSERT INTO demand_supplier_quote_attachments (
    demand_supplier_quote_id,
    quote_number,
    quote_date,
    validity_date,
    attachment_path,
    notes
)
SELECT
    id,
    quote_number,
    quote_date,
    validity_date,
    attachment_path,
    notes
FROM demand_supplier_quotes
WHERE attachment_path IS NOT NULL
  AND attachment_path <> ''
ON CONFLICT (demand_supplier_quote_id, attachment_path) DO NOTHING;
SELECT setval(pg_get_serial_sequence('app_users', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM app_users), 0), 1), COALESCE((SELECT MAX(id) FROM app_users), 0) > 0);
SELECT setval(pg_get_serial_sequence('categories', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM categories), 0), 1), COALESCE((SELECT MAX(id) FROM categories), 0) > 0);
SELECT setval(pg_get_serial_sequence('unit_types', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM unit_types), 0), 1), COALESCE((SELECT MAX(id) FROM unit_types), 0) > 0);
SELECT setval(pg_get_serial_sequence('procurement_items', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM procurement_items), 0), 1), COALESCE((SELECT MAX(id) FROM procurement_items), 0) > 0);
SELECT setval(pg_get_serial_sequence('procurement_item_images', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM procurement_item_images), 0), 1), COALESCE((SELECT MAX(id) FROM procurement_item_images), 0) > 0);
SELECT setval(pg_get_serial_sequence('procurement_item_versions', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM procurement_item_versions), 0), 1), COALESCE((SELECT MAX(id) FROM procurement_item_versions), 0) > 0);
SELECT setval(pg_get_serial_sequence('procurement_projects', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM procurement_projects), 0), 1), COALESCE((SELECT MAX(id) FROM procurement_projects), 0) > 0);
SELECT setval(pg_get_serial_sequence('direct_purchase_dod_documents', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM direct_purchase_dod_documents), 0), 1), COALESCE((SELECT MAX(id) FROM direct_purchase_dod_documents), 0) > 0);
SELECT setval(pg_get_serial_sequence('secretariats', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM secretariats), 0), 1), COALESCE((SELECT MAX(id) FROM secretariats), 0) > 0);
SELECT setval(pg_get_serial_sequence('requester_units', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM requester_units), 0), 1), COALESCE((SELECT MAX(id) FROM requester_units), 0) > 0);
SELECT setval(pg_get_serial_sequence('collaborators', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM collaborators), 0), 1), COALESCE((SELECT MAX(id) FROM collaborators), 0) > 0);
SELECT setval(pg_get_serial_sequence('suppliers', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM suppliers), 0), 1), COALESCE((SELECT MAX(id) FROM suppliers), 0) > 0);
SELECT setval(pg_get_serial_sequence('demand_lists', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM demand_lists), 0), 1), COALESCE((SELECT MAX(id) FROM demand_lists), 0) > 0);
SELECT setval(pg_get_serial_sequence('demand_confirmation_requests', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM demand_confirmation_requests), 0), 1), COALESCE((SELECT MAX(id) FROM demand_confirmation_requests), 0) > 0);
SELECT setval(pg_get_serial_sequence('demand_items', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM demand_items), 0), 1), COALESCE((SELECT MAX(id) FROM demand_items), 0) > 0);
SELECT setval(pg_get_serial_sequence('demand_supplier_quotes', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM demand_supplier_quotes), 0), 1), COALESCE((SELECT MAX(id) FROM demand_supplier_quotes), 0) > 0);
SELECT setval(pg_get_serial_sequence('demand_supplier_quote_items', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM demand_supplier_quote_items), 0), 1), COALESCE((SELECT MAX(id) FROM demand_supplier_quote_items), 0) > 0);
SELECT setval(pg_get_serial_sequence('demand_supplier_quote_attachments', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM demand_supplier_quote_attachments), 0), 1), COALESCE((SELECT MAX(id) FROM demand_supplier_quote_attachments), 0) > 0);
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
