<?php
include 'conexion.php';

$usuario = 'admin';
$clave = 'admin123';
$rol = 'superadmin';
$gimnasio_id = 0;
$email = 'admin@multi.com';

// Verificar si ya existe
$verificar = $conexion->prepare("SELECT id FROM usuarios WHERE usuario = ?");
$verificar->bind_param("s", $usuario);
$verificar->execute();
$verificar->store_result();

if ($verificar->num_rows > 0) {
    $mensaje = "🟡 El superusuario ya existe.";
} else {
    // Encriptar la contraseña con password_hash
    $clave_segura = password_hash($clave, PASSWORD_DEFAULT);

    $insertar = $conexion->prepare("INSERT INTO usuarios (usuario, clave, rol, gimnasio_id, email) VALUES (?, ?, ?, ?, ?)");
    $insertar->bind_param("sssis", $usuario, $clave_segura, $rol, $gimnasio_id, $email);
    $insertar->execute();

    $mensaje = "✅ Superusuario creado correctamente.<br>Usuario: <b>admin</b><br>Contraseña: <b>admin123</b>";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Superusuario</title>
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 30px;
            text-align: center;
        }

        .mensaje {
            background-color: #111;
            border: 1px solid gold;
            border-radius: 10px;
            padding: 20px;
            display: inline-block;
            font-size: 18px;
        }

        a {
            color: lightgreen;
            display: block;
            margin-top: 20px;
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="mensaje">
        <?= $mensaje ?>
    </div>
    <a href="index.php">⬅ Volver al inicio</a>
</body>
</html>
