<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$sorteo_id = intval($_POST['sorteo_id'] ?? 0);

if (!$cliente_id || !$sorteo_id || !$gimnasio_id) {
    echo "<div style='color:red; text-align:center;'>Acceso denegado.</div>";
    exit;
}

// Verificar si ya está inscripto
$verificar = $conexion->query("
    SELECT id FROM sorteos_participantes 
    WHERE cliente_id = $cliente_id AND sorteo_id = $sorteo_id
");

if ($verificar->num_rows == 0) {
    $conexion->query("
        INSERT INTO sorteos_participantes (sorteo_id, cliente_id)
        VALUES ($sorteo_id, $cliente_id)
    ");
}

header("Location: sorteos.php");
exit;
