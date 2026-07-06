<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';
require_once __DIR__ . '/../app/demand_confirmations.php';

$id = (int) ($_GET['id'] ?? 0);
$collaborator = $id ? find_collaborator($id) : null;

if ($id && !$collaborator) {
    http_response_code(404);
    exit('Colaborador nao encontrado.');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => trim((string) ($_POST['name'] ?? '')),
        'document_number' => trim((string) ($_POST['document_number'] ?? '')),
        'registration_number' => trim((string) ($_POST['registration_number'] ?? '')),
        'role' => trim((string) ($_POST['role'] ?? '')),
        'department' => trim((string) ($_POST['department'] ?? '')),
        'email' => strtolower(trim((string) ($_POST['email'] ?? ''))),
        'phone' => trim((string) ($_POST['phone'] ?? '')),
        'is_active' => isset($_POST['is_active']),
    ];

    if ($data['name'] === '') {
        $errors[] = 'Informe o nome do colaborador.';
    }

    if ($data['document_number'] !== '' && strlen(only_digits($data['document_number'])) !== 11) {
        $errors[] = 'Informe um CPF valido com 11 digitos.';
    }

    if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Informe um e-mail valido.';
    }

    if ($data['phone'] !== '' && !in_array(strlen(only_digits($data['phone'])), [10, 11], true)) {
        $errors[] = 'Informe um telefone valido com DDD.';
    }

    if (!$errors) {
        try {
            if ($collaborator) {
                update_collaborator((int) $collaborator['id'], $data);
            } else {
                create_collaborator($data);
            }

            redirect('/collaborators.php');
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage() ?: 'Nao foi possivel salvar o colaborador.';
        }
    }

    $collaborator = array_merge($collaborator ?? [], $data);
}

require __DIR__ . '/../app/views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><?= $collaborator ? 'Editar colaborador' : 'Novo colaborador' ?></h1>
        <p class="text-muted mb-0">Dados usados para preencher a confirmacao formal de demandas.</p>
    </div>

    <a href="/collaborators.php" class="btn btn-outline-secondary">Voltar</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" class="card card-body shadow-sm">
    <div class="row g-3">
        <div class="col-lg-7">
            <label class="form-label">Nome completo</label>
            <input type="text" name="name" class="form-control" required value="<?= e($collaborator['name'] ?? '') ?>">
        </div>

        <div class="col-md-6 col-lg-3">
            <label class="form-label">CPF</label>
            <input type="text" name="document_number" class="form-control" inputmode="numeric" maxlength="14" data-mask="cpf" value="<?= e(format_brazil_document($collaborator['document_number'] ?? '')) ?>">
        </div>

        <div class="col-md-6 col-lg-2">
            <label class="form-label">Matricula</label>
            <input type="text" name="registration_number" class="form-control" value="<?= e($collaborator['registration_number'] ?? '') ?>">
        </div>

        <div class="col-md-6">
            <label class="form-label">Cargo/Função</label>
            <input type="text" name="role" class="form-control" value="<?= e($collaborator['role'] ?? '') ?>">
        </div>

        <div class="col-md-6">
            <label class="form-label">Setor/Unidade</label>
            <input type="text" name="department" class="form-control" value="<?= e($collaborator['department'] ?? '') ?>">
        </div>

        <div class="col-md-6">
            <label class="form-label">E-mail</label>
            <input type="email" name="email" class="form-control" value="<?= e($collaborator['email'] ?? '') ?>">
        </div>

        <div class="col-md-6">
            <label class="form-label">Telefone</label>
            <input type="text" name="phone" class="form-control" inputmode="numeric" maxlength="15" data-mask="phone" value="<?= e(format_brazil_phone($collaborator['phone'] ?? '')) ?>">
        </div>

        <div class="col-12">
            <div class="form-check form-switch">
                <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive" <?= checked_attr($collaborator['is_active'] ?? null, true) ?>>
                <label class="form-check-label" for="isActive">Colaborador ativo</label>
            </div>
        </div>

        <div class="col-12 d-flex justify-content-end gap-2">
            <a href="/collaborators.php" class="btn btn-outline-secondary">Cancelar</a>
            <button class="btn btn-primary">Salvar</button>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function onlyDigits(value) {
        return String(value || '').replace(/\D/g, '');
    }

    function formatCpf(value) {
        return onlyDigits(value).slice(0, 11)
            .replace(/^(\d{3})(\d)/, '$1.$2')
            .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
            .replace(/^(\d{3})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3-$4');
    }

    function formatPhone(value) {
        const digits = onlyDigits(value).slice(0, 11);
        return digits.length <= 10
            ? digits.replace(/^(\d{2})(\d)/, '($1) $2').replace(/(\d{4})(\d{1,4})$/, '$1-$2')
            : digits.replace(/^(\d{2})(\d)/, '($1) $2').replace(/(\d{5})(\d{1,4})$/, '$1-$2');
    }

    document.querySelectorAll('[data-mask]').forEach(function(input) {
        input.addEventListener('input', function() {
            input.value = input.dataset.mask === 'cpf' ? formatCpf(input.value) : formatPhone(input.value);
        });
    });
});
</script>

<?php require __DIR__ . '/../app/views/footer.php'; ?>