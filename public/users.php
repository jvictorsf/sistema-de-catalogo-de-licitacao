<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';

$users = auth_get_users();
$error = trim((string) ($_GET['error'] ?? ''));
$success = trim((string) ($_GET['success'] ?? ''));

require __DIR__ . '/../app/views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Usuarios</h1>
        <p class="text-muted mb-0">Controle de acesso por perfil e permissao.</p>
    </div>
    <a href="/user_form.php" class="btn btn-primary"><i class="bi bi-person-plus"></i>Novo usuario</a>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Usuario</th>
                    <th>Perfil</th>
                    <th>Status</th>
                    <th>Ultimo acesso</th>
                    <th class="text-end">Acoes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <strong><?= e($user['name']) ?></strong>
                            <div class="small text-muted"><?= e($user['username']) ?> - <?= e($user['email']) ?></div>
                        </td>
                        <td><?= e(auth_role_label($user['role'] ?? '')) ?></td>
                        <td>
                            <span class="badge <?= !empty($user['is_active']) ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                <?= !empty($user['is_active']) ? 'Ativo' : 'Inativo' ?>
                            </span>
                            <?php if (!empty($user['must_change_password'])): ?>
                                <div class="small text-muted">Troca de senha pendente</div>
                            <?php endif; ?>
                        </td>
                        <td><?= !empty($user['last_login_at']) ? date('d/m/Y H:i', strtotime((string) $user['last_login_at'])) : '-' ?></td>
                        <td class="text-end">
                            <a href="/user_form.php?id=<?= (int) $user['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                            <form action="/user_toggle.php" method="post" class="d-inline">
                                <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                <input type="hidden" name="is_active" value="<?= !empty($user['is_active']) ? '0' : '1' ?>">
                                <button class="btn btn-sm btn-outline-secondary"><?= !empty($user['is_active']) ? 'Desativar' : 'Ativar' ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../app/views/footer.php'; ?>