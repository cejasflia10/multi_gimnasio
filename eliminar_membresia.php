<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

if (!isset($_GET['id'])) {
    echo "<script>alert('ID de membresía no proporcionado'); window.location='ver_membresias.php';</script>";
    exit;
}

$id = intval($_GET['id']);
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Confirmar que la membresía pertenece al gimnasio
$check = $conexion->query("SELECT id FROM membresias WHERE id = $id AND gimnasio_id = $gimnasio_id LIMIT 1");
if ($check->num_rows === 0) {
    echo "<script>alert('Membresía no encontrada o no autorizada'); window.location='ver_membresias.php';</script>";
    exit;
}

// Eliminar la membresía
$conexion->query("DELETE FROM membresias WHERE id = $id");

echo "<script>alert('Membresía eliminada correctamente'); window.location='ver_membresias.php';</script>";
?>
