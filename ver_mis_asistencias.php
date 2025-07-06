<?php
session_start();
include 'conexion.php';
include 'menu_cliente.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
if ($cliente_id == 0) die("Acceso denegado.");

$asistencias = $conexion->query("
    SELECT fecha, hora 
    FROM asistencias 
    WHERE cliente_id = $cliente_id 
    ORDER BY fecha DESC, hora DESC
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>🧾 Mis Asistencias</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2>🧾 Mis Asistencias</h2>

    <?php if ($asistencias->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Hora</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($fila = $asistencias->fetch_assoc()): ?>
                    <tr>
                        <td><?= $fila['fecha'] ?></td>
                        <td><?= $fila['hora'] ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="text-align: center;">Aún no se registran asistencias.</p>
    <?php endif; ?>
</div>
</body>
</html>
