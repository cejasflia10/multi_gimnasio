<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0);
    session_start();
}
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

$mensaje = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['dni'])) {
    $dni = trim($_POST['dni']);
    $consulta = $conexion->query("SELECT * FROM clientes WHERE dni = '$dni'");
    $cliente = $consulta->fetch_assoc();
    if (!$cliente) {
        $mensaje = "❌ DNI no encontrado.";
    } else {
        $_SESSION['cliente_id'] = $cliente['id'];
        $_SESSION['cliente_nombre'] = $cliente['apellido'] . ' ' . $cliente['nombre'];
        header("Location: panel_cliente.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ingreso Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { margin: 0; background: #000; color: gold; font-family: Arial, sans-serif;
               display: flex; justify-content: center; align-items: center; height: 100vh; }
        .contenedor { background: #111; padding: 30px; border-radius: 10px;
                      box-shadow: 0 0 10px gold; text-align: center; }
        input[type="text"] { padding: 12px; font-size: 18px; width: 250px; border: 1px solid gold;
                             border-radius: 5px; background: #222; color: gold; margin-bottom: 15px; }
        input[type="submit"] { padding: 12px 25px; font-size: 16px; background: gold; color: black;
                               border: none; border-radius: 5px; cursor: pointer; }
        .mensaje { color: red; margin-top: 15px; }
    </style>
</head>
<body>
<div class="contenedor">
    <h2>Acceso Cliente</h2>
    <form method="POST">
        <input type="text" name="dni" placeholder="Ingrese su DNI" required autofocus><br>
        <input type="submit" value="Ingresar">
    </form>
    <?php if (!empty($mensaje)): ?>
        <div class="mensaje"><?= $mensaje ?></div>
    <?php endif; ?>
</div>
</body>
</html>
