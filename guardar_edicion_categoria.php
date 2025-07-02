<?php
session_start();
include 'conexion.php';

if (!isset($_POST['id']) || !isset($_POST['nombre'])) {
    echo "Datos incompletos.";
    exit;
}

$id = intval($_POST['id']);
$nombre = trim($_POST['nombre']);

$stmt = $conexion->prepare("UPDATE categorias SET nombre = ? WHERE id = ?");
$stmt->bind_param("si", $nombre, $id);

if ($stmt->execute()) {
    header("Location: ver_categorias.php?actualizado=1");
    exit;
} else {
    echo "Error al actualizar categoría.";
}
?>
