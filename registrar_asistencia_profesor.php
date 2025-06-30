<?php
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

$codigo = $_POST['codigo'] ?? '';
if (!$codigo || $codigo[0] !== 'P') {
    die("QR inválido");
}

$dni = substr($codigo, 1);
$query = $conexion->query("SELECT id FROM profesores WHERE dni = '$dni' LIMIT 1");

if (!$query || $query->num_rows == 0) {
    die("Profesor no encontrado (DNI: $dni)");
}

$profesor = $query->fetch_assoc();
$profesor_id = $profesor['id'];
$fecha = date('Y-m-d');
$hora = date('H:i:s');

$existe = $conexion->query("SELECT * FROM asistencias_profesor WHERE profesor_id = $profesor_id AND fecha = '$fecha'");

if ($existe->num_rows > 0) {
    $conexion->query("UPDATE asistencias_profesor SET hora_salida = '$hora' WHERE profesor_id = $profesor_id AND fecha = '$fecha'");
    echo "<script>alert('✅ Salida registrada'); window.location='scanner_qr_profesor.php';</script>";
} else {
    $conexion->query("INSERT INTO asistencias_profesor (profesor_id, fecha, hora_ingreso) VALUES ($profesor_id, '$fecha', '$hora')");
    echo "<script>alert('✅ Ingreso registrado'); window.location='scanner_qr_profesor.php';</script>";
}
?>
