<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

// Verificamos si ya existe el usuario admin
$usuario = 'admin';
$contrasena_plana = 'admin123';
$rol = 'Administrador';

$sql_check = "SELECT * FROM usuarios WHERE nombre_usuario = ?";
$stmt_check = $conexion->prepare($sql_check);
$stmt_check->bind_param("s", $usuario);
$stmt_check->execute();
$resultado = $stmt_check->get_result();

if ($resultado->num_rows > 0) {
    echo "El usuario 'admin' ya existe.";
} else {
    $contrasena_hash = password_hash($contrasena_plana, PASSWORD_DEFAULT);
    
    $sql = "INSERT INTO usuarios (nombre_usuario, contrasena, rol) VALUES (?, ?, ?)";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("sss", $usuario, $contrasena_hash, $rol);

    if ($stmt->execute()) {
        echo "Usuario 'admin' creado correctamente.<br>Usuario: admin<br>Contraseña: admin123";
    } else {
        echo "Error al crear el usuario: " . $stmt->error;
    }
}

$stmt_check->close();
$stmt->close();
$conexion->close();
?>
