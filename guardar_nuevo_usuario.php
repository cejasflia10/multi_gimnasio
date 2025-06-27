<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include("conexion.php");

if (
    isset($_POST['nombre_usuario'], $_POST['contrasena'], $_POST['rol'], $_POST['gimnasio_id']) &&
    !empty($_POST['nombre_usuario']) && !empty($_POST['contrasena'])
) {
    $nombre_usuario = $conexion->real_escape_string(trim($_POST['nombre_usuario']));
    $contrasena = $conexion->real_escape_string($_POST['contrasena']);
    $rol = $conexion->real_escape_string($_POST['rol']);
    $gimnasio_id = intval($_POST['gimnasio_id']);
    $permisos = isset($_POST['permisos']) ? implode(',', $_POST['permisos']) : '';

    // Encriptar la contraseña
    $hashed = password_hash($contrasena, PASSWORD_DEFAULT);

    $query = "INSERT INTO usuarios (nombre_usuario, contrasena, rol, gimnasio_id, permisos)
              VALUES ('$nombre_usuario', '$hashed', '$rol', $gimnasio_id, '$permisos')";

    if ($conexion->query($query)) {
        echo "<script>alert('Usuario creado correctamente.'); window.location='ver_usuarios.php';</script>";
    } else {
        echo "Error al crear el usuario: " . $conexion->error;
    }
} else {
    echo "Faltan datos obligatorios.";
}
