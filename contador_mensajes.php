<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$total = 0;

if (isset($_SESSION['cliente_id'])) {
    $cliente_id = $_SESSION['cliente_id'];
    $res = $conexion->query("
        SELECT COUNT(*) AS total 
        FROM mensajes_chat 
        WHERE cliente_id = $cliente_id AND emisor = 'profesor' AND leido = 0
    ");
    $total = $res->fetch_assoc()['total'] ?? 0;

} elseif (isset($_SESSION['profesor_id'])) {
    $profesor_id = $_SESSION['profesor_id'];
    $res = $conexion->query("
        SELECT COUNT(*) AS total 
        FROM mensajes_chat 
        WHERE profesor_id = $profesor_id AND emisor = 'cliente' AND leido = 0
    ");
    $total = $res->fetch_assoc()['total'] ?? 0;
}

echo $total;
