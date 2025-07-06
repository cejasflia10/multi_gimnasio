<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$sorteo_id = intval($_POST['sorteo_id'] ?? 0);
if (!$sorteo_id) exit;

// Elegir 1 participante al azar
$ganador = $conexion->query("
    SELECT cliente_id FROM sorteos_participantes 
    WHERE sorteo_id = $sorteo_id 
    ORDER BY RAND() 
    LIMIT 1
")->fetch_assoc();

if ($ganador) {
    $ganador_id = $ganador['cliente_id'];
    $conexion->query("UPDATE sorteos SET ganador_id = $ganador_id WHERE id = $sorteo_id");
}

header("Location: ver_sorteos_admin.php");
exit;
