<!-- FONT AWESOME -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<style>
/* Ocultar menú horizontal en celulares */
@media screen and (max-width: 768px) {
    .menu-horizontal {
        display: none;
    }
}

/* Estilos menú horizontal */
.menu-horizontal {
    background-color: #900; /* fondo rojo oscuro */
    padding: 10px;
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    font-family: Arial, sans-serif;
    justify-content: center;
    align-items: center;
}

.menu-horizontal .dropdown {
    position: relative;
}

.menu-horizontal .dropbtn {
    background-color: transparent;
    color: gold;
    font-weight: bold;
    border: none;
    cursor: pointer;
    padding: 10px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.menu-horizontal .dropdown-content {
    display: none;
    position: absolute;
    background-color: #222;
    min-width: 200px;
    z-index: 1;
    box-shadow: 0 2px 8px rgba(0,0,0,0.5);
}

.menu-horizontal .dropdown-content a {
    color: gold;
    padding: 10px;
    text-decoration: none;
    display: block;
    font-size: 14px;
}

.menu-horizontal .dropdown-content a:hover {
    background-color: #333;
}

.menu-horizontal .dropdown:hover .dropdown-content {
    display: block;
}
</style>

<!-- MENÚ HORIZONTAL -->
<div class="menu-horizontal-pc">
  <div class="menu-horizontal">
    
    <!-- Botón Volver al Menú con ícono -->
    <div class="dropdown">
        <a href="index.php" class="dropbtn"><i class="fas fa-home"></i> Menú</a>
    </div>

    <!-- Clientes -->
    <div class="dropdown">
        <span class="dropbtn"><i class="fas fa-users"></i> Clientes</span>
        <div class="dropdown-content">
            <a href="ver_clientes.php">Ver Clientes</a>
            <a href="agregar_cliente.php">Agregar Cliente</a>
            <a href="disciplinas.php">Disciplinas</a>
        </div>
    </div>

    <!-- Membresías -->
    <div class="dropdown">
        <span class="dropbtn"><i class="fas fa-id-card"></i> Membresías</span>
        <div class="dropdown-content">
            <a href="nueva_membresia.php">Nueva</a>
            <a href="ver_membresias.php">Ver</a>
            <a href="planes.php">Planes</a>
            <a href="planes_adicionales.php">Adicionales</a>
        </div>
    </div>

    <!-- Asistencias -->
    <div class="dropdown">
        <span class="dropbtn"><i class="fas fa-calendar-check"></i> Asistencias</span>
        <div class="dropdown-content">
            <a href="registrar_asistencia.php">Registrar</a>
            <a href="ver_asistencias.php">Ver</a>
        </div>
    </div>

    <!-- QR -->
    <div class="dropdown">
        <span class="dropbtn"><i class="fas fa-qrcode"></i> QR</span>
        <div class="dropdown-content">
            <a href="scanner_qr.php">Escanear</a>
            <a href="generar_qr.php">Generar</a>
        </div>
    </div>

    <!-- Profesores -->
    <div class="dropdown">
        <span class="dropbtn"><i class="fas fa-user-tie"></i> Profesores</span>
        <div class="dropdown-content">
            <a href="agregar_profesor.php">Agregar</a>
            <a href="ver_profesores.php">Ver</a>
            <a href="ver_pagos_profesor.php">Pagos</a>
        </div>
    </div>

    <!-- Ventas -->
    <div class="dropdown">
        <span class="dropbtn"><i class="fas fa-shopping-cart"></i> Ventas</span>
        <div class="dropdown-content">
            <a href="ventas_protecciones.php">Protecciones</a>
            <a href="ventas_indumentaria.php">Indumentaria</a>
            <a href="ventas_suplementos.php">Suplementos</a>
            <a href="ver_ventas.php">Ver Todas</a>
        </div>
    </div>

    <!-- Gimnasios -->
    <div class="dropdown">
        <span class="dropbtn"><i class="fas fa-dumbbell"></i> Gimnasios</span>
        <div class="dropdown-content">
            <a href="agregar_gimnasio.php">Agregar</a>
            <a href="ver_gimnasios.php">Ver</a>
        </div>
    </div>

    <!-- Usuarios -->
    <div class="dropdown">
        <span class="dropbtn"><i class="fas fa-users-cog"></i> Usuarios</span>
        <div class="dropdown-content">
            <a href="agregar_usuario.php">Agregar</a>
            <a href="ver_usuarios.php">Ver</a>
        </div>
    </div>

    <!-- Configuraciones -->
    <div class="dropdown">
        <span class="dropbtn"><i class="fas fa-cogs"></i> Configuraciones</span>
        <div class="dropdown-content">
            <a href="configurar_planes.php">Planes</a>
            <a href="configurar_accesos.php">Accesos</a>
        </div>
    </div>

    <!-- Panel Cliente -->
    <div class="dropdown">
        <a href="panel_cliente.php" class="dropbtn"><i class="fas fa-user-circle"></i> Panel Cliente</a>
    </div>

    <!-- Cerrar sesión -->
    <div class="dropdown">
        <a href="logout.php" class="dropbtn"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
    </div>

  </div>
</div>
