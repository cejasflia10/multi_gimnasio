<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<p style='color: red;'>❌ ID de asistencia inválido.</p>";
    exit;
}

$id = intval($_GET['id']);
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Obtener los datos del registro
$stmt = $conexion->prepare("SELECT * FROM asistencias_profesores WHERE id = ? AND gimnasio_id = ?");
$stmt->bind_param("ii", $id, $gimnasio_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo "<p style='color: red;'>❌ Registro no encontrado.</p>";
    exit;
}

$registro = $res->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Asistencia del Profesor</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2>✏️ Editar Asistencia del Profesor</h2>

    <form method="POST" action="guardar_edicion_asistencia.php">
        <input type="hidden" name="id" value="<?= $registro['id'] ?>">

        <label>Fecha:</label>
        <input type="date" name="fecha" value="<?= $registro['fecha'] ?>" required><br><br>

        <label>Hora de Ingreso:</label>
        <input type="time" name="hora_ingreso" value="<?= $registro['hora_ingreso'] ?>" required><br><br>

        <label>Hora de Salida:</label>
        <input type="time" name="hora_salida" value="<?= $registro['hora_salida'] ?>" required><br><br>

        <label>Cantidad de Alumnos:</label>
        <input type="number" name="alumnos" value="<?= $registro['alumnos'] ?? 0 ?>" min="0" required><br><br>

        <button type="submit" class="boton">💾 Guardar Cambios</button>
        <a href="pagar_horas_profesor.php" class="boton">↩️ Volver</a>
    </form>
</div>
</body>
</html>
