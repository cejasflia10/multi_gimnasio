<?php
// guardar_renovacion.php — renovar insertando la nueva y PURGE de las anteriores del mismo cliente/gimnasio
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); die("Sin conexión a la base de datos"); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* Helpers */
function hcol(mysqli $db, string $table, string $col): bool {
  $table = $db->real_escape_string($table);
  $col   = $db->real_escape_string($col);
  $res = $db->query("SHOW COLUMNS FROM `{$table}` LIKE '{$col}'");
  return ($res && $res->num_rows > 0);
}
function table_exists(mysqli $db, string $t): bool {
  $t = $db->real_escape_string($t);
  $q = $db->query("SHOW TABLES LIKE '$t'");
  return ($q && $q->num_rows > 0);
}
function bind_params_dynamic(mysqli_stmt $stmt, string $types, array $values): bool {
  // ✅ FIX: el primer argumento (types) también debe ser por referencia en PHP 8
  $refs = [];
  $refs[] = &$types;
  foreach ($values as $k => $v) { $refs[] = &$values[$k]; }
  return call_user_func_array([$stmt, 'bind_param'], $refs);
}
function n($v): float {
  $s = trim((string)$v);
  if ($s === '') return 0.0;
  $s = str_replace(["\xC2\xA0", ' '], '', $s);
  $hasComma = strpos($s, ',') !== false;
  $hasDot   = strpos($s, '.') !== false;
  if ($hasComma && $hasDot) { $s = str_replace('.', '', $s); $s = str_replace(',', '.', $s); }
  elseif ($hasComma && !$hasDot) { $s = str_replace(',', '.', $s); }
  return (float)$s;
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function prep(mysqli $db, string $sql, string $label): mysqli_stmt {
  $stmt = $db->prepare($sql);
  if (!$stmt) { throw new Exception("[$label] prepare() falló: ".$db->error." | SQL: ".$sql); }
  return $stmt;
}

/* Método */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405); die("Método no permitido (solo POST).");
}

/* Entrada */
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? ($_POST['gimnasio_id'] ?? 0));
if ($gimnasio_id <= 0) { http_response_code(403); die("Acceso denegado"); }

$cliente_id         = (int)($_POST['cliente_id'] ?? 0);
$plan_id            = (int)($_POST['plan_id'] ?? 0);
$fecha_inicio       = $_POST['fecha_inicio'] ?? date('Y-m-d');
$fecha_vencimiento  = $_POST['fecha_vencimiento'] ?? '';
$clases_disponibles = (int)($_POST['clases_disponibles'] ?? 0);

$precio_plan    = n($_POST['precio']        ?? 0);
$otros_cargos   = n($_POST['otros_pagos']   ?? 0);
$descuento_pct  = n($_POST['descuento']     ?? 0);
$duracion_meses = (int)($_POST['duracion_meses'] ?? 0);

$pago_efectivo       = n($_POST['pago_efectivo']      ?? 0);
$pago_transferencia  = n($_POST['pago_transferencia'] ?? 0);
$pago_debito         = n($_POST['pago_debito']        ?? 0);
$pago_credito        = n($_POST['pago_credito']       ?? 0);
$pago_cc_manual      = n($_POST['pago_cuenta_corriente'] ?? 0);

$adicionales_ids = isset($_POST['adicionales']) && is_array($_POST['adicionales'])
  ? array_map('intval', $_POST['adicionales'])
  : [];

$fecha_actual = date('Y-m-d H:i:s');

if ($cliente_id <= 0) { http_response_code(400); die("Cliente inválido"); }
if ($plan_id    <= 0) { http_response_code(400); die("Plan inválido"); }

