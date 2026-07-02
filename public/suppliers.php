<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'status' => in_array(($_GET['status'] ?? ''), ['active', 'inactive'], true) ? (string) $_GET['status'] : '',
    'bidding' => in_array(($_GET['bidding'] ?? ''), ['yes', 'no'], true) ? (string) $_GET['bidding'] : '',
    'state' => strtoupper(trim((string) ($_GET['state'] ?? ''))),
    'company_size' => trim((string) ($_GET['company_size'] ?? '')),
];

$suppliers = get_suppliers_filtered($filters);
$allSuppliersForFilters = get_suppliers();
$stateOptions = [];
$companySizeOptions = [];

foreach ($allSuppliersForFilters as $supplierOption) {
    $state = trim((string) ($supplierOption['state'] ?? ''));
    $companySize = trim((string) ($supplierOption['company_size'] ?? ''));

    if ($state !== '') {
        $stateOptions[$state] = $state;
    }

    if ($companySize !== '') {
        $companySizeOptions[$companySize] = $companySize;
    }
}

ksort($stateOptions);
ksort($companySizeOptions);
$hasActiveSupplierFilters = array_filter($filters, static fn (string $value): bool => $value !== '') !== [];

require __DIR__ . '/../app/views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Fornecedores</h1>
        <p class="text-muted mb-0">
            Cadastre fornecedores para vincular orçamentos às demandas.
        </p>
    </div>

    <a href="/supplier_form.php" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i>Novo fornecedor
    </a>
</div>

<form method="get" class="card card-body shadow-sm mb-3 supplier-filter-form">
    <div class="row g-3 align-items-end">
        <div class="col-lg-4">
            <label class="form-label">Pesquisar fornecedor</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input
                    type="search"
                    name="q"
                    class="form-control"
                    placeholder="Nome, CNPJ, contato, e-mail, CNAE, cidade, IE, IM ou situacao"
                    value="<?= e($filters['q']) ?>">
            </div>
        </div>

        <div class="col-sm-6 col-lg-2">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="">Todos</option>
                <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Ativos</option>
                <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Inativos</option>
            </select>
        </div>

        <div class="col-sm-6 col-lg-2">
            <label class="form-label">Licitação</label>
            <select name="bidding" class="form-select">
                <option value="">Todos</option>
                <option value="yes" <?= $filters['bidding'] === 'yes' ? 'selected' : '' ?>>Participa</option>
                <option value="no" <?= $filters['bidding'] === 'no' ? 'selected' : '' ?>>Não participa</option>
            </select>
        </div>

        <div class="col-sm-6 col-lg-1">
            <label class="form-label">UF</label>
            <select name="state" class="form-select">
                <option value="">Todas</option>
                <?php foreach ($stateOptions as $stateOption): ?>
                    <option value="<?= e($stateOption) ?>" <?= $filters['state'] === $stateOption ? 'selected' : '' ?>><?= e($stateOption) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-sm-6 col-lg-2">
            <label class="form-label">Porte</label>
            <select name="company_size" class="form-select">
                <option value="">Todos</option>
                <?php foreach ($companySizeOptions as $companySizeOption): ?>
                    <option value="<?= e($companySizeOption) ?>" <?= $filters['company_size'] === $companySizeOption ? 'selected' : '' ?>><?= e($companySizeOption) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-lg-1 d-grid">
            <button class="btn btn-primary" title="Filtrar fornecedores" aria-label="Filtrar fornecedores">
                <i class="bi bi-funnel"></i>
            </button>
        </div>

        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="text-muted small">
                <?= count($suppliers) ?> fornecedor(es) encontrado(s)<?= $hasActiveSupplierFilters ? ' com os filtros aplicados' : '' ?>.
            </div>

            <?php if ($hasActiveSupplierFilters): ?>
                <a href="/suppliers.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-lg"></i> Limpar filtros
                </a>
            <?php endif; ?>
        </div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 supplier-table">
            <thead class="table-light">
                <tr>
                    <th>Fornecedor</th>
                    <th>Documento</th>
                    <th>Endereço</th>
                    <th>Contato</th>
                    <th>Licitacao</th>
                    <th>Status</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>

            <tbody>
                <?php if (!$suppliers): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            Nenhum fornecedor cadastrado.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($suppliers as $supplier): ?>
                    <?php $mainCnae = is_array($supplier['main_cnae'] ?? null) ? $supplier['main_cnae'] : null; ?>
                    <tr>
                        <td>
                            <strong><?= e($supplier['name']) ?></strong>
                            <?php if (!empty($supplier['trade_name'])): ?>
                                <div class="small text-muted"><?= e($supplier['trade_name']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($supplier['company_size'])): ?>
                                <div class="small text-muted">Porte: <?= e($supplier['company_size']) ?></div>
                            <?php endif; ?>
                            <?php if (($supplier['share_capital'] ?? null) !== null && (string) $supplier['share_capital'] !== ''): ?>
                                <div class="small text-muted">Capital social: R$ <?= number_format((float) $supplier['share_capital'], 2, ',', '.') ?></div>
                            <?php endif; ?>
                            <?php if ($mainCnae): ?>
                                <div class="small text-muted">CNAE: <?= e(trim(($mainCnae['code'] ?? '') . ' ' . ($mainCnae['name'] ?? ''))) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($supplier['notes'])): ?>
                                <div class="small text-muted"><?= e($supplier['notes']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= e($supplier['document'] ? format_brazil_document($supplier['document']) : '-') ?>
                            <?php if (!empty($supplier['state_registration'])): ?>
                                <div class="small text-muted">IE: <?= e($supplier['state_registration']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($supplier['municipal_registration'])): ?>
                                <div class="small text-muted">IM: <?= e($supplier['municipal_registration']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= e(supplier_address_text($supplier)) ?></td>
                        <td>
                            <?= e($supplier['contact_name'] ?: '-') ?>
                            <?php if (!empty($supplier['email'])): ?>
                                <div class="small text-muted"><?= e($supplier['email']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($supplier['phone'])): ?>
                                <div class="small text-muted"><?= e(format_brazil_phone($supplier['phone'])) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= boolish($supplier['participates_bidding'] ?? true, true) ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                <?= boolish($supplier['participates_bidding'] ?? true, true) ? 'Sim' : 'Nao' ?>
                            </span>
                            <?php if (!empty($supplier['website_url'])): ?>
                                <div class="small mt-1"><a href="<?= e($supplier['website_url']) ?>" target="_blank" rel="noopener">Site</a></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $supplier['is_active'] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                <?= $supplier['is_active'] ? 'Ativo' : 'Inativo' ?>
                            </span>
                            <?php if (!empty($supplier['special_status'])): ?>
                                <div class="small text-muted mt-1">Situacao especial: <?= e($supplier['special_status']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($supplier['special_status_date'])): ?>
                                <div class="small text-muted">Data: <?= date('d/m/Y', strtotime((string) $supplier['special_status_date'])) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="table-actions">
                            <a href="/supplier_form.php?id=<?= (int) $supplier['id'] ?>" class="btn btn-sm btn-outline-primary">
                                Editar
                            </a>

                            <?php if ($supplier['is_active']): ?>
                                <form action="/supplier_delete.php" method="post" onsubmit="return confirm('Desativar este fornecedor?')">
                                    <input type="hidden" name="id" value="<?= (int) $supplier['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger">
                                        Desativar
                                    </button>
                                </form>
                            <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
