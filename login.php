<?php
session_start();
include 'conexion.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = $_POST['usuario'];
    $contrasena = $_POST['contrasena'];

    $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE nombre_usuario = ?");
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows == 1) {
        $row = $resultado->fetch_assoc();

        if ($contrasena === $row['contrasena']) {
            $_SESSION['usuario'] = $row['nombre_usuario'];
            $_SESSION['rol'] = $row['rol'];
            $_SESSION['id_gimnasio'] = $row['id_gimnasio'];
            header("Location: index.php");
            exit();
        } else {
            $error = "Contraseña incorrecta.";
        }
    } else {
        $error = "Usuario no encontrado.";
    }

    $stmt->close();
    $conexion->close();
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
            align-items: center;
            justify-content: center;
            height: 100vh;
        }
        .login-box {
            background-color: #222;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px #000;
            text-align: center;
            width: 300px;
        }
        input {
            width: 100%;
            margin-bottom: 15px;
            padding: 10px;
            border: none;
            border-radius: 5px;
        }
        .btn {
            background-color: #ffc107;
            color: black;
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
    <h2>Ingreso al sistema</h2>
    <?php if ($error): ?>
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>
    <form method="post" action="login.php">
        <input type="text" name="usuario" placeholder="Usuario" required>
        <input type="password" name="contrasena" placeholder="Contraseña" required>
        <input type="submit" value="Ingresar" class="btn">
    </form>
</div>
</body>
</html>
