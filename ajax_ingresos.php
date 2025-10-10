<?php
// ajax_ingresos.php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once 'conexion.php';

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($gimnasio_id <= 0) {
  echo '<div class="ing-card"><div class="ing-titulo">Sesión no válida</div></div>';
  exit;
}

/**
 * Igual a tu lógica original, pero devolviendo float y sin tocar nada más.
 * Tablas/columnas:
 *  - membresias: fecha_inicio / total_pagado
 *  - ventas:     fecha        / monto_total
 *  - (si alguna vez usas pagos: fecha / monto)
 */
function obtenerMonto(mysqli $conexion, string $tabla, string $campo_fecha, int $gimnasio_id, string $modo = 'DIA'): float {
    $cond = ($modo === 'MES')
        ? "MONTH($campo_fecha) = MONTH(CURDATE()) AND YEAR($campo_fecha) = YEAR(CURDATE())"
        : "$campo_fecha = CURDATE()";

    $col = ($tabla === 'ventas')
        ? 'monto_total'
        : (($tabla === 'pagos') ? 'monto' : 'total_pagado');

    $sql = "SELECT COALESCE(SUM($col),0) AS total
            FROM `$tabla`
            WHERE $cond AND gimnasio_id = $gimnasio_id";

    $res = $conexion->query($sql);
    if (!$res) return 0.0;
    $row = $res->fetch_assoc();
    return (float)($row['total'] ?? 0);
}

/* --- Cálculos exactamente como tenías --- */
$pagos_dia  = obtenerMonto($conexion, 'membresias', 'fecha_inicio', $gimnasio_id, 'DIA');
$ventas_dia = obtenerMonto($conexion, 'ventas',      'fecha',        $gimnasio_id, 'DIA');

$pagos_mes  = obtenerMonto($conexion, 'membresias', 'fecha_inicio', $gimnasio_id, 'MES');
$ventas_mes = obtenerMonto($conexion, 'ventas',      'fecha',        $gimnasio_id, 'MES');

/* --- Totales y formato $ 1.234,56 --- */
$total_dia = $pagos_dia + $ventas_dia;
$total_mes = $pagos_mes + $ventas_mes;

$fmt_dia = '$ ' . number_format($total_dia, 2, ',', '.');
$fmt_mes = '$ ' . number_format($total_mes, 2, ',', '.');

/* --- HTML mínimo, sin <br> ni posiciones raras (ideal para móvil) --- */
?>
<div class="ing-card">
  <div class="ing-titulo">💰 Ingresos del día</div>
  <div class="ing-monto"><?= htmlspecialchars($fmt_dia) ?></div>
</div>

<div class="ing-card">
  <div class="ing-titulo">📆 Ingresos del mes</div>
  <div class="ing-monto"><?= htmlspecialchars($fmt_mes) ?></div>
</div>
