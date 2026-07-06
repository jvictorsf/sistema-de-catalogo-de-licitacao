<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';

if (auth_current_user()) {
    redirect('/dashboard.php');
}

$errors = [];
$schemaAvailable = auth_schema_available();
$needsSetup = $schemaAvailable && auth_user_count() === 0;
$return = trim((string) ($_GET['return'] ?? $_POST['return'] ?? '/dashboard.php'));

if ($return === '' || !str_starts_with($return, '/') || str_starts_with($return, '//')) {
    $return = '/dashboard.php';
}

if (!$schemaAvailable) {
    $errors[] = 'Autenticacao ainda nao instalada no banco. Rode o schema atualizado.';
}

if (($_GET['error'] ?? '') === 'schema') {
    $errors[] = 'A tabela de usuarios nao foi encontrada. Rode o schema atualizado antes de acessar o sistema.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $schemaAvailable && !$needsSetup) {
    $login = trim((string) ($_POST['login'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!auth_attempt($login, $password)) {
        $errors[] = 'Usuario ou senha invalidos.';
    } else {
        $user = auth_current_user();
        redirect(!empty($user['must_change_password']) ? '/profile.php?must_change=1' : $return);
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Entrar - <?= e(APP_NAME) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/assets/app.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; background: #eef2f7; }
        .auth-shell { min-height: 100vh; display: grid; place-items: center; padding: 1.5rem; }
        .auth-card { width: 100%; max-width: 430px; }
    </style>
</head>
<body>
<main class="auth-shell">
    <div class="auth-card card shadow-sm border-0">
        <div class="card-body p-4">
            <div class="d-flex align-items-center gap-3 mb-4">
                <div class="rounded bg-primary-subtle text-primary p-3"><i class="bi bi-shield-lock fs-3"></i></div>
                <div>
                    <h1 class="h4 mb-0">Acesso ao sistema</h1>
                    <div class="text-muted small"><?= e(APP_NAME) ?></div>
                </div>
            </div>

            <?php if ($needsSetup): ?>
                <div class="alert alert-info">
                    Nenhum usuario foi cadastrado ainda.
                </div>
                <a href="/setup_admin.php" class="btn btn-primary w-100">Criar primeiro administrador</a>
            <?php else: ?>
                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach (array_unique($errors) as $error): ?>
                                <li><?= e($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" class="vstack gap-3">
                    <input type="hidden" name="return" value="<?= e($return) ?>">
                    <div>
                        <label class="form-label">Usuario ou e-mail</label>
                        <input type="text" name="login" class="form-control" autocomplete="username" required autofocus>
                    </div>
                    <div>
                        <label class="form-label">Senha</label>
                        <input type="password" name="password" class="form-control" autocomplete="current-password" required>
                    </div>
                    <button class="btn btn-primary w-100" <?= !$schemaAvailable ? 'disabled' : '' ?>>Entrar</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>