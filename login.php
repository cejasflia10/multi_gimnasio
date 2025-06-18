<?php
session_start();
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = trim($_POST["usuario"]);
    $clave = trim($_POST["clave"]);

    $stmt = $conexion->prepare("SELECT id, usuario, contrasena, rol, debe_cambiar_contrasena FROM usuarios WHERE usuario = ?");
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $row = $resultado->fetch_assoc();

        // Acepta contraseña encriptada o en texto plano
        if (password_verify($clave, $row["contrasena"]) || $clave === $row["contrasena"]) {
            $_SESSION["usuario_id"] = $row["id"];
            $_SESSION["usuario"] = $row["usuario"];
            $_SESSION["rol"] = $row["rol"];

            if ($row["debe_cambiar_contrasena"] == 1) {
                header("Location: cambiar_contrasena.php");
            } else {
                header("Location: index.php");
            }
            exit;
        }
    }

    // Si llega hasta acá, credenciales incorrectas
    $error = "Usuario o contraseña incorrectos.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login - Fight Academy</title>
    <style>
        body {
            background-color: #111;
            color: #f1f1f1;
            font-family: Arial, sans-serif;
            text-align: center;
            padding-top: 100px;
        }

        .login-box {
            background-color: #222;
            padding: 20px;
            display: inline-block;
            border-radius: 10px;
        }

        input, button {
            margin: 10px 0;
            padding: 10px;
            font-size: 16px;
            width: 90%;
        }

        button {
            background-color: gold;
            border: none;
            font-weight: bold;
        }

        .error {
            color: red;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Login - Fight Academy</h2>
        <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>
        <form method="post">
            <input type="text" name="usuario" placeholder="Usuario" required><br>
            <input type="password" name="clave" placeholder="Contraseña" required><br>
            <button type="submit">Ingresar</button>
        </form>
    </div>
</body>
</html>
