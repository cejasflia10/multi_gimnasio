<?php
include 'conexion.php';
require_once 'phpqrcode/qrlib.php';
session_start();
date_default_timezone_set('America/Argentina/Buenos_Aires');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apellido = $_POST['apellido'];
    $nombre = $_POST['nombre'];
    $domicilio = $_POST['domicilio'];
    $telefono = $_POST['telefono'];
    $dni = $_POST['dni'];
    $gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

    $conexion->query("INSERT INTO profesores (apellido, nombre, domicilio, telefono, dni, gimnasio_id)
                      VALUES ('$apellido', '$nombre', '$domicilio', '$telefono', '$dni', $gimnasio_id)");

    $profesor_id = $conexion->insert_id;
    $qr_code = 'P-' . $dni;
    $filename = "qr/qr_profesor_" . $profesor_id . ".png";
    QRcode::png($qr_code, $filename, QR_ECLEVEL_H, 10);

    header("Location: ver_profesores.php");
    exit;
}
?>
