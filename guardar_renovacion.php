<?php
/* ============================================================================
   guardar_renovacion.php — Guardado desde renovar_membresia.php (alineado)
   - Inserta en `membresias` con todos los campos que usa el sistema:
     cliente_id, gimnasio_id/id_gimnasio, plan_id, fechas, clases, precio,
     otros_pagos, pagos_adicionales, descuento, total, pagos desglosados,
     monto_pagado, total_pagado, saldo_cc, activa, forma_pago/metodo_pago.
   - Si no viene fecha_vencimiento, la calcula desde el plan (duracion_meses).
   - Inserta adicionales en `membresias_adicionales (membresia_id, adicional_id)` si existe.
   - Registra también en pagos_mensuales y cuentas_corrientes.
============================================================================ */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';

ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500); exit('❌ Sin conexión a la BD');
}
@$conexion->set_charset('utf8mb4');
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }

/* === Helpers === */
function esc(mysqli $db, $v){
  return "'".$db->real_escape_string((string)$v)."'";
}
function toFloat($v){
  $s = (string)$v;
  $s = str_replace(["\xC2\xA0",' '],'',$s);
  if (strpos($s, ',')!==false && strpos($s, '.')!==false){
    $s=str_replace('.','',$s);
    $s=str_replace(',','.',$s);
  } elseif (strpos($s, ',')!==false){
    $s=str_replace(',','.',$s);
  }
  return (float)$s;
}
function table_has(mysqli $db, string $t){
  $t=$db->real_escape_string($t);
  $r=$db->query("SHOW TABLES LIKE '$t'");
  return ($r && $r->num_rows>0);
}
function col_has(mysqli $db, string $t, string $c){
  $t=$db->real_escape_string($t);
  $c=$db->real_escape_string($c);
  $r=$db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  return ($r && $r->num_rows>0);
}

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

$pago_efectivo         = toFloat($_POST['pago_efectivo'] ?? 0);
$pago_transferencia    = toFloat($_POST['pago_transferencia'] ?? 0);
$pago_debito           = toFloat($_POST['pago_debito'] ?? 0);
$pago_credito          = toFloat($_POST['pago_credito'] ?? 0);
$pago_cuenta_corriente = toFloat($_POST['pago_cuenta_corriente'] ?? 0);

$adicionales = (isset($_POST['adicionales']) && is_array($_POST['adicionales']))
  ? array_map('intval', $_POST['adicionales'])
  : [];

if ($gimnasio_id<=0) { http_response_code(403); exit('❌ Falta gimnasio en sesión'); }
if ($cliente_id<=0)  { http_response_code(422); exit('❌ Falta cliente'); }
if ($plan_id<=0)     { http_response_code(422); exit('❌ Falta plan'); }

/* === Calcular fecha_vencimiento si no vino === */
if ($fecha_vencimiento === '') {
  $dur_meses = 0;
  if (table_has($conexion,'planes') && col_has($conexion,'planes','duracion_meses')) {
    $rs = $conexion->query("SELECT duracion_meses FROM planes WHERE id={$plan_id} LIMIT 1");
    if ($rs && ($r=$rs->fetch_assoc())) {
      $dur_meses = (int)($r['duracion_meses'] ?? 0);
    }
  }
  if ($dur_meses <= 0) $dur_meses = 1;
  $ts = strtotime($fecha_inicio ?: date('Y-m-d'));
  $fecha_vencimiento = date('Y-m-d', strtotime("+{$dur_meses} month", $ts));
}

