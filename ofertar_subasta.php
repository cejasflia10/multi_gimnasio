<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$subasta_id = intval($_POST['subasta_id'] ?? 0);
$monto = floatval($_POST['monto'] ?? 0);

if (!$cliente_id || !$subasta_id || !$monto || !$gimnasio_id) {
    echo "Error en la oferta.";
    exit;
}

// Validar si está activa
$ver = $conexion->query("
    SELECT * FROM subastas 
    WHERE id = $subasta_id AND gimnasio_id = $gimnasio_id AND fecha_cierre >= NOW()
");

if ($ver->num_rows > 0) {
    $conexion->query("
        INSERT INTO subastas_ofertas (subasta_id, cliente_id, monto)
        VALUES ($subasta_id, $cliente_id, $monto)
    ");
}

header("Location: subastas.php");
exit;
