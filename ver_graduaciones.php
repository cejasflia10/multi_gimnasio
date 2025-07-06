<?php
session_start();
include 'conexion.php';
include 'menu_profesor.php';

$profesor_id = $_SESSION['profesor_id'] ?? 0;
if ($profesor_id == 0) die("Acceso denegado.");

$graduaciones = $conexion->query("
    SELECT g.fecha, g.disciplina, g.nivel, g.observaciones, c.apellido, c.nombre
    FROM graduaciones g
    JOIN clientes c ON g.cliente_id = c.id
    WHERE g.profesor_id = $profesor_id
    ORDER BY g.fecha DESC
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Graduaciones</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>

<div class="contenedor">
    <h1 class="titulo-seccion">🎓 Historial de Graduaciones</h1>

    <?php if ($graduaciones->num_rows > 0): ?>
        <div class="tabla-contenedor">
            <table>
                <thead>
                    <tr>
                        <th>Alumno</th>
                        <th>Fecha</th>
                        <th>Disciplina</th>
                        <th>Nivel</th>
                        <th>Observaciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($g = $graduaciones->fetch_assoc()): ?>
                    <tr>
                        <td><?= $g['apellido'] ?>, <?= $g['nombre'] ?></td>
                        <td><?= $g['fecha'] ?></td>
                        <td><?= $g['disciplina'] ?></td>
                        <td><?= $g['nivel'] ?></td>
                        <td><?= nl2br(htmlspecialchars($g['observaciones'])) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="info" style="text-align: center;">No hay graduaciones registradas.</p>
    <?php endif; ?>
</div>

</body>
</html>
