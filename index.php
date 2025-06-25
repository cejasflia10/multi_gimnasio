<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$rol = $_SESSION['rol'] ?? '';
?>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
body { margin: 0; background-color: #111; color: gold; font-family: Arial, sans-serif; }
nav.horizontal {
    display: flex;
    background-color: #1a1a1a;
    justify-content: center;
    flex-wrap: wrap;
    padding: 0;
    border-bottom: 1px solid #333;
}
nav.horizontal .dropdown {
    position: relative;
}
nav.horizontal a, nav.horizontal .dropbtn {
    color: gold;
    padding: 14px 20px;
    text-decoration: none;
    display: block;
    cursor: pointer;
    font-weight: bold;
}
nav.horizontal .dropdown-content {
    display: none;
    position: absolute;
    background-color: #333;
    min-width: 200px;
    z-index: 1000;
    border-radius: 0 0 8px 8px;
    overflow: hidden;
}
nav.horizontal .dropdown-content a {
    padding: 12px;
    color: gold;
    display: block;
    border-bottom: 1px solid #444;
}
nav.horizontal .dropdown:hover .dropdown-content {
    display: block;
}
@media (max-width: 768px) {
    nav.horizontal { display: none; }
}
</style>

<nav class="horizontal">
  <div class="dropdown"><div class="dropbtn">Clientes</div>
    <div class="dropdown-content">
      <a href="agregar_cliente.php">Agregar Cliente</a>
      <a href="ver_clientes.php">Ver Clientes</a>
      <a href="disciplinas.php">Disciplinas</a>
    </div>
  </div>
  <div class="dropdown"><div class="dropbtn">Membresías</div>
    <div class="dropdown-content">
      <a href="agregar_membresia.php">Agregar Membresía</a>
      <a href="ver_membresias.php">Ver Membresías</a>
      <a href="planes.php">Planes</a>
      <a href="planes_adicionales.php">Planes Adicionales</a>
    </div>
  </div>
  <div class="dropdown"><div class="dropbtn">Asistencias</div>
    <div class="dropdown-content">
      <a href="registrar_asistencia.php">Registrar Asistencia</a>
      <a href="ver_asistencias.php">Ver Asistencias</a>
      <a href="registro_online.php">Registro Online</a>
    </div>
  </div>
  <div class="dropdown"><div class="dropbtn">QR</div>
    <div class="dropdown-content">
      <a href="scanner_qr.php">Escanear QR</a>
      <a href="generar_qr.php">Generar QR</a>
    </div>
  </div>
  <div class="dropdown"><div class="dropbtn">Profesores</div>
    <div class="dropdown-content">
      <a href="agregar_profesor.php">Agregar Profesor</a>
      <a href="ver_profesores.php">Ver Profesores</a>
    </div>
  </div>
  <div class="dropdown"><div class="dropbtn">Ventas</div>
    <div class="dropdown-content">
      <a href="ventas_indumentaria.php">Indumentaria</a>
      <a href="ventas_suplementos.php">Suplementos</a>
      <a href="ventas_protecciones.php">Protecciones</a>
    </div>
  </div>
  <div class="dropdown"><div class="dropbtn">Gimnasios</div>
    <div class="dropdown-content">
      <a href="agregar_gimnasio.php">Agregar Gimnasio</a>
      <a href="ver_gimnasios.php">Ver Gimnasios</a>
    </div>
  </div>
  <div class="dropdown"><div class="dropbtn">Usuarios</div>
    <div class="dropdown-content">
      <a href="agregar_usuario.php">Agregar Usuario</a>
      <a href="ver_usuarios.php">Ver Usuarios</a>
      <a href="ver_planes.php">Planes por Gimnasio</a>
    </div>
  </div>
  <div class="dropdown"><div class="dropbtn">Configuraciones</div>
    <div class="dropdown-content">
      <a href="configuracion_general.php">General</a>
      <a href="panel_control.php">Panel General</a>
    </div>
  </div>
  <div class="dropdown"><div class="dropbtn">Panel del Cliente</div>
    <div class="dropdown-content">
      <a href="estado_pagos.php">Estado de Pagos</a>
      <a href="ver_asistencias_cliente.php">Ver Asistencias</a>
      <a href="ver_turnos_cliente.php">Ver Turnos</a>
      <a href="mi_qr.php">Mi QR</a>
    </div>
  </div>
  <div class="dropdown"><a class="dropbtn" href="logout.php">Cerrar Sesión</a></div>
</nav>
