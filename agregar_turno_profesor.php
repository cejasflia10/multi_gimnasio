<?php
session_start();
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificación de acceso
if (!isset($_SESSION['gimnasio_id'])) {
    echo "Acceso denegado.";
    exit;
}

$gimnasio_id = $_SESSION['gimnasio_id'];
$rol_usuario = $_SESSION['rol'] ?? '';
$profesor_id_sesion = $_SESSION['profesor_id'] ?? 0;

// Procesamiento del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $profesor_id = intval($_POST['profesor_id']);
    $fecha = $_POST['fecha'] ?? '';
    $hora_ingreso = $_POST['hora_ingreso'] ?? '';
    $hora_egreso = $_POST['hora_egreso'] ?? '';
    $alumnos_manual = ($_POST['alumnos_manual'] !== '') ? intval($_POST['alumnos_manual']) : 'NULL';

    if ($profesor_id && $fecha && $hora_ingreso && $hora_egreso) {
        $check = $conexion->query("SELECT id FROM asistencias_profesores 
                                   WHERE profesor_id = $profesor_id 
                                   AND fecha = '$fecha' 
                                   AND gimnasio_id = $gimnasio_id");

        if ($check && $check->num_rows > 0) {
            // ACTUALIZAR turno existente
            $id_existente = $check->fetch_assoc()['id'];
            $query = "UPDATE asistencias_profesores 
                      SET hora_ingreso = '$hora_ingreso', 
                          hora_egreso = '$hora_egreso', 
                          alumnos_manual = $alumnos_manual 
                      WHERE id = $id_existente";
        } else {
            // INSERTAR nuevo turno
            $query = "INSERT INTO asistencias_profesores 
                        (profesor_id, fecha, hora_ingreso, hora_egreso, alumnos_manual, gimnasio_id) 
                      VALUES 
                        ($profesor_id, '$fecha', '$hora_ingreso', '$hora_egreso', $alumnos_manual, $gimnasio_id)";
        }

        if ($conexion->query($query)) {
            echo "<div style='color:lime; font-size:20px; text-align:center;'>✅ Turno guardado correctamente.</div>";
        } else {
            echo "<div style='color:red;'>❌ Error: " . $conexion->error . "</div>";
        }
    } else {
        echo "<div style='color:red;'>❌ Todos los campos son obligatorios.</div>";
    }
}

// Listado de profesores
$profesores = $conexion->query("SELECT id, apellido, nombre FROM profesores WHERE gimnasio_id = $gimnasio_id ORDER BY apellido, nombre");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Turno Manual</title>
    <style>
        body { background-color: #000; color: gold; font-family: Arial; padding: 20px; }
        h2 { text-align: center; color: white; }
        form { max-width: 500px; margin: auto; background: #111; padding: 20px; border-radius: 10px; }
        label { display: block; margin-top: 15px; }
        input, select {
            width: 100%; padding: 8px; margin-top: 5px;
            border-radius: 5px; border: none;
        }
        button {
            margin-top: 20px; background: lime; color: black;
            padding: 10px; font-size: 16px; width: 100%;
            border: none; cursor: pointer; border-radius: 5px;
        }
        a { color: white; display: block; text-align: center; margin-top: 15px; }
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

    <label>Hora de egreso:</label>
    <input type="time" name="hora_egreso" required>

    <label>Alumnos (opcional):</label>
    <input type="number" name="alumnos_manual" min="0">

    <button type="submit">Guardar Turno</button>
</form>

<a href="reporte_horas_profesor.php">⬅️ Volver al Reporte</a>

</body>
</html>
