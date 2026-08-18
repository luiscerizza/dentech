<?php
/*
|--------------------------------------------------------------------------
| NAVBAR / SIDEBAR
|--------------------------------------------------------------------------
| Identifica automaticamente a página atual para marcar o menu correto.
|--------------------------------------------------------------------------
*/

$paginaAtual = basename($_SERVER['PHP_SELF'], '.php');

/*
 * Caso o sistema utilize URLs amigáveis/rewrite, também tratamos
 * os nomes sem extensão.
 */
$paginaAtual = trim($paginaAtual, '/');


function menuAtivo(string $pagina, string $paginaAtual): string
{
    return $pagina === $paginaAtual ? 'active' : '';
}
?>

<link rel="stylesheet" href="css/global.css">
<link rel="stylesheet" href="css/variables.css">
<link rel="stylesheet" href="css/layout.css">

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<aside class="sidebar">

    <!-- LOGO -->
    <div class="sidebar-logo">
        <img src="img/logo.png" alt="Dentech">
    </div>

    <!-- MENU -->
    <nav class="sidebar-nav">

        <!-- DASHBOARD -->
        <a
            href="index"
            class="nav-link <?= menuAtivo('index', $paginaAtual) ?>">
            <i class="fa-solid fa-house"></i>
            <span>Dashboard</span>
        </a>


        <!-- PRONTUÁRIOS -->
        <a
            href="prontuarios"
            class="nav-link <?= menuAtivo('prontuarios', $paginaAtual) ?>">
            <i class="fa-solid fa-user-group"></i>
            <span>Prontuários</span>
        </a>


        <!-- AGENDAMENTOS -->
        <a
            href="agendamentos"
            class="nav-link <?= menuAtivo('agendamentos', $paginaAtual) ?>">
            <i class="fa-solid fa-calendar-check"></i>
            <span>Agenda</span>
        </a>


        <!-- ESTOQUE -->
        <a
            href="inventario"
            class="nav-link <?= menuAtivo('inventario', $paginaAtual) ?>">
            <i class="fa-solid fa-box"></i>
            <span>Estoque</span>
        </a>


        <!-- ORÇAMENTOS -->
        <a
            href="orcamento"
            class="nav-link <?= menuAtivo('orcamento', $paginaAtual) ?>">
            <i class="fa-solid fa-file-invoice-dollar"></i>
            <span>Orçamentos</span>
        </a>


        <!-- ÁREA RESTRITA -->
        <a
            href="restrito"
            class="nav-link restricted <?= menuAtivo('restrito', $paginaAtual) ?>">
            <i class="fa-solid fa-lock"></i>
            <span>Área Restrita</span>
        </a>

    </nav>


    <!-- SAIR -->
    <button
        class="logout"
        type="button"
        onclick="location.href='logout.php'">
        <i class="fa-solid fa-right-from-bracket"></i>
        <span>Sair</span>
    </button>

</aside>