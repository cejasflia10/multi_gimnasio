<?php
include 'conexion.php';
require_once 'phpqrcode/qrlib.php';
session_start();
date_default_timezone_set('America/Argentina/Buenos_Aires');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apellido = $_POST['apellido'];
    $nombre = $_POST['nombre'];
    $dni = $_POST['dni'];
    $fecha_nacimiento = $_POST['fecha_nacimiento'];
    $domicilio = $_POST['domicilio'];
    $telefono = $_POST['telefono'];
    $email = $_POST['email'];
    $fecha_vencimiento = $_POST['fecha_vencimiento'];
    $disciplina = $_POST['disciplina'];
    $gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

    $conexion->query("INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, domicilio, telefono, email, fecha_vencimiento, disciplina, gimnasio_id)
                      VALUES ('$apellido', '$nombre', '$dni', '$fecha_nacimiento', '$domicilio', '$telefono', '$email', '$fecha_vencimiento', '$disciplina', $gimnasio_id)");

    $cliente_id = $conexion->insert_id;
    $qr_code = 'C-' . $dni;
    $filename = "qr/qr_cliente_" . $cliente_id . ".png";
    QRcode::png($qr_code, $filename, QR_ECLEVEL_H, 10);

    header("Location: ver_clientes.php");
    exit;
}
?>
