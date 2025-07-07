<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['profesor_id'])) {
    echo "Acceso no autorizado.";
    exit;
}

$profesor_id = $_SESSION['profesor_id'];
$cliente_id = intval($_POST['cliente_id']);
$peso = floatval($_POST['peso']);
$altura = floatval($_POST['altura']);
$imc = floatval($_POST['imc']);
$observaciones = trim($_POST['observaciones']);
$fecha = date('Y-m-d');

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Insertar en tabla progreso_alumno
$sql = "INSERT INTO progreso_alumno (cliente_id, profesor_id, gimnasio_id, peso, altura, imc, observaciones, fecha)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("iiiddsss", $cliente_id, $profesor_id, $gimnasio_id, $peso, $altura, $imc, $observaciones, $fecha);

if ($stmt->execute()) {
    echo "<div style='background:#000; color:gold; padding:20px; text-align:center;'>
            ✅ Progreso guardado correctamente.<br><br>
            <a href='registrar_progreso.php' style='color:orange;'>Volver</a>
          </div>";
} else {
    echo "<div style='background:#000; color:red; padding:20px; text-align:center;'>
            ❌ Error al guardar el progreso.<br><br>
            <a href='registrar_progreso.php' style='color:orange;'>Intentar nuevamente</a>
          </div>";
}
?>
