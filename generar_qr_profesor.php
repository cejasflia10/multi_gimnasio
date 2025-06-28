<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'conexion.php';
require_once 'phpqrcode/qrlib.php'; // Asegurate de tener esta librería

$id_profesor = $_GET['id'] ?? null;

if (!$id_profesor) {
    echo "ID de profesor no especificado.";
    exit;
}

// Obtener datos del profesor
$query = $conexion->query("SELECT id, apellido, nombre, dni FROM profesores WHERE id = $id_profesor");
$profesor = $query->fetch_assoc();

if (!$profesor) {
    echo "Profesor no encontrado.";
    exit;
}

// Carpeta donde se guardará el QR
$carpetaQR = 'qr_profesores/';
if (!file_exists($carpetaQR)) {
    mkdir($carpetaQR, 0777, true);
}

// Contenido del QR (podés usar DNI o ID)
$contenidoQR = $profesor['dni'];
$archivoQR = $carpetaQR . 'qr_profesor_' . $profesor['id'] . '.png';

// Generar QR
QRcode::png($contenidoQR, $archivoQR, QR_ECLEVEL_H, 10);

// Mostrar QR generado
echo "<h2>QR generado para: {$profesor['apellido']} {$profesor['nombre']}</h2>";
echo "<img src='$archivoQR' alt='QR del profesor'><br><br>";
echo "<a href='ver_profesores.php'>← Volver</a>";
?>
