<style>
    /* Ocultar en celulares */
    @media screen and (max-width: 768px) {
        .menu-horizontal {
            display: none;
        }
    }

    /* Estilo visible en PC */
    .menu-horizontal {
        background-color: #000;
        color: gold;
        padding: 10px;
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        font-family: Arial, sans-serif;
        justify-content: center;
    }

    .menu-horizontal a {
        color: gold;
        text-decoration: none;
        padding: 5px 10px;
        display: flex;
        align-items: center;
        gap: 5px;
        font-weight: bold;
    }

    .menu-horizontal a:hover {
        background-color: #222;
        border-radius: 5px;
    }
</style>

<div class="menu-horizontal">
    <a href="ver_clientes.php"><i class="fas fa-users"></i>Clientes</a>
    <a href="ver_membresias.php"><i class="fas fa-id-card-alt"></i>Membresías</a>
    <a href="registrar_asistencia.php"><i class="fas fa-clipboard-check"></i>Asistencias</a>
    <a href="ver_profesores.php"><i class="fas fa-chalkboard-teacher"></i>Profesores</a>
    <a href="ver_ventas.php"><i class="fas fa-shopping-cart"></i>Ventas</a>
    <a href="panel_cliente.php"><i class="fas fa-user-circle"></i>Panel Cliente</a>
    <a href="ver_gimnasios.php"><i class="fas fa-dumbbell"></i>Gimnasios</a>
    <a href="ver_usuarios.php"><i class="fas fa-user-shield"></i>Usuarios</a>
    <a href="configuraciones.php"><i class="fas fa-cogs"></i>Configuraciones</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i>Cerrar sesión</a>
</div>

<!-- Recuerda tener cargado FontAwesome -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
