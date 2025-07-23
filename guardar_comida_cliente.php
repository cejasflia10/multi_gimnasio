<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$registro = trim($_POST['registro_comida'] ?? '');

if ($cliente_id && $registro) {
    $fecha = date('Y-m-d');
    $stmt = $conexion->prepare("INSERT INTO registro_comidas (cliente_id, gimnasio_id, fecha, detalle) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $cliente_id, $gimnasio_id, $fecha, $registro);
    $stmt->execute();
}

header("Location: asistente_ia_api.php");
exit;
