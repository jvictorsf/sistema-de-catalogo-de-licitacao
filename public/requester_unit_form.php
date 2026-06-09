<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$id = (int) ($_GET['id'] ?? 0);
$unit = $id ? find_requester_unit($id) : null;

if ($id && !$unit) {
    http_response_code(404);
    exit('Unidade demandante nao encontrada.');
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
        'is_active' => isset($_POST['is_active']),
    ];

    if (!$data['secretariat_id']) {
        $errors[] = 'A secretaria e obrigatoria.';
    }

    if (!$data['name']) {
        $errors[] = 'O nome da unidade, setor ou subunidade e obrigatorio.';
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
        <h1 class="h3 mb-1"><?= $unit ? 'Editar unidade demandante' : 'Nova unidade demandante' ?></h1>
        <p class="text-muted mb-0">Vincule a unidade a uma secretaria, informe subunidades quando houver e defina o responsavel padrao.</p>
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

        <div class="col-md-8">
            <label class="form-label">Responsavel padrao</label>
            <input
                type="text"
                name="default_responsible_name"
                class="form-control"
                value="<?= e(old($unit ?? [], 'default_responsible_name')) ?>"
                placeholder="Ex.: Diretor(a), Coordenador(a), Secretario(a)">
        </div>

        <div class="col-md-4 d-flex align-items-end">
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
        </div>

        <div class="col-12 d-flex justify-content-end gap-2">
            <a href="/requester_units.php" class="btn btn-outline-secondary">Cancelar</a>
            <button class="btn btn-primary">Salvar</button>
        </div>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const secretariatSelect = document.getElementById('secretariatSelect');
        const parentUnitSelect = document.getElementById('parentUnitSelect');

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
