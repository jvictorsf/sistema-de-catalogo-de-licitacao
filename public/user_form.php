<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';

$id = (int) ($_GET['id'] ?? 0);
$user = $id ? auth_find_user($id) : null;

if ($id && !$user) {
    http_response_code(404);
    exit('Usuario nao encontrado.');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => trim((string) ($_POST['name'] ?? '')),
        'username' => trim((string) ($_POST['username'] ?? '')),
        'email' => trim((string) ($_POST['email'] ?? '')),
        'role' => (string) ($_POST['role'] ?? 'operator'),
        'password' => (string) ($_POST['password'] ?? ''),
        'is_active' => isset($_POST['is_active']),
        'must_change_password' => isset($_POST['must_change_password']),
    ];
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');

    if ($data['password'] !== '' && $data['password'] !== $confirmation) {
        $errors[] = 'A confirmacao da senha nao confere.';
    }

    if (!$user && $data['password'] === '') {
        $errors[] = 'Informe uma senha inicial para o usuario.';
    }

    if (!$errors) {
        try {
            if ($user) {
                auth_update_user((int) $user['id'], $data);
                redirect('/users.php?success=' . rawurlencode('Usuario atualizado.'));
            }

            auth_create_user($data);
            redirect('/users.php?success=' . rawurlencode('Usuario criado.'));
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage() ?: 'Nao foi possivel salvar o usuario.';
        }
    }

    $user = array_merge($user ?? [], $data);
}

$roles = auth_roles();
$permissionLabels = auth_permission_labels();

require __DIR__ . '/../app/views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><?= $user ? 'Editar usuario' : 'Novo usuario' ?></h1>
        <p class="text-muted mb-0">Defina o perfil de acesso e as credenciais.</p>
    </div>
    <a href="/users.php" class="btn btn-outline-secondary">Voltar</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <form method="post" class="card card-body shadow-sm">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Nome</label>
                    <input type="text" name="name" class="form-control" required value="<?= e($user['name'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Usuario</label>
                    <input type="text" name="username" class="form-control" required value="<?= e($user['username'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">E-mail</label>
                    <input type="email" name="email" class="form-control" required value="<?= e($user['email'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Perfil</label>
                    <select name="role" class="form-select">
                        <?php foreach ($roles as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= ($user['role'] ?? 'operator') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <div class="form-check form-switch">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive" <?= checked_attr($user['is_active'] ?? null, true) ?>>
                        <label class="form-check-label" for="isActive">Usuario ativo</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?= $user ? 'Nova senha' : 'Senha inicial' ?></label>
                    <input type="password" name="password" class="form-control" minlength="8" autocomplete="new-password" <?= $user ? '' : 'required' ?>>
                    <?php if ($user): ?><div class="form-text">Deixe em branco para manter a senha atual.</div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Confirmar senha</label>
                    <input type="password" name="password_confirmation" class="form-control" minlength="8" autocomplete="new-password" <?= $user ? '' : 'required' ?>>
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input type="checkbox" name="must_change_password" value="1" class="form-check-input" id="mustChange" <?= checked_attr($user['must_change_password'] ?? false, true) ?>>
                        <label class="form-check-label" for="mustChange">Exigir troca de senha no proximo acesso</label>
                    </div>
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <a href="/users.php" class="btn btn-outline-secondary">Cancelar</a>
                    <button class="btn btn-primary">Salvar</button>
                </div>
            </div>
        </form>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Permissoes por perfil</div>
            <div class="list-group list-group-flush">
                <?php foreach ($roles as $role => $label): ?>
                    <div class="list-group-item">
                        <div class="fw-semibold"><?= e($label) ?></div>
                        <div class="small text-muted">
                            <?= e(implode(', ', array_map(static fn (string $permission): string => $permissionLabels[$permission] ?? $permission, auth_role_permissions($role)))) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../app/views/footer.php'; ?>