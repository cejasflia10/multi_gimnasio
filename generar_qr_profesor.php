<?php
require_once 'phpqrcode/qrlib.php';

// Verificamos si se pasa el DNI por GET
if (!isset($_GET['dni']) || empty($_GET['dni'])) {
    die("DNI no proporcionado.");
}

$dni = trim($_GET['dni']); // Limpieza mínima

// Establecer cabecera de imagen PNG
header('Content-Type: image/png');

// Contenido del QR: debe incluir el prefijo "P-"
$contenido_qr = 'P-' . $dni;

// Generar el QR en pantalla directamente
QRcode::png($contenido_qr, false, QR_ECLEVEL_H, 10);
