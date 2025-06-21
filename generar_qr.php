<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
include("conexion.php");
require_once "phpqrcode/qrlib.php";

$dni = $_POST['dni'] ?? null;

if (!$dni) {
    echo "No se recibió el DNI";
    exit;
}

$stmt = $conexion->prepare("SELECT nombre, apellido FROM clientes WHERE dni = ?");
$stmt->bind_param("s", $dni);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "Cliente no encontrado.";
    exit;
}

$cliente = $result->fetch_assoc();
$nombre = $cliente['nombre'];
$apellido = $cliente['apellido'];

$contenidoQR = $dni;

echo "<h2>QR generado correctamente</h2>";
echo "<p>Cliente: $apellido, $nombre</p>";
echo "<div style='background:white; display:inline-block; padding:10px;'>";
QRcode::png($contenidoQR);
echo "</div>";
?>
