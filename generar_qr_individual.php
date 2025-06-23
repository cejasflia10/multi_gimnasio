<?php
include 'conexion.php';
require 'phpqrcode/qrlib.php';

if (!isset($_GET['id'])) {
    die("ID de cliente no especificado.");
}

$id = intval($_GET['id']);
$resultado = $conexion->query("SELECT * FROM clientes WHERE id = $id");
if ($resultado->num_rows === 0) {
    die("Cliente no encontrado.");
}

$cliente = $resultado->fetch_assoc();
$dni = $cliente['dni'];

if (!is_dir('qr')) {
    mkdir('qr', 0777, true);
}

$filename = 'qr/' . $dni . '.png';
QRcode::png($dni, $filename, 'H', 6, 2);

header("Location: $filename");
exit;
