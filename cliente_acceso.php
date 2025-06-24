<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = trim($_POST['dni'] ?? '');

    if ($dni !== '') {
        $query = "SELECT * FROM clientes WHERE dni = '$dni' LIMIT 1";
        $resultado = $conexion->query($query);

        if ($resultado->num_rows > 0) {
            $cliente = $resultado->fetch_assoc();
            $_SESSION['rol'] = 'cliente';
            $_SESSION['cliente_id'] = $cliente['id'];
            header("Location: panel_cliente.php");
            exit;
        } else {
            $error = "DNI no registrado.";
        }
    } else {
        $error = "Debe ingresar un DNI válido.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acceso Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 50px;
        }
        form {
            background-color: #111;
            display: inline-block;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px gold;
        }
        input[type="text"] {
            padding: 10px;
            font-size: 18px;
            border: none;
            border-radius: 5px;
            width: 200px;
            margin-bottom: 15px;
        }
        button {
            background-color: gold;
            color: black;
            border: none;
            padding: 10px 20px;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
        }
        .error {
            color: red;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <h2>Acceso al Panel del Cliente</h2>
    <form method="POST">
        <input type="text" name="dni" placeholder="Ingresá tu DNI" required><br>
        <button type="submit">Ingresar</button>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
    </form>
</body>
</html>
