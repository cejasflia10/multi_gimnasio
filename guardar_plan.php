<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$nombre = $_POST['nombre'] ?? '';
$precio = $_POST['precio'] ?? 0;
$dias = $_POST['dias'] ?? 0;
$duracion = $_POST['duracion'] ?? 1;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$query = "INSERT INTO planes (nombre, precio, dias_disponibles, duracion, gimnasio_id)
          VALUES ('$nombre', $precio, $dias, $duracion, $gimnasio_id)";

if ($conexion->query($query)) {
    header("Location: planes.php");
} else {
    echo "Error al guardar el plan: " . $conexion->error;
}
?>