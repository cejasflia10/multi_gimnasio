<?php
include 'conexion.php';

// Eliminar cualquier admin anterior
$conexion->query("DELETE FROM usuarios WHERE TRIM(rol) = 'admin'");

// Buscar gimnasio
$gimnasio = $conexion->query("SELECT id FROM gimnasios LIMIT 1");
if ($gimnasio->num_rows == 0) {
    die("⚠️ No hay gimnasios disponibles. Primero debe crear uno.");
}
$gimnasio_id = $gimnasio->fetch_assoc()['id'];

// Crear nuevo admin
$usuario = 'admin';
$clave = '1234';
$rol = 'admin';
$clave_encriptada = password_hash($clave, PASSWORD_BCRYPT);

$stmt = $conexion->prepare("INSERT INTO usuarios (usuario, contrasena, rol, id_gimnasio) VALUES (?, ?, ?, ?)");
$stmt->bind_param("sssi", $usuario, $clave_encriptada, $rol, $gimnasio_id);

if ($stmt->execute()) {
    echo "✅ Usuario admin creado correctamente.<br>Usuario: <b>admin</b> - Contraseña: <b>1234</b>";
} else {
    echo "❌ Error al crear admin: " . $stmt->error;
}
?>
