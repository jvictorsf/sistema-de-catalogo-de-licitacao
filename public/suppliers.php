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
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Fornecedor</th>
                    <th>Documento</th>
                    <th>Contato</th>
                    <th>Status</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>

            <tbody>
                <?php if (!$suppliers): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            Nenhum fornecedor cadastrado.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($suppliers as $supplier): ?>
                    <tr>
                        <td>
                            <strong><?= e($supplier['name']) ?></strong>
                            <?php if (!empty($supplier['notes'])): ?>
                                <div class="small text-muted"><?= e($supplier['notes']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= e($supplier['document'] ? format_brazil_document($supplier['document']) : '-') ?></td>
                        <td>
                            <?= e($supplier['contact_name'] ?: '-') ?>
                            <?php if (!empty($supplier['email'])): ?>
                                <div class="small text-muted"><?= e($supplier['email']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($supplier['phone'])): ?>
                                <div class="small text-muted"><?= e($supplier['phone']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $supplier['is_active'] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                <?= $supplier['is_active'] ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="/supplier_form.php?id=<?= (int) $supplier['id'] ?>" class="btn btn-sm btn-outline-primary">
                                Editar
                            </a>

                            <?php if ($supplier['is_active']): ?>
                                <form action="/supplier_delete.php" method="post" class="d-inline" onsubmit="return confirm('Desativar este fornecedor?')">
                                    <input type="hidden" name="id" value="<?= (int) $supplier['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger">
                                        Desativar
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
