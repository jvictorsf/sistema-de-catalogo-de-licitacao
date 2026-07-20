<?php

require_once __DIR__ . '/../config.php';

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$currentPage = $currentPath === '/' ? 'index.php' : basename($currentPath);
$currentUser = function_exists('auth_current_user') ? auth_current_user() : null;

$primaryNavItems = [
    [
        'href' => '/dashboard.php',
        'label' => 'Dashboard',
        'icon' => 'bi-speedometer2',
        'active' => ['dashboard.php'],
    ],
    [
        'href' => '/project_bi.php',
        'label' => 'Gestao de projetos',
        'icon' => 'bi-graph-up-arrow',
        'permission' => 'bi.view',
        'active' => ['project_bi.php', 'annual_price_comparison.php', 'annual_price_comparison_export.php'],
    ],
    [
        'href' => '/signature_pending.php',
        'label' => 'Assinaturas',
        'icon' => 'bi-pen',
        'permission' => 'confirmations.manage',
        'active' => ['signature_pending.php', 'demand_confirmation_form.php', 'demand_confirmation_file.php'],
    ],
    [
        'href' => '/',
        'label' => 'Itens',
        'icon' => 'bi-box-seam',
        'active' => ['index.php', 'item_form.php', 'item_show.php'],
    ],
    [
        'href' => '/projects.php',
        'label' => 'Projetos',
        'icon' => 'bi-folder2-open',
        'active' => [
            'projects.php',
            'project_form.php',
            'project_show.php',
            'demand_show.php',
            'project_report.php',
            'project_demand_report.php',
            'project_export_word.php',
            'project_pdf.php',
            'project_licitation_annex_i.php',
            'project_licitation_annex_ii.php',
            'project_licitation_annex_iii.php',
            'project_licitation_annex_iv.php',
            'project_lot_annex_i.php',
            'project_lot_annex_ii.php',
            'project_lot_annex_iii.php',
            'project_lot_annex_iv.php',
            'project_lots.php',
            'project_lot_form.php',
            'project_lot_assignments.php',
            'project_licitation_numbers.php',
            'project_quantity_memories.php',
            'project_quantity_memory_form.php',
            'project_quote_request.php',
            'project_quote_request_excel.php',
            'project_quote_request_excel_grouped.php',
            'project_quote_request_denominations.php',
            'project_budgets.php',
            'project_global_price_bank.php',
            'project_supplier_quote_form.php',
            'direct_purchase_dod.php',
            'direct_purchase_dod_export.php',
        ],
    ],
    [
        'href' => '/kits.php',
        'label' => 'Kits',
        'icon' => 'bi-collection',
        'permission' => 'catalog.manage',
        'active' => ['kits.php', 'kit_form.php', 'kit_show.php'],
    ],
];

