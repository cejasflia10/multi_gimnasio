<?php
include 'conexion.php';
session_start();
$rol = $_SESSION['rol'] ?? '';
if (!in_array($rol, ['cliente','admin', 'profesor'])) {
    die("Acceso denegado.");
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = $_POST['dni'] ?? '';

    if ($dni) {
        $resultado = $conexion->query("SELECT * FROM clientes WHERE dni = '$dni' LIMIT 1");

        if ($resultado && $resultado->num_rows > 0) {
            $cliente = $resultado->fetch_assoc();
            $_SESSION['cliente_id'] = $cliente['id'];
            header("Location: panel_cliente.php");
            exit;
        } else {
            $error = "DNI no encontrado.";
        }
    } else {
        $error = "Debe ingresar su DNI.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ingreso de Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
            text-align: center;
        }
        input, button {
            padding: 12px;
            margin: 10px;
            font-size: 18px;
        }
    </style>
</head>
<body>
    <h2>Ingresar con DNI</h2>
    <form method="post">
        <input type="text" name="dni" placeholder="Ingrese su DNI" required><br>
        <button type="submit">Ingresar</button>
    </form>
    <?php if (isset($error)) echo "<p style='color: red;'>$error</p>"; ?>
</body>
</html>
