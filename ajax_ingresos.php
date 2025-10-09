<?php
// ajax_ingresos.php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: text/html; charset=UTF-8');

require_once 'conexion.php';

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($gimnasio_id <= 0) { echo ''; exit; }

/**
 * Devuelve el total (float) para el día o el mes actual.
 * $tabla: 'membresias' | 'ventas' | 'pagos'
 * $campo_fecha: nombre de la columna fecha en la tabla
 * $modo: 'DIA' o 'MES'
 */
function obtenerMonto(mysqli $cx, string $tabla, string $campo_fecha, int $gym_id, string $modo = 'DIA'): float {
    $cond = ($modo === 'MES')
        ? "MONTH($campo_fecha)=MONTH(CURDATE()) AND YEAR($campo_fecha)=YEAR(CURDATE())"
        : "$campo_fecha = CURDATE()";

    // Columna a sumar por tabla
    $col = 'monto';
    if ($tabla === 'ventas')      $col = 'monto_total';
    elseif ($tabla === 'membresias') $col = 'total_pagado';

    $sql = "SELECT COALESCE(SUM($col),0) AS total
            FROM $tabla
            WHERE $cond AND gimnasio_id=$gym_id";
    $res = $cx->query($sql);
    if ($res && ($row = $res->fetch_assoc())) {
        return (float)$row['total'];
    }
    return 0.0;
}

function money(float $n): string {
    // Formato AR/ES: $ 1.234,56
    return '$ ' . number_format($n, 2, ',', '.');
}

// Totales
$pagos_dia  = obtenerMonto($conexion, 'membresias', 'fecha_inicio', $gimnasio_id, 'DIA');
$ventas_dia = obtenerMonto($conexion, 'ventas',     'fecha',        $gimnasio_id, 'DIA');

$pagos_mes  = obtenerMonto($conexion, 'membresias', 'fecha_inicio', $gimnasio_id, 'MES');
$ventas_mes = obtenerMonto($conexion, 'ventas',     'fecha',        $gimnasio_id, 'MES');

$ingreso_dia = $pagos_dia + $ventas_dia;
$ingreso_mes = $pagos_mes + $ventas_mes;

/* 
  IMPORTANTE:
  - Todo el estilo es inline y “a prueba” de reglas externas.
  - writing-mode/transform en horizontal, sin rótulos verticales.
  - Anchos fluidos para que en móvil no se vuelva una “tira” angosta.
*/
?>
<div id="kpis-ingresos"
     style="display:flex;flex-direction:column;gap:12px;writing-mode:horizontal-tb;text-orientation:mixed;transform:none;white-space:normal;word-break:normal;overflow-wrap:break-word;max-width:100%;">
  
  <!-- Ingresos del Día -->
  <div class="kpi-card"
       style="display:flex;align-items:center;justify-content:space-between;gap:12px;
              padding:14px 16px;border:1px solid rgba(15,23,42,.08);border-radius:16px;
              background:#ffffff;box-shadow:0 10px 28px rgba(2,6,23,.08);
              writing-mode:horizontal-tb;transform:none;white-space:normal;max-width:100%;">
    <div style="display:flex;align-items:center;gap:10px;min-width:0;">
      <span aria-hidden="true" style="font-size:22px;line-height:1;">💰</span>
      <h2 style="margin:0;font-size:18px;line-height:1.2;color:#b45309;white-space:normal;">
        Ingresos del día
      </h2>
    </div>
    <strong class="monto" style="font-size:24px;line-height:1;color:#0f172a;white-space:nowrap;">
      <?= money($ingreso_dia) ?>
    </strong>
  </div>

  <!-- Ingresos del Mes -->
  <div class="kpi-card"
       style="display:flex;align-items:center;justify-content:space-between;gap:12px;
              padding:14px 16px;border:1px solid rgba(15,23,42,.08);border-radius:16px;
              background:#ffffff;box-shadow:0 10px 28px rgba(2,6,23,.08);
              writing-mode:horizontal-tb;transform:none;white-space:normal;max-width:100%;">
    <div style="display:flex;align-items:center;gap:10px;min-width:0;">
      <span aria-hidden="true" style="font-size:22px;line-height:1;">📆</span>
      <h2 style="margin:0;font-size:18px;line-height:1.2;color:#b45309;white-space:normal;">
        Ingresos del mes
      </h2>
    </div>
    <strong class="monto" style="font-size:24px;line-height:1;color:#0f172a;white-space:nowrap;">
      <?= money($ingreso_mes) ?>
    </strong>
  </div>
</div>
