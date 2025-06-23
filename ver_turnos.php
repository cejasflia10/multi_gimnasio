<?php
include 'conexion.php';
include 'menu.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$gimnasio_id = $_SESSION['gimnasio_id'];

$dias = $conexion->query("SELECT * FROM dias");
$horarios = $conexion->query("SELECT * FROM horarios");
$profesores = $conexion->query("SELECT * FROM profesores WHERE gimnasio_id = $gimnasio_id");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Turno</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background-color: #111;
            color: #f1c40f;
            font-family: Arial;
            margin-left: 260px;
            padding: 20px;
        }
        form {
            max-width: 400px;
            margin: auto;
            padding: 20px;
            background: #222;
            border-radius: 10px;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        label, select, input {
            display: block;
            width: 100%;
            margin-bottom: 15px;
            padding: 8px;
            font-size: 16px;
            background-color: #111;
            color: gold;
            border: 1px solid #555;
            border-radius: 5px;
        }
        button {
            background: #f1c40f;
            border: none;
            padding: 10px;
            width: 100%;
            color: #000;
            font-weight: bold;
            cursor: pointer;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <form action="guardar_turno.php" method="POST">
        <h2>Nuevo Turno</h2>

        <label>Día:</label>
        <select name="id_dia" required>
            <?php while($d = $dias->fetch_assoc()) echo "<option value='{$d['id']}'>{$d['nombre']}</option>"; ?>
        </select>

        <label>Horario:</label>
        <select name="id_horario" required>
            <?php while($h = $horarios->fetch_assoc()) {
                $rango = substr($h['hora_inicio'], 0, 5) . " - " . substr($h['hora_fin'], 0, 5);
                echo "<option value='{$h['id']}'>$rango</option>";
            } ?>
        </select>

        <label>Profesor:</label>
        <select name="id_profesor" required>
            <?php while($p = $profesores->fetch_assoc()) echo "<option value='{$p['id']}'>{$p['apellido']} {$p['nombre']}</option>"; ?>
        </select>

        <label>Cupo máximo:</label>
        <input type="number" name="cupo_maximo" value="20" min="1">

        <button type="submit">Guardar Turno</button>
    </form>
</body>
</html>
