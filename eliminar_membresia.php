<?php
session_start();
if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado.");
}
include 'conexion.php';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conexion->prepare("DELETE FROM membresias WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo "<script>alert('Membresía eliminada correctamente'); window.location.href = 'membresias.php';</script>";
    } else {
        echo "Error al eliminar membresía: " . $stmt->error;
    }

    $stmt->close();
} else {
    echo "ID inválido.";
}
?>
