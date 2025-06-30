<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

$codigo = $_POST['codigo'] ?? '';
if (!$codigo || $codigo[0] !== 'P') {
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>QR</title></head><body style='background-color:black; color:gold; text-align:center;'>
          <h1>❌ Código inválido.</h1></body></html>";
    exit;
}

$dni = ltrim(substr($codigo, 1), '-');
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Buscar profesor
$query = $conexion->query("SELECT id FROM profesores WHERE dni = '$dni' AND gimnasio_id = $gimnasio_id");

if (!$query || $query->num_rows == 0) {
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>QR</title></head><body style='background-color:black; color:red; text-align:center;'>
          <h1>❌ Profesor no encontrado (DNI: $dni)</h1></body></html>";
    exit;
}

$profesor = $query->fetch_assoc();
$profesor_id = $profesor['id'];
$fecha = date('Y-m-d');
$hora = date('H:i:s');

// Verificar asistencia
$consulta = $conexion->query("SELECT * FROM asistencias_profesor WHERE profesor_id = $profesor_id AND fecha = '$fecha'");

if ($consulta->num_rows > 0) {
    $conexion->query("UPDATE asistencias_profesor SET hora_salida = '$hora' WHERE profesor_id = $profesor_id AND fecha = '$fecha'");
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>QR</title></head><body style='background-color:black; color:lime; text-align:center;'>
          <h1>✅ Salida registrada correctamente.</h1></body></html>";
} else {
    $conexion->query("INSERT INTO asistencias_profesor (profesor_id, fecha, hora_ingreso) VALUES ($profesor_id, '$fecha', '$hora')");
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>QR</title></head><body style='background-color:black; color:lime; text-align:center;'>
          <h1>✅ Ingreso registrado correctamente.</h1></body></html>";
}
