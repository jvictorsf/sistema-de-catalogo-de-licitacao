<?php

declare(strict_types=1);

function navigation_layout_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$basePath = dirname(__DIR__);
$header = (string) file_get_contents($basePath . '/app/views/header.php');
$footer = (string) file_get_contents($basePath . '/app/views/footer.php');
$css = (string) file_get_contents($basePath . '/public/assets/app.css');
$javascript = (string) file_get_contents($basePath . '/public/assets/app.js');

navigation_layout_test_assert(str_contains($header, 'id="appSidebar"'), 'Layout deve possuir a sidebar principal.');
navigation_layout_test_assert(str_contains($header, 'offcanvas-lg offcanvas-start'), 'Sidebar deve se transformar em offcanvas no mobile.');
navigation_layout_test_assert(str_contains($header, 'data-bs-target="#appSidebar"'), 'Cabecalho mobile deve abrir a sidebar.');
navigation_layout_test_assert(str_contains($header, '$visiblePrimaryNavItems'), 'Itens principais devem ser filtrados por permissao antes da renderizacao.');
navigation_layout_test_assert(str_contains($header, '$visibleNavGroups'), 'Grupos devem ocultar itens sem permissao.');
navigation_layout_test_assert(str_contains($header, 'aria-current="page"'), 'Rota atual deve ser identificada para tecnologias assistivas.');
navigation_layout_test_assert(str_contains($header, 'app-sidebar-user-actions'), 'Sidebar deve manter perfil e encerramento da sessao.');
navigation_layout_test_assert(!str_contains($header, 'navbar-expand'), 'Menu superior antigo nao deve permanecer no layout.');

navigation_layout_test_assert(str_contains($css, '--app-sidebar-width'), 'Largura da sidebar deve usar uma dimensao estavel.');
navigation_layout_test_assert(str_contains($css, 'margin-left: var(--app-sidebar-width)'), 'Conteudo desktop deve reservar o espaco da sidebar.');
navigation_layout_test_assert(str_contains($css, 'background: #202622 !important'), 'Sidebar desktop deve sobrescrever o fundo transparente do offcanvas responsivo.');
navigation_layout_test_assert(str_contains($css, '@media (max-width: 991.98px)'), 'Layout deve possuir ajuste para tablet e mobile.');
navigation_layout_test_assert(str_contains($css, 'width: min(20rem, 88vw)'), 'Sidebar mobile deve respeitar a largura da tela.');
navigation_layout_test_assert(str_contains($css, '.app-sidebar-link.active'), 'Item atual deve possuir estado visual ativo.');
navigation_layout_test_assert(str_contains($css, ':focus-visible'), 'Navegacao deve possuir foco visivel.');

navigation_layout_test_assert(str_contains($javascript, "window.bootstrap?.Offcanvas"), 'Sidebar mobile deve integrar com o offcanvas do Bootstrap.');
navigation_layout_test_assert(str_contains($javascript, 'sidebarInstance.hide()'), 'Sidebar deve fechar depois da navegacao mobile.');
navigation_layout_test_assert(str_contains($javascript, 'activeSidebarLink.offsetTop'), 'Item ativo deve ser mantido na area visivel da sidebar.');
navigation_layout_test_assert(str_contains($footer, '</main>') && str_contains($footer, '</div>'), 'Footer deve encerrar a estrutura principal do layout.');

echo "NavigationLayoutTest: OK\n";
