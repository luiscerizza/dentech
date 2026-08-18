<?php
$paginaAtual = basename($_SERVER['PHP_SELF']);

function menuAtivo($paginas)
{
    global $paginaAtual;

    if (!is_array($paginas)) {
        $paginas = [$paginas];
    }

    return in_array($paginaAtual, $paginas, true) ? 'active' : '';
}
?>

<aside class="sidebar">

    <!-- LOGO -->
    <div class="sidebar-logo">
        <a href="index.php">
            <img src="img/logo.png" alt="Dentech">
        </a>
    </div>

    <!-- MENU -->
    <nav class="sidebar-menu">

        <!-- DASHBOARD -->
        <a
            href="index.php"
            class="menu-item <?= menuAtivo('index.php') ?>">
            <i class="fa-solid fa-house"></i>
            <span>Dashboard</span>
        </a>

        <!-- PRONTUÁRIOS -->
        <a
            href="prontuarios.php"
            class="menu-item <?= menuAtivo('prontuarios.php') ?>">
            <i class="fa-regular fa-folder-open"></i>
            <span>Prontuários</span>
        </a>

        <!-- AGENDAMENTOS -->
        <a
            href="agendamentos.php"
            class="menu-item <?= menuAtivo('agendamentos.php') ?>">
            <i class="fa-regular fa-calendar"></i>
            <span>Agendamentos</span>
        </a>

        <!-- FINANCEIRO -->
        <a
            href="financeiro.php"
            class="menu-item <?= menuAtivo([
                                    'financeiro.php',
                                    'novo_lancamento.php',
                                    'visualizar_lancamento.php',
                                    'editar_lancamento.php'
                                ]) ?>">
            <i class="fa-solid fa-dollar-sign"></i>
            <span>Financeiro</span>
        </a>

        <!-- ESTOQUE -->
        <a
            href="inventario.php"
            class="menu-item <?= menuAtivo([
                                    'inventario.php'
                                ]) ?>">
            <i class="fa-solid fa-box"></i>
            <span>Estoque</span>
        </a>

        <!-- AJUDA -->
        <div class="menu-item menu-disabled">
            <i class="fa-regular fa-circle-question"></i>
            <span>Ajuda</span>
        </div>

    </nav>

</aside>