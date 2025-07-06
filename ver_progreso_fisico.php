<?php
session_start();
include 'conexion.php';
include 'menu_profesor.php';

$profesor_id = $_SESSION['profesor_id'] ?? 0;
if ($profesor_id == 0) die("Acceso denegado.");

$progresos = $conexion->query("
    SELECT p.fecha, p.peso, p.altura, p.observaciones, c.apellido, c.nombre
    FROM progreso_fisico p
    JOIN clientes c ON p.cliente_id = c.id
    WHERE p.profesor_id = $profesor_id
    ORDER BY p.fecha DESC
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial Progreso Físico</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h1>📋 Historial de Progreso Físico</h1>

    <?php if ($progresos->num_rows > 0): ?>
    <table>
        <thead>
            <tr>
                <th>Alumno</th>
                <th>Fecha</th>
                <th>Peso</th>
                <th>Altura</th>
                <th>Observaciones</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($p = $progresos->fetch_assoc()): ?>
            <tr>
                <td><?= $p['apellido'] ?>, <?= $p['nombre'] ?></td>
                <td><?= $p['fecha'] ?></td>
                <td><?= $p['peso'] ?> kg</td>
                <td><?= $p['altura'] ?> cm</td>
                <td><?= $p['observaciones'] ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p style="text-align: center; color: orange;">No hay registros de progreso físico aún.</p>
    <?php endif; ?>
</div>
</body>
</html>
