<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$rol = $_SESSION['rol'] ?? '';
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
body {
    margin: 0;
}
.sidebar {
    height: 100vh;
    width: 260px;
    position: fixed;
    background-color: #111;
    color: gold;
    overflow-y: auto;
    transition: transform 0.3s ease-in-out;
    z-index: 999;
}
.sidebar-header {
    background-color: #222;
    padding: 15px;
    text-align: center;
}
.sidebar-header img {
    height: 45px;
    vertical-align: middle;
}
.sidebar-header span {
    display: block;
    margin-top: 5px;
    font-weight: bold;
    color: gold;
    font-size: 16px;
}
.sidebar a, .submenu-toggle {
    display: block;
    color: #ccc;
    padding: 12px 20px;
    text-decoration: none;
    cursor: pointer;
}
.sidebar a:hover, .submenu-toggle:hover {
    background-color: #333;
}
.submenu {
    display: none;
    background-color: #000;
}
.submenu a {
    padding-left: 40px;
}
.menu-toggle {
    display: none;
    position: fixed;
    top: 10px;
    left: 10px;
    z-index: 1000;
    background-color: #111;
    color: gold;
    border: none;
    font-size: 24px;
}
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
    }
    .sidebar.visible {
        transform: translateX(0);
    }
    .menu-toggle {
        display: block;
    }
}
</style>
<?php if ($rol === 'admin' || $rol === 'escuela'): ?>
    <a class="submenu-toggle" onclick="toggleSubmenu('submenu-pagos')">💰 Pagos</a>
    <div id="submenu-pagos" class="submenu">
        <a href="ver_pagos.php">Ver Pagos</a>
        <a href="agregar_pago.php">Agregar Pago</a>
    </div>
<?php endif; ?>

<!-- BOTÓN ☰ PARA CELULARES -->
<button class="menu-toggle" onclick="toggleMenu()">☰</button>

