<?php
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $nombre = $_POST['nombre_usuario'];
    $email = $_POST['email'];
    $rol = $_POST['rol'];
    $id_gimnasio = $_POST['id_gimnasio'];

    $consulta = "UPDATE usuarios SET nombre_usuario=?, email=?, rol=?, id_gimnasio=? WHERE id=?";
    $stmt = $conexion->prepare($consulta);
    $stmt->bind_param("sssii", $nombre, $email, $rol, $id_gimnasio, $id);

    if ($stmt->execute()) {
        header("Location: usuarios.php?mensaje=actualizado");
    } else {
        echo "Error al actualizar.";
    }
}
?>
