<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

if (!isset($_GET['id'])) {
    echo "ID no proporcionado.";
    exit;
}

$id = intval($_GET['id']);

// Eliminar primero los adicionales relacionados
$conexion->query("DELETE FROM membresia_adicionales WHERE membresia_id = $id");

// Luego eliminar la membresía
if ($conexion->query("DELETE FROM membresias WHERE id = $id")) {
    header("Location: ver_membresias.php?mensaje=eliminada");
    exit;
} else {
    echo "Error al eliminar la membresía.";
}
?>
