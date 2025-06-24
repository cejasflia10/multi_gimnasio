<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

if (!isset($_GET['id'])) {
    die("ID no especificado.");
}

$id = intval($_GET['id']);
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Verifica que la membresía pertenezca al gimnasio
$verificar = $conexion->prepare("SELECT id FROM membresias WHERE id = ? AND gimnasio_id = ?");
$verificar->bind_param("ii", $id, $gimnasio_id);
$verificar->execute();
$verificar->store_result();

if ($verificar->num_rows === 0) {
    echo "<script>alert('No se encontró la membresía o no pertenece a este gimnasio.'); history.back();</script>";
    exit;
}
$verificar->close();

// Elimina
$stmt = $conexion->prepare("DELETE FROM membresias WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo "<script>alert('Membresía eliminada correctamente'); window.location.href='ver_membresias.php';</script>";
} else {
    echo "<script>alert('Error al eliminar: " . $stmt->error . "'); history.back();</script>";
}

$stmt->close();
?>
