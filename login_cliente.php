<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = trim($_POST['dni']);

    $resultado = $conexion->query("SELECT * FROM clientes WHERE dni = '$dni'");

    if ($resultado && $resultado->num_rows > 0) {
        $cliente = $resultado->fetch_assoc();
        $_SESSION['cliente_id'] = $cliente['id'];
        $_SESSION['cliente_nombre'] = $cliente['nombre'] . ' ' . $cliente['apellido'];
        header("Location: panel_cliente.php");
        exit;
    } else {
        $error = "DNI no encontrado.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ingreso Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 40px;
        }
        input {
            padding: 10px;
            font-size: 18px;
            width: 80%;
            max-width: 300px;
        }
        button {
            padding: 10px 20px;
            background-color: gold;
            border: none;
            font-weight: bold;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <h2>Ingresar con DNI</h2>
    <form method="POST">
        <input type="number" name="dni" placeholder="Ingrese su DNI" required><br>
        <button type="submit">Ingresar</button>
    </form>
    <?php if (isset($error)) echo "<p style='color: red;'>$error</p>"; ?>
</body>
</html>
