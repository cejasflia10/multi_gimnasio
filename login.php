<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$error = $_GET['error'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login - Multi Gimnasio</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #111;
            color: #f1f1f1;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .login-box {
            background: #222;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.3);
            width: 300px;
        }
        .login-box h2 {
            margin-bottom: 20px;
            text-align: center;
            color: #FFD700;
        }
        .login-box input {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: none;
            border-radius: 6px;
        }
        .login-box button {
            width: 100%;
            padding: 10px;
            background-color: #FFD700;
            border: none;
            border-radius: 6px;
            color: #111;
            font-weight: bold;
            cursor: pointer;
        }
        .error {
            color: #ff4d4d;
            margin-bottom: 15px;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="login-box">
    <h2>Ingreso</h2>
    <?php if ($error): ?>
        <div class="error">
            <?php
            switch ($error) {
                case '1':
                    echo "Faltan datos.";
                    break;
                case '2':
                    echo "Contraseña incorrecta.";
                    break;
                case '3':
                    echo "Usuario no encontrado.";
                    break;
                default:
                    echo "Error desconocido.";
            }
            ?>
        </div>
    <?php endif; ?>
    <form method="POST" action="login_seguro.php">
        <input type="text" name="usuario" placeholder="Usuario" required>
        <input type="password" name="contrasena" placeholder="Contraseña" required>
        <button type="submit">Ingresar</button>
    </form>
</div>
</body>
</html>