/* Fallbacks desde planes (1 solo SELECT) */
$stp = prep($conexion, "SELECT precio, clases_disponibles, duracion_meses FROM planes WHERE id=? AND gimnasio_id=? LIMIT 1", "planes.select");
$stp->bind_param("ii", $plan_id, $gimnasio_id);
$stp->execute();
$stp->bind_result($p_precio, $p_clases, $p_dur);
if ($stp->fetch()) {
  if ($precio_plan <= 0)           $precio_plan = n($p_precio);
  if ($clases_disponibles <= 0)    $clases_disponibles = (int)$p_clases;
  if ($duracion_meses <= 0)        $duracion_meses = (int)$p_dur;
}
$stp->close();

if ($descuento_pct < 0)   $descuento_pct = 0;
if ($descuento_pct > 100) $descuento_pct = 100;

if ($fecha_vencimiento === '' || $fecha_vencimiento === null) {
  $ts = strtotime($fecha_inicio ?: date('Y-m-d'));
  $fecha_vencimiento = date('Y-m-d', strtotime("+{$duracion_meses} month", $ts));
}

/* Adicionales -> precios desde BD */
$precio_adicionales = 0.0;
$adicionales_detalle = [];
if (!empty($adicionales_ids)) {
  $ids_in = implode(',', array_map('intval', $adicionales_ids));
  $stmtAd = prep($conexion, "SELECT id, nombre, precio FROM planes_adicionales WHERE gimnasio_id = ? AND id IN ($ids_in)", "adicionales.select");
  $stmtAd->bind_param('i', $gimnasio_id);
  if ($stmtAd->execute() && ($resAd = $stmtAd->get_result())) {
    while ($row = $resAd->fetch_assoc()) {
      $precio_adicionales += (float)$row['precio'];
      $adicionales_detalle[] = ['id'=>(int)$row['id'], 'nombre'=>(string)$row['nombre'], 'precio'=>(float)$row['precio']];
    }
  }
  $stmtAd->close();
}

/* Cálculos */
$subtotal_cargos = round($precio_plan + $otros_cargos + $precio_adicionales, 2);
$descuento_monto = round($subtotal_cargos * ($descuento_pct / 100), 2);
$total_cargo     = round(max(0, $subtotal_cargos - $descuento_monto), 2);
$total_pagado_hoy= round($pago_efectivo + $pago_transferencia + $pago_debito + $pago_credito, 2);

$metodos = [];
if ($pago_efectivo      > 0) $metodos[] = "Efectivo:$pago_efectivo";
if ($pago_transferencia > 0) $metodos[] = "Transferencia:$pago_transferencia";
if ($pago_debito        > 0) $metodos[] = "Débito:$pago_debito";
if ($pago_credito       > 0) $metodos[] = "Crédito:$pago_credito";
$metodo_pago_str = $metodos ? implode(' | ', $metodos) : 'Sin pagar ahora';

