<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$id = $_GET['id'] ?? 0;

if ($id > 0 && $gimnasio_id > 0) {
    $stmt = $conexion->prepare("DELETE FROM planes WHERE id = ? AND gimnasio_id = ?");
    $stmt->bind_param("ii", $id, $gimnasio_id);

    if ($stmt->execute()) {
        echo "<script>alert('Plan eliminado correctamente'); window.location.href='planes.php';</script>";
    } else {
        echo "<script>alert('Error al eliminar el plan'); window.location.href='planes.php';</script>";
    }
    $stmt->close();
} else {
    echo "<script>alert('ID inválido'); window.location.href='planes.php';</script>";
}
$conexion->close();
