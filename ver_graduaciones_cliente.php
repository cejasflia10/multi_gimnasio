<?php
session_start();
include 'conexion.php';
include 'menu_cliente.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
if ($cliente_id == 0) die("Acceso denegado.");

$graduaciones = $conexion->query("
    SELECT g.fecha, g.disciplina, g.nivel, g.observaciones, p.apellido AS profesor
    FROM graduaciones g
    JOIN profesores p ON g.profesor_id = p.id
    WHERE g.cliente_id = $cliente_id
    ORDER BY g.fecha DESC
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Graduaciones</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>

<div class="contenedor">
    <h1 class="titulo-seccion">🎓 Mis Graduaciones</h1>

    <?php if ($graduaciones->num_rows > 0): ?>
        <div class="tabla-contenedor">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Disciplina</th>
                        <th>Nivel</th>
                        <th>Profesor</th>
                        <th>Observaciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($g = $graduaciones->fetch_assoc()): ?>
                    <tr>
                        <td><?= $g['fecha'] ?></td>
                        <td><?= $g['disciplina'] ?></td>
                        <td><?= $g['nivel'] ?></td>
                        <td><?= $g['profesor'] ?></td>
                        <td><?= nl2br(htmlspecialchars($g['observaciones'])) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="info" style="text-align:center;">Aún no tenés graduaciones registradas.</p>
    <?php endif; ?>
</div>

</body>
</html>
