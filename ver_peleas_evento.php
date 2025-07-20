<?php
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Obtener peleas del evento
$evento_id = $_SESSION['evento_id'] ?? 0;

$peleas = $conexion->query("
    SELECT p.*, 
        cr.nombre AS rojo_nombre, cr.apellido AS rojo_apellido, cr.escuela AS rojo_escuela,
        ca.nombre AS azul_nombre, ca.apellido AS azul_apellido, ca.escuela AS azul_escuela,
        cg.id AS ganador_id, cg.apellido AS ganador_apellido, cg.nombre AS ganador_nombre
    FROM peleas_evento p
    JOIN competidores_evento cr ON p.competidor_rojo_id = cr.id
    JOIN competidores_evento ca ON p.competidor_azul_id = ca.id
    LEFT JOIN competidores_evento cg ON p.ganador_id = cg.id
    WHERE p.evento_id = $evento_id
    ORDER BY p.fecha DESC
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>🥊 Peleas del Evento</title>
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        .pelea {
            display: flex;
            justify-content: space-between;
            border: 2px solid #888;
            padding: 10px;
            margin-bottom: 15px;
            background: #111;
            color: #fff;
        }
        .rojo, .azul {
            width: 30%;
            padding: 10px;
            font-weight: bold;
        }
        .rojo { background: #900; }
        .azul { background: #0060cc; }
        .resultado {
            width: 30%;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .ganador {
            font-size: 1.1em;
            color: gold;
            margin-top: 10px;
        }
    </style>
</head>
<body>
<div class="contenedor">
    <h2>🥊 Peleas del Evento</h2>

    <?php while($fila = $peleas->fetch_assoc()): ?>
        <div class="pelea">
            <div class="rojo">
                <?= $fila['rojo_apellido'] ?> <?= $fila['rojo_nombre'] ?><br>
                🏫 <?= $fila['rojo_escuela'] ?>
            </div>

            <div class="resultado">
                <strong>Resultado:</strong>
                <?= $fila['resultado'] ?: '🕐 Sin resultado' ?><br>

                <?php if ($fila['ganador_id']): ?>
                    <div class="ganador">🏆 Ganador:<br><?= $fila['ganador_apellido'] ?> <?= $fila['ganador_nombre'] ?></div>
                <?php else: ?>
                    <form method="POST" action="guardar_resultado_pelea.php">
                        <input type="hidden" name="pelea_id" value="<?= $fila['id'] ?>">
                        <select name="ganador_id" required>
                            <option value="">Seleccionar Ganador</option>
                            <option value="<?= $fila['competidor_rojo_id'] ?>">🔴 Rojo</option>
                            <option value="<?= $fila['competidor_azul_id'] ?>">🔵 Azul</option>
                        </select>
                        <input type="text" name="resultado" placeholder="Ej: KO, Puntos" required>
                        <button type="submit">💾 Guardar</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="azul">
                <?= $fila['azul_apellido'] ?> <?= $fila['azul_nombre'] ?><br>
                🏫 <?= $fila['azul_escuela'] ?>
            </div>
        </div>
    <?php endwhile; ?>
</div>
</body>
</html>
