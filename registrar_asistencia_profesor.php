<?php
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

if (!isset($_GET['codigo'])) {
    die("Código no proporcionado.");
}

$codigo = $_GET['codigo'];
$dni = substr($codigo, 1); // Quitamos la letra "P"

$profesor_q = $conexion->query("SELECT id FROM profesores WHERE dni = '$dni' LIMIT 1");
if ($profesor_q->num_rows === 0) {
    die("Profesor no encontrado.");
}

$profesor = $profesor_q->fetch_assoc();
$profesor_id = $profesor['id'];
$fecha_actual = date("Y-m-d");
$hora_actual = date("H:i:s");

// Verificar si ya tiene ingreso hoy
$asistencia_q = $conexion->query("SELECT * FROM asistencias_profesor WHERE profesor_id = $profesor_id AND fecha = '$fecha_actual'");

if ($asistencia_q->num_rows > 0) {
    // Ya tiene registro, marcamos salida
    $conexion->query("UPDATE asistencias_profesor SET hora_salida = '$hora_actual' WHERE profesor_id = $profesor_id AND fecha = '$fecha_actual'");
    echo "<script>alert('✅ Salida registrada correctamente'); window.location='scanner_qr_profesor.php';</script>";
} else {
    // No hay registro aún, marcamos entrada
    $conexion->query("INSERT INTO asistencias_profesor (profesor_id, fecha, hora_ingreso) VALUES ($profesor_id, '$fecha_actual', '$hora_actual')");
    echo "<script>alert('✅ Ingreso registrado correctamente'); window.location='scanner_qr_profesor.php';</script>";
}
?>
