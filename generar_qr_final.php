<?php
require 'phpqrcode/qrlib.php';

$id = $_POST['id'] ?? '';
$dni = $_POST['dni'] ?? '';
$nombre = $_POST['nombre'] ?? '';

if (!$id || !$dni || !$nombre) {
    die("Faltan datos");
}

$contenido = "ID:$id | DNI:$dni | $nombre";

// Ruta de guardado
$ruta_qr = "qrs/cliente_" . $id . ".png";
if (!file_exists('qrs')) {
    mkdir('qrs');
}

// Generar el código QR (sin GD, usa directamente PNG desde QRlib)
QRcode::png($contenido, $ruta_qr, QR_ECLEVEL_L, 4);

echo "<!DOCTYPE html>
<html lang='es'>
<head>
<meta charset='UTF-8'>
<title>QR generado</title>
<style>
    body { background-color: #111; color: gold; font-family: Arial; text-align: center; padding-top: 50px; }
    img { margin-top: 20px; border: 4px solid gold; padding: 10px; background: #222; }
</style>
</head>
<body>
<h1>QR generado correctamente</h1>
<p>$contenido</p>
<img src='$ruta_qr' alt='Código QR'>
<br><br>
<a href='generar_qr.php' style='color: gold;'>← Volver</a>
</body>
</html>";
?>
