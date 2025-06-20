<?php
// Ocultar warnings y deprecated
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = isset($_POST['id']) ? trim($_POST['id']) : '';
    $dni = isset($_POST['dni']) ? trim($_POST['dni']) : '';
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';

    if (!empty($id) && !empty($dni) && !empty($nombre)) {
        include 'phpqrcode/qrlib.php';

        $contenido = "ID:$id | DNI:$dni | Nombre:$nombre";
        $archivo = "qrs/cliente_" . $dni . ".png";

        if (!file_exists("qrs")) {
            mkdir("qrs");
        }

        QRcode::png($contenido, $archivo, QR_ECLEVEL_L, 6);

        echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'>
        <title>QR generado</title><style>
        body { background-color: #111; color: gold; text-align: center; font-family: Arial; padding: 20px; }
        img { margin-top: 20px; border: 4px solid gold; background: #222; padding: 10px; }
        a { color: gold; text-decoration: none; font-weight: bold; }
        </style></head><body>";

        echo "<h2>QR generado correctamente</h2>";
        echo "<p>$contenido</p>";
        echo "<img src='$archivo' alt='QR del cliente'><br><br>";
        echo "<a href='generar_qr.html'>← Volver</a>";

        echo "</body></html>";
    } else {
        echo "Faltan datos";
    }
} else {
    echo "Acceso no válido";
}
?>
