<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$isEditing = $id > 0;
$demand = [];

if ($isEditing) {
    $demand = find_demand_list($id);

    if (!$demand) {
        http_response_code(404);
        exit('Demanda não encontrada.');
    }
}

$projectId = (int) ($isEditing
    ? ($demand['project_id'] ?? 0)
    : ($_GET['project_id'] ?? $_POST['project_id'] ?? 0));
$project = find_project($projectId);

if (!$project) {
    http_response_code(404);
    exit('Projeto não encontrado.');
}

$errors = [];
$requesterUnits = get_requester_units(!$isEditing);
$cancelUrl = $isEditing
    ? '/demand_show.php?id=' . (int) $id
    : '/project_show.php?id=' . (int) $project['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $demand = [
        'project_id' => $projectId,
        'requester_unit_id' => (int) ($_POST['requester_unit_id'] ?? 0),
        'secretariat_id' => (int) ($_POST['secretariat_id'] ?? 0),
        'name' => trim($_POST['name'] ?? ''),
        'requester_department' => trim($_POST['requester_department'] ?? ''),
        'responsible_name' => trim($_POST['responsible_name'] ?? ''),
        'notes' => trim($_POST['notes'] ?? ''),
    ];

    if (!$demand['name']) {
        $errors[] = 'O nome da demanda é obrigatório.';
    }

    if ($requesterUnits && !$demand['requester_unit_id']) {
        $errors[] = 'Selecione a unidade/setor demandante.';
    }

    if (!$errors) {
        if ($isEditing) {
            update_demand_list($id, $demand);
            $demandId = $id;
        } else {
            $demandId = create_demand_list($demand);
        }

        redirect('/demand_show.php?id=' . $demandId);
    }
}

require __DIR__ . '/../app/views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><?= $isEditing ? 'Editar demanda' : 'Nova demanda' ?></h1>
        <p class="text-muted mb-0">
            Projeto: <?= e($project['name']) ?>
        </p>
    </div>

    <a href="<?= e($cancelUrl) ?>" class="btn btn-outline-secondary">
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
    <?php if ($isEditing): ?>
        <input type="hidden" name="id" value="<?= (int) $id ?>">
    <?php endif; ?>

    <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
    <input type="hidden" name="secretariat_id" id="secretariatId" value="<?= (int) old($demand, 'secretariat_id') ?>">

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Nome da demanda</label>
            <input
                type="text"
                name="name"
                class="form-control"
                required
                placeholder="Ex.: Demanda da EMEF"
                value="<?= e(old($demand, 'name')) ?>">
        </div>

        <?php if ($requesterUnits): ?>
            <div class="col-md-6">
                <label class="form-label">Unidade/Setor demandante</label>
                <select name="requester_unit_id" id="requesterUnitSelect" class="form-select" required>
                    <option value="">Selecione...</option>

                    <?php foreach ($requesterUnits as $unit): ?>
                        <?php
                            $unitActive = pg_bool($unit['is_active'] ?? true) === 'true';
                            $secretariatActive = pg_bool($unit['secretariat_is_active'] ?? true) === 'true';
                            $isInactive = !$unitActive || !$secretariatActive;
                        ?>
                        <option
                            value="<?= (int) $unit['id'] ?>"
                            data-secretariat-id="<?= (int) ($unit['secretariat_id'] ?? 0) ?>"
                            data-secretariat-name="<?= e($unit['secretariat_name'] ?? '') ?>"
                            data-responsible-name="<?= e($unit['default_responsible_name'] ?? '') ?>"
                            <?= (int) old($demand, 'requester_unit_id') === (int) $unit['id'] ? 'selected' : '' ?>>
                            <?= e($unit['name']) ?><?= $unit['secretariat_name'] ? ' - ' . e($unit['secretariat_name']) : '' ?><?= $isInactive ? ' (desativada)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Secretaria</label>
                <input type="text" id="secretariatName" class="form-control" readonly>
            </div>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-warning mb-0">
                    Cadastre secretarias e unidades demandantes antes de criar demandas com vínculo.
                    <a href="/requester_units.php" class="alert-link">Abrir cadastro de demandantes</a>.
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label">Unidade/Setor demandante</label>
                <input
                    type="text"
                    name="requester_department"
                    class="form-control"
                    placeholder="Ex.: EMEF"
                    value="<?= e(old($demand, 'requester_department')) ?>">
            </div>
        <?php endif; ?>

        <div class="col-md-6">
            <label class="form-label">Responsável</label>
            <input
                type="text"
                name="responsible_name"
                id="responsibleName"
                class="form-control"
                value="<?= e(old($demand, 'responsible_name')) ?>"
                placeholder="Ex.: Diretor(a), Coordenador(a), Secretário(a)">
        </div>

        <div class="col-12">
            <label class="form-label">Observações</label>
            <textarea name="notes" rows="4" class="form-control"><?= e(old($demand, 'notes')) ?></textarea>
        </div>

        <div class="col-12 d-flex justify-content-end gap-2">
            <a href="<?= e($cancelUrl) ?>" class="btn btn-outline-secondary">
                Cancelar
            </a>

            <button class="btn btn-primary">
                <?= $isEditing ? 'Salvar alterações' : 'Salvar' ?>
            </button>
        </div>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const unitSelect = document.getElementById('requesterUnitSelect');
        const secretariatId = document.getElementById('secretariatId');
        const secretariatName = document.getElementById('secretariatName');
        const responsibleName = document.getElementById('responsibleName');

        if (!unitSelect || !secretariatId || !secretariatName || !responsibleName) {
            return;
        }

        function applyRequesterUnitDefaults() {
            const option = unitSelect.options[unitSelect.selectedIndex];

            if (!option || !option.value) {
                secretariatId.value = '';
                secretariatName.value = '';
                return;
            }

            const previousDefault = responsibleName.dataset.currentDefault || '';
            const nextDefault = option.dataset.responsibleName || '';

            secretariatId.value = option.dataset.secretariatId || '';
            secretariatName.value = option.dataset.secretariatName || '';

            if (!responsibleName.value || responsibleName.value === previousDefault) {
                responsibleName.value = nextDefault;
            }

            responsibleName.dataset.currentDefault = nextDefault;
        }

        unitSelect.addEventListener('change', applyRequesterUnitDefaults);
        applyRequesterUnitDefaults();
    });
</script>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
