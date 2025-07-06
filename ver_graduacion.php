<?php
session_start();
include 'conexion.php';
include 'menu_cliente.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
if ($cliente_id == 0) die("Acceso denegado.");

$graduaciones = $conexion->query("
    SELECT fecha_examen, grado, disciplina
    FROM graduaciones_cliente
    WHERE cliente_id = $cliente_id
    ORDER BY fecha_examen DESC
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>🎓 Mi Graduación</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>

<div class="contenedor">
    <h1 class="titulo-seccion">🎓 Mi Graduación</h1>

    <?php if ($graduaciones->num_rows > 0): ?>
        <div class="tabla-contenedor">
            <table>
                <thead>
                    <tr>
                        <th>Fecha de Examen</th>
                        <th>Grado</th>
                        <th>Disciplina</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($g = $graduaciones->fetch_assoc()): ?>
                        <tr>
                            <td><?= $g['fecha_examen'] ?></td>
                            <td><?= $g['grado'] ?></td>
                            <td><?= $g['disciplina'] ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="info" style="text-align: center;">No se encontraron registros de graduación.</p>
    <?php endif; ?>
</div>

</body>
</html>
