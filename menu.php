<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<style>
body {
    margin: 0;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #111;
    color: #f1f1f1;
}
.sidebar {
    width: 250px;
    background-color: #000;
    position: fixed;
    height: 100%;
    overflow-y: auto;
    box-shadow: 2px 0 10px rgba(0,0,0,0.5);
    padding-top: 20px;
}
.sidebar h2 {
    color: gold;
    text-align: center;
    margin-bottom: 20px;
    font-weight: bold;
}
.sidebar a {
    display: block;
    padding: 12px 20px;
    text-decoration: none;
    color: gold;
    transition: background 0.3s;
}
.sidebar a:hover {
    background-color: #222;
}
.submenu {
    display: none;
    background-color: #111;
    margin-left: 10px;
}
.menu-button {
    background-color: #000;
    border: none;
    color: gold;
    width: 100%;
    text-align: left;
    padding: 12px 20px;
    font-size: 16px;
    cursor: pointer;
}
.menu-button:hover {
    background-color: #222;
}
.main {
    margin-left: 250px;
    padding: 20px;
}
</style>

<div class="sidebar">
  <h2>Fight Academy</h2>

  <button class="menu-button" onclick="toggleSubmenu('clientes')">Clientes</button>
  <div class="submenu" id="clientes">
    <a href="agregar_cliente.php">Agregar Cliente</a>
    <a href="ver_clientes.php">Ver Clientes</a>
    <a href="importar_clientes.php">Importar</a>
    <a href="exportar_clientes.php">Exportar</a>
  </div>

  <button class="menu-button" onclick="toggleSubmenu('membresias')">Membresías</button>
  <div class="submenu" id="membresias">
    <a href="nueva_membresia.php">Nueva Membresía</a>
    <a href="ver_membresias.php">Ver Membresías</a>
    <a href="planes.php">Planes</a>
    <a href="adicionales.php">Planes Adicionales</a>
  </div>

  <button class="menu-button" onclick="toggleSubmenu('asistencias')">Asistencias</button>
  <div class="submenu" id="asistencias">
    <a href="registrar_asistencia.php">Registrar</a>
    <a href="visual_ingresos_salidas.php">Ver Ingresos</a>
  </div>

  <button class="menu-button" onclick="toggleSubmenu('ventas')">Ventas</button>
  <div class="submenu" id="ventas">
    <a href="ventas_indumentaria.php">Indumentaria</a>
    <a href="ventas_proteccion.php">Protecciones</a>
    <a href="ventas_suplementos.php">Suplementos</a>
  </div>

  <button class="menu-button" onclick="toggleSubmenu('config')">Configuración</button>
  <div class="submenu" id="config">
    <a href="usuarios.php">Usuarios</a>
    <a href="gimnasios.php">Gimnasios</a>
  </div>
</div>

<script>
function toggleSubmenu(id) {
    const submenu = document.getElementById(id);
    submenu.style.display = submenu.style.display === 'block' ? 'none' : 'block';
}
</script>
