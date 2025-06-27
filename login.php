<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start(); // La sesión dura hasta que se cierre el navegador
}

include("conexion.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $usuario = trim($_POST["usuario"]);
    $clave = trim($_POST["clave"]);

    $stmt = $conexion->prepare("SELECT id, contrasena, rol, id_gimnasio FROM usuarios WHERE usuario = ?");
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $usuario_data = $resultado->fetch_assoc();

        if (password_verify($clave, $usuario_data["contrasena"]) || $clave === $usuario_data["contrasena"]) {
            $_SESSION["usuario"] = $usuario;
            $_SESSION["rol"] = $usuario_data["rol"];
            $_SESSION["gimnasio_id"] = $usuario_data["id_gimnasio"];
            header("Location: index.php");
            // Si es admin, no necesita gimnasio asignado
        if ($usuario_data["rol"] === 'admin') {
            $_SESSION["gimnasio_id"] = 0;
        }
            exit();
        } else {
            $error = "Contraseña incorrecta.";
        }
    } else {
        $error = "Usuario no encontrado.";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Multi Gimnasio</title>
    <style>
        body {
            background-color: #121212;
            color: #f1c40f;
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            height: 100vh;
            justify-content: center;
            align-items: center;
        }

        .login-container {
            background-color: #1e1e1e;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.2);
            width: 90%;
            max-width: 400px;
        }

        h2 {
            text-align: center;
            margin-bottom: 25px;
            color: #f1c40f;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border: none;
            border-radius: 6px;
            background-color: #2c2c2c;
            color: white;
        }

        input[type="submit"] {
            width: 100%;
            padding: 12px;
            margin-top: 15px;
            border: none;
            border-radius: 6px;
            background-color: #f1c40f;
            color: black;
            font-weight: bold;
            cursor: pointer;
        }

        input[type="submit"]:hover {
            background-color: #d4ac0d;
        }

        .error {
            color: red;
            text-align: center;
            margin-top: 15px;
        }

        @media (max-width: 600px) {
            .login-container {
                padding: 20px;
                width: 100%;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Login</h2>
        <form action="login.php" method="POST">
            <input type="text" name="usuario" placeholder="Usuario" required>
            <input type="password" name="clave" placeholder="Contraseña" required>
            <input type="submit" value="Ingresar">
        </form>
        <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>
    </div>
</body>
</html>
