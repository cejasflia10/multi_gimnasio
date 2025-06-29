<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'conexion.php';
require_once 'phpqrcode/qrlib.php';

if (!isset($_GET['id'])) {
    die("ID de profesor no especificado.");
}

$id = intval($_GET['id']);
$profesor_q = $conexion->query("SELECT dni FROM profesores WHERE id = $id");

if ($profesor_q->num_rows === 0) {
    die("Profesor no encontrado.");
}

$profesor = $profesor_q->fetch_assoc();
$dni = $profesor['dni'];

// Contenido del QR con formato P-DNI (ej: P-24533160)
$qr_code = 'P-' . $dni;

// Ruta donde se guarda el QR
$carpeta = __DIR__ . "/qrs";
$filename = "$carpeta/qr_profesor_$id.png";

// Crear carpeta si no existe
if (!is_dir($carpeta)) {
    mkdir($carpeta, 0755, true);
}

// Generar el QR
QRcode::png($qr_code, $filename, QR_ECLEVEL_H, 10);

// Redirigir de vuelta
header("Location: ver_profesores.php");
exit;
?>
