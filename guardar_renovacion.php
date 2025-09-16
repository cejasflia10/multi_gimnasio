<?php
// guardar_renovacion.php — versión con diagnóstico de prepares (POST only)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); die("Sin conexión a la base de datos"); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ================= Helpers ================= */
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
  $refs = []; $refs[] = $types;
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

/* ================= Método ================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405); die("Método no permitido (solo POST).");
}

/* ================= Entrada ================= */
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

if ($fecha_vencimiento === '' || $fecha_vencimiento === null) {
  $ts = strtotime($fecha_inicio ?: date('Y-m-d'));
  $fecha_vencimiento = date('Y-m-d', strtotime("+{$duracion_meses} month", $ts));
}

/* Fallback: precio plan desde BD si vino 0 */
if ($precio_plan <= 0 && $plan_id > 0) {
  $stp = prep($conexion, "SELECT precio FROM planes WHERE id=? AND gimnasio_id=? LIMIT 1", "plan.select");
  $stp->bind_param("ii", $plan_id, $gimnasio_id);
  $stp->execute();
  $stp->bind_result($pplan);
  if ($stp->fetch()) { $precio_plan = n($pplan); }
  $stp->close();
}
if ($descuento_pct < 0)   $descuento_pct = 0;
if ($descuento_pct > 100) $descuento_pct = 100;

/* Adicionales seleccionados: precios desde BD */
$precio_adicionales = 0.0;
$adicionales_detalle = [];
if (!empty($adicionales_ids)) {
  $ids_in = implode(',', array_map('intval', $adicionales_ids));
  $stmtAd = prep($conexion, "SELECT id, nombre, precio FROM planes_adicionales WHERE gimnasio_id = ? AND id IN ($ids_in)", "adicionales.select");
  $stmtAd->bind_param('i', $gimnasio_id);
  if ($stmtAd->execute() && ($resAd = $stmtAd->get_result())) {
    while ($row = $resAd->fetch_assoc()) {
      $precio_adicionales += (float)$row['precio'];
      $adicionales_detalle[] = [
        'id'     => (int)$row['id'],
        'nombre' => (string)$row['nombre'],
        'precio' => (float)$row['precio'],
      ];
    }
  }
  $stmtAd->close();
}

/* ================= Cálculos ================= */
$subtotal_cargos = round($precio_plan + $otros_cargos + $precio_adicionales, 2);
$descuento_monto = round($subtotal_cargos * ($descuento_pct / 100), 2);
$total_cargo     = round(max(0, $subtotal_cargos - $descuento_monto), 2);

$total_pagado_hoy = round($pago_efectivo + $pago_transferencia + $pago_debito + $pago_credito, 2);

$metodos = [];
if ($pago_efectivo      > 0) $metodos[] = "Efectivo:$pago_efectivo";
if ($pago_transferencia > 0) $metodos[] = "Transferencia:$pago_transferencia";
if ($pago_debito        > 0) $metodos[] = "Débito:$pago_debito";
if ($pago_credito       > 0) $metodos[] = "Crédito:$pago_credito";
$metodo_pago_str = $metodos ? implode(' | ', $metodos) : 'Sin pagar ahora';

