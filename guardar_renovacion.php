<?php
// guardar_renovacion.php — renovación única + total robusto + cc_movimientos
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500); die("Sin conexión a la base de datos");
}
@$conexion->set_charset('utf8mb4');

/* ================= Helpers ================= */
function hcol(mysqli $db, string $table, string $col): bool {
  $table = $db->real_escape_string($table);
  $col   = $db->real_escape_string($col);
  $res = $db->query("SHOW COLUMNS FROM `{$table}` LIKE '{$col}'");
  return ($res && $res->num_rows > 0);
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
  $s = str_replace(["\xC2\xA0", ' '], '', $s); // espacios / no-break space
  $hasComma = strpos($s, ',') !== false;
  $hasDot   = strpos($s, '.') !== false;
  if ($hasComma && $hasDot) {
    // Asumimos formato ES: '.' miles, ',' decimales
    $s = str_replace('.', '', $s);
    $s = str_replace(',', '.', $s);
  } elseif ($hasComma && !$hasDot) {
    // Solo coma -> tratar como decimal
    $s = str_replace(',', '.', $s);
  }
  return (float)$s;
}

/* =============== Entrada y validaciones =============== */
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? ($_POST['gimnasio_id'] ?? 0));
if ($gimnasio_id <= 0) { http_response_code(403); die("Acceso denegado"); }

$cliente_id         = (int)($_POST['cliente_id'] ?? 0);
$plan_id            = (int)($_POST['plan_id'] ?? 0);
$fecha_inicio       = $_POST['fecha_inicio'] ?? date('Y-m-d');
$fecha_vencimiento  = $_POST['fecha_vencimiento'] ?? '';
$clases_disponibles = (int)($_POST['clases_disponibles'] ?? 0);

/* Números robustos */
$precio        = n($_POST['precio']        ?? 0);
$otros_pagos   = n($_POST['otros_pagos']   ?? 0);
$descuento     = n($_POST['descuento']     ?? 0);
$duracion_meses= (int)($_POST['duracion_meses'] ?? 0);

/* Pagos individuales (no incluyen CC) */
$pago_efectivo      = n($_POST['pago_efectivo']      ?? 0);
$pago_transferencia = n($_POST['pago_transferencia'] ?? 0);
$pago_debito        = n($_POST['pago_debito']        ?? 0);
$pago_credito       = n($_POST['pago_credito']       ?? 0);
/* A CC (DEBE) */
$pago_cc_manual     = n($_POST['pago_cuenta_corriente'] ?? 0);

$fecha_actual = date('Y-m-d H:i:s');

/* Si falta vencimiento, calcularlo con duracion_meses */
if ($fecha_vencimiento === '' || $fecha_vencimiento === null) {
  $ts = strtotime($fecha_inicio ?: date('Y-m-d'));
  $fecha_vencimiento = date('Y-m-d', strtotime("+{$duracion_meses} month", $ts));
}

/* ========= Fallback: si precio viene 0, leer del plan ========= */
if ($precio <= 0 && $plan_id > 0) {
  $stp = $conexion->prepare("SELECT precio FROM planes WHERE id=? AND gimnasio_id=? LIMIT 1");
  if ($stp) {
    $stp->bind_param("ii", $plan_id, $gimnasio_id);
    $stp->execute();
    $stp->bind_result($precio_plan);
    if ($stp->fetch()) { $precio = n($precio_plan); }
    $stp->close();
  }
}
/* Clamp de descuento (0..100) para evitar totales locos */
if ($descuento < 0)   $descuento = 0;
if ($descuento > 100) $descuento = 100;

/* Totales correctos */
$total_bruto       = round($precio + $otros_pagos, 2);
$total_final       = round($total_bruto - ($total_bruto * ($descuento/100)), 2);
if ($total_final < 0) $total_final = 0; // por seguridad

$total_abonado_hoy = round($pago_efectivo + $pago_transferencia + $pago_debito + $pago_credito, 2);
/* Diferencia: si >0 falta pagar; si <0 sobra */
$dif = round($total_final - $total_abonado_hoy, 2);

