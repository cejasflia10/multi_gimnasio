<?php
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = trim($_POST['dni']);
    $query = $conexion->query("SELECT * FROM clientes WHERE dni = '$dni'");

    if ($query && $query->num_rows === 1) {
        $cliente = $query->fetch_assoc();
        $cliente_id = $cliente['id'];
        $gimnasio_id = $cliente['gimnasio_id'];
        header("Location: panel_cliente.php?cliente_id=$cliente_id&gimnasio_id=$gimnasio_id");
        exit;
    } else {
        echo "<script>alert('DNI no encontrado'); window.location='login_cliente.php';</script>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Ingreso Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { background: #000; color: gold; font-family: Arial; text-align: center; padding-top: 100px; }
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
