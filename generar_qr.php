<?php
require 'phpqrcode/qrlib.php';
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['dni'])) {
    $dni = trim($_POST['dni']);
    $consulta = $conexion->query("SELECT id, nombre, apellido FROM clientes WHERE dni = '$dni' LIMIT 1");
    if ($consulta && $cliente = $consulta->fetch_assoc()) {
        $id = $cliente['id'];
        $nombre = $cliente['nombre'] . ' ' . $cliente['apellido'];
        $contenido = $dni;

        if (!is_dir('qrs')) mkdir('qrs');
        $archivoQR = "qrs/qr_" . $id . ".png";
        QRcode::png($contenido, $archivoQR);

        echo "<body style='background-color:#111; color:#FFD700; text-align:center; font-family:Arial;'>";
        echo "<h2>QR generado correctamente</h2>";
        echo "ID: $id | DNI: $dni | Nombre: $nombre<br><br>";
        echo "<img src='$archivoQR' style='border:5px solid gold; padding:10px;'><br><br>";
        echo "<a href='index.php' style='color:#FFD700;'>← Volver</a>";
        echo "</body>";
    } else {
        echo "<script>alert('DNI no encontrado.'); window.history.back();</script>";
    }
} else {
    echo "<script>alert('No se recibió DNI'); window.history.back();</script>";
}
?>