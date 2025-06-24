<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

if (!isset($_GET['id'])) {
    echo "<script>alert('ID de membresía no proporcionado'); window.location='ver_membresias.php';</script>";
    exit;
}

$id = intval($_GET['id']);
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Buscar membresía actual
$query = "SELECT * FROM membresias WHERE id = $id AND gimnasio_id = $gimnasio_id LIMIT 1";
$result = $conexion->query($query);

if ($result->num_rows === 0) {
    echo "<script>alert('Membresía no encontrada'); window.location='ver_membresias.php';</script>";
    exit;
}

$m = $result->fetch_assoc();
$fecha_inicio = date('Y-m-d');
$duracion_meses = $m['duracion_meses'];
$fecha_vencimiento = date('Y-m-d', strtotime("+$duracion_meses months"));

$conexion->query("INSERT INTO membresias (cliente_id, plan_id, fecha_inicio, fecha_vencimiento, clases_restantes, total, forma_pago, gimnasio_id, pagos_adicionales, otros_pagos, descuento, duracion_meses) VALUES (
    {$m['cliente_id']}, {$m['plan_id']}, '$fecha_inicio', '$fecha_vencimiento', {$m['clases_restantes']}, {$m['total']}, '{$m['forma_pago']}', $gimnasio_id, {$m['pagos_adicionales']}, {$m['otros_pagos']}, {$m['descuento']}, $duracion_meses
)");

echo "<script>alert('Membresía renovada exitosamente'); window.location='ver_membresias.php';</script>";
?>
