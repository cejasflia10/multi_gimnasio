<?php
// Este archivo genera un código QR en tiempo real para un profesor.
// El contenido del QR será: P-<DNI> (por ejemplo: P-12345678)

require_once 'phpqrcode/qrlib.php';

// 1. Obtener el DNI desde el parámetro GET
$dni = $_GET['dni'] ?? '';

// 2. Validar que exista el DNI
if (empty($dni)) {
    header("HTTP/1.1 400 Bad Request");
    echo "DNI no recibido.";
    exit;
}

// 3. Definir el contenido del QR (IMPORTANTE: debe llevar el prefijo "P-")
$contenido_qr = 'P-' . $dni;

// 4. Enviar encabezado HTTP para decir que es una imagen PNG
header('Content-Type: image/png');

// 5. Generar el QR directamente como salida
QRcode::png($contenido_qr, false, QR_ECLEVEL_H, 8);
exit;
?>
