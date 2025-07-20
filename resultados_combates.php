<?php
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$evento_id = $_SESSION['evento_id'] ?? 0;

$combates = $conexion->query("
    SELECT c.id, 
           r.apellido AS rojo_apellido, r.nombre AS rojo_nombre, r.escuela AS rojo_escuela,
           a.apellido AS azul_apellido, a.nombre AS azul_nombre, a.escuela AS azul_escuela,
           c.resultado, c.metodo, d.nombre AS disciplina
    FROM peleas_evento c
    LEFT JOIN competidores_evento r ON c.competidor_rojo_id = r.id
    LEFT JOIN competidores_evento a ON c.competidor_azul_id = a.id
    LEFT JOIN disciplinas_evento d ON c.disciplina_id = d.id
    WHERE c.evento_id = $evento_id
    ORDER BY c.id DESC
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resultados de Combates</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2>🏆 Resultados de Combates</h2>
    <table>
        <tr>
            <th>Rincón Rojo</th>
            <th>Rincón Azul</th>
            <th>Disciplina</th>
            <th>Ganador</th>
            <th>Método</th>
        </tr>
        <?php while ($c = $combates->fetch_assoc()): ?>
        <tr>
            <td><?= $c['rojo_apellido'] . ' ' . $c['rojo_nombre'] ?> (<?= $c['rojo_escuela'] ?>)</td>
            <td><?= $c['azul_apellido'] . ' ' . $c['azul_nombre'] ?> (<?= $c['azul_escuela'] ?>)</td>
            <td><?= $c['disciplina'] ?></td>
            <td><?= $c['resultado'] ?></td>
            <td><?= $c['metodo'] ?? '-' ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
</body>
</html>
