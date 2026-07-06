<?php

require_once __DIR__ . '/../config.php';

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$currentPage = $currentPath === '/' ? 'index.php' : basename($currentPath);

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
        'active' => ['project_bi.php'],
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
            'demand_confirmation_form.php',
            'project_report.php',
            'project_demand_report.php',
            'project_export_word.php',
            'project_pdf.php',
            'project_licitation_annex_i.php',
            'project_licitation_annex_ii.php',
            'project_licitation_annex_iii.php',
            'project_lot_annex_i.php',
            'project_lot_annex_ii.php',
            'project_lot_annex_iii.php',
            'project_lot_annex_iv.php',
            'project_lots.php',
            'project_lot_form.php',
            'project_lot_assignments.php',
            'project_licitation_numbers.php',
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
                'active' => ['categories.php', 'category_form.php'],
            ],
            [
                'href' => '/unit_types.php',
                'label' => 'Unidades',
                'icon' => 'bi-rulers',
                'active' => ['unit_types.php', 'unit_type_form.php'],
            ],
            [
                'href' => '/requester_units.php',
                'label' => 'Demandantes',
                'icon' => 'bi-building',
                'active' => ['requester_units.php', 'requester_unit_form.php', 'secretariat_form.php'],
            ],
            [
                'href' => '/collaborators.php',
                'label' => 'Colaboradores',
                'icon' => 'bi-people',
                'active' => ['collaborators.php', 'collaborator_form.php'],
            ],
            [
                'href' => '/suppliers.php',
                'label' => 'Fornecedores',
                'icon' => 'bi-truck',
                'active' => ['suppliers.php', 'supplier_form.php', 'demand_supplier_quote_form.php', 'demand_budget.php', 'demand_price_bank.php'],
            ],
            [
                'href' => '/library.php',
                'label' => 'Biblioteca',
                'icon' => 'bi-journal-text',
                'active' => ['library.php', 'justification_template_form.php', 'impact_template_form.php'],
            ],
            [
                'href' => '/similar_items.php',
                'label' => 'Semelhantes',
                'icon' => 'bi-intersect',
                'active' => ['similar_items.php'],
            ],
        ],
    ],
    [
        'label' => 'Administracao',
        'icon' => 'bi-gear',
        'items' => [
            [
                'href' => '/data.php',
                'label' => 'Dados',
                'icon' => 'bi-database',
                'active' => ['data.php'],
            ],
            [
                'href' => '/document_hash_validate.php',
                'label' => 'Validar hash',
                'icon' => 'bi-shield-check',
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
                        <?php $active = in_array($currentPage, $navItem['active'], true); ?>
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
                            $groupActive = false;

                            foreach ($navGroup['items'] as $navItem) {
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
                                <?php foreach ($navGroup['items'] as $navItem): ?>
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
                </div>
            </div>
        </div>
    </nav>

    <main class="container-fluid app-shell mb-5">
