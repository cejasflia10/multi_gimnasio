<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['dni'])) {
    $dni = trim($_POST['dni']);
    $consulta = $conexion->query("SELECT * FROM clientes WHERE dni = '$dni'");
    $cliente = $consulta->fetch_assoc();

    if (!$cliente) {
        $mensaje = "DNI no encontrado.";
    } else {
        $cliente_id = $cliente['id'];
        $gimnasio_id = $cliente['gimnasio_id'];
        $membresia_q = $conexion->query("SELECT * FROM membresias 
            WHERE cliente_id = $cliente_id 
            AND fecha_vencimiento >= CURDATE() 
            AND clases_disponibles > 0
            ORDER BY id DESC LIMIT 1");

        if ($membresia_q->num_rows === 0) {
            $mensaje = "No tenés una membresía activa o sin clases disponibles.";
        } else {
            $_SESSION['cliente_id'] = $cliente_id;
            $_SESSION['cliente_nombre'] = $cliente['nombre'];
            $_SESSION['cliente_apellido'] = $cliente['apellido'];
            $_SESSION['gimnasio_id'] = $gimnasio_id;
            $_SESSION['rol'] = 'cliente';

            header("Location: panel_cliente.php");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Login Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { background: #000; color: gold; font-family: Arial, sans-serif; text-align: center; padding: 30px; }
        input, button { padding: 10px; font-size: 18px; margin: 10px; width: 80%; max-width: 300px; }
        button { background-color: gold; color: black; border: none; border-radius: 6px; cursor: pointer; }
    </style>
</head>
<body>

    <h2>🎟️ Ingresar al Panel del Cliente</h2>
    <form method="POST">
        <input type="text" name="dni" placeholder="Ingresá tu DNI" required><br>
        <button type="submit">Ingresar</button>
    </form>

    <?php if (!empty($mensaje)): ?>
        <p style="color:red;"><?= $mensaje ?></p>
    <?php endif; ?>

</body>
</html>
