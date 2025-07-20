<?php
include 'conexion.php';
session_start();

$pelea_id = intval($_GET['id'] ?? 0);
if (!$pelea_id) {
    echo "ID de pelea inválido."; exit;
}

// Obtener info de la pelea
$info = $conexion->query("
    SELECT p.id, cr.nombre AS rojo, ca.nombre AS azul
    FROM peleas_evento p
    JOIN competidores_evento cr ON p.competidor_rojo_id = cr.id
    JOIN competidores_evento ca ON p.competidor_azul_id = ca.id
    WHERE p.id = $pelea_id
")->fetch_assoc();

// Obtener puntajes
$puntajes = $conexion->query("
    SELECT j.nombre AS juez, pj.round, pj.ganador
    FROM puntajes_jueces pj
    JOIN jueces_evento j ON pj.juez_id = j.id
    WHERE pj.pelea_id = $pelea_id
    ORDER BY pj.round, j.nombre
");

$tarjetas = [];
while ($p = $puntajes->fetch_assoc()) {
    $tarjetas[$p['round']][$p['juez']] = $p['ganador'];
}
?>

<h2>Resultados en vivo - Pelea</h2>
<h3><?= $info['rojo'] ?> 🟥 vs <?= $info['azul'] ?> 🟦</h3>

<table border="1" cellpadding="8">
    <tr>
        <th>Round</th>
        <?php
        // Columnas dinámicas de jueces
        $todos_jueces = [];
        foreach ($tarjetas as $round => $jueces) {
            foreach ($jueces as $j => $val) {
                $todos_jueces[$j] = true;
            }
        }
        foreach (array_keys($todos_jueces) as $juez) {
            echo "<th>$juez</th>";
        }
        ?>
    </tr>
    <?php foreach ($tarjetas as $round => $jueces): ?>
    <tr>
        <td>Round <?= $round ?></td>
        <?php foreach (array_keys($todos_jueces) as $juez): ?>
            <td><?= $jueces[$juez] ?? '-' ?></td>
        <?php endforeach; ?>
    </tr>
    <?php endforeach; ?>
</table>
