<?php
session_start();
include 'conexion.php';
require 'phpqrcode/qrlib.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Cliente no válido.");
}

$id = intval($_GET['id']);
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$rol = $_SESSION['rol'] ?? '';

if ($rol === 'admin') {
    $query = "SELECT * FROM clientes WHERE id = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("i", $id);
} else {
    $query = "SELECT * FROM clientes WHERE id = ? AND gimnasio_id = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("ii", $id, $gimnasio_id);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Cliente no encontrado o acceso denegado.");
}

$cliente = $result->fetch_assoc();
$apellido = $cliente['apellido'];
$nombre = $cliente['nombre'];
$dni = $cliente['dni'];

$carpeta = "qr_clientes";
if (!file_exists($carpeta)) {
    mkdir($carpeta, 0777, true);
}

$archivo_qr = $carpeta . "/" . $apellido . "_" . $nombre . "_" . $dni . ".png";

// ✅ GENERAR QR CORRECTAMENTE (corregido)
QRcode::png($dni, $archivo_qr, QR_ECLEVEL_L, 6, 2, false, 0xFFFFFF, 0x000000);

echo "<script>alert('QR generado correctamente.'); window.location.href='ver_clientes.php';</script>";
?>
