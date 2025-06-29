<?php
include 'conexion.php';
require_once 'phpqrcode/qrlib.php';
session_start();

if (!isset($_GET['id'])) {
    die("ID de profesor no especificado.");
}

$id = intval($_GET['id']);

// Obtener el DNI del profesor
$profesor_q = $conexion->query("SELECT dni FROM profesores WHERE id = $id");
if ($profesor_q->num_rows === 0) {
    die("Profesor no encontrado.");
}

$profesor = $profesor_q->fetch_assoc();
$dni = $profesor['dni'];

// Generar QR con formato P-12345678
$qr_code = 'P-' . $dni;
$filename = "qr/qr_profesor_P-$dni.png";
QRcode::png($qr_code, $filename, QR_ECLEVEL_H, 10);

// Redirigir de nuevo
header("Location: ver_profesores.php");
exit;
?>
