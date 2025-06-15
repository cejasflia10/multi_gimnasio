<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "conexion.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $usuario = $_POST["usuario"] ?? '';
    $contrasena = $_POST["contrasena"] ?? '';

    if (empty($usuario) || empty($contrasena)) {
        header("Location: login.php?error=1");
        exit();
    }

    $consulta = "SELECT * FROM usuarios WHERE nombre_usuario = ?";
    $stmt = $conexion->prepare($consulta);
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $row = $resultado->fetch_assoc();

        // Verificación de contraseña (soporte para hash o texto plano)
        if (
            $contrasena === $row['contrasena'] || 
            password_verify($contrasena, $row['contrasena'])
        ) {
            $_SESSION['usuario'] = $row['nombre_usuario'];
            $_SESSION['rol'] = $row['rol'];
            $_SESSION['id_gimnasio'] = $row['id_gimnasio'];
            header("Location: index.php");
            exit();
        } else {
            header("Location: login.php?error=2"); // Contraseña incorrecta
            exit();
        }
    } else {
        header("Location: login.php?error=3"); // Usuario no encontrado
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ingreso - Multi Gimnasio</title>
    <style>
        body {
            background-color: #111;
            color: #f1f1f1;
            font-family: Arial, sans-serif;
            text-align: center;
            margin-top: 100px;
        }
        .login-box {
            background: #222;
            padding: 30px;
            border-radius: 10px;
            display: inline-block;
            box-shadow: 0 0 15px #000;
        }
        input {
            width: 250px;
            padding: 10px;
            margin: 10px;
            font-size: 16px;
            border: none;
            border-radius: 5px;
        }
        button {
            background-color: gold;
            border: none;
            padding: 10px 20px;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
        }
        .error {
            color: red;
            font-weight: bold;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Ingreso al sistema</h2>
        <?php if (isset($_GET["error"])) {
            if ($_GET["error"] == 1) echo "<p class='error'>Complete todos los campos.</p>";
            if ($_GET["error"] == 2) echo "<p class='error'>Contraseña incorrecta.</p>";
            if ($_GET["error"] == 3) echo "<p class='error'>Usuario no encontrado.</p>";
        } ?>
        <form method="post" action="login.php">
            <input type="text" name="usuario" placeholder="Usuario" required><br>
            <input type="password" name="contrasena" placeholder="Contraseña" required><br>
            <button type="submit">Ingresar</button>
        </form>
    </div>
</body>
</html>
