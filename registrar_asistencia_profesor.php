<?php
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();

date_default_timezone_set('America/Argentina/Buenos_Aires');

$codigo = $_GET['codigo'] ?? '';
if (!$codigo || !str_starts_with($codigo, 'P')) {
    exit("Código inválido.");
}

$dni = substr($codigo, 1); // Quitar la letra P
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$fecha = date('Y-m-d');
$hora_actual = date('H:i:s');

// Buscar profesor por DNI y gimnasio
$buscar_profesor = $conexion->query("
    SELECT id FROM profesores
    WHERE dni = '$dni' AND gimnasio_id = $gimnasio_id
");

if ($buscar_profesor->num_rows === 0) {
    echo "<script>alert('❌ Profesor no encontrado (DNI: $dni) en este gimnasio.');window.history.back();</script>";
    exit;
}

$fila = $buscar_profesor->fetch_assoc();
$profesor_id = $fila['id'];

// Verificar si ya tiene un ingreso sin salida
$buscar_asistencia = $conexion->query("
    SELECT id FROM asistencias_profesor
    WHERE profesor_id = $profesor_id AND fecha = '$fecha' AND hora_salida IS NULL
");

if ($buscar_asistencia->num_rows > 0) {
    // Ya tiene ingreso, registrar salida
    $asistencia = $buscar_asistencia->fetch_assoc();
    $id_asistencia = $asistencia['id'];
    $conexion->query("
        UPDATE asistencias_profesor
        SET hora_salida = '$hora_actual'
        WHERE id = $id_asistencia
    ");
    echo "<script>alert('✅ Salida registrada correctamente.');window.location.href='scanner_qr_profesor.php';</script>";
} else {
    // No tiene ingreso, registrar nuevo ingreso
    $conexion->query("
        INSERT INTO asistencias_profesor (profesor_id, hora_entrada, gimnasio_id, fecha)
        VALUES ($profesor_id, '$hora_actual', $gimnasio_id, '$fecha')
    ");
    echo "<script>alert('✅ Ingreso registrado correctamente.');window.location.href='scanner_qr_profesor.php';</script>";
}
?>
