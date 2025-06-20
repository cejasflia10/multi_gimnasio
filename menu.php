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
        <a href="generar_qr.php">Generar QR</a>
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
        <a href="registrar_asistencia.php">Registrar Asistencia</a>
        <a href="asistencia_profesores.php">Asistencia Profesores</a>
      </div>
    </li>

    <li>
      <button class="dropdown-btn">💰 Ventas ▾</button>
      <div class="dropdown-container">
        <a href="ventas.php">Ver Ventas</a>
        <a href="agregar_venta.php">Agregar Venta</a>
      </div>
    </li>

    <li>
      <button class="dropdown-btn">🏢 Gimnasios ▾</button>
      <div class="dropdown-container">
        <a href="gimnasios.php">Ver Gimnasios</a>
        <a href="agregar_gimnasio.php">Agregar Gimnasio</a>
      </div>
    </li>

    <li>
      <button class="dropdown-btn">⚙️ Configuración ▾</button>
      <div class="dropdown-container">
        <a href="usuarios.php">Usuarios</a>
        <a href="permisos.php">Permisos</a>
      </div>
    </li>

    <li>
      <button class="dropdown-btn">📷 QR ▾</button>
      <div class="dropdown-container">
        <a href="registrar_asistencia_qr.php">Asistencia por QR</a>
      </div>
    </li>
  
<li class="nav-item">
    <a href="#" class="nav-link">
        <i class="fas fa-qrcode"></i>
        <p>
            QR
            <i class="right fas fa-angle-left"></i>
        </p>
    </a>
    <ul class="nav nav-treeview" style="background-color: #222;">
        <li class="nav-item">
            <a href="escaneo_qr.php" class="nav-link">
                <i class="far fa-circle nav-icon"></i>
                <p>Escanear QR</p>
            </a>
        </li>
    </ul>
</li>

</ul>
</nav>
