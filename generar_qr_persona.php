<?php
require_once 'phpqrcode/qrlib.php';
include 'conexion.php';

if (!isset($_GET['tipo']) || !isset($_GET['id'])) {
    die("Datos insuficientes para generar QR");
}

$tipo = $_GET['tipo']; // 'cliente' o 'profesor'
$id = intval($_GET['id']);

if ($tipo === 'cliente') {
    $query = $conexion->query("SELECT dni FROM clientes WHERE id = $id");
    $prefijo = 'C-';
} elseif ($tipo === 'profesor') {
    $query = $conexion->query("SELECT dni FROM profesores WHERE id = $id");
    $prefijo = 'P-';
} else {
    die("Tipo inválido");
}

if (!$query || $query->num_rows === 0) {
    die("DNI no encontrado");
}

$row = $query->fetch_assoc();
$dni = $row['dni'];
$contenidoQR = $prefijo . $dni;

$filename = "qr/qr_{$tipo}_$id.png";
QRcode::png($contenidoQR, $filename, QR_ECLEVEL_H, 10);

echo "<h2>QR generado correctamente</h2>";
echo "<img src='$filename' alt='QR generado'>";
echo "<p>$contenidoQR</p>";
echo "<a href='ver_{$tipo}s.php'>Volver</a>";
?>
