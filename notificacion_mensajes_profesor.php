<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$profesor_id = $_SESSION['profesor_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if (!$profesor_id || !$gimnasio_id) exit;

$consulta = $conexion->query("
    SELECT COUNT(*) AS total 
    FROM mensajes_chat 
    WHERE profesor_id = $profesor_id 
    AND emisor = 'cliente' 
    AND leido = 0 
    AND gimnasio_id = $gimnasio_id
");

$total = $consulta->fetch_assoc()['total'] ?? 0;

if ($total > 0) {
    echo "<div class='alerta-mensaje'>🔔 $total mensaje(s) nuevo(s) de alumnos</div>";
}
