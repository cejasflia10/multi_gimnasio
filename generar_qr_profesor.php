<?php
// Importar la librería para generar QR
require_once 'phpqrcode/qrlib.php';

// Verificar si se pasó el DNI
$dni = $_GET['dni'] ?? '';

if (empty($dni)) {
    header("HTTP/1.1 400 Bad Request");
    echo "DNI no recibido.";
    exit;
}

// Contenido del QR: prefijo 'P-' seguido del DNI
$contenido_qr = 'P-' . $dni;

// Encabezado para imagen PNG
header('Content-Type: image/png');

// Generar el QR directamente al navegador (sin guardarlo)
QRcode::png($contenido_qr, false, QR_ECLEVEL_H, 8);
exit;
?>
