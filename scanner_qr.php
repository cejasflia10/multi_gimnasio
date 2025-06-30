<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$codigo = $_GET['codigo'] ?? '';

if (empty($codigo) || $codigo[0] !== 'P') {
    exit('Código inválido.');
}

$dni = substr($codigo, 1);
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$resultado = $conexion->query("SELECT id FROM profesores WHERE dni = '$dni' AND gimnasio_id = $gimnasio_id");
if ($resultado->num_rows === 0) {
    exit("Profesor no encontrado (DNI: $dni) en este gimnasio.");
}

$profesor = $resultado->fetch_assoc();
$profesor_id = $profesor['id'];
$fecha = date('Y-m-d');

// Verificar si ya hay entrada hoy sin salida
$revisar = $conexion->query("SELECT * FROM asistencias_profesor WHERE profesor_id = $profesor_id AND fecha = '$fecha' AND hora_entrada IS NOT NULL AND hora_salida IS NULL");
if ($revisar->num_rows > 0) {
    // Registrar salida
    $conexion->query("UPDATE asistencias_profesor SET hora_salida = CURTIME() WHERE profesor_id = $profesor_id AND fecha = '$fecha' AND hora_salida IS NULL");
    echo "<script>alert('✅ Salida registrada correctamente.'); window.location.href='ver_asistencias_dia.php';</script>";
} else {
    // Registrar ingreso
    $conexion->query("INSERT INTO asistencias_profesor (profesor_id, hora_entrada, fecha, gimnasio_id) VALUES ($profesor_id, CURTIME(), '$fecha', $gimnasio_id)");
    echo "<script>alert('✅ Ingreso registrado correctamente.'); window.location.href='ver_asistencias_dia.php';</script>";
}
?>
