<?php
session_start();
include 'conexion.php';
require_once 'phpqrcode/qrlib.php';

if (!isset($_GET['id'])) {
    die("ID de profesor no especificado.");
}

$id = intval($_GET['id']);
$query = $conexion->query("SELECT dni FROM profesores WHERE id = $id");
if ($query->num_rows === 0) {
    die("Profesor no encontrado.");
}

$profesor = $query->fetch_assoc();
$dni = $profesor['dni'];

// Valor del QR
$qr_contenido = "P-" . $dni;

// Carpeta y nombre de archivo
$carpeta = __DIR__ . "/qrs";
if (!is_dir($carpeta)) {
    mkdir($carpeta, 0755, true);
}

$filename = $carpeta . "/qr_profesor_" . $id . ".png";

// Generar QR
QRcode::png($qr_contenido, $filename, QR_ECLEVEL_H, 10);

// Redirigir a la vista
header("Location: ver_profesores.php");
exit;
?>
