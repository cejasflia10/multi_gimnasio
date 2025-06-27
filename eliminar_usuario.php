<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include("conexion.php");

if (isset($_POST['id']) && is_numeric($_POST['id'])) {
    $id = intval($_POST['id']);

    $query = "DELETE FROM usuarios WHERE id = $id";

    if ($conexion->query($query)) {
        echo "<script>alert('Usuario eliminado correctamente.'); window.location='ver_usuarios.php';</script>";
    } else {
        echo "Error al eliminar: " . $conexion->error;
    }
} else {
    echo "ID no válido.";
}
