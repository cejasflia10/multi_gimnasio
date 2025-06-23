<?php
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$nombre = $_POST['nombre'];
$precio = $_POST['precio'];
$dias = $_POST['dias_disponibles'];
$duracion = $_POST['duracion'];

$conexion->query("INSERT INTO planes (nombre, precio, dias_disponibles, duracion, gimnasio_id) 
VALUES ('$nombre', $precio, $dias, $duracion, $gimnasio_id)");

header("Location: planes.php");