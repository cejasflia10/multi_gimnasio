<?php
include 'conexion.php';

$usuario = 'admin';
$contrasena = password_hash('admin123', PASSWORD_BCRYPT);
$rol = 'admin';
$gimnasio_id = 1; // ID del gimnasio que corresponda

// Verifica si ya existe el usuario admin
$stmt = $conexion->prepare("SELECT id FROM usuarios WHERE usuario = ?");
$stmt->bind_param("s", $usuario);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo "⚠️ El usuario administrador ya existe.";
} else {
    $stmt->close();

    $stmt = $conexion->prepare("INSERT INTO usuarios (usuario, contrasena, rol, gimnasio_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $usuario, $contrasena, $rol, $gimnasio_id);

    if ($stmt->execute()) {
        echo "✅ Usuario administrador creado correctamente.";
    } else {
        echo "❌ Error al crear usuario: " . $stmt->error;
    }
    $stmt->close();
}
?>
