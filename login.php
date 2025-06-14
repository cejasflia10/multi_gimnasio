<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$mensaje_error = "";
if (isset($_SESSION['error_login'])) {
    $mensaje_error = $_SESSION['error_login'];
    unset($_SESSION['error_login']);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar sesión</title>
    <style>
        body {
            background-color: #111;
            color: #f1f1f1;
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .login-container {
            background-color: #222;
            padding: 30px 40px;
            border-radius: 12px;
            box-shadow: 0 0 15px #ffc107;
            width: 300px;
            text-align: center;
        }

        h2 {
            margin-bottom: 20px;
            color: #ffc107;
        }

        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 10px;
            margin: 8px 0 16px;
            border: none;
            border-radius: 6px;
            box-sizing: border-box;
        }

        input[type="submit"] {
            background-color: #ffc107;
            color: #111;
            border: none;
            padding: 10px;
            width: 100%;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }

        .error {
            color: red;
            margin-bottom: 15px;
        }

        .logo {
            margin-bottom: 20px;
        }

        .logo img {
            width: 100px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <img src="logo.png" alt="Logo Gimnasio">
        </div>
        <h2>Login</h2>
        <?php if ($mensaje_error): ?>
            <div class="error"><?php echo $mensaje_error; ?></div>
        <?php endif; ?>
        <form action="login_seguro.php" method="post">
            <input type="text" name="usuario" placeholder="Usuario" required>
            <input type="password" name="contrasena" placeholder="Contraseña" required>
            <input type="submit" value="Ingresar">
        </form>
    </div>
</body>
</html>
