<?php
include 'conexion.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $consulta = "DELETE FROM usuarios WHERE id=?";
    $stmt = $conexion->prepare($consulta);
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        header("Location: usuarios.php?mensaje=eliminado");
    } else {
        echo "Error al eliminar.";
    }
}
?>
