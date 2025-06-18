<?php
include 'conexion.php';

// Eliminar cualquier usuario admin anterior
$conexion->query("DELETE FROM usuarios WHERE TRIM(rol) = 'admin'");

// Obtener el primer gimnasio disponible
$gimnasio = $conexion->query("SELECT id FROM gimnasios LIMIT 1");
if ($gimnasio->num_rows == 0) {
    die("⚠️ No hay gimnasios registrados. Primero debes crear uno.");
}
$gimnasio_id = $gimnasio->fetch_assoc()['id'];

// Crear nuevo usuario admin
$usuario = 'admin';
$contrasena = '1234';
$rol = 'admin';
$contrasena_hash = password_hash($contrasena, PASSWORD_BCRYPT);

$stmt = $conexion->prepare("INSERT INTO usuarios (usuario, contrasena, rol, id_gimnasio) VALUES (?, ?, ?, ?)");
$stmt->bind_param("sssi", $usuario, $contrasena_hash, $rol, $gimnasio_id);

if ($stmt->execute()) {
    echo "✅ Usuario admin creado correctamente.<br><b>Usuario:</b> admin<br><b>Contraseña:</b> 1234";
} else {
    echo "❌ Error al crear admin: " . $stmt->error;
}
?>
