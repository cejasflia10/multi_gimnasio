<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$titulo = trim($_POST['titulo'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');
$premio = trim($_POST['premio'] ?? '');
$fecha = $_POST['fecha'] ?? '';
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($titulo && $descripcion && $premio && $fecha && $gimnasio_id) {
    $stmt = $conexion->prepare("INSERT INTO sorteos (titulo, descripcion, premio, fecha, gimnasio_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $titulo, $descripcion, $premio, $fecha, $gimnasio_id);
    $stmt->execute();
}

header("Location: crear_sorteo.php?ok=1");
exit;

