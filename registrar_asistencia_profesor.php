<?php
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

if (!isset($_POST['codigo'])) {
    die("Código no recibido.");
}

$codigo = $_POST['codigo'];
$dni = substr($codigo, 1); // Elimina la P

$profesor_q = $conexion->query("SELECT id FROM profesores WHERE dni = '$dni' LIMIT 1");
if ($profesor_q->num_rows === 0) {
    die("Profesor no encontrado.");
}

$profesor = $profesor_q->fetch_assoc();
$profesor_id = $profesor['id'];
$fecha = date("Y-m-d");
$hora = date("H:i:s");

// Ver si ya hay un registro hoy
$existe = $conexion->query("SELECT * FROM asistencias_profesor WHERE profesor_id = $profesor_id AND fecha = '$fecha'");

if ($existe->num_rows > 0) {
    // Ya hay ingreso, marcamos salida
    $conexion->query("UPDATE asistencias_profesor SET hora_salida = '$hora' WHERE profesor_id = $profesor_id AND fecha = '$fecha'");
    echo "<script>alert('✅ Salida registrada.'); window.location='scanner_qr_profesor.php';</script>";
} else {
    // No hay ingreso, lo registramos
    $conexion->query("INSERT INTO asistencias_profesor (profesor_id, fecha, hora_ingreso) VALUES ($profesor_id, '$fecha', '$hora')");
    echo "<script>alert('✅ Ingreso registrado.'); window.location='scanner_qr_profesor.php';</script>";
}
?>
