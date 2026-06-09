<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$secretariats = get_secretariats();
$requesterUnits = get_requester_units();

require __DIR__ . '/../app/views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Demandantes</h1>
        <p class="text-muted mb-0">
            Cadastre secretarias, unidades, subunidades e responsaveis padrao para agilizar as demandas.
        </p>
    </div>

    <div class="d-flex gap-2">
        <a href="/secretariat_form.php" class="btn btn-outline-primary">
            <i class="bi bi-plus-lg"></i>Secretaria
        </a>

        <a href="/requester_unit_form.php" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i>Unidade
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header fw-semibold">
                Secretarias
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Secretaria</th>
                            <th>Status</th>
                            <th class="text-end">Acoes</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (!$secretariats): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">
                                    Nenhuma secretaria cadastrada.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($secretariats as $secretariat): ?>
                            <tr>
                                <td><?= e($secretariat['name']) ?></td>
                                <td>
                                    <span class="badge <?= $secretariat['is_active'] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                        <?= $secretariat['is_active'] ? 'Ativa' : 'Inativa' ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="/secretariat_form.php?id=<?= (int) $secretariat['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        Editar
                                    </a>

                                    <?php if ($secretariat['is_active']): ?>
                                        <form action="/secretariat_delete.php" method="post" class="d-inline" onsubmit="return confirm('Desativar esta secretaria?')">
                                            <input type="hidden" name="id" value="<?= (int) $secretariat['id'] ?>">
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
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header fw-semibold">
                Unidades, setores e subunidades demandantes
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Unidade/Subunidade</th>
                            <th>Unidade pai</th>
                            <th>Secretaria</th>
                            <th>Responsavel padrao</th>
                            <th>Status</th>
                            <th class="text-end">Acoes</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (!$requesterUnits): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    Nenhuma unidade cadastrada.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($requesterUnits as $unit): ?>
                            <tr>
                                <td>
                                    <strong><?= e($unit['display_name'] ?? $unit['name']) ?></strong>
                                    <?php if (!empty($unit['parent_id'])): ?>
                                        <span class="badge text-bg-light border ms-1">Subunidade</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($unit['parent_unit_name'] ?? '-') ?></td>
                                <td><?= e($unit['secretariat_name'] ?? '-') ?></td>
                                <td><?= e($unit['default_responsible_name'] ?? '-') ?></td>
                                <td>
                                    <span class="badge <?= $unit['is_active'] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                        <?= $unit['is_active'] ? 'Ativa' : 'Inativa' ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="/requester_unit_form.php?id=<?= (int) $unit['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        Editar
                                    </a>

                                    <?php if ($unit['is_active']): ?>
                                        <form action="/requester_unit_delete.php" method="post" class="d-inline" onsubmit="return confirm('Desativar esta unidade?')">
                                            <input type="hidden" name="id" value="<?= (int) $unit['id'] ?>">
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
    </div>
</div>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
