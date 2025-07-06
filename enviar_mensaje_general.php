<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$cliente_id = intval($_POST['cliente_id'] ?? 0);
$tipo = $_POST['destino_tipo'] ?? '';
$destino_id = intval($_POST['destino_id'] ?? 0);
$mensaje = trim($_POST['mensaje'] ?? '');
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if (!$cliente_id || !$destino_id || !$mensaje || !$tipo || !$gimnasio_id) exit;

if ($tipo === 'profesor') {
    $stmt = $conexion->prepare("
        INSERT INTO mensajes_chat (cliente_id, profesor_id, mensaje, emisor, gimnasio_id)
        VALUES (?, ?, ?, 'cliente', ?)
    ");
    $stmt->bind_param("iisi", $cliente_id, $destino_id, $mensaje, $gimnasio_id);
    $stmt->execute();

} elseif ($tipo === 'cliente') {
    $stmt = $conexion->prepare("
        INSERT INTO mensajes_chat (cliente_id, cliente_receptor_id, mensaje, emisor, gimnasio_id)
        VALUES (?, ?, ?, 'cliente', ?)
    ");
    $stmt->bind_param("iisi", $cliente_id, $destino_id, $mensaje, $gimnasio_id);
    $stmt->execute();
}
