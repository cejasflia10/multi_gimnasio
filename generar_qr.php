<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
session_start();

if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado.");
}

include "phpqrcode/qrlib.php";

// Validar DNI
if (!isset($_GET['dni'])) {
    echo "DNI no especificado.";
    exit;
}

$dni = $_GET['dni'];
$dir = 'temp/';
if (!file_exists($dir)) {
    mkdir($dir);
}
$filename = $dir . 'qr_' . $dni . '.png';

// Generar QR con solo el DNI
QRcode::png($dni, $filename);

echo "<h3>QR generado</h3>";
echo "<img src='$filename'>";
echo "<br><a href='menu.php'>Volver</a>";
