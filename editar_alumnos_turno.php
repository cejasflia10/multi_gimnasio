<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$profesor_id = $_GET['id'] ?? 0;
$fecha = $_GET['fecha'] ?? '';
$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $profesor_id = $_POST['profesor_id'];
    $fecha = $_POST['fecha'];
    $cantidad = $_POST['cantidad'];

    // Guardar en tabla auxiliar (si no existe, deberías crearla)
    $stmt = $conexion->prepare("REPLACE INTO alumnos_turno_profesor (profesor_id, fecha, cantidad, gimnasio_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isii", $profesor_id, $fecha, $cantidad, $_SESSION['gimnasio_id']);
    $stmt->execute();

    $mensaje = "✅ Cantidad de alumnos actualizada.";
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Editar Alumnos</title>
</head>
<body style="background:#000; color:gold; font-family:sans-serif; text-align:center; padding:30px;">
    <h2>👥 Editar Alumnos - <?= $fecha ?></h2>
    <?php if ($mensaje) echo "<p style='color:lime;'>$mensaje</p>"; ?>
    <form method="POST">
        <input type="hidden" name="profesor_id" value="<?= $profesor_id ?>">
        <input type="hidden" name="fecha" value="<?= $fecha ?>">
        <label>Cantidad de alumnos: <input type="number" name="cantidad" required></label><br><br>
        <button type="submit">Guardar</button>
        <a href="reporte_horas_profesor.php" style="color:gold;">Volver</a>
    </form>
</body>
</html>
