<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$profesor_id = $_GET['id'] ?? 0;
$fecha = $_GET['fecha'] ?? '';
$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $profesor_id = $_POST['profesor_id'];
    $fecha = $_POST['fecha'];
    $entrada = $_POST['hora_entrada'];
    $salida = $_POST['hora_salida'];

    $stmt = $conexion->prepare("UPDATE asistencias_profesores SET hora_entrada=?, hora_salida=? WHERE profesor_id=? AND fecha=?");
    $stmt->bind_param("ssis", $entrada, $salida, $profesor_id, $fecha);
    $stmt->execute();

    $mensaje = "✅ Horarios actualizados correctamente.";
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Editar Horas</title>
</head>
<body style="background:#000; color:gold; font-family:sans-serif; text-align:center; padding:30px;">
    <h2>🕒 Editar Horas - <?= $fecha ?></h2>
    <?php if ($mensaje) echo "<p style='color:lime;'>$mensaje</p>"; ?>
    <form method="POST">
        <input type="hidden" name="profesor_id" value="<?= $profesor_id ?>">
        <input type="hidden" name="fecha" value="<?= $fecha ?>">
        <label>Hora Entrada: <input type="time" name="hora_entrada" required></label><br><br>
        <label>Hora Salida: <input type="time" name="hora_salida" required></label><br><br>
        <button type="submit">Guardar</button>
        <a href="reporte_horas_profesor.php" style="color:gold;">Volver</a>
    </form>
</body>
</html>
