<?php
session_start();
require_once 'phpqrcode/qrlib.php';

// Obtener el DNI del profesor desde la URL
$dni = $_GET['dni'] ?? '';
if (!$dni) {
    die("DNI no proporcionado.");
}

$codigo = 'P-' . $dni;

// Encabezado para que el navegador sepa que es una imagen
header('Content-Type: image/png');

// Generar QR en tiempo real (no se guarda)
QRcode::png($codigo, null, 'H', 10);
exit;
