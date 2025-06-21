<style>
    body {
        margin: 0;
        font-family: Arial, sans-serif;
    }

    .sidebar {
        width: 260px;
        background-color: #000;
        color: #ffcc00;
        position: fixed;
        height: 100%;
        overflow-y: auto;
    }

    .sidebar h2 {
        text-align: center;
        padding: 20px 0;
        font-size: 20px;
        background-color: #111;
        margin: 0;
    }

    .sidebar a {
        display: block;
        color: #f1f1f1;
        padding: 12px 20px;
        text-decoration: none;
    }

    .sidebar a:hover {
        background-color: #333;
        color: #ffcc00;
    }

    .submenu {
        display: none;
        background-color: #111;
        padding-left: 20px;
    }

    .menu-item {
        cursor: pointer;
        font-weight: bold;
        padding: 12px 20px;
        border-top: 1px solid #222;
    }

    .menu-item:hover {
        background-color: #222;
    }

    .active + .submenu {
        display: block;
    }

    .content {
        margin-left: 260px;
        padding: 20px;
    }
</style>

<div class="sidebar">
    <h2>🏛️ Fight Academy</h2>

    <div class="menu-item" onclick="toggleSubmenu(this)">👤 Clientes</div>
    <div class="submenu">
        <a href="agregar_cliente.php">Agregar Cliente</a>
        <a href="ver_clientes.php">Ver Clientes</a>
        <a href="disciplinas.php">Disciplinas</a>
    </div>

    <div class="menu-item" onclick="toggleSubmenu(this)">💳 Membresías</div>
    <div class="submenu">
        <a href="agregar_membresia.php">Nueva Membresía</a>
        <a href="ver_membresias.php">Ver Membresías</a>
        <a href="planes.php">Planes</a>
        <a href="planes_adicionales.php">Planes Adicionales</a>
    </div>

    <div class="menu-item" onclick="toggleSubmenu(this)">📝 Registros Online</div>
    <div class="submenu">
        <a href="registrar_cliente_online.php">Formulario Online</a>
    </div>

    <div class="menu-item" onclick="toggleSubmenu(this)">📆 Asistencias</div>
    <div class="submenu">
        <a href="registrar_asistencia.php">Registrar Asistencia</a>
        <a href="visual_ingresos_salidas.php">Ver Ingresos/Egresos</a>
    </div>

    <div class="menu-item" onclick="toggleSubmenu(this)">🔍 QR</div>
    <div class="submenu">
        <a href="formulario_qr.php">Generar QR</a>
        <a href="scanner_qr.php">Escanear QR</a>
    </div>

    <div class="menu-item" onclick="toggleSubmenu(this)">🏋️ Profesores</div>
    <div class="submenu">
        <a href="agregar_profesor.php">Agregar Profesor</a>
        <a href="listar_profesor.php">Ver Profesores</a>
        <a href="asistencia_profesor.php">Asistencia Profesor</a>
    </div>

    <div class="menu-item" onclick="toggleSubmenu(this)">🛒 Ventas</div>
    <div class="submenu">
        <a href="ventas.php">Ventas</a>
        <a href="protecciones.php">Protecciones</a>
        <a href="indumentaria.php">Indumentaria</a>
        <a href="suplementos.php">Suplementos</a>
    </div>

    <div class="menu-item" onclick="toggleSubmenu(this)">🏫 Gimnasios</div>
    <div class="submenu">
        <a href="agregar_gimnasio.php">Agregar Gimnasio</a>
        <a href="ver_gimnasios.php">Ver Gimnasios</a>
    </div>

    <div class="menu-item" onclick="toggleSubmenu(this)">👥 Usuarios</div>
    <div class="submenu">
        <a href="agregar_usuario.php">Agregar Usuario</a>
        <a href="ver_usuarios.php">Ver Usuarios</a>
    </div>

    <div class="menu-item" onclick="toggleSubmenu(this)">⚙️ Configuraciones</div>
    <div class="submenu">
        <a href="configuracion.php">Configuración General</a>
        <a href="permisos.php">Permisos</a>
    </div>
</div>

<script>
    function toggleSubmenu(element) {
        element.classList.toggle("active");
        const submenu = element.nextElementSibling;
        submenu.style.display = submenu.style.display === "block" ? "none" : "block";
    }
</script>
