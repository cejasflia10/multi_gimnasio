<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['profesor_id'])) {
    echo "Acceso denegado.";
    exit;
}

$profesor_id = $_SESSION['profesor_id'];
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Capturar los datos enviados desde registrar_progreso.php
$cliente_id = intval($_POST['cliente_id'] ?? 0);
$disciplina = $_POST['disciplina'] ?? '';
$graduacion = $_POST['graduacion'] ?? '';
$observaciones = $_POST['observaciones'] ?? '';
$fecha = $_POST['fecha'] ?? date('Y-m-d');

// Insertar el progreso
$sql = "INSERT INTO progreso_alumno (cliente_id, profesor_id, gimnasio_id, disciplina, graduacion, observaciones, fecha)
        VALUES (?, ?, ?, ?, ?, ?, ?)";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("iiissss", $cliente_id, $profesor_id, $gimnasio_id, $disciplina, $graduacion, $observaciones, $fecha);

if ($stmt->execute()) {
    echo "<div style='background:#000; color:gold; padding:20px; text-align:center;'>
            ✅ Progreso cargado correctamente.<br><br>
            <a href='registrar_progreso.php' style='color:orange;'>← Volver</a>
          </div>";
} else {
    echo "<div style='background:#000; color:red; padding:20px; text-align:center;'>
            ❌ Error al guardar el progreso.<br><br>
            <a href='registrar_progreso.php' style='color:orange;'>← Intentar nuevamente</a>
          </div>";
}
?>
