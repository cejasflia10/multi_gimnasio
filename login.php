<?php
session_start();
if (isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ingreso al sistema</title>
    <style>
        body {
            background-color: #111;
            color: #fff;
            font-family: Arial, sans-serif;
            text-align: center;
            margin-top: 100px;
        }
        form {
            background-color: #222;
            padding: 20px;
            border-radius: 10px;
            display: inline-block;
        }
        input {
            display: block;
            margin: 10px auto;
            padding: 8px;
            width: 200px;
        }
        .error {
            color: red;
        }
    </style>
</head>
<body>
    <h2>Ingreso al sistema</h2>
    <?php
    if (isset($_GET['error'])) {
        echo '<p class="error">Usuario o contraseña incorrectos.</p>';
    }
    ?>
    <form action="procesar_login.php" method="POST">
        <input type="text" name="usuario" placeholder="Usuario" required>
        <input type="password" name="contrasena" placeholder="Contraseña" required>
        <input type="submit" value="Ingresar">
    </form>
</body>
</html>
