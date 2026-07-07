<?php

declare(strict_types=1);

$testDir = __DIR__;
$tests = array_values(array_filter(glob($testDir . '/*Test.php') ?: [], static fn (string $file): bool => is_file($file)));
sort($tests);

$failed = 0;

foreach ($tests as $test) {
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open([PHP_BINARY, $test], $descriptorSpec, $pipes, sys_get_temp_dir());

    if (!is_resource($process)) {
        fwrite(STDERR, "Nao foi possivel executar {$test}.\n");
        $failed++;
        continue;
    }

    fclose($pipes[0]);
    echo stream_get_contents($pipes[1]);
    $errorOutput = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    if ($errorOutput !== '') {
        fwrite(STDERR, $errorOutput);
    }

    if ($exitCode !== 0) {
        $failed++;
    }
}

if ($failed > 0) {
    fwrite(STDERR, "{$failed} teste(s) falharam.\n");
    exit(1);
}

echo "Todos os testes passaram.\n";