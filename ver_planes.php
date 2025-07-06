<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$resultado = $conexion->query("SELECT * FROM plan_usuarios WHERE gimnasio_id = $gimnasio_id");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Planes del Gimnasio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<script src="fullscreen.js"></script>

<body>
<div class="contenedor">
    <h2>📋 Planes de este gimnasio</h2>
    <a href="agregar_plan.php" class="button">➕ Agregar nuevo plan</a>
    <table>
        <tr>
            <th>Nombre</th>
            <th>Precio</th>
            <th>Clases</th>
            <th>Duración (meses)</th>
        </tr>
        <?php while ($row = $resultado->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row['nombre']) ?></td>
            <td>$<?= number_format($row['precio'], 2) ?></td>
            <td><?= $row['clases'] ?></td>
            <td><?= $row['duracion_meses'] ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
</body>
</html>
