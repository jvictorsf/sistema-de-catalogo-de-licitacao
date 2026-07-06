<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';

$user = auth_current_user();
$errors = [];
$success = false;
$mustChange = (string) ($_GET['must_change'] ?? '') === '1';

if (!$user) {
    redirect('/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');

    if ($newPassword !== $confirmation) {
        $errors[] = 'A confirmacao da senha nao confere.';
    }

    if (!$errors) {
        try {
            auth_change_current_password($currentPassword, $newPassword);
            $success = true;
            $mustChange = false;
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage() ?: 'Nao foi possivel trocar a senha.';
        }
    }
}

require __DIR__ . '/../app/views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Meu acesso</h1>
        <p class="text-muted mb-0">Perfil e troca de senha.</p>
    </div>
</div>

<?php if ($mustChange): ?>
    <div class="alert alert-warning">Troque sua senha para continuar usando o sistema.</div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">Senha atualizada com sucesso.</div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card card-body shadow-sm">
            <div class="text-muted small">Usuario</div>
            <div class="fw-semibold"><?= e($user['name']) ?></div>
            <div class="text-muted mt-2"><?= e($user['username']) ?> - <?= e($user['email']) ?></div>
            <div class="mt-3"><span class="badge text-bg-primary"><?= e(auth_role_label($user['role'] ?? '')) ?></span></div>
        </div>
    </div>

    <div class="col-lg-7">
        <form method="post" class="card card-body shadow-sm">
            <h2 class="h5 mb-3">Trocar senha</h2>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Senha atual</label>
                    <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nova senha</label>
                    <input type="password" name="new_password" class="form-control" minlength="8" required autocomplete="new-password">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Confirmar nova senha</label>
                    <input type="password" name="password_confirmation" class="form-control" minlength="8" required autocomplete="new-password">
                </div>
                <div class="col-12 d-flex justify-content-end">
                    <button class="btn btn-primary">Atualizar senha</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../app/views/footer.php'; ?>