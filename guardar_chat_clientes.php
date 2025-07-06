<?php
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$cliente_id = intval($_POST['cliente_id'] ?? 0);
$cliente_receptor_id = intval($_POST['cliente_receptor_id'] ?? 0);
$mensaje = trim($_POST['mensaje'] ?? '');
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($cliente_id && $cliente_receptor_id && $mensaje && $gimnasio_id) {
    $stmt = $conexion->prepare("INSERT INTO mensajes_chat 
        (cliente_id, cliente_receptor_id, mensaje, emisor, gimnasio_id) 
        VALUES (?, ?, ?, 'cliente', ?)");
    $stmt->bind_param("iisi", $cliente_id, $cliente_receptor_id, $mensaje, $gimnasio_id);
    $stmt->execute();
}
