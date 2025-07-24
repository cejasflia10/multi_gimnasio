<?php
session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

function obtenerMonto($conexion, $tabla, $campo_fecha, $gimnasio_id, $modo = 'DIA') {
    $cond = $modo === 'MES'
        ? "MONTH($campo_fecha) = MONTH(CURDATE()) AND YEAR($campo_fecha) = YEAR(CURDATE())"
        : "$campo_fecha = CURDATE()";
    $col = ($tabla === 'ventas') ? 'monto_total' : (($tabla === 'pagos') ? 'monto' : 'total_pagado');
    $q = "SELECT SUM($col) AS total FROM $tabla WHERE $cond AND gimnasio_id = $gimnasio_id";
    $res = $conexion->query($q);
    return $res && $res->num_rows > 0 ? ($res->fetch_assoc()['total'] ?? 0) : 0;
}

$pagos_dia = obtenerMonto($conexion, 'membresias', 'fecha_inicio', $gimnasio_id, 'DIA');
$ventas_dia = obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'DIA');
$pagos_mes = obtenerMonto($conexion, 'membresias', 'fecha_inicio', $gimnasio_id, 'MES');
$ventas_mes = obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'MES');

echo "
<div class='box bloque-monto'><h2>💰 Ingresos del Día</h2><div class='monto'>$" . number_format($pagos_dia + $ventas_dia, 2) . "</div></div>
<div class='box bloque-monto'><h2>📆 Ingresos del Mes</h2><div class='monto'>$" . number_format($pagos_mes + $ventas_mes, 2) . "</div></div>
";
