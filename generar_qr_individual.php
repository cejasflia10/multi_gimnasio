<?php
include 'conexion.php';
require 'phpqrcode/qrlib.php';

if (!isset($_GET['id'])) {
    echo "ID no válido.";
    exit;
}

$id = intval($_GET['id']);
$result = $conexion->query("SELECT apellido, nombre, dni FROM clientes WHERE id = $id");

if ($result->num_rows == 0) {
    echo "Cliente no encontrado.";
    exit;
}

$cliente = $result->fetch_assoc();
$apellido = $cliente['apellido'];
$nombre = $cliente['nombre'];
$dni = $cliente['dni'];

if (!file_exists("qr_clientes")) {
    mkdir("qr_clientes", 0777, true);
}

$filename = "qr_clientes/" . $apellido . "_" . $nombre . "_" . $dni . ".png";
QRcode::png($dni, $filename, QR_ECLEVEL_L, 6);

echo "<script>alert('QR generado correctamente.'); window.location.href='ver_clientes.php';</script>";
