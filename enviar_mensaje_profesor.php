<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$profesor_id = intval($_POST['profesor_id'] ?? 0);
$cliente_id = intval($_POST['cliente_id'] ?? 0);
$mensaje = trim($_POST['mensaje'] ?? '');
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if (!$profesor_id || !$cliente_id || !$mensaje || !$gimnasio_id) exit;

$stmt = $conexion->prepare("
    INSERT INTO mensajes_chat (cliente_id, profesor_id, mensaje, emisor, gimnasio_id)
    VALUES (?, ?, ?, 'profesor', ?)
");
$stmt->bind_param("iisi", $cliente_id, $profesor_id, $mensaje, $gimnasio_id);
$stmt->execute();
