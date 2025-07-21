<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

date_default_timezone_set('America/Argentina/Buenos_Aires');

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$usuario = $_SESSION['usuario'] ?? null;

// Validar sesión
if (!$usuario) {
    header("Location: login.php");
    exit;
}

$id = intval($_GET['id'] ?? 0);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $hora_ingreso = $_POST['hora_ingreso'] ?? '';
    $hora_salida = $_POST['hora_salida'] ?? '';

    if ($hora_ingreso && $hora_salida) {
        $update = $conexion->prepare("UPDATE asistencias_profesores SET hora_ingreso = ?, hora_salida = ? WHERE id = ? AND gimnasio_id = ?");
        $update->bind_param("ssii", $hora_ingreso, $hora_salida, $id, $gimnasio_id);
        if ($update->execute()) {
            echo "<script>alert('✅ Turno actualizado correctamente'); window.location.href='reporte_horas_profesor.php';</script>";
            exit;
        } else {
            echo "<div style='color:red;text-align:center;'>❌ Error al actualizar.</div>";
        }
        $update->close();
    }
}

// Obtener el turno
$consulta = $conexion->prepare("SELECT * FROM asistencias_profesores WHERE id = ? AND gimnasio_id = ?");
$consulta->bind_param("ii", $id, $gimnasio_id);
$consulta->execute();
$resultado = $consulta->get_result();
$turno = $resultado->fetch_assoc();

if (!$turno) {
    echo "<div style='text-align:center;color:red;'>❌ Turno no encontrado.</div>";
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
        <input type="text" value="<?= htmlspecialchars($turno['fecha']) ?>" disabled class="form-control"><br>

        <label>Hora Ingreso:</label>
        <input type="time" name="hora_ingreso" value="<?= htmlspecialchars($turno['hora_ingreso']) ?>" required class="form-control"><br>

        <label>Hora Salida:</label>
        <input type="time" name="hora_salida" value="<?= htmlspecialchars($turno['hora_salida']) ?>" required class="form-control"><br>

        <button type="submit" style="padding:10px; background-color:gold; color:black; font-weight:bold;">💾 Guardar Cambios</button>
        <a href="reporte_horas_profesor.php" style="margin-left:20px;">🔙 Volver</a>
    </form>
</div>
</body>
</html>
