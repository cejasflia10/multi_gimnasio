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
    <style>
        body { background-color: #111; color: gold; font-family: Arial, sans-serif; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid gold; padding: 10px; text-align: left; }
        th { background-color: #222; }
        a.button { padding: 8px 12px; background-color: gold; color: black; text-decoration: none; border-radius: 6px; }
        a.button:hover { background-color: orange; }
    </style>
</head>
<body>
    <h2>Planes de este gimnasio</h2>
    <a href="agregar_plan.php" class="button">Agregar nuevo plan</a>
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
</body>
</html>
