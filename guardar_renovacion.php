<?php
/* ============================================================================
   guardar_renovacion.php — Guardado desde renovar_membresia.php (alineado)
   - Inserta en `membresias` con todos los campos que tu ver_membresias muestra:
     cliente_id, (gimnasio_id|id_gimnasio), plan_id, fechas, clases, precio,
     otros_pagos, descuento, total, pagos desglosados, monto_pagado, activa.
   - Si no viene fecha_vencimiento, la calcula desde el plan (duracion_meses).
   - Inserta adicionales en `membresias_adicionales (membresia_id, adicional_id)` si existe.
   - Sin transacciones, sin PURGE. Objetivo: que GUARDE y que se VEA en el listado.
============================================================================ */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';

ini_set('display_errors','1'); ini_set('display_startup_errors','1'); error_reporting(E_ALL);

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500); exit('❌ Sin conexión a la BD');
}
@$conexion->set_charset('utf8mb4');
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }

/* === Helpers === */
function esc(mysqli $db, $v){ return "'".$db->real_escape_string((string)$v)."'"; }
function toFloat($v){
  $s = (string)$v; $s = str_replace(["\xC2\xA0",' '],'',$s);
  if (strpos($s, ',')!==false && strpos($s, '.')!==false){ $s=str_replace('.','',$s); $s=str_replace(',','.',$s); }
  elseif (strpos($s, ',')!==false){ $s=str_replace(',','.',$s); }
  return (float)$s;
}
function table_has(mysqli $db, string $t){ $t=$db->real_escape_string($t); $r=$db->query("SHOW TABLES LIKE '$t'"); return ($r && $r->num_rows>0); }
function col_has(mysqli $db, string $t, string $c){ $t=$db->real_escape_string($t); $c=$db->real_escape_string($c); $r=$db->query("SHOW COLUMNS FROM `$t` LIKE '$c'"); return ($r && $r->num_rows>0); }

/* === Solo POST desde tu form === */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405); exit('Método no permitido');
}

/* === Entradas (tal cual tu form) === */
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? $_POST['gimnasio_id'] ?? 0);
$cliente_id  = (int)($_POST['cliente_id'] ?? 0);
$plan_id     = (int)($_POST['plan_id'] ?? 0);

$fecha_inicio       = (string)($_POST['fecha_inicio'] ?? date('Y-m-d'));
$fecha_vencimiento  = (string)($_POST['fecha_vencimiento'] ?? '');
$clases_disponibles = (int)($_POST['clases_disponibles'] ?? 0);
$precio             = toFloat($_POST['precio'] ?? 0);
$otros_pagos        = toFloat($_POST['otros_pagos'] ?? 0);
$descuento_pct      = toFloat($_POST['descuento'] ?? 0);

$pago_efectivo        = toFloat($_POST['pago_efectivo'] ?? 0);
$pago_transferencia   = toFloat($_POST['pago_transferencia'] ?? 0);
$pago_debito          = toFloat($_POST['pago_debito'] ?? 0);
$pago_credito         = toFloat($_POST['pago_credito'] ?? 0);
$pago_cuenta_corriente= toFloat($_POST['pago_cuenta_corriente'] ?? 0);

$adicionales = (isset($_POST['adicionales']) && is_array($_POST['adicionales'])) ? array_map('intval', $_POST['adicionales']) : [];

if ($gimnasio_id<=0) { http_response_code(403); exit('❌ Falta gimnasio en sesión'); }
if ($cliente_id<=0)  { http_response_code(422); exit('❌ Falta cliente'); }
if ($plan_id<=0)     { http_response_code(422); exit('❌ Falta plan'); }

/* === Calcular fecha_vencimiento si no vino === */
if ($fecha_vencimiento === '') {
  $dur_meses = 0;
  if (table_has($conexion,'planes') && col_has($conexion,'planes','duracion_meses')) {
    $rs = $conexion->query("SELECT duracion_meses FROM planes WHERE id={$plan_id} LIMIT 1");
    if ($rs && ($r=$rs->fetch_assoc())) { $dur_meses = (int)($r['duracion_meses'] ?? 0); }
  }
  if ($dur_meses <= 0) $dur_meses = 1;
  $ts = strtotime($fecha_inicio ?: date('Y-m-d'));
  $fecha_vencimiento = date('Y-m-d', strtotime("+{$dur_meses} month", $ts));
}

