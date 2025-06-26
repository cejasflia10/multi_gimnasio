<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rol = $_SESSION['rol'] ?? '';

?>

<nav style="background-color: #000; color: gold; padding: 10px; text-align: center;">
    <?php if (in_array($rol, ['admin', 'cliente_gym'])): ?>
        <a href="ver_clientes.php" style="margin: 0 10px; color: gold;">👥 Clientes</a>
        <a href="ver_membresias.php" style="margin: 0 10px; color: gold;">📄 Membresías</a>
        <a href="ver_asistencias.php" style="margin: 0 10px; color: gold;">📆 Asistencias</a>
        <a href="scanner_qr.php" style="margin: 0 10px; color: gold;">🔲 QR</a>
    <?php endif; ?>

    <?php if ($rol === 'admin'): ?>
        <a href="ver_usuarios.php" style="margin: 0 10px; color: gold;">⚙️ Usuarios</a>
        <a href="ver_gimnasios.php" style="margin: 0 10px; color: gold;">🏋️ Gimnasios</a>
        <a href="ver_planes.php" style="margin: 0 10px; color: gold;">💳 Planes</a>
    <?php endif; ?>

    <?php if ($rol === 'profesor'): ?>
        <a href="scanner_qr.php" style="margin: 0 10px; color: gold;">🔲 QR</a>
    <?php endif; ?>

    <a href="logout.php" style="margin: 0 10px; color: gold;">🔒 Cerrar sesión</a>
</nav>
