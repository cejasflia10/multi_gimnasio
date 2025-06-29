<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'conexion.php';
require_once 'phpqrcode/qrlib.php';
session_start();

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
$qr_code = 'P-' . $dni;

// Generar QR en carpeta temporal
$tempfile = "/tmp/qr_profesor_P-$dni.png";
QRcode::png($qr_code, $tempfile, QR_ECLEVEL_H, 10);

// Copiar a carpeta pública (Render requiere carpeta accesible)
$final_path = __DIR__ . "/public/qr/qr_profesor_P-$dni.png";
if (!is_dir(dirname($final_path))) {
    mkdir(dirname($final_path), 0755, true);
}
copy($tempfile, $final_path);

header("Location: ver_profesores.php");
exit;
?>
