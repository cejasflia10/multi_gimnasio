<?php
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$evento_id = $_SESSION['evento_id'] ?? 0;

$competidores = $conexion->query("
    SELECT ce.id, ce.nombre, ce.apellido, ce.escuela, d.nombre AS disciplina
    FROM competidores_evento ce
    LEFT JOIN disciplinas_evento d ON ce.disciplina_id = d.id
    WHERE ce.evento_id = $evento_id
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Armar Combate</title>
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        .contenedor-combate {
            display: flex;
            justify-content: space-between;
            gap: 40px;
        }
        .rincon {
            flex: 1;
            padding: 15px;
            border: 2px solid #ccc;
            border-radius: 10px;
        }
        .rojo {
            background-color: #ffdddd;
        }
        .azul {
            background-color: #ddeaff;
        }
    </style>
</head>
<body>
<div class="contenedor">
    <h2>🥊 Armar Combate</h2>
    <form method="POST" action="guardar_combate.php">
        <div class="contenedor-combate">
            <div class="rincon rojo">
                <h3>🟥 Rincón Rojo</h3>
                <select name="competidor_rojo_id" required>
                    <option value="">-- Seleccionar --</option>
                    <?php
                    mysqli_data_seek($competidores, 0);
                    while ($c = $competidores->fetch_assoc()):
                    ?>
                        <option value="<?= $c['id'] ?>">
                            <?= $c['apellido'] ?> <?= $c['nombre'] ?> - <?= $c['escuela'] ?> (<?= $c['disciplina'] ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="rincon azul">
                <h3>🟦 Rincón Azul</h3>
                <select name="competidor_azul_id" required>
                    <option value="">-- Seleccionar --</option>
                    <?php
                    mysqli_data_seek($competidores, 0);
                    while ($c = $competidores->fetch_assoc()):
                    ?>
                        <option value="<?= $c['id'] ?>">
                            <?= $c['apellido'] ?> <?= $c['nombre'] ?> - <?= $c['escuela'] ?> (<?= $c['disciplina'] ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <br>
        <label>⏱️ Rounds:</label>
        <input type="number" name="rounds" value="3" min="1" required>

        <label>⏱️ Minutos por Round:</label>
        <input type="number" name="duracion_round" value="2" min="1" required>

        <label>🛑 Descanso (min):</label>
        <input type="number" name="descanso" value="1" min="0" required>

        <br><br>
        <button type="submit">💾 Guardar Combate</button>
        <a href="ver_combates_evento.php">📋 Ver Combates</a>
    </form>
</div>
</body>
</html>
