<?php
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$evento_id = $_SESSION['evento_id'] ?? 0;

// Score: 1 punto por victoria
$competidores = $conexion->query("
    SELECT ce.id, ce.apellido, ce.nombre, ce.escuela, d.nombre AS disciplina,
           SUM(CASE 
               WHEN pe.resultado = CONCAT(ce.apellido, ' ', ce.nombre) THEN 1
               ELSE 0
           END) AS victorias
    FROM competidores_evento ce
    LEFT JOIN peleas_evento pe ON (ce.id = pe.competidor_rojo_id OR ce.id = pe.competidor_azul_id) 
    AND pe.evento_id = $evento_id
    LEFT JOIN disciplinas_evento d ON ce.disciplina_id = d.id
    WHERE ce.evento_id = $evento_id
    GROUP BY ce.id
    ORDER BY victorias DESC, ce.apellido
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Score de Competidores</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2>📊 Score de Competidores</h2>
    <table>
        <tr>
            <th>Nombre</th>
            <th>Escuela</th>
            <th>Disciplina</th>
            <th>Victorias</th>
        </tr>
        <?php while ($c = $competidores->fetch_assoc()): ?>
        <tr>
            <td><?= $c['apellido'] . ' ' . $c['nombre'] ?></td>
            <td><?= $c['escuela'] ?></td>
            <td><?= $c['disciplina'] ?></td>
            <td><?= $c['victorias'] ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
</body>
</html>
