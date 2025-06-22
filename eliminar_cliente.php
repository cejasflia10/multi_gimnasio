<?php
session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? null;
$rol = $_SESSION['rol'] ?? null;

if (!isset($_GET['id']) || (!$gimnasio_id && $rol != 'admin')) {
    die("Acceso denegado.");
}

$id = intval($_GET['id']);

// Si es admin, puede eliminar sin restricción de gimnasio
if ($rol === 'admin') {
    $stmt = $conexion->prepare("DELETE FROM clientes WHERE id = ?");
    $stmt->bind_param("i", $id);
} else {
    // Si no es admin, solo puede eliminar clientes de su propio gimnasio
    $stmt = $conexion->prepare("DELETE FROM clientes WHERE id = ? AND gimnasio_id = ?");
    $stmt->bind_param("ii", $id, $gimnasio_id);
}

if ($stmt->execute()) {
    header("Location: ver_clientes.php");
    exit();
} else {
    echo "Error al eliminar cliente.";
}
