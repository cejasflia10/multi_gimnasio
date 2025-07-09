<?php
session_start();
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$id = intval($_GET['id'] ?? 0);

// Guardar cambios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $hora_ingreso = $_POST['hora_ingreso'];
    $hora_salida = $_POST['hora_salida'];

    $update = $conexion->prepare("UPDATE asistencias_profesores SET hora_ingreso = ?, hora_salida = ? WHERE id = ? AND gimnasio_id = ?");
    $update->bind_param("ssii", $hora_ingreso, $hora_salida, $id, $gimnasio_id);
    if ($update->execute()) {
        echo "<script>alert('✅ Turno actualizado correctamente'); window.location.href='reporte_horas_profesor.php';</script>";
        exit;
    } else {
        echo "<p style='color:red;'>❌ Error al actualizar.</p>";
    }
}

// Obtener turno
$turno = $conexion->query("SELECT * FROM asistencias_profesores WHERE id = $id AND gimnasio_id = $gimnasio_id")->fetch_assoc();
if (!$turno) {
    echo "❌ Turno no encontrado.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Turno</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2 style="text-align:center;">✏️ Editar Turno del Profesor</h2>

    <form method="POST" style="max-width: 400px; margin: auto;">
        <input type="hidden" name="id" value="<?= $turno['id'] ?>">
        
        <label>Fecha:</label>
        <input type="text" value="<?= $turno['fecha'] ?>" disabled class="form-control"><br>

        <label>Hora Ingreso:</label>
        <input type="time" name="hora_ingreso" value="<?= $turno['hora_ingreso'] ?>" required class="form-control"><br>

        <label>Hora Salida:</label>
        <input type="time" name="hora_salida" value="<?= $turno['hora_salida'] ?>" required class="form-control"><br>

        <button type="submit" style="padding:10px; background-color:gold; color:black; font-weight:bold;">💾 Guardar Cambios</button>
        <a href="reporte_horas_profesor.php" style="margin-left:20px;">🔙 Volver</a>
    </form>
</div>
</body>
</html>
