<?php
include 'conexion.php';

$eventos = $conexion->query("SELECT * FROM eventos_deportivos ORDER BY fecha ASC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Eventos Deportivos - Fight Academy</title>
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        .evento {
            background-color: #111;
            border: 1px solid #444;
            padding: 20px;
            margin: 15px 0;
            color: gold;
            border-radius: 10px;
        }
        .evento h3 { margin: 0 0 10px; }
        .evento img { max-width: 300px; border-radius: 5px; }
        iframe { max-width: 100%; border: none; height: 250px; }
    </style>
</head>
<body style="background:black; color:gold;">
    <div class="contenedor">
        <h2>🏆 Próximos Eventos Deportivos</h2>

        <?php while ($e = $eventos->fetch_assoc()): ?>
        <div class="evento">
            <h3><?= htmlspecialchars($e['titulo']) ?></h3>
            <p><strong>📅 Fecha:</strong> <?= $e['fecha'] ?> | <strong>⏰ Hora:</strong> <?= $e['hora'] ?></p>
            <p><strong>📍 Lugar:</strong> <?= htmlspecialchars($e['lugar']) ?></p>
            <p><?= nl2br(htmlspecialchars($e['descripcion'])) ?></p>

            <?php if ($e['flyer']): ?>
                <p><img src="<?= $e['flyer'] ?>" alt="Flyer del evento"></p>
            <?php endif; ?>

            <?php if ($e['video']): ?>
                <p>
                    <?php if (str_contains($e['video'], 'youtube')): ?>
                        <iframe src="<?= str_replace("watch?v=", "embed/", $e['video']) ?>" allowfullscreen></iframe>
                    <?php else: ?>
                        <a href="<?= $e['video'] ?>" target="_blank">🎥 Ver video</a>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
        <?php endwhile; ?>

        <a href="index.php" class="boton-volver">⬅ Volver al inicio</a>
    </div>
</body>
</html>
