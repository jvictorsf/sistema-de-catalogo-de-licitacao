<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$hashInput = trim((string) ($_GET['hash'] ?? $_POST['hash'] ?? ''));
$normalizedHash = normalize_document_hash_input($hashInput);
$records = $normalizedHash !== '' ? find_document_hash_records($hashInput) : [];
$searched = $hashInput !== '';

require __DIR__ . '/../app/views/header.php';

?>

<div class="page-header d-flex justify-content-between align-items-start mb-4">
    <div class="page-title">
        <h1 class="h3 mb-1">Validacao de documentos</h1>
        <p class="text-muted mb-0">
            Consulte a autenticidade de anexos e fechamentos pelo hash gerado pelo sistema.
        </p>
    </div>
</div>

<form method="get" class="card card-body shadow-sm mb-4">
    <div class="row g-3 align-items-end">
        <div class="col-lg-9">
            <label class="form-label">Hash do documento</label>
            <input
                type="text"
                name="hash"
                class="form-control font-monospace"
                placeholder="Cole o hash completo ou os primeiros caracteres"
                value="<?= e($hashInput) ?>"
                autofocus>
            <div class="form-text">
                O sistema aceita o hash SHA-256 completo ou o prefixo exibido no documento.
            </div>
        </div>

        <div class="col-lg-3">
            <button class="btn btn-primary w-100">
                <i class="bi bi-shield-check"></i>Validar
            </button>
        </div>
    </div>
</form>

<?php if ($searched && $normalizedHash === ''): ?>
    <div class="alert alert-warning">
        Informe ao menos parte de um hash hexadecimal valido.
    </div>
<?php endif; ?>

<?php if ($searched && $normalizedHash !== '' && !$records): ?>
    <div class="alert alert-danger">
        Nenhum documento foi encontrado para o hash informado.
    </div>
<?php endif; ?>

<?php if ($records): ?>
    <div class="row g-3">
        <?php foreach ($records as $record): ?>
            <?php
                $recordType = (string) ($record['record_type'] ?? '');
                $status = (string) ($record['status'] ?? '');
                $badgeClass = match ($status) {
                    'valid' => 'text-bg-success',
                    'invalid' => 'text-bg-danger',
                    'rectification' => 'text-bg-warning',
                    default => 'text-bg-secondary',
                };
                $statusLabel = match ($status) {
                    'valid' => 'Valido',
                    'invalid' => 'Invalidado',
                    'rectification' => 'Projeto em retificacao',
                    default => project_status_label($status),
                };
            ?>

            <div class="col-lg-6">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                            <div>
                                <div class="text-muted small">
                                    <?= match ($recordType) { 'project_closure' => 'Hash atual do projeto', 'project_status_event' => 'Evento de status do projeto', default => 'Anexo de licitacao' } ?>
                                </div>
                                <h2 class="h5 mb-0"><?= e($record['annex_label'] ?? '-') ?></h2>
                            </div>

                            <span class="badge <?= e($badgeClass) ?>">
                                <?= e($statusLabel) ?>
                            </span>
                        </div>

                        <dl class="row mb-0">
                            <dt class="col-sm-4">Projeto</dt>
                            <dd class="col-sm-8"><?= e($record['project_name'] ?? '-') ?></dd>

                            <dt class="col-sm-4">Status do projeto</dt>
                            <dd class="col-sm-8">
                                <span class="badge <?= e(project_status_badge_class($record['project_status'] ?? null)) ?>">
                                    <?= e(project_status_label($record['project_status'] ?? null)) ?>
                                </span>
                            </dd>

                            <?php if ($recordType === 'project_status_event'): ?>
                                <dt class="col-sm-4">Transicao</dt>
                                <dd class="col-sm-8">
                                    <?= e(project_status_label($record['from_status'] ?? null) ?: '-') ?>
                                    <i class="bi bi-arrow-right-short"></i>
                                    <?= e(project_status_label($record['to_status'] ?? null)) ?>
                                </dd>

                                <?php if (!empty($record['reason'])): ?>
                                    <dt class="col-sm-4">Justificativa</dt>
                                    <dd class="col-sm-8"><?= e($record['reason']) ?></dd>
                                <?php endif; ?>

                                <?php if (!empty($record['correction_deadline'])): ?>
                                    <dt class="col-sm-4">Prazo</dt>
                                    <dd class="col-sm-8"><?= date('d/m/Y', strtotime((string) $record['correction_deadline'])) ?></dd>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($recordType === 'annex'): ?>
                                <dt class="col-sm-4">Versao</dt>
                                <dd class="col-sm-8">
                                    <?= !empty($record['version_number']) ? 'v' . e((string) $record['version_number']) : '-' ?>
                                </dd>

                                <dt class="col-sm-4">Itens</dt>
                                <dd class="col-sm-8"><?= e((string) ($record['item_count'] ?? 0)) ?></dd>

                                <?php if (($record['total_value'] ?? null) !== null): ?>
                                    <dt class="col-sm-4">Valor total</dt>
                                    <dd class="col-sm-8">
                                        R$ <?= number_format((float) $record['total_value'], 2, ',', '.') ?>
                                    </dd>
                                <?php endif; ?>
                            <?php endif; ?>

                            <dt class="col-sm-4">Gerado em</dt>
                            <dd class="col-sm-8">
                                <?= !empty($record['generated_at']) ? date('d/m/Y H:i', strtotime((string) $record['generated_at'])) : '-' ?>
                            </dd>

                            <?php if (!empty($record['invalidated_at'])): ?>
                                <dt class="col-sm-4">Invalidado em</dt>
                                <dd class="col-sm-8">
                                    <?= date('d/m/Y H:i', strtotime((string) $record['invalidated_at'])) ?>
                                </dd>
                            <?php endif; ?>

                            <dt class="col-sm-4">Hash</dt>
                            <dd class="col-sm-8">
                                <code class="small text-break"><?= e($record['content_hash'] ?? '-') ?></code>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
