<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/repository.php';

function project_bi_test_assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function project_bi_test_assert_close(float $expected, ?float $actual, string $message): void
{
    if ($actual === null || abs($expected - $actual) > 0.0001) {
        throw new RuntimeException($message . ' Esperado: ' . $expected . ' Obtido: ' . var_export($actual, true));
    }
}

$stats = project_bi_price_statistics([100, 110, 120, 500]);
project_bi_test_assert_close(207.5, $stats['average'], 'Media do BI deve considerar todas as fontes.');
project_bi_test_assert_close(115.0, $stats['median'], 'Mediana do BI incorreta.');
project_bi_test_assert_true(project_bi_is_outlier(500, $stats), 'Valor muito discrepante deve ser marcado como outlier.');
project_bi_test_assert_true(!project_bi_is_outlier(110, $stats), 'Valor central nao deve ser marcado como outlier.');

$stable = project_bi_price_statistics([100, 101, 102]);
project_bi_test_assert_true(!project_bi_is_outlier(102, $stable), 'Valores proximos nao devem gerar outlier.');
project_bi_test_assert_true(($stable['coefficient_variation'] ?? 1) < 0.25, 'Coeficiente de variacao baixo deve indicar estabilidade.');

echo "ProjectBiTest: OK\n";