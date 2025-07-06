<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
if (!$gimnasio_id) {
    echo "Acceso denegado.";
    exit;
}

// Sorteos del gimnasio
$sorteos = $conexion->query("
    SELECT * FROM sorteos 
    WHERE gimnasio_id = $gimnasio_id 
    ORDER BY fecha DESC
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ver Sorteos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2 class="titulo-seccion">📋 Sorteos del Gimnasio</h2>

    <?php while ($s = $sorteos->fetch_assoc()):
        $sorteo_id = $s['id'];

        // Buscar cantidad de participantes
        $cant = $conexion->query("
            SELECT COUNT(*) AS total FROM sorteos_participantes 
            WHERE sorteo_id = $sorteo_id
        ")->fetch_assoc()['total'];

        // Buscar nombre del ganador si ya existe
        $ganador = null;
        if ($s['ganador_id']) {
            $g = $conexion->query("SELECT nombre, apellido FROM clientes WHERE id = {$s['ganador_id']}")->fetch_assoc();
            $ganador = $g['apellido'] . ', ' . $g['nombre'];
        }
    ?>
        <div class="card" style="margin-bottom:15px;">
            <h3><?= htmlspecialchars($s['titulo']) ?></h3>
            <p><?= nl2br(htmlspecialchars($s['descripcion'])) ?></p>
            <p><strong>Premio:</strong> <?= htmlspecialchars($s['premio']) ?></p>
            <p><strong>Fecha del sorteo:</strong> <?= $s['fecha'] ?></p>
            <p><strong>Participantes:</strong> <?= $cant ?></p>

            <?php if ($ganador): ?>
                <p style="color:lime;"><strong>Ganador:</strong> <?= $ganador ?> 🎉</p>
            <?php elseif ($cant > 0): ?>
                <form method="POST" action="sortear_ganador.php">
                    <input type="hidden" name="sorteo_id" value="<?= $sorteo_id ?>">
                    <button type="submit">🎲 Sortear Ganador</button>
                </form>
            <?php else: ?>
                <p style="color:orange;">⚠️ Aún sin participantes</p>
            <?php endif; ?>
        </div>
    <?php endwhile; ?>
</div>
</body>
</html>
