<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dni'])) {
    $dni = trim($_POST['dni']);
    $query = $conexion->query("SELECT * FROM clientes WHERE dni = '$dni'");
    $cliente = $query->fetch_assoc();

    if ($cliente) {
        $_SESSION['cliente_id'] = $cliente['id'];
        $_SESSION['cliente_dni'] = $cliente['dni'];
        $_SESSION['cliente_nombre'] = $cliente['nombre'];
        $_SESSION['cliente_apellido'] = $cliente['apellido'];
        $_SESSION['gimnasio_id'] = $cliente['gimnasio_id']; // muy importante en multi-gimnasio

        header("Location: panel_cliente.php");
        exit;
    } else {
        $mensaje = "DNI no encontrado. Por favor verificalo.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ingreso al Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 30px;
            text-align: center;
        }
        input, button {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            font-size: 18px;
            border-radius: 8px;
            border: none;
        }
        button {
            background-color: gold;
            color: black;
            font-weight: bold;
            cursor: pointer;
        }
        .error {
            color: red;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <h2>🔐 Ingresá tu DNI</h2>
    <form method="POST">
        <input type="text" name="dni" placeholder="DNI" required>
        <button type="submit">Ingresar</button>
    </form>

    <?php if ($mensaje): ?>
        <div class="error"><?= $mensaje ?></div>
    <?php endif; ?>
</body>
</html>
