<nav id="sidebar">
  <div class="sidebar-header">
    <h3>🏋️‍♂️ Fight Academy</h3>
  </div>
  <ul class="components">
    <li><a href="index.php">🏠 Dashboard</a></li>

    <li>
      <button class="dropdown-btn">👤 Clientes ▾</button>
      <div class="dropdown-container">
        <a href="clientes.php">Ver Clientes</a>
        <a href="agregar_cliente.php">Agregar Cliente</a>
      </div>
    </li>

    <li>
      <button class="dropdown-btn">📋 Membresías ▾</button>
      <div class="dropdown-container">
        <a href="membresias.php">Ver Membresías</a>
        <a href="agregar_membresia.php">Agregar Membresía</a>
      </div>
    </li>

    <li>
      <button class="dropdown-btn">👨‍🏫 Profesores ▾</button>
      <div class="dropdown-container">
        <a href="profesores.php">Ver Profesores</a>
        <a href="agregar_profesor.php">Agregar Profesor</a>
      </div>
    </li>

    <li>
      <button class="dropdown-btn">🕒 Asistencias ▾</button>
      <div class="dropdown-container">
        <a href="asistencias.php">Registrar Asistencia</a>
        <a href="ver_asistencias.php">Ver Asistencias</a>
      </div>
    </li>

    <li>
      <button class="dropdown-btn">💵 Ventas ▾</button>
      <div class="dropdown-container">
        <a href="ventas.php">Registrar Venta</a>
        <a href="ver_ventas.php">Ver Ventas</a>
      </div>
    </li>

    <li>
      <button class="dropdown-btn">🏢 Gimnasios ▾</button>
      <div class="dropdown-container">
        <a href="ver_gimnasios.php">Ver Gimnasios</a>
        <a href="crear_gimnasio.php">Crear Gimnasio</a>
      </div>
    </li>

    <li>
      <button class="dropdown-btn">⚙️ Configuración ▾</button>
      <div class="dropdown-container">
        <a href="usuarios.php">Ver Usuarios</a>
        <a href="agregar_usuario.php">Agregar Usuario</a>
        <a href="permisos.php">Asignar Permisos</a>
        <a href="cambiar_contrasena.php">Cambiar Contraseña</a>
        <a href="logout.php">Cerrar Sesión</a>
      </div>
    </li>
  
<li class="nav-item">
  <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseQR" aria-expanded="false" aria-controls="collapseQR">
    <i class="fas fa-qrcode"></i>
    <span>QR</span>
  </a>
  <div id="collapseQR" class="collapse" aria-labelledby="headingQR" data-parent="#accordionSidebar">
    <div class="bg-dark py-2 collapse-inner rounded">
      <a class="collapse-item" href="ver_qr_clientes.php">Ver QR generados</a>
      <a class="collapse-item" href="imprimir_qr.php">Imprimir QR</a>
    </div>
  </div>
</li>

<li class="nav-item">
  <a class="nav-link" href="registrar_asistencia_qr.php">
    <i class="fas fa-user-check"></i>
    <span>Asistencia por QR</span>
  </a>
</li>

</ul>
</nav>
<script>
  const dropdowns = document.querySelectorAll(".dropdown-btn");
  dropdowns.forEach(btn => {
    btn.addEventListener("click", function () {
      this.classList.toggle("active");
      const container = this.nextElementSibling;
      container.style.display = container.style.display === "block" ? "none" : "block";
    });
  });
</script>
