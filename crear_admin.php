<?php
include 'conexion.php';

// Verificar si ya existe un admin
$resultado = $conexion->query("SELECT * FROM usuarios WHERE rol = 'admin'");
if ($resultado->num_rows > 0) {
    echo "Ya existe un administrador registrado.";
    exit;
}

// Buscar el primer gimnasio disponible
$gimnasio = $conexion->query("SELECT id FROM gimnasios LIMIT 1");
if ($gimnasio->num_rows == 0) {
    echo "No hay gimnasios registrados. Debes crear un gimnasio primero.";
    exit;
}

$row = $gimnasio->fetch_assoc();
$gimnasio_id = $row['id'];

// Crear el usuario administrador
$usuario = 'admin';
$clave = '1234'; // Contraseña inicial
$clave_encriptada = password_hash($clave, PASSWORD_BCRYPT);
$rol = 'admin';

$stmt = $conexion->prepare("INSERT INTO usuarios (usuario, clave, rol, gimnasio_id) VALUES (?, ?, ?, ?)");
$stmt->bind_param("sssi", $usuario, $clave_encriptada, $rol, $gimnasio_id);

if ($stmt->execute()) {
    echo "Administrador creado con éxito. Usuario: <b>admin</b> | Contraseña: <b>1234</b>";
} else {
    echo "Error al crear administrador: " . $stmt->error;
}

$stmt->close();
?>
