<link rel="stylesheet" href="css/global.css">
<link rel="stylesheet" href="css/variables.css">
<link rel="stylesheet" href="css/layout.css">

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<aside class="sidebar">

    <div class="sidebar-logo">
        <img src="img/logo.png" alt="Dentech">
    </div>

    <nav class="sidebar-nav">

        <a href="index" class="nav-link">
            <i class="fa-solid fa-house"></i>
            <span>Dashboard</span>
        </a>

        <a href="prontuarios" class="nav-link active">
            <i class="fa-solid fa-user-group"></i>
            <span>Prontuários</span>
        </a>

        <a href="agendamentos" class="nav-link">
            <i class="fa-solid fa-calendar-check"></i>
            <span>Agenda</span>
        </a>

        <a href="inventario" class="nav-link">
            <i class="fa-solid fa-box"></i>
            <span>Estoque</span>
        </a>

        <a href="orcamento" class="nav-link">
            <i class="fa-solid fa-file-invoice-dollar"></i>
            <span>Orçamentos</span>
        </a>

        <a href="restrito" class="nav-link restricted">
            <i class="fa-solid fa-lock"></i>
            <span>Área Restrita</span>
        </a>

    </nav>

    <button
        class="logout"
        type="button"
        onclick="location.href='logout.php'">
        <i class="fa-solid fa-right-from-bracket"></i>
        <span>Sair</span>
    </button>

</aside>