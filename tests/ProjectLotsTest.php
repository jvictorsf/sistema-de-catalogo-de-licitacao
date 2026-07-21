<?php

declare(strict_types=1);

function project_lots_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$basePath = dirname(__DIR__);
$repository = (string) file_get_contents($basePath . '/app/repository.php');
$page = (string) file_get_contents($basePath . '/public/project_lots.php');

project_lots_test_assert(
    str_contains($repository, 'function renumber_project_lots_by_insertion'),
    'Repositorio deve disponibilizar a renumeracao automatica dos lotes.'
);
project_lots_test_assert(
    str_contains($repository, 'ORDER BY created_at, id'),
    'Lotes devem ser sequenciados pela ordem de insercao.'
);
project_lots_test_assert(
    str_contains($repository, 'SET lot_number = lot_number + :offset'),
    'Renumeracao deve evitar colisao com a restricao unica dos numeros.'
);
project_lots_test_assert(
    str_contains($repository, 'FOR UPDATE'),
    'Lotes envolvidos devem ser bloqueados durante a renumeracao.'
);
project_lots_test_assert(
    str_contains($repository, 'invalidate_project_annex_versions($projectId)'),
    'Alteracao da sequencia deve invalidar os anexos do projeto.'
);
project_lots_test_assert(
    str_contains($page, 'value="renumber_lots"') && str_contains($page, 'Sequenciar lotes'),
    'Tela de lotes deve oferecer a acao de sequenciamento.'
);
project_lots_test_assert(
    str_contains($page, 'if (!$projectLocked)'),
    'Acao de sequenciamento deve respeitar o bloqueio do projeto.'
);

echo "ProjectLotsTest: OK\n";
