<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';

if (!auth_schema_available()) {
    $schemaMissing = true;
} else {
    $schemaMissing = false;

    if (auth_user_count() > 0) {
        redirect('/login.php');
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$schemaMissing) {
    $data = [
        'name' => trim((string) ($_POST['name'] ?? '')),
        'username' => trim((string) ($_POST['username'] ?? '')),
        'email' => trim((string) ($_POST['email'] ?? '')),
        'password' => (string) ($_POST['password'] ?? ''),
        'role' => 'admin',
        'is_active' => true,
    ];
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');

    if ($data['password'] !== $confirmation) {
        $errors[] = 'A confirmacao da senha nao confere.';
    }

    if (!$errors) {
        try {
            auth_create_user($data, true);
            auth_attempt($data['username'], $data['password']);
            redirect('/dashboard.php');
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage() ?: 'Nao foi possivel criar o administrador.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Primeiro administrador - <?= e(APP_NAME) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/assets/app.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; background: #eef2f7; }
        .auth-shell { min-height: 100vh; display: grid; place-items: center; padding: 1.5rem; }
        .auth-card { width: 100%; max-width: 560px; }
    </style>
</head>
<body>
<main class="auth-shell">
    <div class="auth-card card shadow-sm border-0">
        <div class="card-body p-4">
            <div class="d-flex align-items-center gap-3 mb-4">
                <div class="rounded bg-success-subtle text-success p-3"><i class="bi bi-person-gear fs-3"></i></div>
                <div>
                    <h1 class="h4 mb-0">Primeiro administrador</h1>
                    <div class="text-muted small">Crie a conta inicial para habilitar o acesso protegido.</div>
                </div>
            </div>

            <?php if ($schemaMissing): ?>
                <div class="alert alert-warning mb-0">
                    A tabela `app_users` ainda nao existe. Rode o `database/schema.sql` atualizado e recarregue esta pagina.
                </div>
            <?php else: ?>
                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= e($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Nome</label>
                        <input type="text" name="name" class="form-control" required autofocus value="<?= e($_POST['name'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Usuario</label>
                        <input type="text" name="username" class="form-control" required value="<?= e($_POST['username'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">E-mail</label>
                        <input type="email" name="email" class="form-control" required value="<?= e($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Senha</label>
                        <input type="password" name="password" class="form-control" minlength="8" required autocomplete="new-password">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Confirmar senha</label>
                        <input type="password" name="password_confirmation" class="form-control" minlength="8" required autocomplete="new-password">
                    </div>
                    <div class="col-12 d-grid">
                        <button class="btn btn-primary">Criar administrador</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>