<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$id = (int) ($_GET['id'] ?? 0);
$unit = $id ? find_requester_unit($id) : null;

if ($id && !$unit) {
    http_response_code(404);
    exit('Unidade administrativa nao encontrada.');
}

$secretariats = get_secretariats();
$parentUnits = get_requester_parent_units($unit ? (int) $unit['id'] : null);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'parent_id' => (int) ($_POST['parent_id'] ?? 0),
        'secretariat_id' => (int) ($_POST['secretariat_id'] ?? 0),
        'name' => trim($_POST['name'] ?? ''),
        'default_responsible_name' => trim($_POST['default_responsible_name'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'postal_code' => trim($_POST['postal_code'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'branch' => trim($_POST['branch'] ?? ''),
        'email' => strtolower(trim($_POST['email'] ?? '')),
        'is_active' => isset($_POST['is_active']),
    ];

    if (!$data['secretariat_id']) {
        $errors[] = 'A secretaria e obrigatoria.';
    }

    if (!$data['name']) {
        $errors[] = 'O nome da unidade, setor ou subunidade e obrigatorio.';
    }

    if ($data['postal_code'] !== '' && strlen(only_digits($data['postal_code'])) !== 8) {
        $errors[] = 'Informe um CEP valido com 8 digitos.';
    }

    if ($data['phone'] !== '' && !in_array(strlen(only_digits($data['phone'])), [10, 11], true)) {
        $errors[] = 'Informe um telefone valido com DDD.';
    }

    if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Informe um e-mail valido.';
    }

    if ($data['parent_id']) {
        $parentUnit = find_requester_unit($data['parent_id']);

        if (!$parentUnit || !empty($parentUnit['parent_id'])) {
            $errors[] = 'Selecione uma unidade pai valida.';
        } elseif ((int) ($parentUnit['secretariat_id'] ?? 0) !== $data['secretariat_id']) {
            $errors[] = 'A unidade pai precisa pertencer a mesma secretaria.';
        }
    }

    if (!$errors) {
        if ($unit) {
            update_requester_unit((int) $unit['id'], $data);
        } else {
            create_requester_unit($data);
        }

        redirect('/requester_units.php');
    }

    $unit = array_merge($unit ?? [], $data);
}

require __DIR__ . '/../app/views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><?= $unit ? 'Editar unidade administrativa' : 'Nova unidade administrativa' ?></h1>
        <p class="text-muted mb-0">Vincule secretaria, unidade ou subunidade e informe contatos usados nos documentos.</p>
    </div>

    <a href="/requester_units.php" class="btn btn-outline-secondary">
        Voltar
    </a>
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
    <div class="mb-4 pb-3 border-bottom">
        <h2 class="h6 text-uppercase text-muted mb-3">Hierarquia administrativa</h2>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Secretaria</label>
                <select name="secretariat_id" id="secretariatSelect" class="form-select" required>
                    <option value="">Selecione...</option>

                    <?php foreach ($secretariats as $secretariat): ?>
                        <option
                            value="<?= (int) $secretariat['id'] ?>"
                            <?= (int) old($unit ?? [], 'secretariat_id') === (int) $secretariat['id'] ? 'selected' : '' ?>>
                            <?= e($secretariat['name']) ?>
                            <?= !$secretariat['is_active'] ? ' (inativa)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Unidade pai</label>
                <select name="parent_id" id="parentUnitSelect" class="form-select">
                    <option value="">Nenhuma - unidade principal</option>

                    <?php foreach ($parentUnits as $parentUnit): ?>
                        <option
                            value="<?= (int) $parentUnit['id'] ?>"
                            data-secretariat-id="<?= (int) ($parentUnit['secretariat_id'] ?? 0) ?>"
                            <?= (int) old($unit ?? [], 'parent_id') === (int) $parentUnit['id'] ? 'selected' : '' ?>>
                            <?= e($parentUnit['name']) ?>
                            <?= $parentUnit['secretariat_name'] ? ' - ' . e($parentUnit['secretariat_name']) : '' ?>
                            <?= !$parentUnit['is_active'] ? ' (inativa)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Use para cadastrar Farmacia, Recepcao, Odontologia e outros departamentos dentro de uma unidade.</div>
            </div>

            <div class="col-md-6">
                <label class="form-label">Unidade/Setor/Subunidade</label>
                <input type="text" name="name" class="form-control" required value="<?= e(old($unit ?? [], 'name')) ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label">Responsavel padrao</label>
                <input
                    type="text"
                    name="default_responsible_name"
                    class="form-control"
                    value="<?= e(old($unit ?? [], 'default_responsible_name')) ?>"
                    placeholder="Ex.: Diretor(a), Coordenador(a), Secretario(a)">
            </div>
        </div>
    </div>

    <div class="mb-4 pb-3 border-bottom">
        <h2 class="h6 text-uppercase text-muted mb-3">Endereco e contato institucional</h2>
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label">Endereco</label>
                <input type="text" name="address" class="form-control" value="<?= e(old($unit ?? [], 'address')) ?>" placeholder="Rua, numero, bairro">
            </div>

            <div class="col-md-4">
                <label class="form-label">CEP</label>
                <input type="text" name="postal_code" class="form-control" inputmode="numeric" maxlength="9" data-mask="cep" value="<?= e(old($unit ?? [], 'postal_code')) ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">Telefone</label>
                <input type="text" name="phone" class="form-control" inputmode="numeric" maxlength="15" data-mask="phone" value="<?= e(format_brazil_phone(old($unit ?? [], 'phone'))) ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label">Ramal</label>
                <input type="text" name="branch" class="form-control" value="<?= e(old($unit ?? [], 'branch')) ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label">E-mail</label>
                <input type="email" name="email" class="form-control" value="<?= e(old($unit ?? [], 'email')) ?>">
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center gap-3">
        <div class="form-check form-switch">
            <input
                class="form-check-input"
                type="checkbox"
                role="switch"
                id="is_active"
                name="is_active"
                <?= old($unit ?? [], 'is_active', true) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active">Ativa</label>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="/requester_units.php" class="btn btn-outline-secondary">Cancelar</a>
            <button class="btn btn-primary">Salvar</button>
        </div>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const secretariatSelect = document.getElementById('secretariatSelect');
        const parentUnitSelect = document.getElementById('parentUnitSelect');

        function onlyDigits(value) {
            return String(value || '').replace(/\D/g, '');
        }

        function formatPhone(value) {
            const digits = onlyDigits(value).slice(0, 11);
            return digits.length <= 10
                ? digits.replace(/^(\d{2})(\d)/, '($1) $2').replace(/(\d{4})(\d{1,4})$/, '$1-$2')
                : digits.replace(/^(\d{2})(\d)/, '($1) $2').replace(/(\d{5})(\d{1,4})$/, '$1-$2');
        }

        function formatCep(value) {
            return onlyDigits(value).slice(0, 8).replace(/^(\d{5})(\d)/, '$1-$2');
        }

        document.querySelectorAll('[data-mask]').forEach(function(input) {
            input.addEventListener('input', function() {
                input.value = input.dataset.mask === 'cep' ? formatCep(input.value) : formatPhone(input.value);
            });
        });

        if (!secretariatSelect || !parentUnitSelect) {
            return;
        }

        function syncParentOptions() {
            const selectedSecretariatId = secretariatSelect.value;
            let selectedParentIsInvalid = false;

            Array.from(parentUnitSelect.options).forEach(function(option) {
                if (!option.value) {
                    return;
                }

                const isVisible = !selectedSecretariatId || option.dataset.secretariatId === selectedSecretariatId;
                option.hidden = !isVisible;
                option.disabled = !isVisible;

                if (option.selected && !isVisible) {
                    selectedParentIsInvalid = true;
                }
            });

            if (selectedParentIsInvalid) {
                parentUnitSelect.value = '';
            }
        }

        secretariatSelect.addEventListener('change', syncParentOptions);
        syncParentOptions();
    });
</script>

<?php require __DIR__ . '/../app/views/footer.php'; ?>