<?php
include 'conexion.php';

$usuario = 'admin';
$contrasena_plana = 'admin123';
$rol = 'Admin';

// Verificar si el usuario ya existe
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

    if ($stmt) {
        $stmt->bind_param("sss", $usuario, $contrasena_hash, $rol);
        if ($stmt->execute()) {
            echo "✅ Usuario 'admin' creado correctamente.<br>Usuario: admin<br>Contraseña: admin123";
        } else {
            echo "❌ Error al crear el usuario: " . $stmt->error;
        }
        $stmt->close();
    } else {
        echo "❌ Error en la preparación del statement.";
    }
}

$stmt_check->close();
$conexion->close();
?>
