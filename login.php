<?php
session_start();
include("conexion.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = $_POST['usuario'];
    $clave = $_POST['clave'];

    $stmt = $conexion->prepare("SELECT id, usuario, password, rol FROM usuarios WHERE usuario = ?");
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $row = $resultado->fetch_assoc();
        
        if (password_verify($clave, $row['password']) || $clave === $row['password']) {
            $_SESSION['usuario_id'] = $row['id'];
            $_SESSION['usuario'] = $row['usuario'];
            $_SESSION['rol'] = $row['rol'];

            // Redirigir según rol
            if ($row['rol'] === 'admin') {
                header("Location: index.php");
            } elseif ($row['rol'] === 'escuela') {
                header("Location: index.php");
            } elseif ($row['rol'] === 'profesor') {
                header("Location: asistencia_qr.php");
            } else {
                echo "Rol no reconocido.";
            }
            exit;
        } else {
            echo "Contraseña incorrecta.";
        }
    } else {
        echo "Usuario no encontrado.";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <style>
        body {
            background-color: #111;
            color: #fff;
            font-family: Arial, sans-serif;
            text-align: center;
            padding-top: 100px;
        }
        input {
            padding: 10px;
            margin: 10px;
            width: 250px;
            border-radius: 5px;
            border: none;
        }
        button {
            padding: 10px 20px;
            background-color: gold;
            border: none;
            border-radius: 5px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h2>Acceso al Sistema</h2>
    <form method="post">
        <input type="text" name="usuario" placeholder="Usuario" required><br>
        <input type="password" name="clave" placeholder="Contraseña" required><br>
        <button type="submit">Ingresar</button>
    </form>
</body>
</html>
