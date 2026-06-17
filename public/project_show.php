<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$id = (int) ($_GET['id'] ?? 0);

$project = find_project($id);

if (!$project) {
    http_response_code(404);
    exit('Projeto não encontrado.');
}

$demands = get_project_demands($id);
$consolidatedItems = get_project_consolidated_items($id);
$quoteRequestGroups = supplier_quote_request_groups_from_items($consolidatedItems);
$financialSummary = get_project_financial_summary($id);
$annexStatuses = get_project_annex_statuses($id);
$quoteSuccess = trim($_GET['quote_success'] ?? '');
$projectError = trim($_GET['project_error'] ?? '');
$projectLocked = project_is_closed($project);
$projectInRectification = project_is_rectification($project);
$closureHash = trim((string) ($project['closure_hash'] ?? ''));
$closureShortHash = $closureHash !== '' ? substr($closureHash, 0, 12) : '';

require __DIR__ . '/../app/views/header.php';

?>

<div class="page-header d-flex justify-content-between align-items-start mb-4">
    <div class="page-title">
        <h1 class="h3 mb-1"><?= e($project['name']) ?></h1>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge <?= e(project_status_badge_class($project['status'] ?? null)) ?>">
                <?= e(project_status_label($project['status'] ?? null)) ?>
            </span>
            <p class="text-muted mb-0">
                <?= e($project['description']) ?>
            </p>
        </div>
    </div>

    <div class="page-actions project-actions d-flex gap-2 flex-wrap justify-content-end">
        <?php if (!$projectLocked): ?>
        <a href="/demand_form.php?project_id=<?= (int) $project['id'] ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i>Nova demanda
        </a>

        <a href="/project_supplier_quote_form.php?project_id=<?= (int) $project['id'] ?>" class="btn btn-outline-success">
            <i class="bi bi-cash-coin"></i>Orçamento geral
        </a>

        <a href="/project_licitation_numbers.php?id=<?= (int) $project['id'] ?>" class="btn btn-outline-dark">
            <i class="bi bi-list-ol"></i>Ordenar itens
        </a>

        <a href="/project_lots.php?id=<?= (int) $project['id'] ?>" class="btn btn-outline-dark">
            <i class="bi bi-boxes"></i>Denominacoes
        </a>
        <?php endif; ?>

        <div class="btn-group">
            <button
                type="button"
                class="btn btn-outline-primary dropdown-toggle"
                data-bs-toggle="dropdown"
                aria-expanded="false">
                <i class="bi bi-files"></i>Relatorios
            </button>

            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a href="/project_report.php?id=<?= (int) $project['id'] ?>" class="dropdown-item">
                        Relatorio gerencial
                    </a>
                </li>
                <li>
                    <a href="/project_export_word.php?id=<?= (int) $project['id'] ?>" class="dropdown-item">
                        Exportar Word
                    </a>
                </li>
                <li>
                    <a href="/project_pdf.php?id=<?= (int) $project['id'] ?>" target="_blank" class="dropdown-item">
                        PDF Institucional
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><h6 class="dropdown-header">Demandas sem precos</h6></li>
                <li>
                    <a href="/project_demand_report.php?id=<?= (int) $project['id'] ?>&group=unit&prices=0&format=pdf" target="_blank" class="dropdown-item">
                        Por unidade
                    </a>
                </li>
                <li>
                    <a href="/project_demand_report.php?id=<?= (int) $project['id'] ?>&group=secretariat&prices=0&format=pdf" target="_blank" class="dropdown-item">
                        Por secretaria
                    </a>
                </li>
                <li><h6 class="dropdown-header">Demandas com precos</h6></li>
                <li>
                    <a href="/project_demand_report.php?id=<?= (int) $project['id'] ?>&group=unit&prices=1&format=pdf" target="_blank" class="dropdown-item">
                        Por unidade
                    </a>
                </li>
                <li>
                    <a href="/project_demand_report.php?id=<?= (int) $project['id'] ?>&group=secretariat&prices=1&format=pdf" target="_blank" class="dropdown-item">
                        Por secretaria
                    </a>
                </li>
            </ul>
        </div>

        <div class="btn-group">
            <button
                type="button"
                class="btn btn-outline-success dropdown-toggle"
                data-bs-toggle="dropdown"
                aria-expanded="false">
                <i class="bi bi-file-earmark-check"></i>Licitação
            </button>

            <ul class="dropdown-menu dropdown-menu-end project-licitation-menu">
                <li><h6 class="dropdown-header">Licitacao por item</h6></li>
                <li><h6 class="dropdown-header">Anexo I - Itens, especificacoes, quantitativos e memoria</h6></li>
                <li>
                    <a href="/project_licitation_annex_i.php?id=<?= (int) $project['id'] ?>&format=pdf" target="_blank" class="dropdown-item">
                        PDF institucional
                    </a>
                </li>
                <li>
                    <a href="/project_licitation_annex_i.php?id=<?= (int) $project['id'] ?>&format=word" class="dropdown-item">
                        Exportar Word
                    </a>
                </li>
                <li>
                    <a href="/project_licitation_annex_i.php?id=<?= (int) $project['id'] ?>&format=excel" class="dropdown-item">
                        Exportar Excel
                    </a>
                </li>

                <li><hr class="dropdown-divider"></li>
                <li><h6 class="dropdown-header">Anexo II - Pesquisa e estimativa de precos</h6></li>
                <li>
                    <a href="/project_licitation_annex_ii.php?id=<?= (int) $project['id'] ?>&format=pdf" target="_blank" class="dropdown-item">
                        PDF institucional
                    </a>
                </li>
                <li>
                    <a href="/project_licitation_annex_ii.php?id=<?= (int) $project['id'] ?>&format=word" class="dropdown-item">
                        Exportar Word
                    </a>
                </li>
                <li>
                    <a href="/project_licitation_annex_ii.php?id=<?= (int) $project['id'] ?>&format=excel" class="dropdown-item">
                        Exportar Excel
                    </a>
                </li>

                <li><hr class="dropdown-divider"></li>
                <li><h6 class="dropdown-header">Anexo III - Quadro resumido da estimativa</h6></li>
                <li>
                    <a href="/project_licitation_annex_iii.php?id=<?= (int) $project['id'] ?>&format=pdf" target="_blank" class="dropdown-item">
                        PDF institucional
                    </a>
                </li>
                <li>
                    <a href="/project_licitation_annex_iii.php?id=<?= (int) $project['id'] ?>&format=word" class="dropdown-item">
                        Exportar Word
                    </a>
                </li>
                <li>
                    <a href="/project_licitation_annex_iii.php?id=<?= (int) $project['id'] ?>&format=excel" class="dropdown-item">
                        Exportar Excel
                    </a>
                </li>

                <li><hr class="dropdown-divider"></li>
                <li><h6 class="dropdown-header">Licitacao por lote</h6></li>
                <li>
                    <a href="/project_lots.php?id=<?= (int) $project['id'] ?>" class="dropdown-item">
                        Gerenciar denominacoes e vinculos
                    </a>
                </li>
                <li><h6 class="dropdown-header">Anexo I - Itens por lote e denominacao</h6></li>
                <li>
                    <a href="/project_lot_annex_i.php?id=<?= (int) $project['id'] ?>&format=pdf" target="_blank" class="dropdown-item">
                        PDF institucional
                    </a>
                </li>
                <li>
                    <a href="/project_lot_annex_i.php?id=<?= (int) $project['id'] ?>&format=word" class="dropdown-item">
                        Exportar Word
                    </a>
                </li>
                <li>
                    <a href="/project_lot_annex_i.php?id=<?= (int) $project['id'] ?>&format=excel" class="dropdown-item">
                        Exportar Excel
                    </a>
                </li>

                <li><h6 class="dropdown-header">Anexo II - Pesquisa e estimativa por lote</h6></li>
                <li>
                    <a href="/project_lot_annex_ii.php?id=<?= (int) $project['id'] ?>&format=pdf" target="_blank" class="dropdown-item">
                        PDF institucional
                    </a>
                </li>
                <li>
                    <a href="/project_lot_annex_ii.php?id=<?= (int) $project['id'] ?>&format=word" class="dropdown-item">
                        Exportar Word
                    </a>
                </li>
                <li>
                    <a href="/project_lot_annex_ii.php?id=<?= (int) $project['id'] ?>&format=excel" class="dropdown-item">
                        Exportar Excel
                    </a>
                </li>

                <li><h6 class="dropdown-header">Anexo III - Quadro resumido por lote</h6></li>
                <li>
                    <a href="/project_lot_annex_iii.php?id=<?= (int) $project['id'] ?>&format=pdf" target="_blank" class="dropdown-item">
                        PDF institucional
                    </a>
                </li>
                <li>
                    <a href="/project_lot_annex_iii.php?id=<?= (int) $project['id'] ?>&format=word" class="dropdown-item">
                        Exportar Word
                    </a>
                </li>
                <li>
                    <a href="/project_lot_annex_iii.php?id=<?= (int) $project['id'] ?>&format=excel" class="dropdown-item">
                        Exportar Excel
                    </a>
                </li>

                <li><h6 class="dropdown-header">Anexo IV - Quadro resumido da estimativa por lote</h6></li>
                <li>
                    <a href="/project_lot_annex_iv.php?id=<?= (int) $project['id'] ?>&format=pdf" target="_blank" class="dropdown-item">
                        PDF institucional
                    </a>
                </li>
                <li>
                    <a href="/project_lot_annex_iv.php?id=<?= (int) $project['id'] ?>&format=word" class="dropdown-item">
                        Exportar Word
                    </a>
                </li>
                <li>
                    <a href="/project_lot_annex_iv.php?id=<?= (int) $project['id'] ?>&format=excel" class="dropdown-item">
                        Exportar Excel
                    </a>
                </li>

                <li><hr class="dropdown-divider"></li>
                <li><h6 class="dropdown-header">Solicitação ao fornecedor</h6></li>
                <li>
                    <a href="/project_quote_request.php?id=<?= (int) $project['id'] ?>" target="_blank" class="dropdown-item">
                        PDF institucional
                    </a>
                </li>
                <li>
                    <a href="/project_quote_request_excel.php?id=<?= (int) $project['id'] ?>" class="dropdown-item">
                        Excel geral
                    </a>
                </li>
                <li>
                    <a href="/project_quote_request_excel_grouped.php?id=<?= (int) $project['id'] ?>" class="dropdown-item">
                        Excel geral por grupo
                    </a>
                </li>

                <?php if ($quoteRequestGroups): ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><h6 class="dropdown-header">Excel por grupo</h6></li>

                    <?php foreach ($quoteRequestGroups as $group): ?>
                        <li>
                            <a
                                href="/project_quote_request_excel.php?id=<?= (int) $project['id'] ?>&group_id=<?= (int) $group['id'] ?>"
                                class="dropdown-item">
                                <?= e($group['name']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>

        <a href="/projects.php" class="btn btn-outline-secondary">
            Voltar
        </a>
    </div>
</div>

<?php if ($quoteSuccess): ?>
    <div class="alert alert-success">
        <?= e($quoteSuccess) ?>
    </div>
<?php endif; ?>

<?php if ($projectError): ?>
    <div class="alert alert-danger">
        <?= e($projectError) ?>
    </div>
<?php endif; ?>

<?php if ($projectLocked): ?>
    <div class="alert alert-warning d-flex gap-3 align-items-start">
        <i class="bi bi-lock-fill fs-5"></i>
        <div>
            <div class="fw-semibold">Projeto fechado para alteracoes</div>
            <div>
                <?= e(project_closed_edit_message()) ?>
                <?php if ($closureShortHash): ?>
                    Hash de fechamento:
                    <a href="/document_hash_validate.php?hash=<?= e($closureHash) ?>" class="alert-link font-monospace">
                        <?= e($closureShortHash) ?>
                    </a>.
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php elseif ($projectInRectification): ?>
    <div class="alert alert-danger d-flex gap-3 align-items-start">
        <i class="bi bi-exclamation-triangle fs-5"></i>
        <div>
            <div class="fw-semibold">Projeto em retificacao</div>
            <div>As alteracoes estao liberadas para correcao. Ao concluir, altere o status para Fechado para gerar novo hash.</div>
        </div>
    </div>
<?php endif; ?>

<?php if ($annexStatuses): ?>
    <div class="card card-body mb-4">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <div class="fw-semibold">Controle dos anexos de licitacao</div>
                <div class="text-muted small">Quando os itens, a ordem ou os precos mudam, gere novamente os anexos desatualizados.</div>
            </div>

            <?php if (!$projectLocked): ?>
                <a href="/project_licitation_numbers.php?id=<?= (int) $project['id'] ?>" class="btn btn-sm btn-outline-dark">
                    <i class="bi bi-list-ol"></i>Ajustar numeracao
                </a>
            <?php endif; ?>
        </div>

        <div class="row g-2 mt-2">
            <?php foreach ($annexStatuses as $status): ?>
                <?php
                    $badgeClass = match ($status['status']) {
                        'valid' => 'text-bg-success',
                        'stale' => 'text-bg-warning',
                        default => 'text-bg-secondary',
                    };
                    $statusText = match ($status['status']) {
                        'valid' => 'Atual',
                        'stale' => 'Regenerar',
                        default => 'Pendente',
                    };
                ?>
                <div class="col-md-4">
                    <div class="border rounded p-2 h-100">
                        <div class="d-flex justify-content-between gap-2">
                            <strong><?= e($status['label']) ?></strong>
                            <span class="badge <?= e($badgeClass) ?>"><?= e($statusText) ?></span>
                        </div>
                        <div class="small text-muted mt-1">
                            Versao: <?= e($status['version_number'] ? 'v' . $status['version_number'] : '-') ?>
                            | Hash: <?= e($status['short_hash']) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card card-body">
            <div class="text-muted small">Qtd. solicitada</div>

            <div class="h4 mb-0">
                <?= e((string) ($financialSummary['total_requested_quantity'] ?? 0)) ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card card-body">
            <div class="text-muted small">Qtd. aprovada</div>

            <div class="h4 mb-0">
                <?= e((string) ($financialSummary['total_approved_quantity'] ?? 0)) ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card card-body">
            <div class="text-muted small">Valor total estimado</div>

            <div class="h4 mb-0">
                R$ <?= number_format((float) ($financialSummary['total_estimated_value'] ?? 0), 2, ',', '.') ?>
            </div>

            <?php if (!empty($financialSummary['uses_supplier_average'])): ?>
                <div class="small text-muted mt-1">Calculado por médias de orçamento</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card h-100 project-demands-card">
            <div class="card-header d-flex justify-content-between align-items-center gap-3">
                <div class="fw-semibold">Demandas por Unidade/Setor</div>

                <?php if ($demands): ?>
                    <div class="project-demand-search input-group input-group-sm">
                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>
                        <input
                            type="search"
                            id="projectDemandSearch"
                            class="form-control"
                            placeholder="Pesquisar demanda">
                    </div>
                <?php endif; ?>
            </div>

            <div class="card-body">
                <?php if (!$demands): ?>
                    <div class="empty-state">
                        Nenhuma demanda cadastrada.
                    </div>
                <?php endif; ?>

                <?php if ($demands): ?>
                    <div class="project-demand-list">
                        <?php foreach ($demands as $demand): ?>
                            <?php
                                $searchText = implode(' ', [
                                    $demand['name'] ?? '',
                                    $demand['secretariat_name'] ?? '',
                                    $demand['requester_department'] ?? '',
                                    $demand['responsible_name'] ?? '',
                                ]);
                            ?>
                            <div class="project-demand-item" data-demand-search="<?= e(mb_strtolower($searchText)) ?>">
                                <div class="project-demand-content">
                                    <div class="project-demand-name"><?= e($demand['name']) ?></div>

                                    <div class="project-demand-secretariat">
                                        <?= e($demand['secretariat_name'] ?? 'Sem secretaria') ?>
                                    </div>

                                    <div class="project-demand-meta">
                                        <span>Setor: <?= e($demand['requester_department'] ?: '-') ?></span>
                                        <span>Responsável: <?= e($demand['responsible_name'] ?: '-') ?></span>
                                    </div>
                                </div>

                                <div class="project-demand-actions">
                                    <a
                                        href="/demand_show.php?id=<?= (int) $demand['id'] ?>"
                                        class="btn btn-sm btn-outline-primary">
                                        Abrir
                                    </a>

                                    <?php if (!$projectLocked): ?>
                                    <a
                                        href="/demand_budget.php?id=<?= (int) $demand['id'] ?>"
                                        class="btn btn-sm btn-outline-success">
                                        Orçamento
                                    </a>

                                    <form
                                        action="/demand_delete.php"
                                        method="post"
                                        onsubmit="return confirm('Deseja excluir esta demanda?')">

                                        <input type="hidden" name="id" value="<?= (int) $demand['id'] ?>">
                                        <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">

                                        <button class="btn btn-sm btn-outline-danger">
                                            Excluir
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div id="projectDemandEmptySearch" class="empty-state d-none">
                        Nenhuma demanda encontrada.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header fw-semibold">
                Consolidação Geral do Projeto
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 project-consolidated-table">
                    <thead class="table-light">
                        <tr>
                            <th>Código</th>
                            <th>Numero licitacao</th>
                            <th>Item</th>
                            <th>Demandas</th>
                            <th>Qtd. solicitada</th>
                            <th>Qtd. aprovada</th>
                            <th>Valor médio unit.</th>
                            <th>Total estimado</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (!$consolidatedItems): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    Nenhum item demandado.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($consolidatedItems as $item): ?>
                            <tr>
                                <td>
                                    <span class="badge text-bg-dark">
                                        <?= e($item['tracking_code']) ?>
                                    </span>
                                </td>

                                <td class="fw-semibold">
                                    <?= e((string) ($item['licitation_number'] ?? '-')) ?>
                                </td>

                                <td>
                                    <?= e($item['item_name']) ?>
                                </td>

                                <td>
                                    <?= e((string) $item['demand_count']) ?>
                                </td>

                                <td>
                                    <?= e((string) $item['total_quantity']) ?>
                                </td>

                                <td class="fw-semibold">
                                    <?= e((string) $item['total_approved_quantity']) ?>
                                </td>

                                <td>
                                    R$ <?= number_format((float) $item['average_unit_price'], 2, ',', '.') ?>
                                </td>

                                <td class="fw-semibold">
                                    R$ <?= number_format((float) $item['estimated_total'], 2, ',', '.') ?>
                                    <?php if (!empty($item['uses_supplier_average'])): ?>
                                        <div class="small text-muted">média de orçamento</div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>

                </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('projectDemandSearch');
        const emptyState = document.getElementById('projectDemandEmptySearch');
        const demandCards = Array.from(document.querySelectorAll('[data-demand-search]'));

        if (!searchInput || !emptyState || demandCards.length === 0) {
            return;
        }

        searchInput.addEventListener('input', function() {
            const query = searchInput.value.trim().toLocaleLowerCase();
            let visibleCount = 0;

            demandCards.forEach(function(card) {
                const visible = query === '' || card.dataset.demandSearch.includes(query);
                card.classList.toggle('d-none', !visible);

                if (visible) {
                    visibleCount++;
                }
            });

            emptyState.classList.toggle('d-none', visibleCount > 0);
        });
    });
</script>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
