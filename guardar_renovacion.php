<?php
// guardar_renovacion.php — renovación con CARGO (plan+otros+adicionales), PAGO por métodos, y DEBE a CC opcional
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
/* Normaliza números de entrada: 10,5 | 12.345,67 | 1234.56 */
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

/* =============== Entrada y validaciones =============== */
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? ($_POST['gimnasio_id'] ?? 0));
if ($gimnasio_id <= 0) { http_response_code(403); die("Acceso denegado"); }

$cliente_id         = (int)($_POST['cliente_id'] ?? 0);
$plan_id            = (int)($_POST['plan_id'] ?? 0);
$fecha_inicio       = $_POST['fecha_inicio'] ?? date('Y-m-d');
$fecha_vencimiento  = $_POST['fecha_vencimiento'] ?? '';
$clases_disponibles = (int)($_POST['clases_disponibles'] ?? 0);

/* Números robustos */
$precio_plan   = n($_POST['precio']        ?? 0);
$otros_cargos  = n($_POST['otros_pagos']   ?? 0);        // UI dice “Otros cargos”, se suma a CARGO
$descuento_pct = n($_POST['descuento']     ?? 0);
$duracion_meses= (int)($_POST['duracion_meses'] ?? 0);

/* Pagos individuales (no incluyen CC) */
$pago_efectivo      = n($_POST['pago_efectivo']      ?? 0);
$pago_transferencia = n($_POST['pago_transferencia'] ?? 0);
$pago_debito        = n($_POST['pago_debito']        ?? 0);
$pago_credito       = n($_POST['pago_credito']       ?? 0);
/* A CC (DEBE) -> se registra como CARGO adicional (deuda) */
$pago_cc_manual     = n($_POST['pago_cuenta_corriente'] ?? 0);

/* Adicionales seleccionados */
$adicionales_ids = isset($_POST['adicionales']) && is_array($_POST['adicionales'])
  ? array_map('intval', $_POST['adicionales'])
  : [];

$fecha_actual = date('Y-m-d H:i:s');

/* Si falta vencimiento, calcularlo con duracion_meses */
if ($fecha_vencimiento === '' || $fecha_vencimiento === null) {
  $ts = strtotime($fecha_inicio ?: date('Y-m-d'));
  $fecha_vencimiento = date('Y-m-d', strtotime("+{$duracion_meses} month", $ts));
}

/* ========= Fallback: si precio del plan viene 0, leer del plan ========= */
if ($precio_plan <= 0 && $plan_id > 0) {
  $stp = $conexion->prepare("SELECT precio FROM planes WHERE id=? AND gimnasio_id=? LIMIT 1");
  if ($stp) {
    $stp->bind_param("ii", $plan_id, $gimnasio_id);
    $stp->execute();
    $stp->bind_result($pplan);
    if ($stp->fetch()) { $precio_plan = n($pplan); }
    $stp->close();
  }
}

/* Clamp de descuento (0..100) */
if ($descuento_pct < 0)   $descuento_pct = 0;
if ($descuento_pct > 100) $descuento_pct = 100;

/* ===== Traer precios de adicionales (desde BD) ===== */
$precio_adicionales = 0.0;
$adicionales_detalle = [];

