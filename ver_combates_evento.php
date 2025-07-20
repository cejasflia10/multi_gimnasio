<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
// Resto del código...

$resultado = $conexion->query("
    SELECT 
        c.id,
        cr.nombre AS nombre_rojo, cr.apellido AS apellido_rojo, cr.escuela AS escuela_rojo,
        ca.nombre AS nombre_azul, ca.apellido AS apellido_azul, ca.escuela AS escuela_azul,
        c.ganador, c.resultado
    FROM combates_evento c
    JOIN competidores_evento cr ON c.rojo_id = cr.id
    JOIN competidores_evento ca ON c.azul_id = ca.id
    ORDER BY c.id DESC
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ver Combates del Evento</title>
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        .pelea {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            background-color: #111;
            border: 1px solid #444;
            margin-bottom: 15px;
            padding: 15px;
            border-radius: 8px;
            color: #FFD700;
        }
        .rinc {
            width: 45%;
            padding: 10px;
            border-radius: 8px;
        }
        .rojo { background-color: #440000; }
        .azul { background-color: #001144; }
        .ganador {
            width: 100%;
            text-align: center;
            margin-top: 10px;
            font-weight: bold;
            font-size: 18px;
            color: #0f0;
        }
        @media (max-width: 768px) {
            .rinc { width: 100%; margin-bottom: 10px; }
        }
    </style>
</head>
<body>
<div class="contenedor">
    <h2>🥋 Combates del Evento</h2>

    <?php while ($fila = $resultado->fetch_assoc()): ?>
        <div class="pelea">
            <div class="rinc rojo">
                <h3>🔴 Rincón Rojo</h3>
                <p><strong><?= $fila['apellido_rojo'] ?> <?= $fila['nombre_rojo'] ?></strong></p>
                <p>Escuela: <?= $fila['escuela_rojo'] ?></p>
            </div>
            <div class="rinc azul">
                <h3>🔵 Rincón Azul</h3>
                <p><strong><?= $fila['apellido_azul'] ?> <?= $fila['nombre_azul'] ?></strong></p>
                <p>Escuela: <?= $fila['escuela_azul'] ?></p>
            </div>
            <div class="ganador">
                🏆 Ganador:
                <?= ($fila['ganador'] == 'rojo') 
                    ? "🔴 " . $fila['apellido_rojo'] . " " . $fila['nombre_rojo'] 
                    : (($fila['ganador'] == 'azul') 
                        ? "🔵 " . $fila['apellido_azul'] . " " . $fila['nombre_azul']
                        : "🤝 Empate") ?>
                <?= $fila['resultado'] ? " - " . $fila['resultado'] : "" ?>
            </div>
        </div>
    <?php endwhile; ?>

    <a href="panel_eventos.php" class="boton">⬅️ Volver al Panel</a>
</div>
</body>
</html>
