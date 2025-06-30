<?php
session_start();
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

$codigo = $_POST['codigo'] ?? '';
if (!$codigo || $codigo[0] !== 'P') {
    echo "<script>alert('Código inválido.'); window.location='scanner_qr_profesor.php';</script>";
    exit;
}

$dni = substr($codigo, 1);
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Buscar profesor con DNI y gimnasio actual
$query = $conexion->query("SELECT id FROM profesores WHERE dni = '$dni' AND gimnasio_id = $gimnasio_id");

if (!$query || $query->num_rows == 0) {
    echo "<script>alert('Profesor no encontrado (DNI: $dni) en este gimnasio.'); window.location='scanner_qr_profesor.php';</script>";
    exit;
}

$profesor = $query->fetch_assoc();
$profesor_id = $profesor['id'];
$fecha = date('Y-m-d');
$hora = date('H:i:s');

// Verificar si ya tiene asistencia hoy
$consulta = $conexion->query("SELECT * FROM asistencias_profesor WHERE profesor_id = $profesor_id AND fecha = '$fecha'");

if ($consulta->num_rows > 0) {
    // Ya tiene ingreso → registrar salida
    $conexion->query("UPDATE asistencias_profesor SET hora_salida = '$hora' WHERE profesor_id = $profesor_id AND fecha = '$fecha'");
    echo "<script>alert('✅ Salida registrada correctamente.'); window.location='scanner_qr_profesor.php';</script>";
} else {
    // No tiene ingreso → registrar entrada
    $conexion->query("INSERT INTO asistencias_profesor (profesor_id, fecha, hora_ingreso) VALUES ($profesor_id, '$fecha', '$hora')");
    echo "<script>alert('✅ Ingreso registrado correctamente.'); window.location='scanner_qr_profesor.php';</script>";
}
?>
