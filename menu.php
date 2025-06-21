<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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
    color: #fff;
    overflow-y: auto;
}
.sidebar h2 {
    text-align: center;
    font-size: 20px;
    padding: 20px;
    margin: 0;
    background-color: #222;
    color: gold;
}
.sidebar a {
    display: block;
    color: #ccc;
    padding: 12px 20px;
    text-decoration: none;
    transition: 0.3s;
}
.sidebar a:hover {
    background-color: #333;
    color: #fff;
}
.sidebar i {
    margin-right: 10px;
    color: gold;
}
.submenu {
    display: none;
    background-color: #1c1c1c;
}
.sidebar a.submenu-toggle:after {
    content: "\f0d7";
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
    float: right;
}
.sidebar .active + .submenu {
    display: block;
}
</style>

<div class="sidebar">
    <h2><i class="fas fa-dumbbell"></i> Fight Academy</h2>

    <a href="#" class="submenu-toggle"><i class="fas fa-users"></i> Clientes</a>
    <div class="submenu">
        <a href="agregar_cliente.php">Agregar Cliente</a>
        <a href="ver_clientes.php">Ver Clientes</a>
    </div>

    <a href="#" class="submenu-toggle"><i class="fas fa-id-card-alt"></i> Membresías</a>
    <div class="submenu">
        <a href="nueva_membresia.php">Nueva Membresía</a>
        <a href="ver_membresias.php">Ver Membresías</a>
        <a href="planes.php">Planes</a>
        <a href="planes_adicionales.php">Planes Adicionales</a>
    </div>

    <a href="#" class="submenu-toggle"><i class="fas fa-calendar-check"></i> Asistencias</a>
    <div class="submenu">
        <a href="registrar_asistencia.php">Registrar Asistencia</a>
        <a href="asistencias_index.php">Ver Asistencias</a>
    </div>
<li class="submenu">
  <a href="#"><i class="fas fa-qrcode"></i> QR <span class="arrow">&#9660;</span></a>
  <ul>
    <li><a href="scanner_qr.php"><i class="fas fa-camera"></i> Escanear QR</a></li>
    <li><a href="formulario_qr.php"><i class="fas fa-qrcode"></i> Generar QR</a></li>
  </ul>

</div>

    <a href="#" class="submenu-toggle"><i class="fas fa-chalkboard-teacher"></i> Profesores</a>
    <div class="submenu">
        <a href="agregar_profesor.php">Agregar Profesor</a>
        <a href="listar_profesor.php">Ver Profesores</a>
        <a href="asistencia_profesor.php">Asistencia Profesores</a>
        <a href="reporte_asistencias_profesores.php">Reporte Mensual</a>
    </div>

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
        <a href="permisos.php">Permisos</a>
    </div>

    <a href="#" class="submenu-toggle"><i class="fas fa-shopping-cart"></i> Ventas</a>
    <div class="submenu">
        <a href="ventas_indumentaria.php">Indumentaria</a>
        <a href="ventas_protecciones.php">Protecciones</a>
        <a href="ventas_suplementos.php">Suplementos</a>
        <a href="ver_ventas.php">Ver Ventas</a>
    </div>

</div>

<script>
    const toggles = document.querySelectorAll('.submenu-toggle');
    toggles.forEach(toggle => {
        toggle.addEventListener('click', () => {
            toggle.classList.toggle('active');
            const submenu = toggle.nextElementSibling;
            submenu.style.display = submenu.style.display === 'block' ? 'none' : 'block';
        });
    });
</script>
