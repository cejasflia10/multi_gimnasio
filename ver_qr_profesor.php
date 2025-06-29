<?php
session_start();
require_once 'phpqrcode/qrlib.php';

// Validar DNI recibido
$dni = $_GET['dni'] ?? '';
if (!$dni) {
    die("DNI no proporcionado.");
}

// Contenido del QR
$codigo = 'P-' . $dni;

// Encabezado correcto
header('Content-Type: image/png');

// Evitar errores de salida previa
ob_clean();
flush();

// Generar y mostrar QR
QRcode::png($codigo, null, 'H', 10);
exit;
?>
