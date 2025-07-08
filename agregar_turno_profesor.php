<?php
session_start();
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Verificar acceso válido
if (!isset($_SESSION['gimnasio_id'])) {
    echo "Acceso denegado.";
    exit;
}

$gimnasio_id = $_SESSION['gimnasio_id'];
$rol_usuario = $_SESSION['rol'] ?? '';
$profesor_id_sesion = $_SESSION['profesor_id'] ?? 0;

// Si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $profesor_id = intval($_POST['profesor_id']);
    $fecha = $_POST['fecha'];
    $hora_ingreso = $_POST['hora_ingreso'];
    $hora_salida = $_POST['hora_salida'];
    $alumnos_manual = ($_POST['alumnos_manual'] !== '') ? intval($_POST['alumnos_manual']) : 'NULL';

    if ($profesor_id && $fecha && $hora_ingreso && $hora_salida) {
        $query = "INSERT INTO asistencias_profesores 
                    (profesor_id, fecha, hora_ingreso, hora_salida, alumnos_manual, gimnasio_id) 
                  VALUES 
                    ($profesor_id, '$fecha', '$hora_ingreso', '$hora_salida', $alumnos_manual, $gimnasio_id)";
        if ($conexion->query($query)) {
            echo "<div style='color:lime; font-size:20px; text-align:center;'>✅ Turno agregado correctamente.</div>";
        } else {
            echo "<div style='color:red;'>❌ Error al guardar: " . $conexion->error . "</div>";
        }
    } else {
        echo "<div style='color:red;'>❌ Todos los campos son obligatorios.</div>";
    }
}

// Obtener lista de profesores del gimnasio actual
$profesores = $conexion->query("SELECT id, apellido, nombre FROM profesores WHERE gimnasio_id = $gimnasio_id ORDER BY apellido, nombre");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Turno Manual</title>
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h2 {
            text-align: center;
            color: white;
        }
        form {
            max-width: 500px;
            margin: auto;
            background: #111;
            padding: 20px;
            border-radius: 10px;
        }
        label {
            display: block;
            margin-top: 15px;
        }
        input, select {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            border-radius: 5px;
            border: none;
        }
        button {
            margin-top: 20px;
            background: lime;
            color: black;
            padding: 10px;
            font-size: 16px;
            width: 100%;
            border: none;
            cursor: pointer;
            border-radius: 5px;
        }
        a {
            color: white;
            display: block;
            text-align: center;
            margin-top: 15px;
        }
    </style>
</head>
<body>

<h2>➕ Agregar Turno Manual</h2>

<form method="POST">
    <?php if ($rol_usuario === 'profesor' && $profesor_id_sesion): ?>
        <input type="hidden" name="profesor_id" value="<?= $profesor_id_sesion ?>">
        <p>📌 Profesor: <strong>
            <?php
            $p = $conexion->query("SELECT apellido, nombre FROM profesores WHERE id = $profesor_id_sesion")->fetch_assoc();
            echo $p['apellido'] . ' ' . $p['nombre'];
            ?>
        </strong></p>
    <?php else: ?>
        <label>Profesor:</label>
        <select name="profesor_id" required>
            <option value="">Seleccionar</option>
            <?php while ($p = $profesores->fetch_assoc()): ?>
                <option value="<?= $p['id'] ?>"><?= $p['apellido'] . ' ' . $p['nombre'] ?></option>
            <?php endwhile; ?>
        </select>
    <?php endif; ?>

    <label>Fecha:</label>
    <input type="date" name="fecha" required>

    <label>Hora de ingreso:</label>
    <input type="time" name="hora_ingreso" required>

    <label>Hora de salida:</label>
    <input type="time" name="hora_salida" required>

    <label>Alumnos (opcional):</label>
    <input type="number" name="alumnos_manual" min="0">

    <button type="submit">Guardar Turno</button>
</form>

<a href="reporte_horas_profesor.php">⬅️ Volver al Reporte</a>

</body>
</html>
