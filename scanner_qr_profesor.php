<?php
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('America/Argentina/Buenos_Aires');

include 'conexion.php';

$profesor_id = $_SESSION['profesor_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$fecha = date('Y-m-d');
$hora_actual = date('H:i:s');

if ($profesor_id == 0 || $gimnasio_id == 0) {
    die("Error: sesión inválida");
}

// Buscar si ya tiene ingreso hoy sin egreso
$verificar = $conexion->query("
    SELECT id FROM asistencias_profesor 
    WHERE profesor_id = $profesor_id 
    AND fecha = '$fecha' 
    AND gimnasio_id = $gimnasio_id 
    AND hora_egreso IS NULL
");

if ($verificar->num_rows > 0) {
    // Si ya tiene ingreso sin egreso: marcar salida
    $conexion->query("
        UPDATE asistencias_profesor 
        SET hora_egreso = '$hora_actual' 
        WHERE profesor_id = $profesor_id 
        AND fecha = '$fecha' 
        AND hora_egreso IS NULL 
        AND gimnasio_id = $gimnasio_id
    ");
    $mensaje = "👋 ¡Egreso registrado correctamente!";
} else {
    // Sino, registrar ingreso
    $conexion->query("
        INSERT INTO asistencias_profesor (profesor_id, fecha, hora_ingreso, gimnasio_id)
        VALUES ($profesor_id, '$fecha', '$hora_actual', $gimnasio_id)
    ");
    $mensaje = "👋 ¡Ingreso registrado correctamente!";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Ingreso/Egreso Profesor</title>
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    body { background: #000; color: gold; font-family: Arial; text-align: center; padding: 30px; }
    .mensaje { font-size: 24px; margin-top: 40px; }
  </style>
</head>
<body>
  <h2 class="mensaje"><?= $mensaje ?></h2>
  <a href="panel_profesor.php" style="color: gold; text-decoration: underline;">⬅ Volver al panel</a>
</body>
</html>
