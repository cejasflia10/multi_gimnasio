<?php
// ajax_ingresos.php
// Devuelve las tarjetas de ingresos con HTML limpio (sin estilos inline ni saltos <br> forzados)

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once 'conexion.php';

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($gimnasio_id <= 0) {
    echo '<div class="ing-row"><div class="ing-titulo">Sesión no válida</div></div>';
    exit;
}

/**
 * Suma montos por día o mes.
 * $tabla: membresias | ventas
 * $campo_fecha: fecha_inicio (membresias) | fecha (ventas)
 * $modo: 'DIA' | 'MES'
 */
function obtenerMonto(mysqli $cx, string $tabla, string $campo_fecha, int $gymId, string $modo = 'DIA'): float {
    $condFechas = ($modo === 'MES')
        ? "MONTH($campo_fecha) = MONTH(CURDATE()) AND YEAR($campo_fecha) = YEAR(CURDATE())"
        : "$campo_fecha = CURDATE()";

    // Columna a sumar según tabla
    $col = ($tabla === 'ventas')
        ? 'monto_total'
        : (($tabla === 'pagos') ? 'monto' : 'total_pagado'); // fallback para otras tablas

    // Aseguremos columna existente en cada caso conocido
    if ($tabla === 'membresias') $col = 'monto_pagado';       // si tu columna es total_pagado, cámbiala aquí
    if ($tabla === 'ventas')     $col = 'monto_total';

    $sql = "
        SELECT COALESCE(SUM($col), 0) AS total
        FROM $tabla
        WHERE $condFechas AND gimnasio_id = $gymId
    ";
    $res = $cx->query($sql);
    if (!$res) return 0.0;
    $row = $res->fetch_assoc();
    return (float)($row['total'] ?? 0);
}

try {
    // Ajusta nombres de columnas si en tu DB son otros
    $pagos_dia = obtenerMonto($conexion, 'membresias', 'fecha_inicio', $gimnasio_id, 'DIA');
    $ventas_dia = obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'DIA');

    $pagos_mes = obtenerMonto($conexion, 'membresias', 'fecha_inicio', $gimnasio_id, 'MES');
    $ventas_mes = obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'MES');

    $ingreso_dia = $pagos_dia + $ventas_dia;
    $ingreso_mes = $pagos_mes + $ventas_mes;

    // Formato AR: $ 1.234,56
    $fmtDia = '$ ' . number_format($ingreso_dia, 2, ',', '.');
    $fmtMes = '$ ' . number_format($ingreso_mes, 2, ',', '.');

} catch (Throwable $e) {
    // Ante error, devolvemos tarjetas con 0 y un pequeño mensaje (sin romper layout)
    $fmtDia = '$ 0,00';
    $fmtMes = '$ 0,00';
}

// ---------- HTML limpio (lo inyecta index dentro de #ingresos-body) ----------
?>
<div class="ing-card">
  <div class="ing-titulo">💰 Ingresos del día</div>
  <div class="ing-monto"><?= htmlspecialchars($fmtDia) ?></div>
</div>

<div class="ing-card">
  <div class="ing-titulo">🗓️ Ingresos del mes</div>
  <div class="ing-monto"><?= htmlspecialchars($fmtMes) ?></div>
</div>
