<?php
session_start();
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
if (!$gimnasio_id) {
    echo "Acceso denegado.";
    exit;
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    echo "ID inválido.";
    exit;
}

// Cargar turno
$turno = $conexion->query("
    SELECT a.*, p.apellido, p.nombre 
    FROM asistencias_profesores a
    JOIN profesores p ON a.profesor_id = p.id
    WHERE a.id = $id AND a.gimnasio_id = $gimnasio_id
")->fetch_assoc();

if (!$turno) {
    echo "<p style='color:red;'>❌ Turno no encontrado o no pertenece a este gimnasio.</p>";
    exit;
}

// Guardar cambios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha = $_POST['fecha'];
    $hora_ingreso = $_POST['hora_ingreso'];
    $hora_egreso = $_POST['hora_egreso'];
    $alumnos = ($_POST['alumnos_manual'] !== '') ? intval($_POST['alumnos_manual']) : 'NULL';

    if ($fecha && $hora_ingreso && $hora_egreso) {
        $update = $conexion->query("
            UPDATE asistencias_profesores 
            SET fecha = '$fecha', 
                hora_ingreso = '$hora_ingreso', 
                hora_egreso = '$hora_egreso', 
                alumnos_manual = $alumnos
            WHERE id = $id AND gimnasio_id = $gimnasio_id
        ");

        if ($update) {
            echo "<p style='color:lime; text-align:center;'>✅ Turno actualizado correctamente.</p>";
            // Recargar turno actualizado
            $turno = $conexion->query("
                SELECT a.*, p.apellido, p.nombre 
                FROM asistencias_profesores a
                JOIN profesores p ON a.profesor_id = p.id
                WHERE a.id = $id AND a.gimnasio_id = $gimnasio_id
            ")->fetch_assoc();
        } else {
            echo "<p style='color:red;'>❌ Error al actualizar: " . $conexion->error . "</p>";
        }
    } else {
        echo "<p style='color:red;'>❌ Todos los campos son obligatorios.</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Editar Turno</title>
    <style>
        body { background: #000; color: gold; font-family: Arial; padding: 20px; }
        form {
            max-width: 500px; margin: auto; background: #111;
            padding: 20px; border-radius: 10px;
        }
        label { display: block; margin-top: 15px; }
        input {
            width: 100%; padding: 8px; margin-top: 5px;
            border-radius: 5px; border: none;
        }
        button {
            margin-top: 20px; background: gold; color: black;
            padding: 10px; font-size: 16px; width: 100%;
            border: none; cursor: pointer; border-radius: 5px;
        }
        a { color: white; display: block; text-align: center; margin-top: 15px; }
    </style>
</head>
<body>

<h2 style="text-align:center; color:white;">✏️ Editar Turno de <?= $turno['apellido'] . ' ' . $turno['nombre'] ?></h2>

<form method="POST">
    <label>Fecha:</label>
    <input type="date" name="fecha" value="<?= $turno['fecha'] ?>" required>

    <label>Hora de ingreso:</label>
    <input type="time" name="hora_ingreso" value="<?= $turno['hora_ingreso'] ?>" required>

    <label>Hora de egreso:</label>
    <input type="time" name="hora_egreso" value="<?= $turno['hora_egreso'] ?>" required>

    <label>Alumnos (opcional):</label>
    <input type="number" name="alumnos_manual" min="0" value="<?= $turno['alumnos_manual'] ?>">

    <button type="submit">💾 Guardar Cambios</button>
</form>

<a href="reporte_horas_profesor.php">⬅️ Volver al reporte</a>

</body>
</html>
