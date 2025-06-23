<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<!-- BOTÓN ☰ PARA CELULARES -->
<button class="menu-toggle" onclick="toggleMenu()">☰</button>

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
    transition: transform 0.3s ease-in-out;
    z-index: 999;
}
.sidebar.hidden {
    transform: translateX(-100%);
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
.menu-toggle {
    display: none;
    position: fixed;
    top: 10px;
    left: 10px;
    z-index: 1000;
    background: #111;
    color: gold;
    border: none;
    padding: 10px;
    font-size: 24px;
    cursor: pointer;
}
@media (max-width: 768px) {
    .menu-toggle {
        display: block;
    }
    body {
        padding-left: 0 !important;
    }
}
</style>

<!-- MENÚ LATERAL -->
<div class="sidebar hidden" id="sidebar">
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
        <a href="registro_online.php" target="_blank"><i class="fas fa-link"></i> Registro Online</a></li>

    </div>

    <a href="#" class="submenu-toggle"><i class="fas fa-qrcode"></i> QR</a>
    <div class="submenu">
        <a href="ver_asistencia_qr.php">Ver Asistencias QR</a></li>
        <a href="ver_asistencias_mes.php">Asistencias del Mes</a></li>
        <a href="ver_asistencia_qr.php">Ver Asistencia QR del Día</a></li>
        <a href="generar_qr.php">Generar QR</a>
        <a href="scanner_qr.php">Escanear QR</a>
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
        <a href="reporte_ventas.php">Reportes</a>
    </div>
</div>

<!-- JS PARA TOGGLE -->
<script>
  // Mostrar u ocultar el menú lateral en celulares
  function toggleMenu() {
    const sidebar = document.getElementById("sidebar");
    sidebar.classList.toggle("hidden");
  }

  // Mostrar y ocultar submenús
  document.querySelectorAll(".submenu-toggle").forEach(toggle => {
    toggle.addEventListener("click", function(e) {
      e.preventDefault();
      // Remover 'active' de otros
      document.querySelectorAll(".submenu-toggle").forEach(el => el.classList.remove("active"));
      // Cerrar todos los submenús
      document.querySelectorAll(".submenu").forEach(menu => menu.style.display = "none");

      // Activar el actual
      toggle.classList.add("active");
      const submenu = toggle.nextElementSibling;
      if (submenu && submenu.classList.contains("submenu")) {
        submenu.style.display = "block";
      }
    });
  });
</script>
