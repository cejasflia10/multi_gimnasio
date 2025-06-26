<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = trim($_POST['dni'] ?? '');

    $query = "SELECT * FROM clientes WHERE dni = '$dni'";
    $resultado = $conexion->query($query);

    if ($resultado && $resultado->num_rows > 0) {
        $cliente = $resultado->fetch_assoc();
        $_SESSION['rol'] = 'cliente_id';
        $_SESSION['cliente_id'] = $cliente['id'];

        header("Location: panel_cliente.php");
        exit;
    } else {
        $error = "DNI no encontrado. Verifica los datos.";
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
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 50px;
        }
        h2 {
            color: gold;
        }
        form {
            margin-top: 20px;
        }
        input[type="text"] {
            padding: 10px;
            font-size: 16px;
            width: 80%;
            max-width: 300px;
            border: none;
            border-radius: 5px;
        }
        input[type="submit"] {
            margin-top: 15px;
            padding: 10px 20px;
            font-size: 16px;
            background-color: gold;
            border: none;
            color: black;
            border-radius: 5px;
            cursor: pointer;
        }
        .error {
            color: red;
            margin-top: 15px;
        }
    </style>
</head>
<body>

    <h2>Ingreso de Cliente</h2>
    <form method="POST">
        <input type="text" name="dni" placeholder="Ingresá tu DNI" required><br>
        <input type="submit" value="Ingresar">
    </form>

    <?php if (isset($error)): ?>
        <p class="error"><?= $error ?></p>
    <?php endif; ?>

</body>
</html>
