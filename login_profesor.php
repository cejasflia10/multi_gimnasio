<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = trim($_POST['dni'] ?? '');

    if (!empty($dni)) {
        $stmt = $conexion->prepare("SELECT id, apellido, nombre, gimnasio_id FROM profesores WHERE dni = ?");
        $stmt->bind_param("s", $dni);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            $prof = $res->fetch_assoc();
            $_SESSION['profesor_id'] = $prof['id'];
            $_SESSION['profesor_nombre'] = $prof['apellido'] . ' ' . $prof['nombre'];
            $_SESSION['gimnasio_id'] = $prof['gimnasio_id'];
            header("Location: panel_profesor.php");
            exit;
        } else {
            $mensaje = "DNI no encontrado.";
        }
    } else {
        $mensaje = "Ingrese un DNI válido.";
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
        input[type="text"], input[type="submit"] {
            padding: 12px;
            font-size: 18px;
            width: 250px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        input[type="text"] {
            border: 1px solid gold;
            background: #222;
            color: gold;
        }
        input[type="submit"] {
            background: gold;
            color: black;
            border: none;
            cursor: pointer;
        }
        .mensaje {
            color: red;
            margin-top: 10px;
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
