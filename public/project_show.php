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
$financialSummary = get_project_financial_summary($id);

require __DIR__ . '/../app/views/header.php';

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><?= e($project['name']) ?></h1>

        <p class="text-muted mb-0">
            <?= e($project['description']) ?>
        </p>
    </div>

    <div class="d-flex gap-2">
        <a href="/demand_form.php?project_id=<?= (int) $project['id'] ?>" class="btn btn-primary">
            Nova demanda
        </a>

        <a href="/project_report.php?id=<?= (int) $project['id'] ?>" class="btn btn-outline-success">
            Relatório
        </a>

        <a href="/project_export_word.php?id=<?= (int) $project['id'] ?>" class="btn btn-outline-primary">
            Exportar Word
        </a>

        <a href="/project_pdf.php?id=<?= (int) $project['id'] ?>" target="_blank" class="btn btn-outline-danger">
            PDF Institucional
        </a>

        <a href="/projects.php" class="btn btn-outline-secondary">
            Voltar
        </a>
    </div>
</div>

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
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header fw-semibold">
                Demandas por Unidade/Setor
            </div>

            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Demanda</th>
                            <th>Secretaria</th>
                            <th>Responsável</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (!$demands): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    Nenhuma demanda cadastrada.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($demands as $demand): ?>
                            <tr>
                                <td>
                                    <strong><?= e($demand['name']) ?></strong>

                                    <div class="small text-muted">
                                        <?= e($demand['requester_department']) ?>
                                    </div>
                                </td>

                                <td>
                                    <?= e($demand['secretariat_name'] ?? '-') ?>
                                </td>

                                <td>
                                    <?= e($demand['responsible_name']) ?>
                                </td>

                                <td class="text-end">
                                    <a
                                        href="/demand_show.php?id=<?= (int) $demand['id'] ?>"
                                        class="btn btn-sm btn-outline-primary">
                                        Abrir
                                    </a>

                                    <a
                                        href="/demand_budget.php?id=<?= (int) $demand['id'] ?>"
                                        class="btn btn-sm btn-outline-success">
                                        Orçamento
                                    </a>

                                    <a href="/project_export_word.php?id=<?= (int) $project['id'] ?>" class="btn btn-outline-primary">
                                        Exportar Word
                                    </a>

                                    <a href="/project_pdf.php?id=<?= (int) $project['id'] ?>" target="_blank" class="btn btn-outline-danger">
                                        PDF Institucional
                                    </a>

                                    <form
                                        action="/demand_delete.php"
                                        method="post"
                                        class="d-inline"
                                        onsubmit="return confirm('Deseja excluir esta demanda?')">

                                        <input type="hidden" name="id" value="<?= (int) $demand['id'] ?>">
                                        <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">

                                        <button class="btn btn-sm btn-outline-danger">
                                            Excluir
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>

                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header fw-semibold">
                Consolidação Geral do Projeto
            </div>

            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Código</th>
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
                                <td colspan="7" class="text-center text-muted py-4">
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
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>

                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
