<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if (!$cliente_id || !$gimnasio_id) {
    echo "<div style='color:red; text-align:center;'>Acceso denegado.</div>";
    exit;
}

// Obtener sorteos activos
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
    <title>Sorteos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2 class="titulo-seccion">🎁 Sorteos Activos</h2>

    <?php while ($s = $sorteos->fetch_assoc()): 
        $sorteo_id = $s['id'];

        // Verificar si ya participó
        $ya = $conexion->query("
            SELECT id FROM sorteos_participantes 
            WHERE cliente_id = $cliente_id AND sorteo_id = $sorteo_id
        ")->num_rows > 0;
    ?>
        <div class="card" style="margin-bottom: 15px;">
            <h3><?= htmlspecialchars($s['titulo']) ?> 🎉</h3>
            <p><?= nl2br(htmlspecialchars($s['descripcion'])) ?></p>
            <p><strong>Premio:</strong> <?= htmlspecialchars($s['premio']) ?></p>
            <p><strong>Fecha del sorteo:</strong> <?= $s['fecha'] ?></p>

            <?php if ($s['ganador_id']): ?>
                <p style="color:lime;"><strong>Ganador confirmado 🎊</strong></p>
            <?php elseif ($ya): ?>
                <p style="color:gold;">✅ Ya estás participando</p>
            <?php else: ?>
                <form method="POST" action="participar_sorteo.php">
                    <input type="hidden" name="sorteo_id" value="<?= $sorteo_id ?>">
                    <button type="submit">🎟️ Participar</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endwhile; ?>
</div>
</body>
</html>