/* === Total, adicionales, pagado, saldo cc === */
$suma_adics = 0.0;
if ($adicionales && table_has($conexion,'planes_adicionales') && col_has($conexion,'planes_adicionales','precio')) {
  $ids = implode(',', array_map('intval',$adicionales));
  $rs = $conexion->query("
    SELECT SUM(precio) AS s 
    FROM planes_adicionales 
    WHERE gimnasio_id={$gimnasio_id} AND id IN ($ids)
  ");
  if ($rs && ($r=$rs->fetch_assoc())) {
    $suma_adics = (float)($r['s'] ?? 0);
  }
}

$bruto       = (float)$precio + (float)$suma_adics + (float)$otros_pagos;
$descuento   = max(0.0, min(100.0, (float)$descuento_pct));
$total_final = round(max(0, $bruto - ($bruto * $descuento / 100)), 2);
$monto_pagado= round(
  $pago_efectivo +
  $pago_transferencia +
  $pago_debito +
  $pago_credito +
  $pago_cuenta_corriente,
  2
);
$saldo_cc = max(0, $total_final - $monto_pagado);

/* === Texto forma/metodo de pago === */
$metodos_txt = [];
if ($pago_efectivo         > 0) $metodos_txt[] = 'efectivo';
if ($pago_transferencia    > 0) $metodos_txt[] = 'transferencia';
if ($pago_debito           > 0) $metodos_txt[] = 'debito';
if ($pago_credito          > 0) $metodos_txt[] = 'credito';
if ($pago_cuenta_corriente > 0) $metodos_txt[] = 'cuenta_corriente';

$forma_pago_str = implode(' + ', $metodos_txt);
if ($forma_pago_str === '') $forma_pago_str = 'sin_datos';

/* === Detectar col del gimnasio en membresias === */
$gym_col = null;
if (col_has($conexion,'membresias','gimnasio_id'))      $gym_col = 'gimnasio_id';
elseif (col_has($conexion,'membresias','id_gimnasio'))  $gym_col = 'id_gimnasio';
if (!$gym_col) { http_response_code(500); exit('❌ membresias no tiene gimnasio_id ni id_gimnasio'); }

/* === Armar INSERT en membresias (solo columnas que existan) === */
$cols = [];
$vals = [];
$SET = function(string $col, $value) use (&$cols,&$vals,$conexion){
  if (!col_has($conexion,'membresias',$col)) return;
  $cols[] = "`$col`";
  if (is_numeric($value)) {
    $vals[] = (string)$value;
  } else {
    $vals[] = esc($conexion,$value);
  }
};

/* Base imprescindible */
$SET('cliente_id',         $cliente_id);
$SET($gym_col,             $gimnasio_id);
$SET('plan_id',            $plan_id);
$SET('fecha_inicio',       $fecha_inicio);
$SET('fecha_vencimiento',  $fecha_vencimiento);

/* Datos económicos principales */
$SET('precio',             number_format($precio, 2, '.', ''));
$SET('otros_pagos',        number_format($otros_pagos, 2, '.', ''));
$SET('pagos_adicionales',  number_format($suma_adics, 2, '.', ''));
$SET('descuento',          number_format($descuento, 2, '.', ''));
$SET('total',              number_format($total_final, 2, '.', ''));

/* Clases */
$SET('clases_disponibles', $clases_disponibles);
$SET('clases_restantes',   $clases_disponibles);

/* Pagos desglosados: monto_* y pago_* */
$SET('monto_efectivo',         number_format($pago_efectivo, 2, '.', ''));
$SET('monto_transferencia',    number_format($pago_transferencia, 2, '.', ''));
$SET('monto_debito',           number_format($pago_debito, 2, '.', ''));
$SET('monto_credito',          number_format($pago_credito, 2, '.', ''));
$SET('saldo_cc',               number_format($saldo_cc, 2, '.', ''));

$SET('pago_efectivo',          number_format($pago_efectivo, 2, '.', ''));
$SET('pago_transferencia',     number_format($pago_transferencia, 2, '.', ''));
$SET('pago_debito',            number_format($pago_debito, 2, '.', ''));
$SET('pago_credito',           number_format($pago_credito, 2, '.', ''));
$SET('pago_cuenta_corriente',  number_format($pago_cuenta_corriente, 2, '.', ''));

/* Totales pagados */
$SET('monto_pagado',           number_format($monto_pagado, 2, '.', ''));
$SET('total_pagado',           number_format($monto_pagado, 2, '.', ''));

/* Forma/metodo pago texto */
$SET('forma_pago',  $forma_pago_str);
$SET('metodo_pago', $forma_pago_str);

/* Activa */
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
  if (col_has($conexion,'membresias_adicionales','membresia_id') &&
      col_has($conexion,'membresias_adicionales','adicional_id')) {

    $pairs = [];
    foreach ($adicionales as $aid) {
      $pairs[] = "(".(int)$membresia_id.", ".(int)$aid.")";
    }
    $sqlA = "INSERT INTO `membresias_adicionales` (`membresia_id`,`adicional_id`)
             VALUES ".implode(',', $pairs);
    $conexion->query($sqlA); // si falla, no detenemos la renovación principal
  }
}

/* ==========================================================
   Registrar pago en pagos_mensuales
   ========================================================== */
$total_formas_pago = $pago_efectivo + $pago_transferencia + $pago_debito + $pago_credito + $pago_cuenta_corriente;

