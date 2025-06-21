<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
include("conexion.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = $_POST["usuario"];
    $clave = $_POST["clave"];

    $stmt = $conexion->prepare("SELECT id, contraseña, rol, id_gimnasio FROM usuarios WHERE usuario = ?");
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows == 1) {
        $usuario_data = $resultado->fetch_assoc();

        if ($clave === $usuario_data["contraseña"] || password_verify($clave, $usuario_data["contraseña"])) {
            $_SESSION["usuario_id"] = $usuario_data["id"];
            $_SESSION["rol"] = $usuario_data["rol"];
            $_SESSION["gimnasio_id"] = $usuario_data["id_gimnasio"];
            header("Location: index.php");
            exit();
        } else {
            $error = "Contraseña incorrecta";
        }
    } else {
        $error = "Usuario no encontrado";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login - MultiGimnasio</title>
    <style>
        body {
            background-color: #111;
            color: #ffd700;
            font-family: Arial, sans-serif;
            text-align: center;
            padding-top: 80px;
        }
        input {
            padding: 10px;
            margin: 8px;
            border-radius: 5px;
            border: none;
        }
        button {
            padding: 10px 20px;
            background-color: gold;
            border: none;
            cursor: pointer;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h2>Ingreso al Sistema</h2>
    <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
    <form method="POST" action="login.php">
        <input type="text" name="usuario" placeholder="Usuario" required><br>
        <input type="password" name="clave" placeholder="Contraseña" required><br>
        <button type="submit">Ingresar</button>
    </form>
</body>
</html>