try {
  $conexion->begin_transaction();

  /* Lock del cliente */
  $conexion->query("SELECT id FROM membresias WHERE cliente_id = {$cliente_id} AND gimnasio_id = {$gimnasio_id} FOR UPDATE");

  /* 1) Insert nueva membresía (columnas dinámicas) */
  $has_mp_mem  = hcol($conexion, 'membresias', 'metodo_pago');
  $has_scc_mem = hcol($conexion, 'membresias', 'saldo_cc');
  $has_act_mem = hcol($conexion, 'membresias', 'activa');
  $has_tp_mem  = hcol($conexion, 'membresias', 'total_pagado');
  $has_op_mem  = hcol($conexion, 'membresias', 'otros_pagos');
  $has_dm_mem  = hcol($conexion, 'membresias', 'duracion_meses');
  $has_ad_mem  = hcol($conexion, 'membresias', 'adicionales_total');

  $cols  = ['cliente_id','plan_id','fecha_inicio','fecha_vencimiento','clases_disponibles','precio','descuento','total','gimnasio_id'];
  $types =  'iissiddii';
  $vals  = [$cliente_id, $plan_id, $fecha_inicio, $fecha_vencimiento, $clases_disponibles, $precio_plan, $descuento_pct, $total_cargo, $gimnasio_id];

  if ($has_op_mem) { $cols[]='otros_pagos';        $types.='d'; $vals[]=$otros_cargos; }
  if ($has_tp_mem) { $cols[]='total_pagado';       $types.='d'; $vals[]=$total_pagado_hoy; }
  if ($has_mp_mem) { $cols[]='metodo_pago';        $types.='s'; $vals[]=$metodo_pago_str; }
  if ($has_scc_mem){ $cols[]='saldo_cc';           $types.='d'; $vals[]=0.0; }
  if ($has_dm_mem) { $cols[]='duracion_meses';     $types.='i'; $vals[]=$duracion_meses; }
  if ($has_ad_mem) { $cols[]='adicionales_total';  $types.='d'; $vals[]=$precio_adicionales; }

  foreach ([
    'pago_efectivo'        => $pago_efectivo,
    'pago_transferencia'   => $pago_transferencia,
    'pago_debito'          => $pago_debito,
    'pago_credito'         => $pago_credito,
    'pago_cuenta_corriente'=> $pago_cc_manual
  ] as $col => $val) {
    if (hcol($conexion,'membresias',$col)) { $cols[] = $col; $types .= 'd'; $vals[] = $val; }
  }

  $placeholders = implode(',', array_fill(0, count($cols), '?'));
  $stmt = prep($conexion, "INSERT INTO membresias (".implode(',', $cols).") VALUES ($placeholders)", "membresias.insert");
  if (!bind_params_dynamic($stmt, $types, $vals) || !$stmt->execute()) {
    throw new Exception("[membresias.insert] execute(): ".$stmt->error);
  }
  $membresia_id = (int)$stmt->insert_id;
  $stmt->close();

  /* 2) Adicionales de la nueva (si existe esa tabla/cols) */
  if (!empty($adicionales_detalle) && table_exists($conexion, 'membresias_adicionales') && hcol($conexion,'membresias_adicionales','membresia_id')) {
    $colsAd = ['membresia_id']; $typesAd='i';
    $optCols = [];
    foreach (['cliente_id'=>'i','gimnasio_id'=>'i','plan_id'=>'i','fecha_inicio'=>'s','adicional_id'=>'i','nombre'=>'s','precio'=>'d'] as $c=>$t) {
      if (hcol($conexion,'membresias_adicionales',$c)) { $optCols[$c]=$t; }
    }
    $colsAd = array_merge($colsAd, array_keys($optCols));
    $typesAd.= implode('', array_values($optCols));
    $phAd    = implode(',', array_fill(0, 1+count($optCols), '?'));
    $sqlInsA = "INSERT INTO membresias_adicionales (".implode(',', $colsAd).") VALUES ($phAd)";
    $stA = prep($conexion, $sqlInsA, "membresias_adicionales.insert");
    foreach ($adicionales_detalle as $ad) {
      $valsAd = [$membresia_id];
      foreach (array_keys($optCols) as $c) {
        switch ($c) {
          case 'cliente_id':      $valsAd[] = $cliente_id; break;
          case 'gimnasio_id':     $valsAd[] = $gimnasio_id; break;
          case 'plan_id':         $valsAd[] = $plan_id; break;
          case 'fecha_inicio':    $valsAd[] = $fecha_inicio; break;
          case 'adicional_id':    $valsAd[] = (int)$ad['id']; break;
          case 'nombre':          $valsAd[] = (string)$ad['nombre']; break;
          case 'precio':          $valsAd[] = (float)$ad['precio']; break;
        }
      }
      if (!bind_params_dynamic($stA, $typesAd, $valsAd) || !$stA->execute()) {
        throw new Exception("[membresias_adicionales.insert] execute(): ".$stA->error);
      }
    }
    $stA->close();
  }

  /* 3) Pagos (tabla/cols dinámicas) */
  $table_pagos = null;
  if (table_exists($conexion, 'pagos'))                $table_pagos = 'pagos';
  elseif (table_exists($conexion, 'pagos_membresia'))  $table_pagos = 'pagos_membresia';

  if ($table_pagos) {
    $has_concepto   = hcol($conexion, $table_pagos, 'concepto');
    $has_fecha      = hcol($conexion, $table_pagos, 'fecha');
    $has_fecha_pago = hcol($conexion, $table_pagos, 'fecha_pago');
    $has_total      = hcol($conexion, $table_pagos, 'total');
    $has_monto      = hcol($conexion, $table_pagos, 'monto');
    $has_importe    = hcol($conexion, $table_pagos, 'importe');
    $has_json       = hcol($conexion, $table_pagos, 'metadata_json');

    $has_efec = hcol($conexion, $table_pagos, 'efectivo');
    $has_trf  = hcol($conexion, $table_pagos, 'transferencia');
    $has_deb  = hcol($conexion, $table_pagos, 'debito');
    $has_cred = hcol($conexion, $table_pagos, 'credito');
    $has_cc   = hcol($conexion, $table_pagos, 'cuenta_corriente');

    $has_metodo     = hcol($conexion, $table_pagos, 'metodo');
    $has_created_at = hcol($conexion, $table_pagos, 'created_at');
    $has_creado_en  = hcol($conexion, $table_pagos, 'creado_en');

    $colsPay = ['cliente_id','gimnasio_id']; $typesPay='ii'; $valsPay=[ $cliente_id, $gimnasio_id ];
    if ($has_concepto)   { $colsPay[]='concepto';   $typesPay.='s'; $valsPay[] = "Renovación de membresía #{$membresia_id}"; }
    if ($has_fecha)      { $colsPay[]='fecha';      $typesPay.='s'; $valsPay[] = date('Y-m-d'); }
    if ($has_fecha_pago) { $colsPay[]='fecha_pago'; $typesPay.='s'; $valsPay[] = date('Y-m-d H:i:s'); }

    if ($has_efec) { $colsPay[]='efectivo';        $typesPay.='d'; $valsPay[]=$pago_efectivo; }
    if ($has_trf)  { $colsPay[]='transferencia';   $typesPay.='d'; $valsPay[]=$pago_transferencia; }
    if ($has_deb)  { $colsPay[]='debito';          $typesPay.='d'; $valsPay[]=$pago_debito; }
    if ($has_cred) { $colsPay[]='credito';         $typesPay.='d'; $valsPay[]=$pago_credito; }
    if ($has_cc)   { $colsPay[]='cuenta_corriente';$typesPay.='d'; $valsPay[]=$pago_cc_manual; }

    if      ($has_total)   { $colsPay[]='total';   $typesPay.='d'; $valsPay[]=$total_pagado_hoy; }
    elseif  ($has_monto)   { $colsPay[]='monto';   $typesPay.='d'; $valsPay[]=$total_pagado_hoy; }
    elseif  ($has_importe) { $colsPay[]='importe'; $typesPay.='d'; $valsPay[]=$total_pagado_hoy; }

    if ($has_metodo)     { $colsPay[]='metodo';     $typesPay.='s'; $valsPay[]=$metodo_pago_str; }
    if ($has_created_at) { $colsPay[]='created_at'; $typesPay.='s'; $valsPay[] = date('Y-m-d H:i:s'); }
    if ($has_creado_en)  { $colsPay[]='creado_en';  $typesPay.='s'; $valsPay[] = date('Y-m-d H:i:s'); }

    if ($has_json) {
      $colsPay[]='metadata_json'; $typesPay.='s';
      $meta = [
        'membresia_id'=>$membresia_id,'plan_id'=>$plan_id,
        'fecha_inicio'=>$fecha_inicio,'fecha_vencimiento'=>$fecha_vencimiento,'duracion_meses'=>$duracion_meses,
        'precio_plan'=>$precio_plan,'otros_cargos'=>$otros_cargos,'adicionales'=>$adicionales_detalle,
        'descuento_pct'=>$descuento_pct,'descuento_monto'=>$descuento_monto,
        'total_cargo'=>$total_cargo,'total_pagado_hoy'=>$total_pagado_hoy,'pago_cc_manual'=>$pago_cc_manual
      ];
      $valsPay[] = json_encode($meta, JSON_UNESCAPED_UNICODE);
    }

    $phPay = implode(',', array_fill(0, count($colsPay), '?'));
    $sqlPay = "INSERT INTO {$table_pagos} (".implode(',', $colsPay).") VALUES ($phPay)";
    $insPago = prep($conexion, $sqlPay, "{$table_pagos}.insert_dynamic");
    if (!bind_params_dynamic($insPago, $typesPay, $valsPay) || !$insPago->execute()) {
      throw new Exception("[{$table_pagos}.insert_dynamic] execute(): ".$insPago->error);
    }
    $insPago->close();
  }

  /* 4) CC movimientos */
  $debe_total  = 0.0;
  $haber_total = 0.0;
  if (table_exists($conexion, 'cc_movimientos')) {
    $fecha_cc = $fecha_actual;

    if ($total_cargo > 0.0) {
      $concepto = "Renovación membresía #{$membresia_id} — cargo total";
      $stmtCC = prep($conexion,"INSERT INTO cc_movimientos (gimnasio_id, cliente_id, venta_id, fecha, concepto, debe, haber) VALUES (?, ?, ?, ?, ?, ?, 0)","cc_movimientos.cargo");
      $stmtCC->bind_param("iiissd", $gimnasio_id, $cliente_id, $membresia_id, $fecha_cc, $concepto, $total_cargo);
      $stmtCC->execute(); $stmtCC->close(); $debe_total += $total_cargo;
    }
    if ($total_pagado_hoy > 0.0) {
      $concepto = "Renovación membresía #{$membresia_id} — pago hoy";
      $stmtCC2 = prep($conexion,"INSERT INTO cc_movimientos (gimnasio_id, cliente_id, venta_id, fecha, concepto, debe, haber) VALUES (?, ?, ?, ?, ?, 0, ?)","cc_movimientos.pago");
      $stmtCC2->bind_param("iiissd", $gimnasio_id, $cliente_id, $membresia_id, $fecha_cc, $concepto, $total_pagado_hoy);
      $stmtCC2->execute(); $stmtCC2->close(); $haber_total += $total_pagado_hoy;
    }
    if ($pago_cc_manual > 0.0) {
      $concepto = "Renovación membresía #{$membresia_id} — a cuenta corriente (manual)";
      $stmtCC3 = prep($conexion,"INSERT INTO cc_movimientos (gimnasio_id, cliente_id, venta_id, fecha, concepto, debe, haber) VALUES (?, ?, ?, ?, ?, ?, 0)","cc_movimientos.cc_manual");
      $stmtCC3->bind_param("iiissd", $gimnasio_id, $cliente_id, $membresia_id, $fecha_cc, $concepto, $pago_cc_manual);
      $stmtCC3->execute(); $stmtCC3->close(); $debe_total += $pago_cc_manual;
    }
  }

  /* 5) saldo_cc en nueva membresía */
  $saldo_cc_final = round($debe_total - $haber_total, 2);
  if (hcol($conexion, 'membresias', 'saldo_cc')) {
    $stmtUpd = prep($conexion, "UPDATE membresias SET saldo_cc = ? WHERE id = ? AND gimnasio_id = ?", "membresias.update_saldo");
    $stmtUpd->bind_param("dii", $saldo_cc_final, $membresia_id, $gimnasio_id);
    $stmtUpd->execute(); $stmtUpd->close();
  }

  /* 6) Asegurar única activa (opcional) */
  if (hcol($conexion, 'membresias', 'activa')) {
    $stmtOff = prep($conexion, "UPDATE membresias SET activa = 0 WHERE cliente_id = ? AND gimnasio_id = ? AND id <> ?", "membresias.deactivate_others");
    $stmtOff->bind_param("iii", $cliente_id, $gimnasio_id, $membresia_id);
    $stmtOff->execute(); $stmtOff->close();

    $stmtOn = prep($conexion, "UPDATE membresias SET activa = 1 WHERE id = ? AND gimnasio_id = ?", "membresias.activate_new");
    $stmtOn->bind_param("ii", $membresia_id, $gimnasio_id);
    $stmtOn->execute(); $stmtOn->close();
  }

  /* 7) PURGE: eliminar TODAS las membresías anteriores del mismo cliente/gimnasio */
  $stOld = prep($conexion,
    "SELECT id FROM membresias WHERE cliente_id = ? AND gimnasio_id = ? AND id <> ?",
    "membresias.select_old"
  );
  $stOld->bind_param("iii", $cliente_id, $gimnasio_id, $membresia_id);
  $stOld->execute();
  $resOld = $stOld->get_result();
  $oldIds = [];
  while ($r = $resOld->fetch_assoc()) { $oldIds[] = (int)$r['id']; }
  $stOld->close();

  if (!empty($oldIds)) {
    $in = implode(',', array_map('intval', $oldIds));

    if (table_exists($conexion, 'membresias_adicionales') && hcol($conexion,'membresias_adicionales','membresia_id')) {
      $conexion->query("DELETE FROM membresias_adicionales WHERE membresia_id IN ($in)");
    }
    if (table_exists($conexion, 'cc_movimientos') && hcol($conexion,'cc_movimientos','venta_id')) {
      if (hcol($conexion,'cc_movimientos','gimnasio_id')) {
        $conexion->query("DELETE FROM cc_movimientos WHERE venta_id IN ($in) AND gimnasio_id = {$gimnasio_id}");
      } else {
        $conexion->query("DELETE FROM cc_movimientos WHERE venta_id IN ($in)");
      }
    }
    if (table_exists($conexion, 'asistencias') && hcol($conexion,'asistencias','membresia_id')) {
      $conexion->query("DELETE FROM asistencias WHERE membresia_id IN ($in)");
    }
    if (table_exists($conexion, 'pagos')) {
      if (hcol($conexion,'pagos','metadata_json')) {
        $likeConds = [];
        foreach ($oldIds as $oid) $likeConds[] = "metadata_json LIKE '%\"membresia_id\":$oid%'";
        $cond = implode(' OR ', $likeConds);
        $extra = hcol($conexion,'pagos','gimnasio_id') ? " AND gimnasio_id = {$gimnasio_id}" : "";
        $conexion->query("DELETE FROM pagos WHERE ($cond)$extra");
      }
      if (hcol($conexion,'pagos','concepto')) {
        $concepts = array_map(fn($oid)=>"'Renovación de membresía #$oid'", $oldIds);
        $extra = hcol($conexion,'pagos','gimnasio_id') ? " AND gimnasio_id = {$gimnasio_id}" : "";
        $conexion->query("DELETE FROM pagos WHERE concepto IN (".implode(',', $concepts).")$extra");
      }
    }
    if (table_exists($conexion, 'pagos_membresia') && hcol($conexion,'pagos_membresia','membresia_id')) {
        $conexion->query("DELETE FROM pagos_membresia WHERE membresia_id IN ($in)");
    }

    $conexion->query("DELETE FROM membresias WHERE id IN ($in)");
  }

  $conexion->commit();
  header("Location: ver_membresias.php?exito=1", true, 303);
  exit;

} catch (Throwable $e) {
  $conexion->rollback();
  http_response_code(500);
  echo "Error al guardar renovación: " . h($e->getMessage());
}
