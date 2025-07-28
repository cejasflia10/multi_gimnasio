<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_eventos.php';

$sql = "
SELECT 
    ce.id,
    ce.evento_id,
    cr.nombre AS nombre_rojo,
    cr.apellido AS apellido_rojo,
    ca.nombre AS nombre_azul,
    ca.apellido AS apellido_azul,
    ce.ganador,
    ce.resultado,
    ce.minutos_combate,
    ce.minutos_descanso,
    ce.fecha
FROM combates_evento ce
LEFT JOIN competidores_evento cr ON ce.rojo_id = cr.id
LEFT JOIN competidores_evento ca ON ce.azul_id = ca.id
ORDER BY ce.fecha DESC
";

$resultado = $conexion->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resultados de Combates</title>
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        body { background: #111; color: gold; font-family: Arial, sans-serif; }
        .contenedor { max-width: 900px; margin: auto; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border: 1px solid gold; text-align: center; }
        th { background: #222; }
        tr:nth-child(even) { background: #222; }
        .rojo { color: #ff4444; font-weight: bold; }
        .azul { color: #44aaff; font-weight: bold; }
        .btn-ver { background: gold; color: #111; padding: 5px 10px; border-radius: 5px; text-decoration: none; }
        .btn-ver:hover { background: orange; }
    </style>
</head>
<body>
<div class="contenedor">
    <h2>🥊 Resultados de Combates</h2>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Competidor Rojo</th>
                <th>Competidor Azul</th>
                <th>Ganador</th>
                <th>Resultado</th>
                <th>Duración (min)</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($resultado->num_rows > 0): ?>
                <?php while ($fila = $resultado->fetch_assoc()): ?>
                <tr>
                    <td><?= date("d/m/Y H:i", strtotime($fila['fecha'])) ?></td>
                    <td class="rojo"><?= $fila['apellido_rojo']." ".$fila['nombre_rojo'] ?></td>
                    <td class="azul"><?= $fila['apellido_azul']." ".$fila['nombre_azul'] ?></td>
                    <td><strong><?= ucfirst($fila['ganador']) ?></strong></td>
                    <td><?= $fila['resultado'] ?></td>
                    <td><?= $fila['minutos_combate'] ?>'</td>
                    <td>
                        <a class="btn-ver" href="ver_combate.php?id=<?= $fila['id'] ?>">👁 Ver</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7">⚠️ No hay combates registrados.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