$navGroups = [
    [
        'label' => 'Cadastros',
        'icon' => 'bi-ui-checks-grid',
        'items' => [
            [
                'href' => '/categories.php',
                'label' => 'Categorias',
                'icon' => 'bi-tags',
                'permission' => 'catalog.manage',
                'active' => ['categories.php', 'category_form.php'],
            ],
            [
                'href' => '/unit_types.php',
                'label' => 'Unidades',
                'icon' => 'bi-rulers',
                'permission' => 'catalog.manage',
                'active' => ['unit_types.php', 'unit_type_form.php'],
            ],
            [
                'href' => '/requester_units.php',
                'label' => 'Unidades Adm.',
                'icon' => 'bi-building',
                'permission' => 'requesters.manage',
                'active' => ['requester_units.php', 'requester_unit_form.php', 'secretariat_form.php'],
            ],
            [
                'href' => '/collaborators.php',
                'label' => 'Colaboradores',
                'icon' => 'bi-people',
                'permission' => 'requesters.manage',
                'active' => ['collaborators.php', 'collaborator_form.php'],
            ],
            [
                'href' => '/suppliers.php',
                'label' => 'Fornecedores',
                'icon' => 'bi-truck',
                'permission' => 'suppliers.manage',
                'active' => ['suppliers.php', 'supplier_form.php', 'demand_supplier_quote_form.php', 'demand_budget.php', 'demand_price_bank.php'],
            ],
            [
                'href' => '/library.php',
                'label' => 'Biblioteca',
                'icon' => 'bi-journal-text',
                'permission' => 'catalog.manage',
                'active' => ['library.php', 'justification_template_form.php', 'impact_template_form.php'],
            ],
            [
                'href' => '/similar_items.php',
                'label' => 'Semelhantes',
                'icon' => 'bi-intersect',
                'permission' => 'catalog.manage',
                'active' => ['similar_items.php'],
            ],
        ],
    ],
    [
        'label' => 'Administracao',
        'icon' => 'bi-gear',
        'items' => [
            [
                'href' => '/users.php',
                'label' => 'Usuarios',
                'icon' => 'bi-people-gear',
                'permission' => 'system.manage_users',
                'active' => ['users.php', 'user_form.php'],
            ],
            [
                'href' => '/data.php',
                'label' => 'Dados',
                'icon' => 'bi-database',
                'permission' => 'system.manage_data',
                'active' => ['data.php'],
            ],
            [
                'href' => '/environment_diagnostics.php',
                'label' => 'Diagnostico',
                'icon' => 'bi-activity',
                'permission' => 'system.view_diagnostics',
                'active' => ['environment_diagnostics.php'],
            ],
            [
                'href' => '/system_logs.php',
                'label' => 'Logs',
                'icon' => 'bi-terminal',
                'permission' => 'system.view_logs',
                'active' => ['system_logs.php'],
            ],
            [
                'href' => '/editor_settings.php',
                'label' => 'Editor e documentos',
                'icon' => 'bi-file-earmark-font',
                'permission' => 'system.manage_editor_settings',
                'active' => ['editor_settings.php'],
            ],
            [
                'href' => '/document_hash_validate.php',
                'label' => 'Validar hash',
                'icon' => 'bi-shield-check',
                'permission' => 'hashes.view',
                'active' => ['document_hash_validate.php'],
            ],
        ],
    ],
];
?>
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <title><?= e(APP_NAME) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/assets/app.css" rel="stylesheet">
    <?php if (function_exists('rich_text_editor_assets_requested') && rich_text_editor_assets_requested()): ?>
        <?php
        $richTextEditorSettings = rich_text_editor_resolved_settings();
        $richTextEditorClientSettings = [
            'default_text_align' => $richTextEditorSettings['default_text_align'],
            'force_text_alignment' => $richTextEditorSettings['force_text_alignment'],
            'font_family' => $richTextEditorSettings['font_family'],
            'font_css' => rich_text_editor_font_css($richTextEditorSettings),
            'font_size_pt' => $richTextEditorSettings['font_size_pt'],
            'line_height' => $richTextEditorSettings['line_height'],
            'paragraph_spacing_pt' => $richTextEditorSettings['paragraph_spacing_pt'],
        ];
        ?>
        <link href='/assets/rich-text-editor.css' rel='stylesheet'>
        <style>
            :root {
                --rich-text-align: <?= e($richTextEditorSettings['default_text_align']) ?>;
                --rich-text-font-family: <?= e(rich_text_editor_font_css($richTextEditorSettings)) ?>;
                --rich-text-font-size: <?= e(rich_text_editor_css_number($richTextEditorSettings['font_size_pt'])) ?>pt;
                --rich-text-line-height: <?= e(rich_text_editor_css_number($richTextEditorSettings['line_height'])) ?>;
                --rich-text-paragraph-spacing: <?= e(rich_text_editor_css_number($richTextEditorSettings['paragraph_spacing_pt'])) ?>pt;
            }
        </style>
        <script id="rich-text-editor-defaults" type="application/json"><?= json_encode($richTextEditorClientSettings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
    <?php endif; ?>
</head>

<body>
    <nav class="navbar navbar-expand-xl navbar-dark bg-dark app-navbar mb-4">
        <div class="container-fluid px-4">
            <a class="navbar-brand fw-semibold d-flex align-items-center gap-2" href="/">
                <i class="bi bi-boxes"></i>
                <span><?= e(APP_NAME) ?></span>
            </a>

            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#mainNavbar"
                aria-controls="mainNavbar"
                aria-expanded="false"
                aria-label="Alternar navegacao">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNavbar">
                <div class="navbar-nav ms-auto">
                    <?php foreach ($primaryNavItems as $navItem): ?>
                        <?php
                            if (!empty($navItem['permission']) && !auth_can((string) $navItem['permission'])) {
                                continue;
                            }

                            $active = in_array($currentPage, $navItem['active'], true);
                        ?>
                        <a
                            href="<?= e($navItem['href']) ?>"
                            class="nav-link d-flex align-items-center gap-2 <?= $active ? 'active' : '' ?>"
                            <?= $active ? 'aria-current="page"' : '' ?>>
                            <i class="bi <?= e($navItem['icon']) ?>"></i>
                            <span><?= e($navItem['label']) ?></span>
                        </a>
                    <?php endforeach; ?>

                    <?php foreach ($navGroups as $navGroup): ?>
                        <?php
                            $visibleItems = array_values(array_filter(
                                $navGroup['items'],
                                static fn (array $navItem): bool => empty($navItem['permission']) || auth_can((string) $navItem['permission'])
                            ));

                            if (!$visibleItems) {
                                continue;
                            }

                            $groupActive = false;

                            foreach ($visibleItems as $navItem) {
                                if (in_array($currentPage, $navItem['active'], true)) {
                                    $groupActive = true;
                                    break;
                                }
                            }
                        ?>
                        <div class="nav-item dropdown">
                            <a
                                href="#"
                                class="nav-link dropdown-toggle d-flex align-items-center gap-2 <?= $groupActive ? 'active' : '' ?>"
                                role="button"
                                data-bs-toggle="dropdown"
                                aria-expanded="false"
                                <?= $groupActive ? 'aria-current="page"' : '' ?>>
                                <i class="bi <?= e($navGroup['icon']) ?>"></i>
                                <span><?= e($navGroup['label']) ?></span>
                            </a>

                            <ul class="dropdown-menu dropdown-menu-end">
                                <?php foreach ($visibleItems as $navItem): ?>
                                    <?php $active = in_array($currentPage, $navItem['active'], true); ?>
                                    <li>
                                        <a
                                            href="<?= e($navItem['href']) ?>"
                                            class="dropdown-item d-flex align-items-center gap-2 <?= $active ? 'active' : '' ?>">
                                            <i class="bi <?= e($navItem['icon']) ?>"></i>
                                            <span><?= e($navItem['label']) ?></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($currentUser): ?>
                        <div class="nav-item dropdown">
                            <a
                                href="#"
                                class="nav-link dropdown-toggle d-flex align-items-center gap-2"
                                role="button"
                                data-bs-toggle="dropdown"
                                aria-expanded="false">
                                <i class="bi bi-person-circle"></i>
                                <span><?= e($currentUser['name'] ?? 'Usuario') ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <span class="dropdown-item-text small text-muted">
                                        <?= e(auth_role_label($currentUser['role'] ?? '')) ?>
                                    </span>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a href="/profile.php" class="dropdown-item d-flex align-items-center gap-2">
                                        <i class="bi bi-key"></i><span>Minha senha</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="/logout.php" class="dropdown-item d-flex align-items-center gap-2">
                                        <i class="bi bi-box-arrow-right"></i><span>Sair</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <main class="container-fluid app-shell mb-5">