if (!empty($adicionales_ids)) {
  $ids_in = implode(',', array_map('intval', $adicionales_ids));
  $sqlAd  = "SELECT id, nombre, precio FROM planes_adicionales WHERE gimnasio_id = ? AND id IN ($ids_in)";
  $stmtAd = $conexion->prepare($sqlAd);
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

/* ===== Cálculo financiero =====
   CARGO = precio_plan + otros_cargos + adicionales
   Descuento % sobre ese subtotal
   total_cargo = subtotal - descuento
   total_pagado_hoy = suma métodos (efec/trans/deb/cred)
   dif = total_cargo - total_pagado_hoy
   A CC manual se registra como CARGO extra (DEBE).
*/
$subtotal_cargos = round($precio_plan + $otros_cargos + $precio_adicionales, 2);
$descuento_monto = round($subtotal_cargos * ($descuento_pct / 100), 2);
$total_cargo     = round(max(0, $subtotal_cargos - $descuento_monto), 2);

$total_pagado_hoy = round($pago_efectivo + $pago_transferencia + $pago_debito + $pago_credito, 2);
$dif = round($total_cargo - $total_pagado_hoy, 2);

/* Métodos de pago (texto) */
$metodos = [];
if ($pago_efectivo      > 0) $metodos[] = "Efectivo:$pago_efectivo";
if ($pago_transferencia > 0) $metodos[] = "Transferencia:$pago_transferencia";
if ($pago_debito        > 0) $metodos[] = "Débito:$pago_debito";
if ($pago_credito       > 0) $metodos[] = "Crédito:$pago_credito";
$metodo_pago_str = $metodos ? implode(' | ', $metodos) : 'Sin pagar ahora';

try {
  $conexion->begin_transaction();

  /* ===== Lock filas del cliente para evitar condiciones de carrera ===== */
  $conexion->query("SELECT id FROM membresias WHERE cliente_id = {$cliente_id} AND gimnasio_id = {$gimnasio_id} FOR UPDATE");

  /* ===== 1) Mover anteriores a historial (si existe) ===== */
  if (table_exists($conexion, 'membresias_historial')) {
    $resPrev = $conexion->query("
      SELECT * FROM membresias WHERE cliente_id = {$cliente_id} AND gimnasio_id = {$gimnasio_id}
    ");

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
      $sqlHist = "INSERT INTO membresias_historial (".implode(',', $hist_cols).") VALUES ($placeholders)";
      $stmtHist = $conexion->prepare($sqlHist);
      if (!$stmtHist) { throw new Exception("Prepare historial: ".$conexion->error); }

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
          throw new Exception("Exec historial: ".$stmtHist->error);
        }
      }
      $stmtHist->close();
    }
  }

  /* ===== 2) Eliminar membresías anteriores ===== */
  $stmtDel = $conexion->prepare("DELETE FROM membresias WHERE cliente_id = ? AND gimnasio_id = ?");
  $stmtDel->bind_param("ii", $cliente_id, $gimnasio_id);
  $stmtDel->execute();
  $stmtDel->close();

  /* ===== 3) Insertar NUEVA membresía (columnas dinámicas) ===== */
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

  // Desgloses (si existen en tu schema)
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
  $sqlMem = "INSERT INTO membresias (".implode(',', $cols).") VALUES ($placeholders)";
  $stmt = $conexion->prepare($sqlMem);
  if (!$stmt) { throw new Exception("Prepare membresias: ".$conexion->error); }
  if (!bind_params_dynamic($stmt, $types, $vals) || !$stmt->execute()) {
    throw new Exception("Exec membresias: ".$stmt->error);
  }
  $membresia_id = (int)$stmt->insert_id;
  $stmt->close();

  /* ===== 3.b) Guardar detalle de adicionales si existe ===== */
  if (!empty($adicionales_detalle) && table_exists($conexion, 'membresias_adicionales')) {
    // Limpieza opcional del ciclo exacto
    $del = $conexion->prepare("DELETE FROM membresias_adicionales WHERE cliente_id = ? AND gimnasio_id = ? AND plan_id = ? AND fecha_inicio = ?");
    $del->bind_param('iiis', $cliente_id, $gimnasio_id, $plan_id, $fecha_inicio);
    $del->execute();
    $del->close();

    $insAd = $conexion->prepare("INSERT INTO membresias_adicionales
      (membresia_id, cliente_id, gimnasio_id, plan_id, fecha_inicio, adicional_id, nombre, precio)
      VALUES (?,?,?,?,?,?,?,?)");
    foreach ($adicionales_detalle as $ad) {
      $aid = (int)$ad['id']; $an = (string)$ad['nombre']; $ap = (float)$ad['precio'];
      $insAd->bind_param('iiiiisdd', $membresia_id, $cliente_id, $gimnasio_id, $plan_id, $fecha_inicio, $aid, $an, $ap);
      $insAd->execute();
    }
    $insAd->close();
  }

  /* ===== 4) Registrar pagos en tabla de pagos si existe ===== */
  $table_pagos = null;
  if (table_exists($conexion, 'pagos'))                $table_pagos = 'pagos';
  elseif (table_exists($conexion, 'pagos_membresia'))  $table_pagos = 'pagos_membresia';

  if ($table_pagos) {
    $insPago = $conexion->prepare("INSERT INTO $table_pagos
      (cliente_id, gimnasio_id, concepto, fecha, efectivo, transferencia, debito, credito, cuenta_corriente, total, metadata_json)
      VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    $concepto = "Renovación de membresía #{$membresia_id}";
    $fechaHoy = date('Y-m-d');
    $meta = [
      'membresia_id'      => $membresia_id,
      'plan_id'           => $plan_id,
      'fecha_inicio'      => $fecha_inicio,
      'fecha_vencimiento' => $fecha_vencimiento,
      'duracion_meses'    => $duracion_meses,
      'precio_plan'       => $precio_plan,
      'otros_cargos'      => $otros_cargos,
      'adicionales'       => $adicionales_detalle,
      'descuento_pct'     => $descuento_pct,
      'descuento_monto'   => $descuento_monto,
      'total_cargo'       => $total_cargo,
      'total_pagado_hoy'  => $total_pagado_hoy,
      'pago_cc_manual'    => $pago_cc_manual
    ];
    $json = json_encode($meta, JSON_UNESCAPED_UNICODE);

    $insPago->bind_param(
      'iissdddddds',
      $cliente_id, $gimnasio_id, $concepto, $fechaHoy,
      $pago_efectivo, $pago_transferencia, $pago_debito, $pago_credito, $pago_cc_manual,
      $total_pagado_hoy, $json
    );
    if (!$insPago->execute()) { throw new Exception('No pude registrar el pago.'); }
    $insPago->close();
  }

  /* ===== 5) Asientos en cuenta corriente (si existe) =====
     Modelo: una línea CARGO (debe) por total_cargo;
             una línea PAGO (haber) por total_pagado_hoy;
             si hay "a CC manual", otra línea CARGO (debe) por ese monto.
     (Esto deja trazabilidad clara; el saldo final queda de la diferencia)
  */
  $debe_total  = 0.0;
  $haber_total = 0.0;

  if (table_exists($conexion, 'cc_movimientos')) {
    $fecha_cc = $fecha_actual;

    // CARGO total de la renovación
    if ($total_cargo > 0.0) {
      $concepto = "Renovación membresía #{$membresia_id} — cargo total";
      $stmtCC = $conexion->prepare("
        INSERT INTO cc_movimientos (gimnasio_id, cliente_id, venta_id, fecha, concepto, debe, haber)
        VALUES (?, ?, ?, ?, ?, ?, 0)
      ");
      if (!$stmtCC) { throw new Exception("Prepare cc_movimientos (cargo): ".$conexion->error); }
      $stmtCC->bind_param("iiissd", $gimnasio_id, $cliente_id, $membresia_id, $fecha_cc, $concepto, $total_cargo);
      if (!$stmtCC->execute()) { throw new Exception("Exec cc_movimientos (cargo): ".$stmtCC->error); }
      $stmtCC->close();
      $debe_total += $total_cargo;
    }

    // PAGO abonado hoy (haber)
    if ($total_pagado_hoy > 0.0) {
      $concepto = "Renovación membresía #{$membresia_id} — pago hoy";
      $stmtCC2 = $conexion->prepare("
        INSERT INTO cc_movimientos (gimnasio_id, cliente_id, venta_id, fecha, concepto, debe, haber)
        VALUES (?, ?, ?, ?, ?, 0, ?)
      ");
      if (!$stmtCC2) { throw new Exception("Prepare cc_movimientos (pago): ".$conexion->error); }
      $stmtCC2->bind_param("iiissd", $gimnasio_id, $cliente_id, $membresia_id, $fecha_cc, $concepto, $total_pagado_hoy);
      if (!$stmtCC2->execute()) { throw new Exception("Exec cc_movimientos (pago): ".$stmtCC2->error); }
      $stmtCC2->close();
      $haber_total += $total_pagado_hoy;
    }

    // CARGO manual a CC (si el operador decidió pasar una parte a cuenta)
    if ($pago_cc_manual > 0.0) {
      $concepto = "Renovación membresía #{$membresia_id} — a cuenta corriente (manual)";
      $stmtCC3 = $conexion->prepare("
        INSERT INTO cc_movimientos (gimnasio_id, cliente_id, venta_id, fecha, concepto, debe, haber)
        VALUES (?, ?, ?, ?, ?, ?, 0)
      ");
      if (!$stmtCC3) { throw new Exception("Prepare cc_movimientos (cc manual): ".$conexion->error); }
      $stmtCC3->bind_param("iiissd", $gimnasio_id, $cliente_id, $membresia_id, $fecha_cc, $concepto, $pago_cc_manual);
      if (!$stmtCC3->execute()) { throw new Exception("Exec cc_movimientos (cc manual): ".$stmtCC3->error); }
      $stmtCC3->close();
      $debe_total += $pago_cc_manual;
    }
  }

  /* ===== 6) Actualizar saldo_cc en membresía si existe ===== */
  $saldo_cc_final = round($debe_total - $haber_total, 2);
  if (hcol($conexion, 'membresias', 'saldo_cc')) {
    $stmtUpd = $conexion->prepare("UPDATE membresias SET saldo_cc = ? WHERE id = ? AND gimnasio_id = ?");
    $stmtUpd->bind_param("dii", $saldo_cc_final, $membresia_id, $gimnasio_id);
    $stmtUpd->execute();
    $stmtUpd->close();
  }

  /* ===== 7) Asegurar una sola membresía activa ===== */
  if (hcol($conexion, 'membresias', 'activa')) {
    $conexion->query("UPDATE membresias
                      SET activa = 0
                      WHERE cliente_id = {$cliente_id} AND gimnasio_id = {$gimnasio_id} AND id <> {$membresia_id}");
    $conexion->query("UPDATE membresias
                      SET activa = 1
                      WHERE id = {$membresia_id} AND gimnasio_id = {$gimnasio_id}");
  }
  // Limpieza extra por si algo quedó
  $stmtClean = $conexion->prepare("DELETE FROM membresias WHERE cliente_id = ? AND gimnasio_id = ? AND id <> ?");
  $stmtClean->bind_param("iii", $cliente_id, $gimnasio_id, $membresia_id);
  $stmtClean->execute();
  $stmtClean->close();

  $conexion->commit();
  header("Location: ver_membresias.php?exito=1");q
  
  exit;

} catch (Throwable $e) {
  $conexion->rollback();
  http_response_code(500);
  echo "Error al guardar renovación: " . h($e->getMessage());
}
