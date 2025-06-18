<?php
include 'conexion.php';

$usuario = "admin";
$clave = password_hash("admin123", PASSWORD_BCRYPT);
$rol = "admin";
$gimnasio_id = 1; // o el ID correspondiente

// Verificar si ya existe
$stmt = $conexion->prepare("SELECT id FROM usuarios WHERE usuario = ?");
$stmt->bind_param("s", $usuario);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo "⚠️ El usuario ya existe.";
} else {
    $stmt->close();
    $stmt = $conexion->prepare("INSERT INTO usuarios (usuario, clave, rol, gimnasio_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $usuario, $clave, $rol, $gimnasio_id);
    if ($stmt->execute()) {
        echo "✅ Usuario administrador creado correctamente.";
    } else {
        echo "❌ Error al crear el usuario: " . $stmt->error;
    }
}
$stmt->close();
?>