/* ================= Transacción ================= */
try {
  $conexion->begin_transaction();

  /* Lock fila(s) del cliente */
  $conexion->query("SELECT id FROM membresias WHERE cliente_id = {$cliente_id} AND gimnasio_id = {$gimnasio_id} FOR UPDATE");

  /* 1) Pasar actuales a historial (si existe) */
  if (table_exists($conexion, 'membresias_historial')) {
    $resPrev = $conexion->query("SELECT * FROM membresias WHERE cliente_id = {$cliente_id} AND gimnasio_id = {$gimnasio_id}");
    if ($resPrev && $resPrev->num_rows > 0) {
      $has_mp_hist   = hcol($conexion, 'membresias_historial', 'metodo_pago');
      $has_op_hist   = hcol($conexion, 'membresias_historial', 'otros_pagos');
      $has_tot_hist  = hcol($conexion, 'membresias_historial', 'total');
      $has_dm_hist   = hcol($conexion, 'membresias_historial', 'duracion_meses');

      $hist_cols  = ['cliente_id','gimnasio_id','plan_id','precio','clases_disponibles','fecha_inicio','fecha_vencimiento'];
      $hist_types =  'iiidiss';
      if ($has_op_hist)  { $hist_cols[]='otros_pagos';    $hist_types.='d'; }
      if ($has_mp_hist)  { $hist_cols[]='metodo_pago';    $hist_types.='s'; }
      if ($has_tot_hist) { $hist_cols[]='total';          $hist_types.='d'; }
      if ($has_dm_hist)  { $hist_cols[]='duracion_meses'; $hist_types.='i'; }

      $placeholders = implode(',', array_fill(0, count($hist_cols), '?'));
      $stmtHist = prep($conexion, "INSERT INTO membresias_historial (".implode(',', $hist_cols).") VALUES ($placeholders)", "historial.insert");

      while ($m = $resPrev->fetch_assoc()) {
        $vals = [
          (int)$m['cliente_id'], (int)$m['gimnasio_id'], (int)$m['plan_id'],
          n($m['precio'] ?? 0), (int)($m['clases_disponibles'] ?? 0),
          (string)($m['fecha_inicio'] ?? date('Y-m-d')),
          (string)($m['fecha_vencimiento'] ?? date('Y-m-d')),
        ];
        if ($has_op_hist)  { $vals[] = n($m['otros_pagos'] ?? 0); }
        if ($has_mp_hist)  { $vals[] = (string)($m['metodo_pago'] ?? ''); }
        if ($has_tot_hist) { $vals[] = n($m['total'] ?? 0); }
        if ($has_dm_hist)  { $vals[] = (int)($m['duracion_meses'] ?? 0); }

        if (!bind_params_dynamic($stmtHist, $hist_types, $vals) || !$stmtHist->execute()) {
          throw new Exception("[historial.insert] execute(): ".$stmtHist->error);
        }
      }
      $stmtHist->close();
    }
  }

  /* 2) Borrar membresías anteriores del cliente */
  $stmtDel = prep($conexion, "DELETE FROM membresias WHERE cliente_id = ? AND gimnasio_id = ?", "membresias.delete_prev");
  $stmtDel->bind_param("ii", $cliente_id, $gimnasio_id);
  $stmtDel->execute();
  $stmtDel->close();

  /* 3) Insertar nueva membresía (columnas dinámicas) */
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

  /* 3.b) Guardar detalle de adicionales — DINÁMICO según columnas reales */
  if (!empty($adicionales_detalle) && table_exists($conexion, 'membresias_adicionales')) {
    $has_mid  = hcol($conexion,'membresias_adicionales','membresia_id');
    $has_cli  = hcol($conexion,'membresias_adicionales','cliente_id');
    $has_gym  = hcol($conexion,'membresias_adicionales','gimnasio_id');
    $has_plan = hcol($conexion,'membresias_adicionales','plan_id');
    $has_fi   = hcol($conexion,'membresias_adicionales','fecha_inicio');
    $has_aid  = hcol($conexion,'membresias_adicionales','adicional_id');
    $has_nom  = hcol($conexion,'membresias_adicionales','nombre');
    $has_prec = hcol($conexion,'membresias_adicionales','precio');

    if ($has_mid) {
      $whereCols = ['membresia_id']; $whereTyp = 'i'; $whereVals = [$membresia_id];
      if ($has_cli)  { $whereCols[]='cliente_id';  $whereTyp.='i'; $whereVals[]=$cliente_id; }
      if ($has_gym)  { $whereCols[]='gimnasio_id'; $whereTyp.='i'; $whereVals[]=$gimnasio_id; }
      if ($has_plan) { $whereCols[]='plan_id';     $whereTyp.='i'; $whereVals[]=$plan_id; }
      if ($has_fi)   { $whereCols[]='fecha_inicio';$whereTyp.='s'; $whereVals[]=$fecha_inicio; }

      $whereSql = implode(' AND ', array_map(fn($c)=>"$c = ?", $whereCols));
      $sqlDel   = "DELETE FROM membresias_adicionales WHERE $whereSql";
      $stmtDel  = $conexion->prepare($sqlDel);
      if ($stmtDel) {
        bind_params_dynamic($stmtDel, $whereTyp, $whereVals);
        $stmtDel->execute();
        $stmtDel->close();
      }

      $colsAd  = ['membresia_id']; $typesAd='i';
      if ($has_cli)  { $colsAd[]='cliente_id';  $typesAd.='i'; }
      if ($has_gym)  { $colsAd[]='gimnasio_id'; $typesAd.='i'; }
      if ($has_plan) { $colsAd[]='plan_id';     $typesAd.='i'; }
      if ($has_fi)   { $colsAd[]='fecha_inicio';$typesAd.='s'; }
      if ($has_aid)  { $colsAd[]='adicional_id';$typesAd.='i'; }
      if ($has_nom)  { $colsAd[]='nombre';      $typesAd.='s'; }
      if ($has_prec) { $colsAd[]='precio';      $typesAd.='d'; }

      $phAd   = implode(',', array_fill(0, count($colsAd), '?'));
      $sqlIns = "INSERT INTO membresias_adicionales (".implode(',', $colsAd).") VALUES ($phAd)";
      $stmtAd = $conexion->prepare($sqlIns);
      if (!$stmtAd) { throw new Exception("Prepare membresias_adicionales (dinámico): ".$conexion->error); }

      foreach ($adicionales_detalle as $ad) {
        $valsAd = [ $membresia_id ];
        if ($has_cli)  { $valsAd[] = $cliente_id; }
        if ($has_gym)  { $valsAd[] = $gimnasio_id; }
        if ($has_plan) { $valsAd[] = $plan_id; }
        if ($has_fi)   { $valsAd[] = $fecha_inicio; }
        if ($has_aid)  { $valsAd[] = (int)$ad['id']; }
        if ($has_nom)  { $valsAd[] = (string)$ad['nombre']; }
        if ($has_prec) { $valsAd[] = (float)$ad['precio']; }

        if (!bind_params_dynamic($stmtAd, $typesAd, $valsAd) || !$stmtAd->execute()) {
          throw new Exception("Exec membresias_adicionales (dinámico): ".$stmtAd->error);
        }
      }
      $stmtAd->close();
    }
  }

  /* 4) Registrar pago en tabla de pagos — DINÁMICO según columnas reales */
  $table_pagos = null;
  if (table_exists($conexion, 'pagos'))                $table_pagos = 'pagos';
  elseif (table_exists($conexion, 'pagos_membresia'))  $table_pagos = 'pagos_membresia';

  if ($table_pagos) {
    // Descubrir columnas disponibles
    $has_concepto   = hcol($conexion, $table_pagos, 'concepto');
    $has_fecha      = hcol($conexion, $table_pagos, 'fecha');
    $has_fecha_pago = hcol($conexion, $table_pagos, 'fecha_pago'); // <-- requerida en tu error
    $has_total      = hcol($conexion, $table_pagos, 'total');
    $has_monto      = hcol($conexion, $table_pagos, 'monto');
    $has_importe    = hcol($conexion, $table_pagos, 'importe');
    $has_json       = hcol($conexion, $table_pagos, 'metadata_json');

    $has_efec = hcol($conexion, $table_pagos, 'efectivo');
    $has_trf  = hcol($conexion, $table_pagos, 'transferencia');
    $has_deb  = hcol($conexion, $table_pagos, 'debito');
    $has_cred = hcol($conexion, $table_pagos, 'credito');
    $has_cc   = hcol($conexion, $table_pagos, 'cuenta_corriente');

    // Otros posibles campos útiles (evita NOT NULL sin default):
    $has_metodo     = hcol($conexion, $table_pagos, 'metodo');
    $has_created_at = hcol($conexion, $table_pagos, 'created_at');
    $has_creado_en  = hcol($conexion, $table_pagos, 'creado_en');

    $colsPay = ['cliente_id','gimnasio_id'];
    $typesPay = 'ii';
    $valsPay = [ $cliente_id, $gimnasio_id ];

    if ($has_concepto)   { $colsPay[]='concepto';   $typesPay.='s'; $valsPay[] = "Renovación de membresía #{$membresia_id}"; }
    if ($has_fecha)      { $colsPay[]='fecha';      $typesPay.='s'; $valsPay[] = date('Y-m-d'); }
    if ($has_fecha_pago) { $colsPay[]='fecha_pago'; $typesPay.='s'; $valsPay[] = date('Y-m-d H:i:s'); } // <-- agregado

    if ($has_efec) { $colsPay[]='efectivo';       $typesPay.='d'; $valsPay[]=$pago_efectivo; }
    if ($has_trf)  { $colsPay[]='transferencia';  $typesPay.='d'; $valsPay[]=$pago_transferencia; }
    if ($has_deb)  { $colsPay[]='debito';         $typesPay.='d'; $valsPay[]=$pago_debito; }
    if ($has_cred) { $colsPay[]='credito';        $typesPay.='d'; $valsPay[]=$pago_credito; }
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

  /* 5) Cuenta corriente (si existe) */
  $debe_total  = 0.0;
  $haber_total = 0.0;
  if (table_exists($conexion, 'cc_movimientos')) {
    $fecha_cc = $fecha_actual;

    if ($total_cargo > 0.0) {
      $stmtCC = prep($conexion,
        "INSERT INTO cc_movimientos (gimnasio_id, cliente_id, venta_id, fecha, concepto, debe, haber)
         VALUES (?, ?, ?, ?, ?, ?, 0)",
        "cc_movimientos.cargo"
      );
      $concepto = "Renovación membresía #{$membresia_id} — cargo total";
      $stmtCC->bind_param("iiissd", $gimnasio_id, $cliente_id, $membresia_id, $fecha_cc, $concepto, $total_cargo);
      if (!$stmtCC->execute()) { throw new Exception("[cc_movimientos.cargo] execute(): ".$stmtCC->error); }
      $stmtCC->close(); $debe_total += $total_cargo;
    }
    if ($total_pagado_hoy > 0.0) {
      $stmtCC2 = prep($conexion,
        "INSERT INTO cc_movimientos (gimnasio_id, cliente_id, venta_id, fecha, concepto, debe, haber)
         VALUES (?, ?, ?, ?, ?, 0, ?)",
        "cc_movimientos.pago"
      );
      $concepto = "Renovación membresía #{$membresia_id} — pago hoy";
      $stmtCC2->bind_param("iiissd", $gimnasio_id, $cliente_id, $membresia_id, $fecha_cc, $concepto, $total_pagado_hoy);
      if (!$stmtCC2->execute()) { throw new Exception("[cc_movimientos.pago] execute(): ".$stmtCC2->error); }
      $stmtCC2->close(); $haber_total += $total_pagado_hoy;
    }
    if ($pago_cc_manual > 0.0) {
      $stmtCC3 = prep($conexion,
        "INSERT INTO cc_movimientos (gimnasio_id, cliente_id, venta_id, fecha, concepto, debe, haber)
         VALUES (?, ?, ?, ?, ?, ?, 0)",
        "cc_movimientos.cc_manual"
      );
      $concepto = "Renovación membresía #{$membresia_id} — a cuenta corriente (manual)";
      $stmtCC3->bind_param("iiissd", $gimnasio_id, $cliente_id, $membresia_id, $fecha_cc, $concepto, $pago_cc_manual);
      if (!$stmtCC3->execute()) { throw new Exception("[cc_movimientos.cc_manual] execute(): ".$stmtCC3->error); }
      $stmtCC3->close(); $debe_total += $pago_cc_manual;
    }
  }

  /* 6) saldo_cc en membresía (si existe) */
  $saldo_cc_final = round($debe_total - $haber_total, 2);
  if (hcol($conexion, 'membresias', 'saldo_cc')) {
    $stmtUpd = prep($conexion, "UPDATE membresias SET saldo_cc = ? WHERE id = ? AND gimnasio_id = ?", "membresias.update_saldo");
    $stmtUpd->bind_param("dii", $saldo_cc_final, $membresia_id, $gimnasio_id);
    $stmtUpd->execute();
    $stmtUpd->close();
  }

  /* 7) única activa + limpieza */
  if (hcol($conexion, 'membresias', 'activa')) {
    $conexion->query("UPDATE membresias SET activa = 0 WHERE cliente_id = {$cliente_id} AND gimnasio_id = {$gimnasio_id} AND id <> {$membresia_id}");
    $conexion->query("UPDATE membresias SET activa = 1 WHERE id = {$membresia_id} AND gimnasio_id = {$gimnasio_id}");
  }
  $stmtClean = prep($conexion, "DELETE FROM membresias WHERE cliente_id = ? AND gimnasio_id = ? AND id <> ?", "membresias.clean_others");
  $stmtClean->bind_param("iii", $cliente_id, $gimnasio_id, $membresia_id);
  $stmtClean->execute();
  $stmtClean->close();

  $conexion->commit();
  header("Location: ver_membresias.php?exito=1", true, 303);
  exit;

} catch (Throwable $e) {
  $conexion->rollback();
  http_response_code(500);
  echo "Error al guardar renovación: " . h($e->getMessage());
}
