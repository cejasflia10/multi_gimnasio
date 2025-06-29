<?php
session_start(); // ¡SIEMPRE debe ir primero, sin espacios arriba!

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

$qr_code = 'P-' . $dni;
$carpeta = __DIR__ . "/qr";
$filename = "$carpeta/qr_profesor_P-$dni.png";

// Verificar si la carpeta existe
if (!is_dir($carpeta)) {
    mkdir($carpeta, 0755, true);
}

// Generar QR con nivel de corrección H y tamaño 10
QRcode::png($qr_code, $filename, 'H', 10);

// Redirigir de vuelta al listado
header("Location: ver_profesores.php");
exit;
?>
