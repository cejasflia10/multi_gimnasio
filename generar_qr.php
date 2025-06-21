<?php
include 'conexion.php';
require_once 'phpqrcode/qrlib.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST["dni"])) {
    $dni = trim($_POST["dni"]);
    $filename = "temp_qr_" . $dni . ".png";
    QRcode::png($dni, $filename);

    echo "<h2>QR generado</h2>";
    echo "<img src='$filename' /><br><a href='generar_qr.php'>Volver</a>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Generar QR del Cliente</title>
    <style>
        body { background: #000; color: gold; text-align: center; padding-top: 50px; font-family: sans-serif; }
        input { padding: 10px; font-size: 16px; }
    </style>
</head>
<body>
    <h1>Generar QR del Cliente</h1>
    <form method="POST">
        <input type="text" name="dni" placeholder="DNI del cliente" required />
        <input type="submit" value="Generar QR" />
    </form>
</body>
</html>