/* === Total y pagado (como espera ver_membresias) === */
$suma_adics = 0.0;
if ($adicionales && table_has($conexion,'planes_adicionales') && col_has($conexion,'planes_adicionales','precio')) {
  $ids = implode(',', array_map('intval',$adicionales));
  $rs = $conexion->query("SELECT SUM(precio) AS s FROM planes_adicionales WHERE gimnasio_id={$gimnasio_id} AND id IN ($ids)");
  if ($rs && ($r=$rs->fetch_assoc())) $suma_adics = (float)($r['s'] ?? 0);
}
$bruto       = (float)$precio + (float)$suma_adics + (float)$otros_pagos;
$descuento   = max(0.0, min(100.0, (float)$descuento_pct));
$total_final = round(max(0, $bruto - ($bruto * $descuento / 100)), 2);
$monto_pagado= round($pago_efectivo + $pago_transferencia + $pago_debito + $pago_credito + $pago_cuenta_corriente, 2);

/* === Detectar col del gimnasio en membresias === */
$gym_col = null;
if (col_has($conexion,'membresias','gimnasio_id')) $gym_col = 'gimnasio_id';
elseif (col_has($conexion,'membresias','id_gimnasio')) $gym_col = 'id_gimnasio';
if (!$gym_col) { http_response_code(500); exit('❌ membresias no tiene gimnasio_id ni id_gimnasio'); }

/* === Armar INSERT en membresias (solo columnas que existan) === */
$cols = []; $vals = [];
$SET = function(string $col, $value) use (&$cols,&$vals,$conexion){
  if (!col_has($conexion,'membresias',$col)) return;
  $cols[] = "`$col`";
  if (is_numeric($value)) { $vals[] = (string)$value; }
  else                    { $vals[] = esc($conexion,$value); }
};

/* Base imprescindible */
$SET('cliente_id',         $cliente_id);
$SET($gym_col,             $gimnasio_id);
$SET('plan_id',            $plan_id);
$SET('fecha_inicio',       $fecha_inicio);
$SET('fecha_vencimiento',  $fecha_vencimiento);

/* Lo que ver_membresias acostumbra a mostrar */
$SET('precio',             number_format($precio, 2, '.', ''));
$SET('otros_pagos',        number_format($otros_pagos, 2, '.', ''));
$SET('descuento',          number_format($descuento, 2, '.', ''));
$SET('total',              number_format($total_final, 2, '.', ''));

$SET('clases_disponibles', $clases_disponibles);
$SET('clases_restantes',   $clases_disponibles);

/* Pagos desglosados + total pagado (si tus columnas existen) */
$SET('monto_efectivo',         number_format($pago_efectivo, 2, '.', ''));
$SET('monto_transferencia',    number_format($pago_transferencia, 2, '.', ''));
$SET('monto_debito',           number_format($pago_debito, 2, '.', ''));
$SET('monto_credito',          number_format($pago_credito, 2, '.', ''));
$SET('monto_cuenta_corriente', number_format($pago_cuenta_corriente, 2, '.', ''));
$SET('monto_pagado',           number_format($monto_pagado, 2, '.', ''));

/* Bandera de activa si la tenés */
$SET('activa', 1);

/* Seguridad: si por algún motivo no hay columnas, abortar claro */
if (!$cols) { http_response_code(500); exit('❌ No hay columnas válidas para insertar en membresias'); }

/* Ejecutar INSERT (sin transacción para que “quede” sí o sí) */
$conexion->autocommit(true);
$sql = "INSERT INTO `membresias` (".implode(',', $cols).") VALUES (".implode(',', $vals).")";
if (!$conexion->query($sql)) {
  http_response_code(500);
  exit('❌ No se pudo renovar: '.$conexion->error);
}

$membresia_id = (int)$conexion->insert_id;

/* === Guardar adicionales puente si corresponde === */
if ($membresia_id > 0 && $adicionales && table_has($conexion,'membresias_adicionales')) {
  // Si la tabla sólo tiene (membresia_id, adicional_id), insertamos así.
  if (col_has($conexion,'membresias_adicionales','membresia_id') && col_has($conexion,'membresias_adicionales','adicional_id')) {
    $pairs = [];
    foreach ($adicionales as $aid) {
      $pairs[] = "(".(int)$membresia_id.", ".(int)$aid.")";
    }
    $sqlA = "INSERT INTO `membresias_adicionales` (`membresia_id`,`adicional_id`) VALUES ".implode(',', $pairs);
    $conexion->query($sqlA); // si falla, no detenemos la renovación principal
  }
}

/* === Listo: volver al listado mostrando éxito === */
header('Location: ver_membresias.php?ok=1');
exit;
