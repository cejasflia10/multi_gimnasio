<?php
include 'conexion.php';
require 'phpqrcode/qrlib.php';

if (!isset($_GET['dni'])) {
    die("DNI no especificado.");
}

$dni = intval($_GET['dni']);
$codigo = "P$dni"; // SIN GUION

// Generar QR en tiempo real (Render no permite guardar archivo)
header('Content-Type: image/png');
QRcode::png($codigo, false, QR_ECLEVEL_L, 8);
?>
