<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$id = (int) ($_GET['id'] ?? 0);
$project = find_project($id);

if (!$project) {
    http_response_code(404);
    exit('Projeto nao encontrado.');
}

$projectLocked = project_is_locked($project);
$months = max(0, (int) ($_GET['months'] ?? 0));
$candidates = get_project_global_price_bank_candidates($id, $months);

require __DIR__ . '/../app/views/header.php';
?>

<div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
    <div>
        <h1 class="h3 mb-1">Banco de precos de orcamentos gerais</h1>
        <p class="text-muted mb-0">
            Projeto: <?= e($project['name']) ?>
        </p>
    </div>

    <div class="d-flex gap-2 flex-wrap justify-content-end">
        <?php if (!$projectLocked): ?>
            <a href="/project_supplier_quote_form.php?project_id=<?= (int) $project['id'] ?>" class="btn btn-outline-success">
                <i class="bi bi-cash-coin"></i>Orcamento geral
            </a>
        <?php endif; ?>
        <a href="/project_show.php?id=<?= (int) $project['id'] ?>" class="btn btn-outline-secondary">
            Voltar
        </a>
    </div>
</div>

<?php if ($projectLocked): ?>
    <div class="alert alert-warning">
        <?= e(project_locked_edit_message($project)) ?>
    </div>
<?php endif; ?>

<div class="card card-body mb-4">
    <form method="get" class="row g-3 align-items-end">
        <input type="hidden" name="id" value="<?= (int) $id ?>">

        <div class="col-md-4">
            <label class="form-label">Periodo historico</label>
            <select name="months" class="form-select">
                <option value="0" <?= $months === 0 ? 'selected' : '' ?>>Todos os periodos</option>
                <option value="3" <?= $months === 3 ? 'selected' : '' ?>>Ultimos 3 meses</option>
                <option value="6" <?= $months === 6 ? 'selected' : '' ?>>Ultimos 6 meses</option>
                <option value="12" <?= $months === 12 ? 'selected' : '' ?>>Ultimos 12 meses</option>
                <option value="24" <?= $months === 24 ? 'selected' : '' ?>>Ultimos 24 meses</option>
            </select>
        </div>

        <div class="col-md-3">
            <button class="btn btn-outline-primary w-100">
                <i class="bi bi-funnel"></i>Filtrar
            </button>
        </div>

        <div class="col-md-5">
            <div class="alert alert-info mb-0 py-2">
                Escolha um orcamento historico compativel para preencher o orcamento geral deste projeto.
            </div>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Origem</th>
                    <th>Fornecedor</th>
                    <th>Orcamento</th>
                    <th>Compatibilidade</th>
                    <th>Total aplicavel</th>
                    <th>Anexos</th>
                    <th class="text-end">Acao</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$candidates): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            Nenhum orcamento geral historico compativel foi encontrado.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($candidates as $candidate): ?>
                    <?php
                        $useUrl = '/project_supplier_quote_form.php?' . http_build_query([
                            'project_id' => (int) $project['id'],
                            'global_price_key' => (string) $candidate['key'],
                            'months' => $months,
                        ]);
                    ?>
                    <tr>
                        <td>
                            <strong><?= e($candidate['source_project_name']) ?></strong>
                            <div class="small text-muted">Projeto #<?= (int) $candidate['source_project_id'] ?></div>
                        </td>
                        <td>
                            <?= e($candidate['supplier_name']) ?>
                            <?php if (!empty($candidate['supplier_document'])): ?>
                                <div class="small text-muted"><?= e(format_brazil_document($candidate['supplier_document'])) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= e($candidate['quote_number'] ?: '-') ?>
                            <?php if (!empty($candidate['quote_date'])): ?>
                                <div class="small text-muted">Data: <?= date('d/m/Y', strtotime((string) $candidate['quote_date'])) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($candidate['validity_date'])): ?>
                                <div class="small text-muted">Validade: <?= date('d/m/Y', strtotime((string) $candidate['validity_date'])) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge text-bg-light border">
                                <?= (int) $candidate['matched_item_count'] ?>/<?= (int) $candidate['target_item_count'] ?> itens
                            </span>
                        </td>
                        <td class="fw-semibold">
                            R$ <?= number_format((float) $candidate['estimated_total'], 2, ',', '.') ?>
                        </td>
                        <td>
                            <?php if (!empty($candidate['attachment_paths'])): ?>
                                <?= count($candidate['attachment_paths']) ?> anexo(s)
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($projectLocked): ?>
                                <span class="text-muted small">Somente consulta</span>
                            <?php else: ?>
                                <a href="<?= e($useUrl) ?>" class="btn btn-sm btn-primary">
                                    Usar no orcamento geral
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
