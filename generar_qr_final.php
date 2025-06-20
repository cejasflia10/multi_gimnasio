<?php
// Ocultar errores deprecated y warnings
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);

require_once('phpqrcode/qrlib.php');

// Validar que existan los datos necesarios
if (!isset($_GET['id']) || !isset($_GET['dni']) || !isset($_GET['nombre'])) {
    echo "Faltan datos";
    exit;
}

// Datos del cliente
$id = $_GET['id'];
$dni = $_GET['dni'];
$nombre = $_GET['nombre'];

// Texto del QR
$contenido = "ID:$id | DNI:$dni | $nombre";

// Crear carpeta 'qrs' si no existe
if (!file_exists('qrs')) {
    mkdir('qrs', 0777, true);
}

// Nombre de archivo
$nombre_archivo = 'qrs/cliente_' . $id . '_' . $dni . '.png';

// Generar QR y guardarlo
QRcode::png($contenido, $nombre_archivo, QR_ECLEVEL_L, 10);

// Mostrar imagen generada
echo "<h2>QR generado para $nombre</h2>";
echo "<img src='$nombre_archivo' alt='QR del cliente'><br>";
echo "<a href='generar_qr.html'>← Volver</a>";
?>
