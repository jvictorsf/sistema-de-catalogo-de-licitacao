<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

function project_quote_request_denominations_quantity(array $group): float
{
    return array_reduce(
        $group['items'] ?? [],
        static fn (float $total, array $item): float => $total + (float) ($item['total_approved_quantity'] ?? $item['total_quantity'] ?? 0),
        0.0
    );
}

function project_quote_request_denominations_label(array $group): string
{
    return 'Lote ' . (int) ($group['lot_number'] ?? 0) . ' - ' . (string) ($group['name'] ?? 'Denominacao');
}

$id = (int) ($_GET['id'] ?? 0);
$project = find_project($id);

if (!$project) {
    http_response_code(404);
    exit('Projeto nao encontrado.');
}

$items = get_project_consolidated_items($id);
$lotGroups = array_values(array_filter(
    get_project_lot_groups($id, $items),
    static fn (array $group): bool => empty($group['is_unassigned']) && (int) ($group['lot_id'] ?? 0) > 0
));

require __DIR__ . '/../app/views/header.php';
?>

<div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
    <div>
        <h1 class="h3 mb-1">Solicitacao ao fornecedor por denominacao</h1>
        <p class="text-muted mb-0">
            Escolha uma denominacao para baixar somente os itens daquele lote, ou gere o arquivo separado por todas as denominacoes.
        </p>
    </div>

    <a href="/project_show.php?id=<?= (int) $project['id'] ?>" class="btn btn-outline-secondary">
        Voltar
    </a>
</div>

<div class="card card-body mb-3">
    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
        <div>
            <div class="fw-semibold"><?= e($project['name']) ?></div>
            <div class="text-muted small">Arquivos consolidados separados por denominacao.</div>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <a href="/project_quote_request.php?id=<?= (int) $project['id'] ?>&group_by=denomination" target="_blank" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-filetype-pdf"></i> PDF separado
            </a>
            <a href="/project_quote_request.php?id=<?= (int) $project['id'] ?>&group_by=denomination&format=word" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-file-word"></i> Word separado
            </a>
            <a href="/project_quote_request_excel_grouped.php?id=<?= (int) $project['id'] ?>&group_by=denomination" class="btn btn-outline-success btn-sm">
                <i class="bi bi-file-earmark-spreadsheet"></i> Excel separado
            </a>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Denominacao</th>
                    <th>Itens</th>
                    <th>Quantidade</th>
                    <th class="text-end">Arquivos filtrados</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$lotGroups): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">
                            Nenhuma denominacao com itens vinculados foi encontrada para este projeto.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($lotGroups as $group): ?>
                    <tr>
                        <td>
                            <strong><?= e(project_quote_request_denominations_label($group)) ?></strong>
                            <?php if (!empty($group['justification'])): ?>
                                <div class="small text-muted"><?= e(mb_strimwidth((string) $group['justification'], 0, 180, '...')) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= e((string) count($group['items'] ?? [])) ?></td>
                        <td><?= e(format_decimal_quantity(project_quote_request_denominations_quantity($group))) ?></td>
                        <td class="text-end">
                            <div class="table-actions justify-content-end">
                                <a href="/project_quote_request.php?id=<?= (int) $project['id'] ?>&lot_id=<?= (int) $group['lot_id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    PDF
                                </a>
                                <a href="/project_quote_request.php?id=<?= (int) $project['id'] ?>&lot_id=<?= (int) $group['lot_id'] ?>&format=word" class="btn btn-sm btn-outline-primary">
                                    Word
                                </a>
                                <a href="/project_quote_request_excel.php?id=<?= (int) $project['id'] ?>&lot_id=<?= (int) $group['lot_id'] ?>" class="btn btn-sm btn-outline-success">
                                    Excel
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
