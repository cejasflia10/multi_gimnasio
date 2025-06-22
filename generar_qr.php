<?php
session_start();
require 'phpqrcode/qrlib.php';

if (!isset($_GET['dni'])) {
    die("DNI no especificado.");
}

$dni = $_GET['dni'];
$carpeta = "qrs/";

if (!file_exists($carpeta)) {
    mkdir($carpeta, 0777, true);
}

$archivo_qr = $carpeta . $dni . ".png";
QRcode::png($dni, $archivo_qr, QR_ECLEVEL_L, 6);

header("Location: ver_clientes.php");
exit;
