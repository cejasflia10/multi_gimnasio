<?php
include 'conexion.php';

$usuario = "admin";
$clave = password_hash("admin123", PASSWORD_BCRYPT);
$rol = "admin";
$gimnasio_id = 1; // el ID del gimnasio principal o general

$stmt = $conexion->prepare("INSERT INTO usuarios (usuario, clave, rol, gimnasio_id) VALUES (?, ?, ?, ?)");
$stmt->bind_param("sssi", $usuario, $clave, $rol, $gimnasio_id);

if ($stmt->execute()) {
    echo "Usuario administrador creado correctamente.";
} else {
    echo "Error: " . $stmt->error;
}
$stmt->close();
?>
