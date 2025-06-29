<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Iniciando generación QR...<br>";

include 'conexion.php';
require_once 'phpqrcode/qrlib.php';
session_start();

if (!isset($_GET['id'])) {
    die("ID de profesor no especificado.");
}

$id = intval($_GET['id']);
echo "ID recibido: $id<br>";

$profesor_q = $conexion->query("SELECT dni FROM profesores WHERE id = $id");
if ($profesor_q->num_rows === 0) {
    die("Profesor no encontrado.");
}

$profesor = $profesor_q->fetch_assoc();
$dni = $profesor['dni'];
echo "DNI: $dni<br>";

$qr_code = 'P-' . $dni;
$filename = __DIR__ . "/qr/qr_profesor_P-$dni.png";

echo "Generando archivo en: $filename<br>";
QRcode::png($qr_code, $filename, 'H', 10);

echo "QR generado correctamente.<br>";
?>
