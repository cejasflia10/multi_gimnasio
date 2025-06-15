<?php
session_start();
include "conexion.php";

// Mostrar errores en desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$mensaje = '';

// Procesar formulario si se envió
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = $_POST['usuario'] ?? '';
    $contrasena = $_POST['contrasena'] ?? '';

    if (!$usuario || !$contrasena) {
        $mensaje = "Por favor complete ambos campos.";
    } else {
        $consulta = "SELECT * FROM usuarios WHERE nombre_usuario = ?";
        $stmt = $conexion->prepare($consulta);
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows === 1) {
            $row = $resultado->fetch_assoc();
            $contrasena_sha256 = hash('sha256', $contrasena);
            if ($contrasena_sha256 === $row['contrasena']) {
                $_SESSION['usuario'] = $row['nombre_usuario'];
                $_SESSION['rol'] = $row['rol'];
                $_SESSION['id_gimnasio'] = $row['id_gimnasio'] ?? null;
                header("Location: index.php");
                exit();
            } else {
                $mensaje = "Contraseña incorrecta.";
            }
        } else {
            $mensaje = "Usuario no encontrado.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login - Multi Gimnasio</title>
    <style>
        body {
            background-color: #111;
            color: #f1f1f1;
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            flex-direction: column;
        }
        input, button {
            margin: 5px;
            padding: 8px;
            font-size: 16px;
        }
        .mensaje {
            color: red;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <h2>Ingreso al sistema</h2>
    <?php if (!empty($mensaje)): ?>
        <div class="mensaje"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <form method="POST" action="">
        <input type="text" name="usuario" placeholder="Usuario" required><br>
        <input type="password" name="contrasena" placeholder="Contraseña" required><br>
        <button type="submit">Ingresar</button>
    </form>
</body>
</html>
