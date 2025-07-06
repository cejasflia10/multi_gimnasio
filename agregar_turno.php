<?php
include 'conexion.php';
include 'menu_horizontal.php';

$dias = $conexion->query("SELECT * FROM dias");
$horarios = $conexion->query("SELECT * FROM horarios");
$profesores = $conexion->query("SELECT * FROM profesores");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="estilo_unificado.css">

    <meta charset="UTF-8">
    <title>Agregar Turno</title>
    
</head>

<body>
    <div class="contenedor">

    <form action="guardar_turno.php" method="POST">
        <h2>Nuevo Turno</h2>
        
        <label>Día:</label>
        <select name="id_dia" required>
            <?php while($d = $dias->fetch_assoc()) echo "<option value='{$d['id']}'>{$d['nombre']}</option>"; ?>
        </select>

        <label>Horario:</label>
        <select name="id_horario" required>
            <?php while($h = $horarios->fetch_assoc()) echo "<option value='{$h['id']}'>{$h['rango']}</option>"; ?>
        </select>

        <label>Profesor:</label>
        <select name="id_profesor" required>
            <?php while($p = $profesores->fetch_assoc()) echo "<option value='{$p['id']}'>{$p['apellido']} {$p['nombre']}</option>"; ?>
        </select>

        <label>Cupo máximo:</label>
        <input type="number" name="cupo_maximo" value="20" min="1">

        <button type="submit">Guardar Turno</button>
    </form>
</div>

</body>
</html>
