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
    <nav class="sidebar-nav">

        <!-- DASHBOARD -->
        <a
            href="index.php"
            class="nav-link <?= menuAtivo('index.php') ?>">
            <i class="fa-solid fa-house"></i>
            <span>Dashboard</span>
        </a>

        <!-- PRONTUÁRIOS -->
        <a
            href="prontuarios.php"
            class="nav-link <?= menuAtivo([
                                'prontuarios.php',
                                'editar_prontuario.php',
                                'visualizar_prontuario.php'
                            ]) ?>">
            <i class="fa-regular fa-folder-open"></i>
            <span>Prontuários</span>
        </a>

        <!-- AGENDAMENTOS -->
        <a
            href="agendamentos.php"
            class="nav-link <?= menuAtivo([
                                'agendamentos.php'
                            ]) ?>">
            <i class="fa-regular fa-calendar"></i>
            <span>Agendamentos</span>
        </a>

        <!-- FINANCEIRO -->
        <a
            href="financeiro.php"
            class="nav-link <?= menuAtivo([
                                'financeiro.php',
                                'novo_lancamento.php',
                                'editar_lancamento.php',
                                'visualizar_lancamento.php'
                            ]) ?>">
            <i class="fa-solid fa-dollar-sign"></i>
            <span>Financeiro</span>
        </a>

        <!-- ESTOQUE -->
        <a
            href="inventario.php"
            class="nav-link <?= menuAtivo([
                                'inventario.php'
                            ]) ?>">
            <i class="fa-solid fa-box"></i>
            <span>Estoque</span>
        </a>

        <!-- AJUDA -->
        <div class="nav-link">
            <i class="fa-regular fa-circle-question"></i>
            <span>Ajuda</span>
        </div>

    </nav>

</aside>