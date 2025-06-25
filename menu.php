<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rol = $_SESSION['rol'] ?? '';
?>

<div class="sidebar">
    <h2>Fight Academy</h2>
    <ul class="menu">
        <?php if ($rol === 'admin' || $rol === 'cliente_gym') : ?>
        <li><a href="index.php"><i class="fas fa-home"></i> Panel General</a></li>
        <li><a href="#"><i class="fas fa-users"></i> Clientes</a>
            <ul class="submenu">
                <li><a href="agregar_cliente.php">Agregar</a></li>
                <li><a href="ver_clientes.php">Ver</a></li>
                <li><a href="disciplinas.php">Disciplinas</a></li>
            </ul>
        </li>
        <li><a href="#"><i class="fas fa-id-card"></i> Membresías</a>
            <ul class="submenu">
                <li><a href="agregar_membresia.php">Nueva</a></li>
                <li><a href="ver_membresias.php">Ver</a></li>
                <li><a href="planes.php">Planes</a></li>
                <li><a href="adicionales.php">Planes adicionales</a></li>
            </ul>
        </li>
        <li><a href="#"><i class="fas fa-dumbbell"></i> Asistencias</a>
            <ul class="submenu">
                <li><a href="registrar_asistencia_qr.php">Registrar</a></li>
                <li><a href="ver_asistencias.php">Ver</a></li>
            </ul>
        </li>
        <li><a href="#"><i class="fas fa-qrcode"></i> QR</a>
            <ul class="submenu">
                <li><a href="generar_qr.php">Generar</a></li>
                <li><a href="scanner_qr.php">Escanear</a></li>
            </ul>
        </li>
        <li><a href="#"><i class="fas fa-chalkboard-teacher"></i> Profesores</a>
            <ul class="submenu">
                <li><a href="agregar_profesor.php">Agregar</a></li>
                <li><a href="ver_profesores.php">Ver</a></li>
            </ul>
        </li>
        <li><a href="#"><i class="fas fa-shopping-cart"></i> Ventas</a>
            <ul class="submenu">
                <li><a href="ventas.php">Registrar</a></li>
                <li><a href="productos.php">Productos</a></li>
            </ul>
        </li>
        <li><a href="#"><i class="fas fa-building"></i> Gimnasios</a>
            <ul class="submenu">
                <li><a href="ver_gimnasios.php">Ver</a></li>
                <li><a href="agregar_gimnasio.php">Agregar</a></li>
            </ul>
        </li>
        <li><a href="#"><i class="fas fa-user-cog"></i> Usuarios</a>
            <ul class="submenu">
                <li><a href="ver_usuarios.php">Ver</a></li>
                <li><a href="agregar_usuario.php">Agregar</a></li>
            </ul>
        </li>
        <li><a href="configuraciones.php"><i class="fas fa-cogs"></i> Configuraciones</a></li>
        <?php endif; ?>

        <?php if ($rol === 'profesor') : ?>
        <li><a href="#"><i class="fas fa-user"></i> Profesor</a>
            <ul class="submenu">
                <li><a href="scanner_qr.php">Escanear QR</a></li>
                <li><a href="ver_asistencias.php">Ver Asistencias</a></li>
                <li><a href="ver_profesores.php">Perfil</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Salir</a></li>
    </ul>
</div>