/* Métodos de pago (texto) */
$metodos = [];
if ($pago_efectivo      > 0) $metodos[] = "Efectivo:$pago_efectivo";
if ($pago_transferencia > 0) $metodos[] = "Transferencia:$pago_transferencia";
if ($pago_debito        > 0) $metodos[] = "Débito:$pago_debito";
if ($pago_credito       > 0) $metodos[] = "Crédito:$pago_credito";
$metodo_pago_str = $metodos ? implode(' | ', $metodos) : 'Sin pagar ahora';

try {
  $conexion->begin_transaction();

  /* ===== Lock filas del cliente para evitar duplicados ===== */
  $conexion->query("SELECT id FROM membresias WHERE cliente_id = {$cliente_id} AND gimnasio_id = {$gimnasio_id} FOR UPDATE");

  /* ===== 1) Mover anteriores a historial ===== */
  $resPrev = $conexion->query("
    SELECT * FROM membresias
    WHERE cliente_id = {$cliente_id} AND gimnasio_id = {$gimnasio_id}
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

  /* ===== 2) Eliminar todas las membresías anteriores ===== */
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

  $cols  = ['cliente_id','plan_id','fecha_inicio','fecha_vencimiento','clases_disponibles','precio','descuento'];
  $types =  'iissidd';
  $vals  = [$cliente_id, $plan_id, $fecha_inicio, $fecha_vencimiento, $clases_disponibles, $precio, $descuento];

  if ($has_op_mem) { $cols[]='otros_pagos';  $types.='d'; $vals[]=$otros_pagos; }
  if ($has_tp_mem) { $cols[]='total_pagado'; $types.='d'; $vals[]=$total_abonado_hoy; }
  if ($has_mp_mem) { $cols[]='metodo_pago';  $types.='s'; $vals[]=$metodo_pago_str; }
  if ($has_scc_mem){ $cols[]='saldo_cc';     $types.='d'; $vals[]=0.0; }

  // TOTAL correcto
  $cols[]='total'; $types.='d'; $vals[]=$total_final;

  // Desgloses (si existen en tu schema)
  if (hcol($conexion,'membresias','pago_efectivo'))        { $cols[]='pago_efectivo';        $types.='d'; $vals[]=$pago_efectivo; }
  if (hcol($conexion,'membresias','pago_transferencia'))   { $cols[]='pago_transferencia';   $types.='d'; $vals[]=$pago_transferencia; }
  if (hcol($conexion,'membresias','pago_debito'))          { $cols[]='pago_debito';          $types.='d'; $vals[]=$pago_debito; }
  if (hcol($conexion,'membresias','pago_credito'))         { $cols[]='pago_credito';         $types.='d'; $vals[]=$pago_credito; }
  if (hcol($conexion,'membresias','pago_cuenta_corriente')){ $cols[]='pago_cuenta_corriente';$types.='d'; $vals[]=$pago_cc_manual; }

  $cols[]='gimnasio_id'; $types.='i'; $vals[]=$gimnasio_id;
  if (hcol($conexion,'membresias','duracion_meses')) { $cols[]='duracion_meses'; $types.='i'; $vals[]=$duracion_meses; }
  if ($has_act_mem) { $cols[]='activa'; $types.='i'; $vals[]=1; }

  $placeholders = implode(',', array_fill(0, count($cols), '?'));
  $sqlMem = "INSERT INTO membresias (".implode(',', $cols).") VALUES ($placeholders)";
  $stmt = $conexion->prepare($sqlMem);
  if (!$stmt) { throw new Exception("Prepare membresias: ".$conexion->error); }
  if (!bind_params_dynamic($stmt, $types, $vals) || !$stmt->execute()) {
    throw new Exception("Exec membresias: ".$stmt->error);
  }
  $membresia_id = (int)$stmt->insert_id;
  $stmt->close();

  /* ===== 4) cc_movimientos (DEBE/HABER) ===== */
  $fecha_cc   = $fecha_actual;
  $debe_total  = 0.0;
  $haber_total = 0.0;

  if ($pago_cc_manual > 0.009) {
    $concepto = "Renovación membresía #{$membresia_id} - CC manual";
    $stmtCC = $conexion->prepare("
      INSERT INTO cc_movimientos (gimnasio_id, cliente_id, venta_id, fecha, concepto, debe, haber)
      VALUES (?, ?, ?, ?, ?, ?, 0)
    ");
    if (!$stmtCC) { throw new Exception("Prepare cc_movimientos (manual): ".$conexion->error); }
    $stmtCC->bind_param("iiissd", $gimnasio_id, $cliente_id, $membresia_id, $fecha_cc, $concepto, $pago_cc_manual);
    if (!$stmtCC->execute()) { throw new Exception("Exec cc_movimientos (manual): ".$stmtCC->error); }
    $stmtCC->close();
    $debe_total += $pago_cc_manual;
    $dif = round($dif - $pago_cc_manual, 2);
  }

  if (abs($dif) > 0.009) {
    if ($dif > 0) {
      $concepto = "Renovación membresía #{$membresia_id} - deuda (remanente)";
      $stmtCC2 = $conexion->prepare("
        INSERT INTO cc_movimientos (gimnasio_id, cliente_id, venta_id, fecha, concepto, debe, haber)
        VALUES (?, ?, ?, ?, ?, ?, 0)
      ");
      if (!$stmtCC2) { throw new Exception("Prepare cc_movimientos (remanente debe): ".$conexion->error); }
      $stmtCC2->bind_param("iiissd", $gimnasio_id, $cliente_id, $membresia_id, $fecha_cc, $concepto, $dif);
      if (!$stmtCC2->execute()) { throw new Exception("Exec cc_movimientos (remanente debe): ".$stmtCC2->error); }
      $stmtCC2->close();
      $debe_total += $dif;
    } else {
      $haber = abs($dif);
      $concepto = "Renovación membresía #{$membresia_id} - saldo a favor (remanente)";
      $stmtCC3 = $conexion->prepare("
        INSERT INTO cc_movimientos (gimnasio_id, cliente_id, venta_id, fecha, concepto, debe, haber)
        VALUES (?, ?, ?, ?, ?, 0, ?)
      ");
      if (!$stmtCC3) { throw new Exception("Prepare cc_movimientos (remanente haber): ".$conexion->error); }
      $stmtCC3->bind_param("iiissd", $gimnasio_id, $cliente_id, $membresia_id, $fecha_cc, $concepto, $haber);
      if (!$stmtCC3->execute()) { throw new Exception("Exec cc_movimientos (remanente haber): ".$stmtCC3->error); }
      $stmtCC3->close();
      $haber_total += $haber;
    }
  }

  /* ===== 5) Actualizar saldo_cc si existe ===== */
  $saldo_cc_final = round($debe_total - $haber_total, 2);
  if (hcol($conexion, 'membresias', 'saldo_cc')) {
    $stmtUpd = $conexion->prepare("UPDATE membresias SET saldo_cc = ? WHERE id = ? AND gimnasio_id = ?");
    $stmtUpd->bind_param("dii", $saldo_cc_final, $membresia_id, $gimnasio_id);
    $stmtUpd->execute();
    $stmtUpd->close();
  }

  /* ===== 6) Asegurar una sola membresía ===== */
  if (hcol($conexion, 'membresias', 'activa')) {
    $conexion->query("UPDATE membresias
                      SET activa = 0
                      WHERE cliente_id = {$cliente_id} AND gimnasio_id = {$gimnasio_id} AND id <> {$membresia_id}");
    $conexion->query("UPDATE membresias
                      SET activa = 1
                      WHERE id = {$membresia_id} AND gimnasio_id = {$gimnasio_id}");
  }
  // Limpieza extra por si algo sobrevivió
  $stmtClean = $conexion->prepare("DELETE FROM membresias WHERE cliente_id = ? AND gimnasio_id = ? AND id <> ?");
  $stmtClean->bind_param("iii", $cliente_id, $gimnasio_id, $membresia_id);
  $stmtClean->execute();
  $stmtClean->close();

  $conexion->commit();
  header("Location: ver_membresias.php?exito=1");
  exit;

} catch (Throwable $e) {
  $conexion->rollback();
  http_response_code(500);
  echo "Error al guardar renovación: " . htmlspecialchars($e->getMessage());
}
