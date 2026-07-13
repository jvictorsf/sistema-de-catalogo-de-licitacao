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
    exit('Demanda não encontrada.');
}

$project = find_project((int) $demand['project_id']);
$projectLocked = project_is_locked($project);
$collaborators = get_collaborators(true);
$createdFlow = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $createdFlow = create_demand_confirmation_flow($demandId, $_POST);
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage() ?: 'Não foi possível gerar o fluxo de assinatura.';
    }
}

$postedSigners = is_array($_POST['signers'] ?? null) ? array_values($_POST['signers']) : [];
if (!$postedSigners) {
    $postedSigners[] = ['requester_name' => (string) ($demand['responsible_name'] ?? '')];
}

function render_demand_signer_row(array $signer, int|string $index, array $collaborators, bool $locked = false): void
{
    $prefix = 'signers[' . $index . ']';
    ?>
    <div class="signature-signer border rounded p-3" data-signer-row>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <strong data-signer-title>Assinante</strong>
                <div class="small text-muted">Selecione um colaborador ou preencha manualmente.</div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger" data-remove-signer title="Remover assinante" <?= $locked ? 'disabled' : '' ?>>
                <i class="bi bi-trash"></i>
            </button>
        </div>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Colaborador cadastrado</label>
                <select name="<?= e($prefix) ?>[collaborator_id]" class="form-select" data-collaborator <?= $locked ? 'disabled' : '' ?>>
                    <option value="">Preencher manualmente</option>
                    <?php foreach ($collaborators as $collaborator): ?>
                        <option
                            value="<?= (int) $collaborator['id'] ?>"
                            <?= (int) ($signer['collaborator_id'] ?? 0) === (int) $collaborator['id'] ? 'selected' : '' ?>
                            data-name="<?= e($collaborator['name']) ?>"
                            data-document="<?= e(format_brazil_document($collaborator['document_number'] ?? '')) ?>"
                            data-role="<?= e($collaborator['role'] ?? '') ?>"
                            data-email="<?= e($collaborator['email'] ?? '') ?>"
                            data-phone="<?= e(format_brazil_phone($collaborator['phone'] ?? '')) ?>">
                            <?= e($collaborator['name']) ?><?= !empty($collaborator['role']) ? ' - ' . e($collaborator['role']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-6">
                <label class="form-label">Nome completo</label>
                <input type="text" name="<?= e($prefix) ?>[requester_name]" class="form-control" data-field="name" required value="<?= e($signer['requester_name'] ?? '') ?>" <?= $locked ? 'disabled' : '' ?>>
            </div>
            <div class="col-md-4 col-lg-2">
                <label class="form-label">CPF</label>
                <input type="text" name="<?= e($prefix) ?>[requester_document]" class="form-control" data-field="document" inputmode="numeric" maxlength="14" value="<?= e($signer['requester_document'] ?? '') ?>" <?= $locked ? 'disabled' : '' ?>>
            </div>
            <div class="col-md-8 col-lg-4">
                <label class="form-label">Cargo/Função</label>
                <input type="text" name="<?= e($prefix) ?>[requester_role]" class="form-control" data-field="role" value="<?= e($signer['requester_role'] ?? '') ?>" <?= $locked ? 'disabled' : '' ?>>
            </div>
            <div class="col-md-6">
                <label class="form-label">E-mail</label>
                <input type="email" name="<?= e($prefix) ?>[requester_email]" class="form-control" data-field="email" value="<?= e($signer['requester_email'] ?? '') ?>" <?= $locked ? 'disabled' : '' ?>>
            </div>
            <div class="col-md-6">
                <label class="form-label">Telefone</label>
                <input type="text" name="<?= e($prefix) ?>[requester_phone]" class="form-control" data-field="phone" maxlength="15" value="<?= e($signer['requester_phone'] ?? '') ?>" <?= $locked ? 'disabled' : '' ?>>
            </div>
        </div>
    </div>
    <?php
}

require __DIR__ . '/../app/views/header.php';
?>

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Novo fluxo de assinaturas</h1>
        <p class="text-muted mb-0"><?= e($demand['name']) ?> · <?= e($project['name'] ?? '-') ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="/signature_pending.php" class="btn btn-outline-primary"><i class="bi bi-list-check"></i>Pendências</a>
        <a href="/demand_show.php?id=<?= (int) $demandId ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i>Voltar</a>
    </div>
</div>

<?php if ($projectLocked): ?>
    <div class="alert alert-warning"><?= e(project_locked_edit_message($project)) ?></div>
<?php endif; ?>

<?php if (!demand_signature_flows_table_exists()): ?>
    <div class="alert alert-danger">A estrutura de fluxos ainda não existe neste banco. Rode o <code>database/schema.sql</code> atualizado antes de criar solicitações.</div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if ($createdFlow): ?>
    <section class="border rounded bg-body p-3 mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
            <div>
                <h2 class="h5 mb-1">Links gerados</h2>
                <div class="text-muted small"><?= e($createdFlow['title']) ?> · Fluxo <?= e(mb_strtolower(demand_signature_flow_mode_label($createdFlow['mode']))) ?></div>
            </div>
            <span class="badge text-bg-success align-self-start"><?= count($createdFlow['requests']) ?> assinante(s)</span>
        </div>
        <div class="vstack gap-3">
            <?php foreach ($createdFlow['requests'] as $createdRequest): ?>
                <?php $absoluteUrl = app_url($createdRequest['sign_url']); ?>
                <div>
                    <div class="d-flex justify-content-between small mb-1">
                        <strong><?= (int) $createdRequest['signer_order'] ?>. <?= e($createdRequest['requester_name']) ?></strong>
                        <span class="badge <?= e(demand_confirmation_status_badge_class($createdRequest['status'])) ?>"><?= e(demand_confirmation_status_label($createdRequest['status'])) ?></span>
                    </div>
                    <div class="input-group">
                        <input type="text" class="form-control" readonly value="<?= e($absoluteUrl) ?>" data-generated-link>
                        <button class="btn btn-outline-secondary" type="button" data-copy-link title="Copiar link"><i class="bi bi-clipboard"></i><span class="d-none d-sm-inline">Copiar</span></button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="small text-muted mt-3">Envie cada link ao respectivo assinante. Em fluxo sequencial, os links posteriores ficam aguardando a conclusão da etapa anterior.</div>
    </section>
<?php endif; ?>

<form method="post" class="vstack gap-4">
    <input type="hidden" name="demand_id" value="<?= (int) $demandId ?>">

    <section class="border rounded bg-body p-3 p-lg-4">
        <h2 class="h5 mb-3">Configuração do fluxo</h2>
        <div class="row g-3">
            <div class="col-lg-7">
                <label class="form-label">Título</label>
                <input type="text" name="title" class="form-control" value="<?= e($_POST['title'] ?? ('Confirmação da demanda ' . $demand['name'])) ?>" <?= $projectLocked ? 'disabled' : '' ?>>
            </div>
            <div class="col-sm-5 col-lg-2">
                <label class="form-label">Validade</label>
                <input type="date" name="expires_at" class="form-control" value="<?= e($_POST['expires_at'] ?? (new DateTimeImmutable('+7 days'))->format('Y-m-d')) ?>" <?= $projectLocked ? 'disabled' : '' ?>>
            </div>
            <div class="col-sm-7 col-lg-3">
                <label class="form-label d-block">Ordem das assinaturas</label>
                <div class="btn-group w-100" role="group">
                    <?php $selectedMode = demand_signature_flow_mode($_POST['mode'] ?? 'parallel'); ?>
                    <input type="radio" class="btn-check" name="mode" id="modeParallel" value="parallel" <?= $selectedMode === 'parallel' ? 'checked' : '' ?> <?= $projectLocked ? 'disabled' : '' ?>>
                    <label class="btn btn-outline-primary" for="modeParallel"><i class="bi bi-people"></i>Paralelo</label>
                    <input type="radio" class="btn-check" name="mode" id="modeSequential" value="sequential" <?= $selectedMode === 'sequential' ? 'checked' : '' ?> <?= $projectLocked ? 'disabled' : '' ?>>
                    <label class="btn btn-outline-primary" for="modeSequential"><i class="bi bi-list-ol"></i>Sequencial</label>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label">Declaração comum a todos os assinantes</label>
                <textarea name="statement_text" rows="4" class="form-control" <?= $projectLocked ? 'disabled' : '' ?>><?= e($_POST['statement_text'] ?? demand_confirmation_default_statement()) ?></textarea>
            </div>
        </div>
    </section>

    <section class="border rounded bg-body p-3 p-lg-4">
        <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
            <div>
                <h2 class="h5 mb-1">Assinantes</h2>
                <p class="small text-muted mb-0">A ordem abaixo define as etapas do fluxo sequencial.</p>
            </div>
            <button type="button" class="btn btn-outline-primary" id="addSigner" <?= $projectLocked ? 'disabled' : '' ?>><i class="bi bi-person-plus"></i>Adicionar</button>
        </div>
        <div class="vstack gap-3" id="signersList">
            <?php foreach ($postedSigners as $index => $signer): ?>
                <?php render_demand_signer_row(is_array($signer) ? $signer : [], $index, $collaborators, $projectLocked); ?>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="d-flex flex-column-reverse flex-sm-row justify-content-between gap-2">
        <a href="/collaborator_form.php" class="btn btn-outline-secondary"><i class="bi bi-person-plus"></i>Novo colaborador</a>
        <button class="btn btn-primary" <?= ($projectLocked || !demand_signature_flows_table_exists()) ? 'disabled' : '' ?>><i class="bi bi-link-45deg"></i>Gerar fluxo e links</button>
    </div>
</form>

<template id="signerTemplate"><?php render_demand_signer_row([], '__INDEX__', $collaborators, false); ?></template>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const list = document.getElementById('signersList');
    const template = document.getElementById('signerTemplate');
    const addButton = document.getElementById('addSigner');
    let nextIndex = <?= count($postedSigners) ?>;

    function refreshRows() {
        const rows = [...list.querySelectorAll('[data-signer-row]')];
        rows.forEach((row, index) => {
            row.querySelector('[data-signer-title]').textContent = (index + 1) + '. Assinante';
            const remove = row.querySelector('[data-remove-signer]');
            if (remove) remove.disabled = rows.length === 1;
        });
    }

    function bindRow(row) {
        row.querySelector('[data-collaborator]')?.addEventListener('change', function() {
            const option = this.selectedOptions[0];
            if (!option?.value) return;
            ['name', 'document', 'role', 'email', 'phone'].forEach(function(field) {
                const input = row.querySelector('[data-field="' + field + '"]');
                if (input) input.value = option.dataset[field] || '';
            });
        });
        row.querySelector('[data-remove-signer]')?.addEventListener('click', function() {
            row.remove();
            refreshRows();
        });
    }

    list.querySelectorAll('[data-signer-row]').forEach(bindRow);
    refreshRows();

    addButton?.addEventListener('click', function() {
        if (list.querySelectorAll('[data-signer-row]').length >= 20) return;
        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(nextIndex++));
        const row = wrapper.firstElementChild;
        list.appendChild(row);
        bindRow(row);
        refreshRows();
        row.querySelector('[data-field="name"]')?.focus();
    });

    document.querySelectorAll('[data-copy-link]').forEach(function(button) {
        button.addEventListener('click', async function() {
            const input = button.closest('.input-group').querySelector('[data-generated-link]');
            input.select();
            await navigator.clipboard.writeText(input.value);
            button.innerHTML = '<i class="bi bi-check-lg"></i><span class="d-none d-sm-inline">Copiado</span>';
        });
    });
});
</script>

<?php require __DIR__ . '/../app/views/footer.php'; ?>