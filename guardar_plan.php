<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$gimnasio_id = $_POST['gimnasio_id'] ?? 0;
$nombre = trim($_POST['nombre'] ?? '');
$precio = floatval($_POST['precio'] ?? 0);
$clases = intval($_POST['clases_disponibles'] ?? 0);
$dias = intval($_POST['dias_disponibles'] ?? 0);
$duracion = intval($_POST['duracion'] ?? 1);

if (!$nombre || $precio <= 0) {
    echo "<script>alert('Datos incompletos o inválidos'); history.back();</script>";
    exit;
}

$id = $_POST['id'] ?? null;
if ($id) {
    // Editar plan existente
    $stmt = $conexion->prepare("UPDATE planes SET nombre=?, precio=?, clases_disponibles=?, dias_disponibles=?, duracion=? WHERE id=? AND gimnasio_id=?");
    $stmt->bind_param("sdiiiii", $nombre, $precio, $clases, $dias, $duracion, $id, $gimnasio_id);
} else {
    // Agregar nuevo plan
    $stmt = $conexion->prepare("INSERT INTO planes (nombre, precio, clases_disponibles, dias_disponibles, duracion, gimnasio_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sdiiii", $nombre, $precio, $clases, $dias, $duracion, $gimnasio_id);
}

if ($stmt->execute()) {
    echo "<script>alert('Plan guardado correctamente'); window.location.href='planes.php';</script>";
} else {
    echo "<script>alert('Error al guardar: " . $stmt->error . "'); history.back();</script>";
}
$stmt->close();
$conexion->close();
