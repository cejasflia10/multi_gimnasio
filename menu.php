
<!-- menu.php -->
<style>
  body {
    margin: 0;
    font-family: 'Segoe UI', sans-serif;
    background-color: #111;
    color: #f1f1f1;
  }
  .sidebar {
    width: 250px;
    height: 100vh;
    background-color: #000;
    position: fixed;
    top: 0;
    left: 0;
    padding-top: 20px;
    overflow-y: auto;
    box-shadow: 2px 0 5px rgba(255, 215, 0, 0.1);
  }

  .sidebar h2 {
    color: #ffc107;
    text-align: center;
    margin-bottom: 20px;
    font-size: 18px;
  }

  .sidebar ul {
    list-style-type: none;
    padding: 0;
    margin: 0;
  }

  .sidebar ul li {
    padding: 8px 15px;
    border-radius: 5px;
    transition: background-color 0.2s;
    cursor: pointer;
  }

  .sidebar ul li:hover {
    background-color: #333;
  }

  .sidebar ul li a {
    color: #f1f1f1;
    text-decoration: none;
    display: block;
  }

  .section-title {
    color: #ffc107;
    font-weight: bold;
    font-size: 15px;
    margin: 15px 15px 5px;
  }

  .submenu {
    display: none;
    padding-left: 15px;
  }

  .active-submenu {
    display: block;
  }

  .main-content {
    margin-left: 260px;
    padding: 20px;
  }

  .toggle::after {
    content: " ▸";
    float: right;
  }

  .toggle.active::after {
    content: " ▾";
  }
</style>

<div class="sidebar">
  <h2>🏋️‍♂️ Fight Academy</h2>

  <div class="section-title toggle" onclick="toggleMenu('clientes')">Clientes</div>
  <ul id="clientes" class="submenu">
    
</ul>
<!-- ... dentro del menú ya existente ... -->
<li><a href="exportar_datos.php">📤 Exportar</a></li>
<li><a href="importar_datos.php">📥 Importar</a></li>

  <div class="section-title toggle" onclick="toggleMenu('membresias')">Membresías</div>
  <ul id="membresias" class="submenu">
    <li><a href="nueva_membresia.php">➕ Nueva Membresía</a></li>
    <li><a href="ver_membresias.php">🗂 Ver Membresías</a></li>
    <li><a href="planes.php">📋 Planes</a></li>
    <li><a href="planes_adicionales.php">➕ Planes Adicionales</a></li>
  </ul>

  <div class="section-title toggle" onclick="toggleMenu('profesores')">Profesores</div>
  <ul id="profesores" class="submenu">
    <li><a href="agregar_profesor.php">➕ Agregar Profesor</a></li>
    <li><a href="ver_profesores.php">👨‍🏫 Ver Profesores</a></li>
    <li><a href="asistencia_profesor.php">🕒 Asistencia</a></li>
    <li><a href="pagos_profesor.php">💵 Pagos</a></li>
  </ul>
  </ul>
<div class="section-title toggle" onclick="toggleMenu('productos')">Productos y Ventas</div>
<ul id="productos" class="submenu">
  <!-- Protecciones -->
  <li><a href="protecciones.php">🥊 Ver Protecciones</a></li>
  <li><a href="agregar_proteccion.php">➕ Agregar Protección</a></li>

  <!-- Indumentaria -->
  <li><a href="indumentaria.php">👕 Ver Indumentaria</a></li>
  <li><a href="agregar_indumentaria.php">➕ Agregar Indumentaria</a></li>

  <!-- Suplementos -->
  <li><a href="suplementos.php">🧃 Ver Suplementos</a></li>
  <li><a href="agregar_suplemento.php">➕ Agregar Suplemento</a></li>

  <!-- Ventas -->
  <li><a href="ventas_protecciones.php">💰 Venta de Protecciones</a></li>
  <li><a href="ventas_indumentaria.php">💰 Venta de Indumentaria</a></li>
  <li><a href="ventas_suplementos.php">💰 Venta de Suplementos</a></li>
</ul>

  <div class="section-title toggle" onclick="toggleMenu('general')">General</div>
  <ul id="general" class="submenu">
    <li><a href="registrar_asistencia.php">📲 Registrar Asistencia</a></li>
  </ul>
  <!-- NUEVO BLOQUE PARA GIMNASIOS -->
<div class="section-title toggle" onclick="toggleMenu('gimnasios')">Gimnasios</div>
<ul id="gimnasios" class="submenu">
  <li><a href="agregar_gimnasio.php">➕ Agregar Gimnasio</a></li>
  <li><a href="ver_gimnasios.php">🏢 Ver Gimnasios</a></li>
  <li><a href="usuarios_gimnasio.php">👥 Usuarios</a></li>
  <li><a href="estadisticas_gimnasio.php">📊 Estadísticas</a></li>
</ul>
<div class="section-title toggle" onclick="toggleMenu('admin')">Administración</div>
<ul id="admin" class="submenu">
  <li><a href="ver_gimnasios.php">🏢 Ver Gimnasios</a></li>
  <li><a href="planes_gimnasio.php">📆 Planes de Uso</a></li>
</ul>
<div class="section-title toggle" onclick="toggleMenu('sesion')">Cuenta</div>
<ul id="sesion" class="submenu">
  <li><a href="login.php">🔑 Iniciar Sesión</a></li>
  <li><a href="logout.php">🚪 Cerrar Sesión</a></li>
<a href="agregar_usuario.php">➕ Agregar Usuario</a>
<a href="editar_usuario.php?id=1">✏️ Editar Usuario</a>
<a href="ver_usuarios.php">👥 Ver Usuarios</a>

  </div>
</li>

</div>

<script>
  function toggleMenu(id) {
    const menu = document.getElementById(id);
    const toggleTitle = menu.previousElementSibling;
    menu.classList.toggle('active-submenu');
    toggleTitle.classList.toggle('active');
  }
</script>
