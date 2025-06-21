<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$rol = $_SESSION['rol'] ?? '';
?>

<div class="sidebar">
    <h2>Fight Academy Scorpions</h2>
    <ul class="menu">

        <?php if ($rol === 'admin' || $rol === 'escuela') : ?>
        <li><a href="#">Clientes</a>
            <ul>
                <li><a href="agregar_cliente.php">Agregar Cliente</a></li>
                <li><a href="ver_clientes.php">Ver Clientes</a></li>
                <li><a href="ver_asistencias_qr.php">Ver Asistencias</a></li>
                <li><a href="importar_clientes.php">Importar Clientes</a></li>
                <li><a href="exportar_clientes.php">Exportar Clientes</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <?php if ($rol === 'admin' || $rol === 'escuela') : ?>
        <li><a href="#">Membresías</a>
            <ul>
                <li><a href="agregar_membresia.php">Nueva Membresía</a></li>
                <li><a href="ver_membresias.php">Ver Membresías</a></li>
                <li><a href="planes_adicionales.php">Planes Adicionales</a></li>
                <li><a href="disciplinas.php">Disciplinas</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <?php if ($rol === 'admin' || $rol === 'escuela') : ?>
        <li><a href="#">Ventas</a>
            <ul>
                <li><a href="ventas.php">Registrar Venta</a></li>
                <li><a href="ver_ventas.php">Ver Ventas</a></li>
                <li><a href="productos.php">Productos</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <?php if ($rol === 'admin' || $rol === 'escuela' || $rol === 'profesor') : ?>
        <li><a href="#">QR</a>
            <ul>
                <li><a href="registrar_asistencia_qr.php">Escanear QR</a></li>
                <li><a href="generar_qr.php">Generar QR</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <?php if ($rol === 'admin' || $rol === 'escuela') : ?>
        <li><a href="#">Asistencias</a>
            <ul>
                <li><a href="asistencias_clientes.php">Asistencia Clientes</a></li>
                <li><a href="asistencias_profesores.php">Asistencia Profesores</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <?php if ($rol === 'admin') : ?>
        <li><a href="#">Administración</a>
            <ul>
                <li><a href="usuarios.php">Usuarios</a></li>
                <li><a href="gimnasios.php">Gimnasios</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <li><a href="logout.php">Cerrar Sesión</a></li>
    </ul>
</div>
