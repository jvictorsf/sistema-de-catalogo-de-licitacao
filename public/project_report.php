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

$consolidatedItems = get_project_consolidated_items($id);
$itemsByDemand = get_project_items_by_demand($id);
$financialSummary = get_project_financial_summary($id);
$secretariatSummary = get_project_secretariat_summary($id);
$signatures = get_project_signature_blocks($id);

require __DIR__ . '/../app/views/header.php';

?>

<div class="d-flex justify-content-between align-items-center mb-4 print-hide">
    <div>
        <h1 class="h3 mb-1">Relatório do Projeto</h1>

        <p class="text-muted mb-0">
            <?= e($project['name']) ?>
        </p>
    </div>

    <div class="d-flex gap-2">
        <a href="/project_export_word.php?id=<?= (int) $project['id'] ?>" class="btn btn-outline-primary">
            Exportar Word
        </a>

        <a href="/project_pdf.php?id=<?= (int) $project['id'] ?>" target="_blank" class="btn btn-outline-danger">
            PDF Institucional
        </a>

        <button onclick="window.print()" class="btn btn-outline-secondary">
            Imprimir
        </button>

        <a href="/project_show.php?id=<?= (int) $project['id'] ?>" class="btn btn-outline-secondary">
            Voltar
        </a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h2 class="h4 mb-2"><?= e($project['name']) ?></h2>

        <p class="mb-0">
            <?= nl2br(e($project['description'])) ?>
        </p>
    </div>
</div>

<ul class="nav nav-tabs print-hide mb-4" id="projectReportTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button
            class="nav-link active"
            id="summary-tab"
            data-bs-toggle="tab"
            data-bs-target="#summary-tab-pane"
            type="button"
            role="tab">
            Resumo
        </button>
    </li>

    <li class="nav-item" role="presentation">
        <button
            class="nav-link"
            id="secretariats-tab"
            data-bs-toggle="tab"
            data-bs-target="#secretariats-tab-pane"
            type="button"
            role="tab">
            Por Secretaria
        </button>
    </li>

    <li class="nav-item" role="presentation">
        <button
            class="nav-link"
            id="consolidated-tab"
            data-bs-toggle="tab"
            data-bs-target="#consolidated-tab-pane"
            type="button"
            role="tab">
            Consolidado
        </button>
    </li>

    <li class="nav-item" role="presentation">
        <button
            class="nav-link"
            id="demands-tab"
            data-bs-toggle="tab"
            data-bs-target="#demands-tab-pane"
            type="button"
            role="tab">
            Por Demanda
        </button>
    </li>

    <li class="nav-item" role="presentation">
        <button
            class="nav-link"
            id="justifications-tab"
            data-bs-toggle="tab"
            data-bs-target="#justifications-tab-pane"
            type="button"
            role="tab">
            Justificativas e Impactos
        </button>
    </li>

    <li class="nav-item" role="presentation">
        <button
            class="nav-link"
            id="signatures-tab"
            data-bs-toggle="tab"
            data-bs-target="#signatures-tab-pane"
            type="button"
            role="tab">
            Assinaturas
        </button>
    </li>
</ul>

