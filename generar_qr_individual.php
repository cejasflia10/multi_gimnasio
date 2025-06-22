<?php
ob_start();
session_start();
include 'conexion.php';
require 'phpqrcode/qrlib.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID inválido.");
}

$id = intval($_GET['id']);
$gimnasio_id = $_SESSION['gimnasio_id'] ?? null;
$rol = $_SESSION['rol'] ?? '';

if (!$gimnasio_id && $rol !== 'admin') {
    die("Acceso denegado.");
}

$query = ($rol === 'admin')
    ? "SELECT * FROM clientes WHERE id = $id"
    : "SELECT * FROM clientes WHERE id = $id AND gimnasio_id = $gimnasio_id";

$resultado = $conexion->query($query);

if (!$resultado || $resultado->num_rows === 0) {
    die("Cliente no encontrado o acceso denegado.");
}

$cliente = $resultado->fetch_assoc();
$apellido = $cliente['apellido'];
$nombre = $cliente['nombre'];
$dni = $cliente['dni'];

// Crear carpeta si no existe
$carpeta = "qr_clientes";
if (!file_exists($carpeta)) {
    mkdir($carpeta, 0777, true);
}

$nombre_archivo = "$carpeta/{$apellido}_{$nombre}_{$dni}.png";

// Generar QR de forma segura
QRcode::png($dni, $nombre_archivo, QR_ECLEVEL_L, 6);

echo "<script>alert('QR generado correctamente'); window.location.href='ver_clientes.php';</script>";
ob_end_flush();
?>
