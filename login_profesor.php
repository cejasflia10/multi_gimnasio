<?php
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // poner en 1 si usás HTTPS con dominio SSL
session_start();
include 'conexion.php';

$_SESSION['profesor_id'] = $prof['id'];
$_SESSION['profesor_nombre'] = $prof['apellido'] . ' ' . $prof['nombre'];

header("Location: panel_profesor.php");
exit;

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = trim($_POST['dni'] ?? '');

    if (!empty($dni)) {
        $stmt = $conexion->prepare("SELECT id, apellido, nombre FROM profesores WHERE dni = ?");
        $stmt->bind_param("s", $dni);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            $prof = $res->fetch_assoc();
            $_SESSION['profesor_id'] = $prof['id'];
            $_SESSION['profesor_nombre'] = $prof['apellido'] . ' ' . $prof['nombre'];

            header("Location: panel_profesor.php");
            exit;
        } else {
            $mensaje = "❌ DNI no encontrado.";
        }
    } else {
        $mensaje = "❌ Ingrese un DNI válido.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login Profesor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #000;
            color: gold;
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .contenedor {
            background: #111;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px gold;
            text-align: center;
        }
        input[type="text"] {
            padding: 12px;
            font-size: 18px;
            width: 250px;
            border: 1px solid gold;
            border-radius: 5px;
            background: #222;
            color: gold;
            margin-bottom: 15px;
        }
        input[type="submit"] {
            padding: 12px 25px;
            font-size: 16px;
            background: gold;
            color: black;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .mensaje {
            color: red;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="contenedor">
        <h2>Ingreso Profesor</h2>
        <form method="POST">
            <input type="text" name="dni" placeholder="Ingrese DNI" autofocus required><br>
            <input type="submit" value="Entrar">
        </form>
        <?php if (!empty($mensaje)): ?>
            <div class="mensaje"><?= $mensaje ?></div>
        <?php endif; ?>
    </div>
</body>
</html>
