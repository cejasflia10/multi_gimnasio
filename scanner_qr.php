<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("conexion.php");

$dni = $_GET['dni'] ?? '';
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if (empty($dni) || $gimnasio_id == 0) {
    die("❌ Error: DNI o gimnasio no válidos.");
}

$query = "SELECT * FROM clientes WHERE dni = '$dni' AND gimnasio_id = $gimnasio_id LIMIT 1";
$resultado = $conexion->query($query);

if ($resultado && $resultado->num_rows > 0) {
    $cliente = $resultado->fetch_assoc();
    $id_cliente = $cliente['id'];
    $nombre = $cliente['nombre'] . ' ' . $cliente['apellido'];

    // Redirige para registrar asistencia
    header("Location: registrar_asistencia_qr.php?cliente_id=$id_cliente");
    exit;
} else {
    echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <style>
        body {
            background-color: #111;
            color: gold;
            text-align: center;
            padding-top: 60px;
            font-family: Arial, sans-serif;
        }
        a {
            display: inline-block;
            margin-top: 20px;
            color: yellow;
            font-size: 20px;
            text-decoration: underline;
        }
    </style></head><body>
    <h2>⚠️ No se encontró el cliente con ese DNI o no pertenece a tu gimnasio.</h2>
    <a href='scanner_qr.php'>🔄 Escanear otro</a>
    </body></html>";
}
?>
