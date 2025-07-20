<?php
session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$usuario_id = $_SESSION['usuario_id'] ?? 0;
$rol = $_SESSION['rol'] ?? '';

// Solo limitar si NO es admin
if ($rol !== 'admin') {
    // Obtener el máximo de clientes del plan activo
    $plan = $conexion->query("SELECT max_clientes FROM planes_acceso WHERE gimnasio_id = $gimnasio_id LIMIT 1")->fetch_assoc();
    $max_clientes = intval($plan['max_clientes'] ?? 0);

    // Contar los clientes actuales
    $consulta = $conexion->query("SELECT COUNT(*) AS total FROM clientes WHERE gimnasio_id = $gimnasio_id");
    $actuales = $consulta->fetch_assoc();
    $total_clientes = intval($actuales['total'] ?? 0);

    if ($max_clientes > 0 && $total_clientes >= $max_clientes) {
        echo "<script>alert('⚠️ Límite de clientes alcanzado según el plan actual.'); window.location.href = 'ver_clientes.php';</script>";
        exit;
    }
}
?>
