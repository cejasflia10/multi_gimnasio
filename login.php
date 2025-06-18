<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = trim($_POST["usuario"]);
    $clave = trim($_POST["clave"]);

    $stmt = $conexion->prepare("SELECT id, usuario, contrasena, rol, debe_cambiar_contrasena, id_gimnasio FROM usuarios WHERE usuario = ?");
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $row = $resultado->fetch_assoc();

        if (password_verify($clave, $row["contrasena"]) || $clave === $row["contrasena"]) {
            $_SESSION["usuario_id"] = $row["id"];
            $_SESSION["usuario"] = $row["usuario"];
            $_SESSION["rol"] = $row["rol"];
            $_SESSION["gimnasio_id"] = $row["id_gimnasio"];

            if ($row["debe_cambiar_contrasena"] == 1) {
                header("Location: cambiar_contrasena.php");
            } else {
                header("Location: index.php");
            }
            exit;
        }
    }

    echo "<script>alert('Usuario o contraseña incorrectos'); window.location.href='login.php';</script>";
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
            color: #FFD700;
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .login-box {
            background-color: #222;
            padding: 30px;
            border-radius: 10px;
            width: 300px;
        }
        input {
            width: 100%;
            padding: 10px;
            margin-top: 10px;
            background: #333;
            border: none;
            color: #FFD700;
            border-radius: 5px;
        }
        button {
            width: 100%;
            margin-top: 15px;
            padding: 10px;
            background: #FFD700;
            color: #111;
            font-weight: bold;
            border: none;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <form method="POST">
            <h2>Iniciar Sesión</h2>
            <input type="text" name="usuario" placeholder="Usuario" required>
            <input type="password" name="clave" placeholder="Contraseña" required>
            <button type="submit">Ingresar</button>
        </form>
    </div>
</body>
</html>
