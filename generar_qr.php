<?php
session_start();
if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado.");
}

// ⚠️ Ocultar warnings deprecated y warnings visibles
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
ini_set('display_errors', 0);

include 'conexion.php';
require 'phpqrcode/qrlib.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST["dni"]) || empty(trim($_POST["dni"]))) {
        echo "<script>alert('No se recibió el DNI'); window.history.back();</script>";
        exit;
    }

    $dni = trim($_POST["dni"]);
    $gimnasio_id = $_SESSION["gimnasio_id"];

    $stmt = $conexion->prepare("SELECT id, nombre, apellido FROM clientes WHERE dni = ? AND gimnasio_id = ?");
    $stmt->bind_param("si", $dni, $gimnasio_id);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        $cliente = $resultado->fetch_assoc();
        $contenido = $cliente['dni'] . " | " . $cliente['nombre'] . " " . $cliente['apellido'] . " | ID:" . $cliente['id'];
    } else {
        echo "<script>alert('El DNI no está registrado en este gimnasio.'); window.history.back();</script>";
        exit;
    }

    if (!file_exists('qr_temp')) {
        mkdir('qr_temp');
    }

    $nombreArchivo = 'qr_temp/qr_' . $cliente['id'] . '.png';
    QRcode::png($contenido, $nombreArchivo, QR_ECLEVEL_L, 5);

    echo "<body style='background-color:#111;color:#FFD700;text-align:center;padding-top:40px;'>";
    echo "<h2>QR generado correctamente</h2>";
    echo "<img src='$nombreArchivo' alt='QR generado'><br><br>";
    echo "<a href='formulario_qr.php' style='color:#FFD700;'>⬅️ Generar otro</a>";
    echo "</body>";
} else {
    echo "<script>alert('Acceso inválido'); window.location.href='formulario_qr.php';</script>";
}
?>
