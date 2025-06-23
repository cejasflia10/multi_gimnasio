<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$nombre = $_POST['nombre'] ?? '';
$precio = $_POST['precio'] ?? '';
$dias_disponibles = $_POST['dias_disponibles'] ?? 0;
$duracion = $_POST['duracion_meses'] ?? 1;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Si es edición (ya existe ID)
if (isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $query = "UPDATE planes SET nombre='$nombre', precio='$precio', dias_disponibles=$dias_disponibles, duracion_meses=$duracion 
              WHERE id = $id AND gimnasio_id = $gimnasio_id";
} else {
    $query = "INSERT INTO planes (nombre, precio, dias_disponibles, duracion_meses, gimnasio_id) 
              VALUES ('$nombre', '$precio', $dias_disponibles, $duracion, $gimnasio_id)";
}

if ($conexion->query($query)) {
    header("Location: planes.php");
    exit;
} else {
    echo "Error al guardar el plan: " . $conexion->error;
}
?>
