<?php
include 'conexion.php';
session_start();
$juez_id = $_SESSION['juez_id'] ?? 0;
$juez_nombre = $_SESSION['juez_nombre'] ?? '';

$peleas = $conexion->query("
    SELECT p.id, cr.nombre AS rojo, ca.nombre AS azul
    FROM peleas_evento p
    JOIN competidores_evento cr ON p.competidor_rojo_id = cr.id
    JOIN competidores_evento ca ON p.competidor_azul_id = ca.id
    WHERE p.id NOT IN (
        SELECT pelea_id FROM puntajes_jueces WHERE juez_id = $juez_id
    )
");
?>

<h2>Bienvenido, <?= $juez_nombre ?></h2>
<h3>Peleas asignadas para juzgar:</h3>

<ul>
<?php while($fila = $peleas->fetch_assoc()): ?>
    <li>
        <?= $fila['rojo'] ?> 🟥 vs <?= $fila['azul'] ?> 🟦
        <a href="planilla_puntaje.php?pelea_id=<?= $fila['id'] ?>">📝 Cargar puntuación</a>
    </li>
<?php endwhile; ?>
</ul>
