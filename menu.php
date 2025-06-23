<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$rol = $_SESSION['rol'] ?? '';
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<button class="menu-toggle" onclick="toggleMenu()">☰</button>

<style>
/* estilos omitidos para brevedad, iguales a los anteriores */
</style>

<div class="sidebar" id="sidebar">
    <h2><i class="fas fa-dumbbell"></i> Fight Academy</h2>

    <?php if ($rol === 'admin' || $rol === 'cliente_gym') { ?>
    <a href="#" class="submenu-toggle"><i class="fas fa-users"></i> Clientes</a>
    <div class="submenu">
        <a href="agregar_cliente.php">Agregar Cliente</a>
        <a href="ver_clientes.php">Ver Clientes</a>
    </div>
    <?php } ?>

    <?php if ($rol === 'admin' || $rol === 'cliente_gym') { ?>
    <a href="#" class="submenu-toggle"><i class="fas fa-id-card-alt"></i> Membresías</a>
    <div class="submenu">
        <a href="nueva_membresia.php">Nueva Membresía</a>
        <a href="ver_membresias.php">Ver Membresías</a>
        <a href="planes.php">Planes</a>
        <a href="planes_adicionales.php">Planes Adicionales</a>
    </div>
    <?php } ?>

    <?php if ($rol === 'admin' || $rol === 'cliente_gym' || $rol === 'profesor') { ?>
    <a href="#" class="submenu-toggle"><i class="fas fa-calendar-check"></i> Asistencias</a>
    <div class="submenu">
        <a href="registrar_asistencia.php">Registrar Asistencia</a>
        <a href="asistencias_index.php">Ver Asistencias</a>
        <a href="registro_online.php" target="_blank"><i class="fas fa-link"></i> Registro Online</a>
    </div>
    <?php } ?>

    <?php if ($rol === 'admin' || $rol === 'cliente_gym' || $rol === 'profesor') { ?>
    <a href="#" class="submenu-toggle"><i class="fas fa-qrcode"></i> QR</a>
    <div class="submenu">
        <a href="ver_asistencia_qr.php">Ver Asistencias QR</a>
        <a href="ver_asistencias_mes.php">Asistencias del Mes</a>
        <a href="ver_asistencia_qr.php">Ver Asistencia QR del Día</a>
        <a href="generar_qr.php">Generar QR</a>
        <a href="scanner_qr.php">Escanear QR</a>
    </div>
    <?php } ?>

    <?php if ($rol === 'admin' || $rol === 'cliente_gym' || $rol === 'profesor') { ?>
    <a href="#" class="submenu-toggle"><i class="fas fa-chalkboard-teacher"></i> Profesores</a>
    <div class="submenu">
        <a href="agregar_profesor.php">Agregar Profesor</a>
        <a href="listar_profesor.php">Ver Profesores</a>
        <a href="asistencia_profesor.php">Asistencia Profesores</a>
        <a href="reporte_asistencias_profesores.php">Reporte Mensual</a>
    </div>
    <?php } ?>

    <?php if ($rol === 'admin') { ?>
    <a href="#" class="submenu-toggle"><i class="fas fa-dumbbell"></i> Gimnasios</a>
    <div class="submenu">
        <a href="agregar_gimnasio.php">Agregar Gimnasio</a>
        <a href="ver_gimnasios.php">Ver Gimnasios</a>
    </div>

    <a href="#" class="submenu-toggle"><i class="fas fa-user-cog"></i> Usuarios</a>
    <div class="submenu">
        <a href="agregar_usuario.php">Agregar Usuario</a>
        <a href="ver_usuarios.php">Ver Usuarios</a>
    </div>

    <a href="#" class="submenu-toggle"><i class="fas fa-cogs"></i> Configuraciones</a>
    <div class="submenu">
        <a href="configuracion_general.php">General</a>
        <a href="ver_turnos.php"><i class="fas fa-calendar-alt"></i> Turnos y Horarios</a>
        <a href="permisos.php">Permisos</a>
    </div>
    <?php } ?>

    <?php if ($rol === 'admin' || $rol === 'cliente_gym') { ?>
    <a href="#" class="submenu-toggle"><i class="fas fa-shopping-cart"></i> Ventas</a>
    <div class="submenu">
        <a href="ventas_indumentaria.php">Indumentaria</a>
        <a href="ventas_protecciones.php">Protecciones</a>
        <a href="ventas_suplementos.php">Suplementos</a>
        <a href="reporte_ventas.php">Reportes</a>
    </div>
    <?php } ?>

    <?php if ($rol === 'cliente') { ?>
    <a href="#" class="submenu-toggle"><i class="fas fa-id-card"></i> Acceso Clientes</a>
    <div class="submenu">
        <a href="cliente_acceso.php"><i class="fas fa-sign-in-alt"></i> Ingreso por DNI</a>
        <a href="reservar_turno.php"><i class="fas fa-calendar-plus"></i> Reservar Turno</a>
        <a href="ver_turnos_cliente.php?dni=<?= $_SESSION['dni_cliente'] ?? '' ?>">
            <i class="fas fa-calendar-check"></i> Ver Mis Turnos</a>
        <a href="estado_pagos.php?dni=<?= $_SESSION['dni_cliente'] ?? '' ?>">
            <i class="fas fa-dollar-sign"></i> Estado de Pagos</a>
        <a href="mi_qr.php?dni=<?= $_SESSION['dni_cliente'] ?? '' ?>">
            <i class="fas fa-qrcode"></i> Mi Código QR</a>
    </div>
    <?php } ?>
</div>

<script>
function toggleMenu() {
    const sidebar = document.getElementById("sidebar");
    sidebar.classList.toggle("hidden");
}

document.querySelectorAll(".submenu-toggle").forEach(toggle => {
    toggle.addEventListener("click", function(e) {
        e.preventDefault();
        document.querySelectorAll(".submenu-toggle").forEach(el => el.classList.remove("active"));
        document.querySelectorAll(".submenu").forEach(menu => menu.style.display = "none");

        toggle.classList.add("active");
        const submenu = toggle.nextElementSibling;
        if (submenu && submenu.classList.contains("submenu")) {
            submenu.style.display = "block";
        }
    });
});
</script>
