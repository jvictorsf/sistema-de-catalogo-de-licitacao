<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repository.php';

$stmt = db()->query("
    SELECT
        a.id AS item_a_id,
        a.tracking_code AS item_a_code,
        a.name AS item_a_name,
        b.id AS item_b_id,
        b.tracking_code AS item_b_code,
        b.name AS item_b_name,
        similarity(a.name, b.name) AS similarity_score
    FROM procurement_items a
    INNER JOIN procurement_items b ON a.id < b.id
    WHERE similarity(a.name, b.name) >= 0.45
    ORDER BY similarity_score DESC, a.name
    LIMIT 100
");

$pairs = $stmt->fetchAll();

require __DIR__ . '/../app/views/header.php';

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Possíveis Itens Duplicados</h1>
        <p class="text-muted mb-0">
            Itens com nomes parecidos que podem precisar de revisão.
        </p>
    </div>

    <a href="/" class="btn btn-outline-secondary">
        Voltar ao catálogo
    </a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Item A</th>
                    <th>Item B</th>
                    <th>Similaridade</th>
                </tr>
            </thead>

            <tbody>
                <?php if (!$pairs): ?>
                    <tr>
                        <td colspan="3" class="text-center text-muted py-4">
                            Nenhuma possível duplicidade encontrada.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($pairs as $pair): ?>
                    <tr>
                        <td>
                            <a href="/item_show.php?id=<?= (int) $pair['item_a_id'] ?>" target="_blank">
                                <?= e($pair['item_a_code'] . ' - ' . $pair['item_a_name']) ?>
                            </a>
                        </td>

                        <td>
                            <a href="/item_show.php?id=<?= (int) $pair['item_b_id'] ?>" target="_blank">
                                <?= e($pair['item_b_code'] . ' - ' . $pair['item_b_name']) ?>
                            </a>
                        </td>

                        <td>
                            <span class="badge text-bg-warning">
                                <?= number_format((float) $pair['similarity_score'] * 100, 0) ?>%
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>

        </table>
    </div>
</div>

<?php require __DIR__ . '/../app/views/footer.php'; ?>