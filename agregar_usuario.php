<?php
include "conexion.php";

$usuario = $_POST['usuario'] ?? '';
$contrasena_plana = $_POST['contrasena'] ?? '';
$rol = $_POST['rol'] ?? 'admin';

$contrasena_hash = hash('sha256', $contrasena_plana);

$verificar = $conexion->prepare("SELECT id FROM usuarios WHERE nombre_usuario = ?");
$verificar->bind_param("s", $usuario);
$verificar->execute();
$resultado = $verificar->get_result();

if ($resultado->num_rows > 0) {
    echo "El usuario ya existe.";
} else {
    $sql = "INSERT INTO usuarios (nombre_usuario, contrasena, rol) VALUES (?, ?, ?)";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("sss", $usuario, $contrasena_hash, $rol);
    if ($stmt->execute()) {
        echo "Usuario creado correctamente.";
    } else {
        echo "Error al crear el usuario: " . $stmt->error;
    }
}
?>