<div class="sidebar" id="sidebar">
    <!-- Encabezado con logo y nombre -->
    <div class="sidebar-header">
        <img src="assets/logo_gym_cjs.png" alt="Gym System CJS">
        <span>Gym System CJS</span>
    </div>

    <?php if (in_array($rol, ['admin', 'cliente_gym'])): ?>
    <div class="submenu-toggle" onclick="toggleSubmenu('clientesSubmenu')"><i class="fas fa-users"></i> Clientes</div>
    <div class="submenu" id="clientesSubmenu">
        <a href="agregar_cliente.php">Agregar Cliente</a>
        <a href="ver_clientes.php">Ver Clientes</a>
        <a href="disciplinas.php">Disciplinas</a>
    </div>

    <div class="submenu-toggle" onclick="toggleSubmenu('membresiasSubmenu')"><i class="fas fa-id-card"></i> Membresías</div>
    <div class="submenu" id="membresiasSubmenu">
        <a href="agregar_membresia.php">Agregar Membresía</a>
        <a href="ver_membresias.php">Ver Membresías</a>
        <a href="planes.php">Planes</a>
        <a href="planes_adicionales.php">Planes Adicionales</a>
    </div>
    <?php endif; ?>

    <?php if (in_array($rol, ['admin', 'cliente_gym', 'profesor'])): ?>
    <div class="submenu-toggle" onclick="toggleSubmenu('asistenciasSubmenu')"><i class="fas fa-check-circle"></i> Asistencias</div>
    <div class="submenu" id="asistenciasSubmenu">
        <a href="registrar_asistencia.php">Registrar Asistencia</a>
        <a href="ver_asistencia.php">Ver Asistencias</a>
        <a href="registro_online.php" target="_blank"><i class="fas fa-link"></i> Registro Online</a>
    </div>

    <div class="submenu-toggle" onclick="toggleSubmenu('qrSubmenu')"><i class="fas fa-qrcode"></i> QR</div>
    <div class="submenu" id="qrSubmenu">
        <a href="scanner_qr.php">Escanear QR</a>
        <a href="generar_qr.php">Generar QR</a>
    </div>
    <?php endif; ?>

    <?php if (in_array($rol, ['admin', 'cliente_gym'])): ?>
    <div class="submenu-toggle" onclick="toggleSubmenu('profesoresSubmenu')"><i class="fas fa-chalkboard-teacher"></i> Profesores</div>
    <div class="submenu" id="profesoresSubmenu">
        <a href="agregar_profesor.php">Agregar Profesor</a>
        <a href="ver_profesores.php">Ver Profesores</a>
    </div>

    <div class="submenu-toggle" onclick="toggleSubmenu('ventasSubmenu')"><i class="fas fa-shopping-cart"></i> Ventas</div>
    <div class="submenu" id="ventasSubmenu">
        <a href="ventas_indumentaria.php">Indumentaria</a>
        <a href="ventas_suplementos.php">Suplementos</a>
        <a href="ventas_protecciones.php">Protecciones</a>
    </div>

    <div class="submenu-toggle" onclick="toggleSubmenu('accesoClientesSubmenu')"><i class="fas fa-id-badge"></i> Acceso Clientes</div>
    <div class="submenu" id="accesoClientesSubmenu">
        <a href="cliente_acceso.php"><i class="fas fa-user-check"></i> Ingreso por DNI</a>
        <a href="reservar_turno.php"><i class="fas fa-calendar-plus"></i> Reservar Turno</a>
        <a href="ver_turnos_cliente.php"><i class="fas fa-calendar-alt"></i> Ver Mis Turnos</a>
        <a href="estado_pagos.php"><i class="fas fa-dollar-sign"></i> Estado de Pagos</a>
        <a href="mi_qr.php"><i class="fas fa-qrcode"></i> Mi Código QR</a>
    </div>
    <?php endif; ?>

    <?php if ($rol === 'cliente'): ?>
    <div class="submenu-toggle" onclick="toggleSubmenu('seguimientoClienteSubmenu')"><i class="fas fa-apple-alt"></i> Nutrición</div>
    <div class="submenu" id="seguimientoClienteSubmenu">
        <a href="ficha_habitos.php"><i class="fas fa-edit"></i> Ficha de Hábitos</a>
        <a href="ver_habitos_profesor.php?id=<?= $_SESSION['cliente_id'] ?? 0 ?>"><i class="fas fa-eye"></i> Ver Ficha</a>
        <a href="ver_seguimiento_cliente.php"><i class="fas fa-list"></i> Seguimiento Semanal</a>
    </div>
    <?php endif; ?>

    <?php if ($rol === 'admin'): ?>
    <div class="submenu-toggle" onclick="toggleSubmenu('gimnasiosSubmenu')"><i class="fas fa-dumbbell"></i> Gimnasios</div>
    <div class="submenu" id="gimnasiosSubmenu">
        <a href="agregar_gimnasio.php">Agregar Gimnasio</a>
        <a href="ver_gimnasios.php">Ver Gimnasios</a>
    </div>

    <div class="submenu-toggle" onclick="toggleSubmenu('usuariosSubmenu')"><i class="fas fa-user-cog"></i> Usuarios</div>
    <div class="submenu" id="usuariosSubmenu">
        <a href="agregar_usuario.php">Agregar Usuario</a>
        <a href="ver_planes.php">Planes por gimnasio</a>
        <a href="ver_usuarios.php">Ver Usuarios</a>
    </div>

    <div class="submenu-toggle" onclick="toggleSubmenu('configuracionesSubmenu')"><i class="fas fa-cogs"></i> Configuraciones</div>
    <div class="submenu" id="configuracionesSubmenu">
        <a href="configuracion_general.php">General</a>
        <a href="panel_control.php">Panel General</a>
    </div>
    <?php endif; ?>

    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
</div>

<script>
function toggleMenu() {
    document.getElementById('sidebar').classList.toggle('visible');
}
function toggleSubmenu(id) {
    const submenu = document.getElementById(id);
    submenu.style.display = submenu.style.display === 'block' ? 'none' : 'block';
}
</script>
