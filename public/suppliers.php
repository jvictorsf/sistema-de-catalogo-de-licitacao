<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$suppliers = get_suppliers();

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
