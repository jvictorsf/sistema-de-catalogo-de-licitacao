<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';

function authorization_coverage_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$permissionLabels = auth_permission_labels();
$policies = auth_route_policies();
$publicFiles = glob(__DIR__ . '/../public/*.php') ?: [];
$unregisteredRoutes = [];
$missingBootstraps = [];

foreach ($publicFiles as $file) {
    $page = basename($file);

    if (!auth_route_is_registered($page)) {
        $unregisteredRoutes[] = $page;
    }

    $source = (string) file_get_contents($file);
    $hasBootstrap = str_contains($source, "../app/config.php")
        || str_contains($source, "../app/repository.php")
        || str_contains($source, "../app/AiSuggestionService.php")
        || str_contains($source, "item_similar_check.php");

    if (!$hasBootstrap) {
        $missingBootstraps[] = $page;
    }
}

authorization_coverage_assert(
    $unregisteredRoutes === [],
    'Toda pagina publica deve possuir politica explicita. Ausentes: ' . implode(', ', $unregisteredRoutes)
);
authorization_coverage_assert(
    $missingBootstraps === [],
    'Toda pagina deve inicializar a autenticacao direta ou indiretamente. Ausentes: ' . implode(', ', $missingBootstraps)
);

foreach ($policies as $page => $policy) {
    foreach ((array) $policy as $permission) {
        authorization_coverage_assert(
            isset($permissionLabels[$permission]),
            'A rota ' . $page . ' referencia uma permissao inexistente: ' . $permission
        );
    }
}

foreach (auth_roles() as $role => $label) {
    foreach (auth_role_permissions($role) as $permission) {
        authorization_coverage_assert(
            isset($permissionLabels[$permission]),
            'O perfil ' . $label . ' referencia uma permissao inexistente: ' . $permission
        );
    }
}

foreach (auth_role_permissions('viewer') as $permission) {
    authorization_coverage_assert(
        !str_ends_with($permission, '.manage'),
        'O perfil Consulta nao pode receber permissao de gestao: ' . $permission
    );
}

foreach (['admin', 'manager', 'operator'] as $role) {
    foreach (['catalog', 'projects', 'budgets'] as $scope) {
        if (auth_role_can($role, $scope . '.manage')) {
            authorization_coverage_assert(
                auth_role_can($role, $scope . '.view'),
                'Permissao de gestao deve incluir consulta para ' . $role . ': ' . $scope
            );
        }
    }
}

$repositorySource = (string) file_get_contents(__DIR__ . '/../app/repository.php');
authorization_coverage_assert(
    str_contains($repositorySource, "require_once __DIR__ . '/config.php';"),
    'O repository deve carregar config.php para proteger endpoints legados.'
);

$authSource = (string) file_get_contents(__DIR__ . '/../app/auth.php');
authorization_coverage_assert(
    str_contains($authSource, "auth_forbid(null, 'unmapped_route')"),
    'Rotas autenticadas nao mapeadas devem ser negadas por padrao.'
);

echo 'AuthorizationCoverageTest: OK (' . count($publicFiles) . " rotas auditadas)\n";