<div class="tab-content" id="projectReportTabsContent">

    <div
        class="tab-pane fade show active"
        id="summary-tab-pane"
        role="tabpanel"
        tabindex="0">

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card card-body">
                    <div class="text-muted small">Quantidade solicitada</div>

                    <div class="h4 mb-0">
                        <?= e((string) ($financialSummary['total_requested_quantity'] ?? 0)) ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card card-body">
                    <div class="text-muted small">Quantidade aprovada</div>

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

        <div class="alert alert-info">
            Este relatório consolida os quantitativos das demandas vinculadas ao projeto,
            agrupando itens iguais e apresentando os totais estimados para planejamento da contratação.
        </div>
    </div>

    <div
        class="tab-pane fade"
        id="secretariats-tab-pane"
        role="tabpanel"
        tabindex="0">

        <div class="card">
            <div class="card-header fw-semibold">
                Resumo por Secretaria
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Secretaria</th>
                            <th>Demandas</th>
                            <th>Qtd. solicitada</th>
                            <th>Qtd. aprovada</th>
                            <th>Total estimado</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (!$secretariatSummary): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    Nenhuma demanda vinculada a secretaria.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($secretariatSummary as $row): ?>
                            <tr>
                                <td><?= e($row['secretariat_name']) ?></td>
                                <td><?= e((string) $row['demand_count']) ?></td>
                                <td><?= e((string) ($row['total_requested_quantity'] ?? 0)) ?></td>
                                <td><?= e((string) ($row['total_approved_quantity'] ?? 0)) ?></td>
                                <td class="fw-semibold">
                                    R$ <?= number_format((float) ($row['total_estimated_value'] ?? 0), 2, ',', '.') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div
        class="tab-pane fade"
        id="consolidated-tab-pane"
        role="tabpanel"
        tabindex="0">

        <div class="card">
            <div class="card-header fw-semibold">
                Consolidação Geral dos Itens
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Código</th>
                            <th>Item</th>
                            <th>Un.</th>
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
                                <td><?= e($item['tracking_code']) ?></td>
                                <td><?= e($item['item_name']) ?></td>
                                <td><?= e($item['unit_type_abbreviation'] ?: ($item['unit_type_name'] ?? '-')) ?></td>
                                <td><?= e((string) $item['demand_count']) ?></td>
                                <td><?= e((string) $item['total_quantity']) ?></td>
                                <td class="fw-semibold"><?= e((string) $item['total_approved_quantity']) ?></td>
                                <td>R$ <?= number_format((float) $item['average_unit_price'], 2, ',', '.') ?></td>
                                <td class="fw-semibold">R$ <?= number_format((float) $item['estimated_total'], 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div
        class="tab-pane fade"
        id="demands-tab-pane"
        role="tabpanel"
        tabindex="0">

        <div class="card">
            <div class="card-header fw-semibold">
                Detalhamento por Demanda
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Demanda</th>
                            <th>Secretaria</th>
                            <th>Setor/Unidade</th>
                            <th>Responsável</th>
                            <th>Código</th>
                            <th>Item</th>
                            <th>Un.</th>
                            <th>Qtd. solic.</th>
                            <th>Qtd. aprov.</th>
                            <th>Valor unit.</th>
                            <th>Total</th>
                            <th>Observação</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (!$itemsByDemand): ?>
                            <tr>
                                <td colspan="12" class="text-center text-muted py-4">
                                    Nenhum item por demanda.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($itemsByDemand as $item): ?>
                            <tr>
                                <td><?= e($item['demand_name']) ?></td>
                                <td><?= e($item['secretariat_name'] ?? '-') ?></td>
                                <td><?= e($item['requester_department']) ?></td>
                                <td><?= e($item['responsible_name']) ?></td>
                                <td><?= e($item['tracking_code']) ?></td>
                                <td><?= e($item['item_name']) ?></td>
                                <td><?= e($item['unit_type_abbreviation'] ?: ($item['unit_type_name'] ?? '-')) ?></td>
                                <td><?= e((string) $item['quantity']) ?></td>
                                <td><?= e((string) $item['approved_quantity']) ?></td>
                                <td>R$ <?= number_format((float) ($item['estimated_unit_price'] ?? 0), 2, ',', '.') ?></td>
                                <td>R$ <?= number_format((float) ($item['estimated_total'] ?? 0), 2, ',', '.') ?></td>
                                <td><?= e($item['notes']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div
        class="tab-pane fade"
        id="justifications-tab-pane"
        role="tabpanel"
        tabindex="0">

        <div class="card">
            <div class="card-header fw-semibold">
                Justificativas e Impactos Ambientais
            </div>

            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Código</th>
                            <th>Item</th>
                            <th>Justificativa</th>
                            <th>Impactos ambientais</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (!$consolidatedItems): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    Nenhum item encontrado.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($consolidatedItems as $item): ?>
                            <tr>
                                <td><?= e($item['tracking_code']) ?></td>
                                <td><?= e($item['item_name']) ?></td>
                                <td><?= nl2br(e($item['justification'])) ?></td>
                                <td><?= render_environmental_impacts_list($item['environmental_impacts']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div
        class="tab-pane fade"
        id="signatures-tab-pane"
        role="tabpanel"
        tabindex="0">

        <div class="card">
            <div class="card-header fw-semibold">
                Assinaturas das Unidades Demandantes
            </div>

            <div class="card-body">
                <div class="row g-5">
                    <?php foreach ($signatures as $signature): ?>
                        <div class="col-md-6">
                            <div class="text-center mt-5">
                                <div style="border-top: 1px solid #000; padding-top: 8px;">
                                    <strong>
                                        <?= e($signature['responsible_name'] ?: 'Responsável pela demanda') ?>
                                    </strong>

                                    <div class="text-muted small">
                                        <?= e($signature['secretariat_name'] ?? 'Sem secretaria') ?><br>
                                        <?= e($signature['requester_department'] ?: $signature['name']) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (!$signatures): ?>
                        <div class="col-12 text-center text-muted py-4">
                            Nenhuma demanda cadastrada para assinatura.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