if ($total_formas_pago > 0 && table_has($conexion,'pagos_mensuales')) {

  $colsP = [];
  $valsP = [];
  $SET_P = function(string $col, $value) use (&$colsP,&$valsP,$conexion){
    if (!col_has($conexion,'pagos_mensuales',$col)) return;
    $colsP[] = "`$col`";
    if (is_numeric($value)) $valsP[] = (string)$value;
    else                    $valsP[] = esc($conexion,$value);
  };

  // gimnasio
  if (col_has($conexion,'pagos_mensuales','gimnasio_id')) {
    $SET_P('gimnasio_id', $gimnasio_id);
  } elseif (col_has($conexion,'pagos_mensuales','id_gimnasio')) {
    $SET_P('id_gimnasio', $gimnasio_id);
  }

  // cliente y membresía
  $SET_P('cliente_id',    $cliente_id);
  $SET_P('membresia_id',  $membresia_id);

  // fecha de pago
  $SET_P('fecha_pago', $fecha_inicio);
  $SET_P('fecha',      $fecha_inicio);

  // concepto / descripción
  $concepto = "Renovación membresía Plan #{$plan_id}";
  $SET_P('concepto',    $concepto);
  $SET_P('descripcion', $concepto);
  $SET_P('detalle',     $concepto);

  // montos
  $SET_P('monto_efectivo',         number_format($pago_efectivo, 2, '.', ''));
  $SET_P('monto_transferencia',    number_format($pago_transferencia, 2, '.', ''));
  $SET_P('monto_debito',           number_format($pago_debito, 2, '.', ''));
  $SET_P('monto_credito',          number_format($pago_credito, 2, '.', ''));
  $SET_P('monto_cuenta_corriente', number_format($pago_cuenta_corriente, 2, '.', ''));

  $SET_P('monto_total', number_format($monto_pagado, 2, '.', ''));
  $SET_P('total',       number_format($monto_pagado, 2, '.', ''));

  $SET_P('tipo',      'renovacion_membresia');
  $SET_P('origen',    'membresia');
  $SET_P('origen_id', $membresia_id);

  if ($colsP) {
    $sqlP = "INSERT INTO `pagos_mensuales` (".implode(',', $colsP).")
             VALUES (".implode(',', $valsP).")";
    $conexion->query($sqlP);
  }
}

/* ==========================================================
   Registrar saldo en cuentas_corrientes (si corresponde)
   ========================================================== */
if ($pago_cuenta_corriente > 0 && table_has($conexion,'cuentas_corrientes')) {

  $colsC = [];
  $valsC = [];
  $SET_C = function(string $col, $value) use (&$colsC,&$valsC,$conexion){
    if (!col_has($conexion,'cuentas_corrientes',$col)) return;
    $colsC[] = "`$col`";
    if (is_numeric($value)) $valsC[] = (string)$value;
    else                    $valsC[] = esc($conexion,$value);
  };

  if (col_has($conexion,'cuentas_corrientes','gimnasio_id')) {
    $SET_C('gimnasio_id', $gimnasio_id);
  } elseif (col_has($conexion,'cuentas_corrientes','id_gimnasio')) {
    $SET_C('id_gimnasio', $gimnasio_id);
  }

  $SET_C('cliente_id', $cliente_id);
  $SET_C('fecha',      $fecha_inicio);
  $SET_C('fecha_mov',  $fecha_inicio);

  $desc_cc = "Renovación membresía (a cuenta) Plan #{$plan_id}";
  $SET_C('descripcion', $desc_cc);
  $SET_C('detalle',     $desc_cc);
  $SET_C('concepto',    $desc_cc);

  if (col_has($conexion,'cuentas_corrientes','debe')) {
    $SET_C('debe', number_format($pago_cuenta_corriente, 2, '.', ''));
  } else {
    $SET_C('monto', number_format($pago_cuenta_corriente, 2, '.', ''));
  }

  $SET_C('origen',    'membresia');
  $SET_C('origen_id', $membresia_id);
  $SET_C('tipo',      'renovacion_membresia');

  if ($colsC) {
    $sqlC = "INSERT INTO `cuentas_corrientes` (".implode(',', $colsC).")
             VALUES (".implode(',', $valsC).")";
    $conexion->query($sqlC);
  }
}

/* === Listo: volver al listado mostrando éxito === */
header('Location: ver_membresias.php?ok=1');
exit;
