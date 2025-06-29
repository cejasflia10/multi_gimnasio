<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$dni = $_GET['dni'] ?? '';
$dni = trim($dni);

if ($dni === '') {
    echo "<div style='text-align:center; color:red; margin-top:50px; font-size:20px;'>❌ Código QR vacío<br><a href='javascript:history.back()' style='color:violet'>← Volver</a></div>";
    exit;
}

// Buscar al profesor por DNI
$prof = $conexion->query("SELECT * FROM profesores WHERE dni = '$dni' LIMIT 1");
if ($prof->num_rows === 0) {
    echo "<div style='text-align:center; color:red; margin-top:50px; font-size:20px;'>⚠️ QR no válido para profesor<br><a href='javascript:history.back()' style='color:violet'>← Volver</a></div>";
    exit;
}

$profesor = $prof->fetch_assoc();
$profesor_id = $profesor['id'];
$nombre = $profesor['nombre'] . ' ' . $profesor['apellido'];
$fecha_hoy = date('Y-m-d');
$hora_actual = date('H:i:s');
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Verificar si ya ingresó hoy y no egresó
$check = $conexion->query("SELECT * FROM asistencias_profesores 
    WHERE profesor_id = $profesor_id AND fecha = '$fecha_hoy' 
    AND hora_salida IS NULL 
    ORDER BY id DESC LIMIT 1");

if ($check->num_rows > 0) {
    // Ya ingresó → registrar egreso
    $conexion->query("UPDATE asistencias_profesores 
        SET hora_salida = '$hora_actual' 
        WHERE profesor_id = $profesor_id AND fecha = '$fecha_hoy' AND hora_salida IS NULL");

    echo "<div style='text-align:center; color:lightgreen; margin-top:50px; font-size:20px;'>✅ Egreso registrado<br>Profesor: <b>$nombre</b><br>Hora: $hora_actual<br><a href='scanner_qr.php' style='color:violet'>← Volver</a></div>";
} else {
    // Registrar ingreso
    $conexion->query("INSERT INTO asistencias_profesores (profesor_id, fecha, hora_ingreso, gimnasio_id)
        VALUES ($profesor_id, '$fecha_hoy', '$hora_actual', $gimnasio_id)");

    echo "<div style='text-align:center; color:lightgreen; margin-top:50px; font-size:20px;'>✅ Ingreso registrado<br>Profesor: <b>$nombre</b><br>Hora: $hora_actual<br><a href='scanner_qr.php' style='color:violet'>← Volver</a></div>";
}
?>
