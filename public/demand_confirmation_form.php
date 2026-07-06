<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';
require_once __DIR__ . '/../app/demand_confirmations.php';

$demandId = (int) ($_GET['demand_id'] ?? $_POST['demand_id'] ?? 0);
$demand = find_demand_list($demandId);

if (!$demand) {
    http_response_code(404);
    exit('Demanda nao encontrada.');
}

$project = find_project((int) $demand['project_id']);
$projectLocked = project_is_locked($project);
$collaborators = get_collaborators(true);
$createdRequest = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $createdRequest = create_demand_confirmation_request($demandId, $_POST);
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage() ?: 'Nao foi possivel gerar o link de assinatura.';
    }
}

require __DIR__ . '/../app/views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Solicitar confirmacao da demanda</h1>
        <p class="text-muted mb-0"><?= e($demand['name']) ?> - <?= e($project['name'] ?? '-') ?></p>
    </div>

    <a href="/demand_show.php?id=<?= (int) $demandId ?>" class="btn btn-outline-secondary">Voltar</a>
</div>

<?php if ($projectLocked): ?>
    <div class="alert alert-warning"><?= e(project_locked_edit_message($project)) ?></div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($createdRequest): ?>
    <?php $absoluteUrl = app_url($createdRequest['sign_url']); ?>
    <div class="alert alert-success">
        <div class="fw-semibold mb-2">Link gerado com sucesso.</div>
        <div class="input-group">
            <input type="text" class="form-control" readonly value="<?= e($absoluteUrl) ?>" id="generatedSignatureLink">
            <button class="btn btn-outline-secondary" type="button" id="copyGeneratedLink">
                <i class="bi bi-clipboard"></i>Copiar
            </button>
        </div>
        <div class="small mt-2">Guarde ou envie este link agora. Por seguranca, o token nao sera exibido novamente.</div>
    </div>
<?php endif; ?>

<div class="card card-body shadow-sm">
    <form method="post" class="row g-3">
        <input type="hidden" name="demand_id" value="<?= (int) $demandId ?>">

        <div class="col-12">
            <label class="form-label">Colaborador cadastrado</label>
            <select name="collaborator_id" id="collaboratorSelect" class="form-select" <?= $projectLocked ? 'disabled' : '' ?>>
                <option value="">Preencher manualmente</option>
                <?php foreach ($collaborators as $collaborator): ?>
                    <option
                        value="<?= (int) $collaborator['id'] ?>"
                        data-name="<?= e($collaborator['name']) ?>"
                        data-document="<?= e(format_brazil_document($collaborator['document_number'] ?? '')) ?>"
                        data-role="<?= e($collaborator['role'] ?? '') ?>"
                        data-email="<?= e($collaborator['email'] ?? '') ?>"
                        data-phone="<?= e(format_brazil_phone($collaborator['phone'] ?? '')) ?>">
                        <?= e($collaborator['name']) ?><?= !empty($collaborator['role']) ? ' - ' . e($collaborator['role']) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">Use a base de colaboradores para evitar digitar CPF, cargo e contato toda vez.</div>
        </div>

        <div class="col-md-6">
            <label class="form-label">Nome do responsavel</label>
            <input type="text" name="requester_name" id="requesterName" class="form-control" required value="<?= e($demand['responsible_name'] ?? '') ?>" <?= $projectLocked ? 'disabled' : '' ?>>
        </div>

        <div class="col-md-3">
            <label class="form-label">CPF</label>
            <input type="text" name="requester_document" id="requesterDocument" class="form-control" inputmode="numeric" maxlength="14" <?= $projectLocked ? 'disabled' : '' ?>>
        </div>

        <div class="col-md-3">
            <label class="form-label">Cargo/Função</label>
            <input type="text" name="requester_role" id="requesterRole" class="form-control" <?= $projectLocked ? 'disabled' : '' ?>>
        </div>

        <div class="col-md-6">
            <label class="form-label">E-mail</label>
            <input type="email" name="requester_email" id="requesterEmail" class="form-control" <?= $projectLocked ? 'disabled' : '' ?>>
        </div>

        <div class="col-md-3">
            <label class="form-label">Telefone</label>
            <input type="text" name="requester_phone" id="requesterPhone" class="form-control" maxlength="15" <?= $projectLocked ? 'disabled' : '' ?>>
        </div>

        <div class="col-md-3">
            <label class="form-label">Validade do link</label>
            <input type="date" name="expires_at" class="form-control" value="<?= e((new DateTimeImmutable('+7 days'))->format('Y-m-d')) ?>" <?= $projectLocked ? 'disabled' : '' ?>>
        </div>

        <div class="col-12">
            <label class="form-label">Declaracao</label>
            <textarea name="statement_text" rows="4" class="form-control" <?= $projectLocked ? 'disabled' : '' ?>><?= e(demand_confirmation_default_statement()) ?></textarea>
        </div>

        <div class="col-12 d-flex justify-content-end gap-2">
            <a href="/collaborator_form.php" class="btn btn-outline-secondary">
                <i class="bi bi-person-plus"></i>Novo colaborador
            </a>
            <button class="btn btn-primary" <?= $projectLocked ? 'disabled' : '' ?>>
                <i class="bi bi-link-45deg"></i>Gerar link
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const select = document.getElementById('collaboratorSelect');
    const copyButton = document.getElementById('copyGeneratedLink');
    const linkInput = document.getElementById('generatedSignatureLink');

    if (select) {
        select.addEventListener('change', function() {
            const option = select.selectedOptions[0];
            if (!option || !option.value) return;
            document.getElementById('requesterName').value = option.dataset.name || '';
            document.getElementById('requesterDocument').value = option.dataset.document || '';
            document.getElementById('requesterRole').value = option.dataset.role || '';
            document.getElementById('requesterEmail').value = option.dataset.email || '';
            document.getElementById('requesterPhone').value = option.dataset.phone || '';
        });
    }

    if (copyButton && linkInput) {
        copyButton.addEventListener('click', async function() {
            linkInput.select();
            await navigator.clipboard.writeText(linkInput.value);
            copyButton.innerHTML = '<i class="bi bi-check-lg"></i>Copiado';
        });
    }
});
</script>

<?php require __DIR__ . '/../app/views/footer.php'; ?>