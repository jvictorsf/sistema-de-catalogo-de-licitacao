<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$project = $id ? find_project($id) : null;

$errors = [];
$projectLocked = $project ? project_is_locked($project) : false;
$projectInRectification = $project ? project_is_rectification($project) : false;
$projectReopened = $project ? project_is_reopened($project) : false;

if ($id && !$project) {
    http_response_code(404);
    exit('Projeto nao encontrado.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'status' => trim($_POST['status'] ?? 'draft'),
        'cancellation_reason' => trim($_POST['cancellation_reason'] ?? ''),
        'reopen_reason' => trim($_POST['reopen_reason'] ?? ''),
        'reopen_mode' => trim($_POST['reopen_mode'] ?? 'continuity'),
        'reopen_correction_deadline' => trim($_POST['reopen_correction_deadline'] ?? ''),
    ];

    if (!$data['name']) {
        $errors[] = 'O nome do projeto e obrigatorio.';
    }

    if (!$errors) {
        try {
            if ($project) {
                update_project((int) $project['id'], $data);
                redirect('/project_show.php?id=' . (int) $project['id']);
            }

            $newId = create_project($data);
            redirect('/project_show.php?id=' . $newId);
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
    }

    $project = array_merge($project ?? [], $data);
    $projectLocked = $project ? project_is_locked($project) : false;
    $projectInRectification = $project ? project_is_rectification($project) : false;
    $projectReopened = $project ? project_is_reopened($project) : false;
}

$selectedStatus = (string) ($project['status'] ?? 'draft');
$selectedReopenMode = (string) ($project['reopen_mode'] ?? 'continuity');

require __DIR__ . '/../app/views/header.php';

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">
            <?= $project ? 'Editar projeto' : 'Novo projeto de contratacao' ?>
        </h1>
        <p class="text-muted mb-0">
            Exemplo: Licitacao de Informatica 2026.
        </p>
    </div>

    <a href="/projects.php" class="btn btn-outline-secondary">
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

<?php if ($projectLocked): ?>
    <div class="alert alert-warning">
        <?= e(project_locked_edit_message($project)) ?>
    </div>
<?php elseif ($projectInRectification): ?>
    <div class="alert alert-danger">
        Projeto em retificacao. Depois de concluir as correcoes, altere o status para Fechado para gerar novo hash.
    </div>
<?php elseif ($projectReopened): ?>
    <div class="alert alert-primary">
        Projeto reaberto. Ao concluir a continuidade ou correcao, altere o status para Fechado para gerar novo hash.
    </div>
<?php endif; ?>

<form method="post" class="card card-body shadow-sm" id="projectForm">
    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label">Nome do projeto</label>
            <input
                type="text"
                name="name"
                class="form-control"
                required
                value="<?= e($project['name'] ?? '') ?>"
                <?= $projectLocked ? 'readonly' : '' ?>>
        </div>

        <div class="col-md-4">
            <label class="form-label">Status</label>
            <select name="status" class="form-select" id="projectStatusSelect">
                <?php foreach (project_status_options_for_form($project) as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $selectedStatus === $value ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12">
            <label class="form-label">Descricao</label>
            <textarea
                name="description"
                rows="4"
                class="form-control"
                <?= $projectLocked ? 'readonly' : '' ?>><?= e($project['description'] ?? '') ?></textarea>
        </div>

        <div class="col-12 <?= $selectedStatus === 'canceled' ? '' : 'd-none' ?>" data-status-panel="canceled">
            <label class="form-label">Justificativa do cancelamento</label>
            <textarea
                name="cancellation_reason"
                rows="3"
                class="form-control"
                data-required-when="canceled"><?= e($project['cancellation_reason'] ?? '') ?></textarea>
        </div>

        <div class="col-12 <?= $selectedStatus === 'reopened' ? '' : 'd-none' ?>" data-status-panel="reopened">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Tipo de reabertura</label>
                    <select name="reopen_mode" class="form-select" id="projectReopenMode">
                        <?php foreach (project_reopen_mode_options() as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $selectedReopenMode === $value ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4 <?= $selectedReopenMode === 'correction' ? '' : 'd-none' ?>" data-reopen-deadline-panel>
                    <label class="form-label">Prazo para correcao</label>
                    <input
                        type="date"
                        name="reopen_correction_deadline"
                        class="form-control"
                        data-required-when-reopen-mode="correction"
                        value="<?= e($project['reopen_correction_deadline'] ?? '') ?>">
                </div>

                <div class="col-12">
                    <label class="form-label">Justificativa da reabertura</label>
                    <textarea
                        name="reopen_reason"
                        rows="3"
                        class="form-control"
                        data-required-when="reopened"><?= e($project['reopen_reason'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div class="col-12 d-flex justify-content-end gap-2">
            <a href="/projects.php" class="btn btn-outline-secondary">Cancelar</a>
            <button class="btn btn-primary">Salvar</button>
        </div>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const statusSelect = document.getElementById('projectStatusSelect');
        const reopenMode = document.getElementById('projectReopenMode');
        const panels = Array.from(document.querySelectorAll('[data-status-panel]'));
        const statusRequired = Array.from(document.querySelectorAll('[data-required-when]'));
        const deadlinePanel = document.querySelector('[data-reopen-deadline-panel]');
        const deadlineInput = document.querySelector('[data-required-when-reopen-mode]');

        function syncStatusPanels() {
            const status = statusSelect ? statusSelect.value : '';

            panels.forEach(function(panel) {
                panel.classList.toggle('d-none', panel.dataset.statusPanel !== status);
            });

            statusRequired.forEach(function(input) {
                input.required = input.dataset.requiredWhen === status;
            });

            syncReopenMode();
        }

        function syncReopenMode() {
            const status = statusSelect ? statusSelect.value : '';
            const mode = reopenMode ? reopenMode.value : '';
            const deadlineRequired = status === 'reopened' && mode === 'correction';

            if (deadlinePanel) {
                deadlinePanel.classList.toggle('d-none', !deadlineRequired);
            }

            if (deadlineInput) {
                deadlineInput.required = deadlineRequired;
            }
        }

        if (statusSelect) {
            statusSelect.addEventListener('change', syncStatusPanels);
        }

        if (reopenMode) {
            reopenMode.addEventListener('change', syncReopenMode);
        }

        syncStatusPanels();
    });
</script>

<?php require __DIR__ . '/../app/views/footer.php'; ?>