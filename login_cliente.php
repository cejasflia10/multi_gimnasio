<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = trim($_POST['dni']);
    echo "DNI ingresado: $dni<br>";

    $query = $conexion->query("SELECT * FROM clientes WHERE dni = '$dni'");

    if ($query && $query->num_rows === 1) {
        echo "Cliente encontrado. Iniciando sesión...<br>";
        $cliente = $query->fetch_assoc();
        $_SESSION['cliente_id'] = $cliente['id'];
        $_SESSION['gimnasio_id'] = $cliente['gimnasio_id'];
        echo "Sesión iniciada: cliente_id = " . $_SESSION['cliente_id'] . ", gimnasio_id = " . $_SESSION['gimnasio_id'] . "<br>";
        echo "<script>setTimeout(function(){ window.location = 'panel_cliente.php'; }, 2000);</script>";
    } else {
        echo "<span style='color:red;'>❌ No se encontró el cliente con ese DNI.</span><br>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Ingreso Cliente (Debug)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { background: #111; color: gold; font-family: Arial; text-align: center; padding-top: 80px; }
        input, button { padding: 10px; font-size: 16px; margin-top: 10px; }
    </style>
</head>
<body>
    <h2>Acceso Cliente</h2>
    <form method="POST">
        <input type="text" name="dni" placeholder="Ingresá tu DNI" required><br>
        <button type="submit">Ingresar</button>
    </form>
</body>
</html>
