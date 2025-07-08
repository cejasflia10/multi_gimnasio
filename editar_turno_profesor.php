<?php
session_start();
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

if (!isset($_SESSION['gimnasio_id'])) {
    echo "Acceso denegado.";
    exit;
}

$gimnasio_id = $_SESSION['gimnasio_id'];
$turno_id = intval($_GET['id'] ?? 0);

if ($turno_id <= 0) {
    echo "Turno no válido.";
    exit;
}

$mensaje = "";

// Obtener datos actuales del turno
$turno = $conexion->query("SELECT * FROM asistencias_profesores WHERE id = $turno_id AND gimnasio_id = $gimnasio_id")->fetch_assoc();

if (!$turno) {
    echo "Turno no encontrado.";
    exit;
}

// Si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha = $_POST['fecha'];
    $hora_ingreso = $_POST['hora_ingreso'];
    $hora_salida = $_POST['hora_salida'];
    $alumnos_manual = trim($_POST['alumnos_manual']) !== "" ? intval($_POST['alumnos_manual']) : null;

    $stmt = $conexion->prepare("UPDATE asistencias_profesores 
        SET fecha = ?, hora_ingreso = ?, hora_salida = ?, alumnos_manual = ? 
        WHERE id = ? AND gimnasio_id = ?");
    $stmt->bind_param("sssiii", $fecha, $hora_ingreso, $hora_salida, $alumnos_manual, $turno_id, $gimnasio_id);

    if ($stmt->execute()) {
        $mensaje = "✅ Turno actualizado correctamente.";
        // Actualizar datos cargados
        $turno['fecha'] = $fecha;
        $turno['hora_ingreso'] = $hora_ingreso;
        $turno['hora_salida'] = $hora_salida;
        $turno['alumnos_manual'] = $alumnos_manual;
    } else {
        $mensaje = "❌ Error al actualizar turno.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Turno</title>
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
            font-weight: bold;
        }
        input {
            width: 100%;
            padding: 8px;
            background: #222;
            color: white;
            border: 1px solid #555;
            border-radius: 5px;
        }
        button {
            margin-top: 20px;
            padding: 10px;
            background: gold;
            color: black;
            font-weight: bold;
            border: none;
            width: 100%;
            border-radius: 5px;
        }
        .mensaje {
            text-align: center;
            margin-top: 15px;
            color: lime;
        }
        .volver {
            text-align: center;
            margin-top: 15px;
        }
        .volver a {
            color: white;
            text-decoration: underline;
        }
    </style>
</head>
<body>

<h2>✏️ Editar Turno</h2>

<?php if ($mensaje): ?>
    <div class="mensaje"><?= $mensaje ?></div>
<?php endif; ?>

<form method="POST">
    <label>Fecha:</label>
    <input type="date" name="fecha" value="<?= $turno['fecha'] ?>" required>

    <label>Hora Ingreso:</label>
    <input type="time" name="hora_ingreso" value="<?= $turno['hora_ingreso'] ?>" required>

    <label>Hora Salida:</label>
    <input type="time" name="hora_salida" value="<?= $turno['hora_salida'] ?>" required>

    <label>Alumnos (manual):</label>
    <input type="number" name="alumnos_manual" value="<?= $turno['alumnos_manual'] ?>" min="0" placeholder="opcional">

    <button type="submit">Guardar Cambios</button>
</form>

<div class="volver">
    <a href="reporte_horas_profesor.php">⬅️ Volver al Reporte</a>
</div>

</body>
</html>
