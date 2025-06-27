<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include("conexion.php");

if (
    isset($_POST['id'], $_POST['nombre_usuario'], $_POST['rol'], $_POST['gimnasio_id']) &&
    is_numeric($_POST['id']) && is_numeric($_POST['gimnasio_id'])
) {
    $id = intval($_POST['id']);
    $nombre_usuario = $conexion->real_escape_string(trim($_POST['nombre_usuario']));
    $rol = $conexion->real_escape_string($_POST['rol']);
    $gimnasio_id = intval($_POST['gimnasio_id']);

    $query = "UPDATE usuarios 
              SET nombre_usuario = '$nombre_usuario', rol = '$rol', gimnasio_id = $gimnasio_id 
              WHERE id = $id";

    if ($conexion->query($query)) {
        echo "<script>alert('Usuario actualizado correctamente.'); window.location='ver_usuarios.php';</script>";
    } else {
        echo "Error al actualizar: " . $conexion->error;
    }
} else {
    echo "Datos incompletos.";
}
