<style>
    body {
        margin: 0;
        font-family: Arial, sans-serif;
    }

    .sidebar {
        height: 100vh;
        width: 240px;
        position: fixed;
        background-color: #111;
        padding-top: 20px;
        overflow-y: auto;
    }

    .sidebar h2 {
        color: gold;
        text-align: center;
        margin-bottom: 20px;
    }

    .sidebar a {
        padding: 10px 20px;
        display: block;
        color: white;
        text-decoration: none;
        transition: 0.3s;
    }

    .sidebar a:hover {
        background-color: #333;
    }

    .submenu {
        display: none;
        background-color: #222;
    }

    .submenu a {
        padding-left: 40px;
        font-size: 14px;
    }

    .has-submenu:hover .submenu {
        display: block;
    }

    .icon {
        margin-right: 5px;
    }

    .main-content {
        margin-left: 240px;
        padding: 20px;
        background-color: #222;
        color: #f1f1f1;
        min-height: 100vh;
    }
</style>

<div class="sidebar">
    <h2>Fight Academy</h2>

    <a href="index.php">📊 Panel</a>

    <div class="has-submenu">
        <a href="#">👤 Clientes</a>
        <div class="submenu">
            <a href="agregar_cliente.php">➕ Agregar Cliente</a>
            <a href="ver_clientes.php">📄 Ver Clientes</a>
        </div>
    </div>

    <div class="has-submenu">
        <a href="#">📅 Membresías</a>
        <div class="submenu">
            <a href="agregar_membresia.php">➕ Nueva Membresía</a>
            <a href="ver_membresias.php">📄 Ver Membresías</a>
        </div>
    </div>

    <div class="has-submenu">
        <a href="#">🌐 Registros Online</a>
        <div class="submenu">
            <a href="ver_registros.php">📄 Ver Registros</a>
        </div>
    </div>

    <div class="has-submenu">
        <a href="#">📚 Asistencias</a>
        <div class="submenu">
            <a href="asistencia_clientes.php">👥 Clientes</a>
            <a href="asistencia_profesores.php">👨‍🏫 Profesores</a>
            <a href="scanner_qr.php">📷 QR</a>
        </div>
    </div>

    <div class="has-submenu">
        <a href="#">👨‍🏫 Profesores</a>
        <div class="submenu">
            <a href="agregar_profesor.php">➕ Agregar Profesor</a>
            <a href="listar_profesor.php">📄 Ver Profesores</a>
            <a href="asistencia_profesor.php">🕒 Registrar Asistencia</a>
            <a href="reporte_profesor.php">💰 Reporte de Pagos</a>
        </div>
    </div>

    <div class="has-submenu">
        <a href="#">🛒 Ventas</a>
        <div class="submenu">
            <a href="ventas_indumentaria.php">👕 Indumentaria</a>
            <a href="ventas_protecciones.php">🥊 Protecciones</a>
            <a href="ventas_suplementos.php">💊 Suplementos</a>
        </div>
    </div>

    <div class="has-submenu">
        <a href="#">🏢 Gimnasios</a>
        <div class="submenu">
            <a href="agregar_gimnasio.php">➕ Agregar Gimnasio</a>
            <a href="ver_gimnasios.php">📄 Ver Gimnasios</a>
        </div>
    </div>

    <div class="has-submenu">
        <a href="#">🔐 Usuarios</a>
        <div class="submenu">
            <a href="agregar_usuario.php">➕ Agregar Usuario</a>
            <a href="ver_usuarios.php">📄 Ver Usuarios</a>
        </div>
    </div>
</div>
<div class="has-submenu">
    <a href="#">🔐 Usuarios</a>
    <div class="submenu">
        <a href="agregar_usuario.php">➕ Agregar Usuario</a>
        <a href="ver_usuarios.php">📄 Ver Usuarios</a>
        <a href="permisos_usuarios.php">⚙️ Permisos</a>
    </div>
</div>
