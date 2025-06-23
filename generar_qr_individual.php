<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
require_once 'phpqrcode/qrlib.php'; // Ruta a la librería QR

if (!isset($_GET['id'])) {
    die("ID no proporcionado.");
}

$id = intval($_GET['id']);

// Obtener datos del cliente
$query = "SELECT dni FROM clientes WHERE id = $id";
$resultado = $conexion->query($query);

if ($resultado->num_rows === 0) {
    die("Cliente no encontrado.");
}

$cliente = $resultado->fetch_assoc();
$dni = $cliente['dni'];

// Generar código QR con el DNI
$qr_data = $dni;
$qr_nombre_archivo = "qr/cliente_" . $id . ".png";

// Crear directorio si no existe
if (!file_exists('qr')) {
    mkdir('qr', 0777, true);
}

// Generar el QR y guardarlo
QRcode::png($qr_data, $qr_nombre_archivo, QR_ECLEVEL_L, 8);

// Redirigir de vuelta
header("Location: ver_clientes.php");
exit;
