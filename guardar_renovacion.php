<?php
// guardar_renovacion.php — renovaciones de membresía
// MISMA LÓGICA que guardar_membresia.php:
// - Inserta en membresias
// - Registra DEBE/HABER en cc_movimientos
// - Sincroniza gym_clientes_plan (si vienen turnos_json)
// - Desactiva la membresía vieja (membresia_id) si se envía

if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/conexion.php';

if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($gimnasio_id <= 0) { http_response_code(403); die("Acceso denegado."); }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405); die("Acceso no permitido.");
}

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function table_exists($db, $t){
  if (!($db instanceof mysqli)) return false;
  $q = $db->query("SHOW TABLES LIKE '".$db->real_escape_string($t)."'");
  return ($q && $q->num_rows > 0);
}
function hcol($db, $table, $col){
  if (!($db instanceof mysqli)) return false;
  $table = $db->real_escape_string($table);
  $col   = $db->real_escape_string($col);
  $res = $db->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
  return ($res && $res->num_rows > 0);
}
function pick_fixed_table($db){
  foreach (['clientes_fijos','turnos_personalizados'] as $t) {
    if (table_exists($db, $t)) return $t;
  }
  return null;
}

/* ===== 1) Entradas ===== */
$cliente_id         = (int)($_POST['cliente_id'] ?? 0);
$plan_id            = (int)($_POST['plan_id'] ?? 0);
$vieja_membresia_id = (int)($_POST['membresia_id'] ?? 0); // la que se renueva, opcional

$fecha_inicio       = $_POST['fecha_inicio'] ?? date('Y-m-d');
$fecha_venc_post    = $_POST['fecha_vencimiento'] ?? '';
$otros_pagos        = (float)($_POST['otros_pagos'] ?? 0);
$descuento_pct      = (float)($_POST['descuento'] ?? 0);
$adicionales        = $_POST['adicionales'] ?? [];

// pagos HOY (no incluyen cuenta corriente)
$pago_efectivo      = (float)($_POST['pago_efectivo'] ?? 0);
$pago_transferencia = (float)($_POST['pago_transferencia'] ?? 0);
$pago_debito        = (float)($_POST['pago_debito'] ?? 0);
$pago_credito       = (float)($_POST['pago_credito'] ?? 0);

// parte que el recepcionista manda explícitamente a Cuenta Corriente (DEBE)
$pago_cc_manual     = (float)($_POST['pago_cuenta_corriente'] ?? 0);

/* 💥 CAMPOS DEL FORMULARIO VIEJO DE RENOVACIÓN
   (a partir de acá los usamos como respaldo si los nuevos vienen en 0) */
$monto_pago_form    = (float)($_POST['monto_pago']    ?? 0);
$monto_pagado_form  = (float)($_POST['monto_pagado']  ?? 0);
$total_pagado_form  = (float)($_POST['total_pagado']  ?? 0);

// turnos personalizados (JSON con posible profesor_id)
$turnos_json_raw    = $_POST['turnos_json'] ?? '[]';
$turnos_arr         = json_decode($turnos_json_raw, true);
if (!is_array($turnos_arr)) $turnos_arr = [];

if ($cliente_id <= 0) die("Cliente inválido.");
if ($plan_id    <= 0) die("Plan inválido.");

/* ===== 2) Datos del plan ===== */
$qPlan = $conexion->prepare("
  SELECT precio, clases_disponibles, duracion_meses
  FROM planes
  WHERE id = ? AND gimnasio_id = ?
");
$qPlan->bind_param('ii', $plan_id, $gimnasio_id);
$qPlan->execute();
$plan = $qPlan->get_result()->fetch_assoc();
$qPlan->close();
if (!$plan) die("Plan no encontrado.");

$precio_plan = (float)$plan['precio'];
$clases_plan = (int)$plan['clases_disponibles'];
$duracion    = (int)$plan['duracion_meses'];

/* Fecha de vencimiento (si no vino del form, calcular en backend) */
$fi_ts = strtotime($fecha_inicio ?: date('Y-m-d'));
if ($fi_ts === false) $fi_ts = time();
$fecha_vencimiento = ($fecha_venc_post === '' || $fecha_venc_post === null)
  ? date('Y-m-d', strtotime("+{$duracion} month", $fi_ts))
  : $fecha_venc_post;

/* ===== 3) Adicionales (desde DB) ===== */
$total_adicionales = 0.0;
$adicionales_ids   = [];
if (!empty($adicionales) && is_array($adicionales)) {
  $adicionales_ids = array_values(array_filter(array_map('intval', $adicionales), function($x){ return $x>0; }));
  if ($adicionales_ids) {
    $ids_list = implode(',', $adicionales_ids);
    $sqlAd = "SELECT id, precio FROM planes_adicionales WHERE id IN ($ids_list) AND gimnasio_id = ?";
    $resAd = $conexion->prepare($sqlAd);
    $resAd->bind_param('i', $gimnasio_id);
    $resAd->execute();
    $rs = $resAd->get_result();
    while ($r = $rs->fetch_assoc()) {
      $total_adicionales += (float)$r['precio'];
    }
    $resAd->close();
  }
}

/* ===== 4) Total en servidor ===== */
$total_bruto = $precio_plan + $total_adicionales + $otros_pagos;
$total_final = round($total_bruto - ($total_bruto * ($descuento_pct / 100)), 2);

/* ===== 5) Total abonado HOY (solo medios inmediatos) ===== */
$total_abonado_hoy = round($pago_efectivo + $pago_transferencia + $pago_debito + $pago_credito, 2);

/* 🔁 PARCHE CLAVE:
   Si los 4 medios vienen en 0 pero el formulario trae un monto antiguo
   (monto_pago / monto_pagado / total_pagado), usamos ese valor como
   pago en EFECTIVO para que:
   - total_pagado no quede en 0
   - el INDEX cuente ese pago en ingresos del día/mes
*/
if ($total_abonado_hoy <= 0.0001) {
  $backup = 0;
  if ($monto_pago_form    > 0) $backup = $monto_pago_form;
  elseif ($monto_pagado_form  > 0) $backup = $monto_pagado_form;
  elseif ($total_pagado_form  > 0) $backup = $total_pagado_form;

  if ($backup > 0) {
    $pago_efectivo     = $backup;
    $total_abonado_hoy = $backup;
  }
}

/* ===== 6) Diferencia respecto al total ===== */
$dif = round($total_final - $total_abonado_hoy, 2);

/* Detalle de métodos pagados hoy (texto) */
$metodos = [];
if ($pago_efectivo      > 0) $metodos[] = "Efectivo:{$pago_efectivo}";
if ($pago_transferencia > 0) $metodos[] = "Transferencia:{$pago_transferencia}";
if ($pago_debito        > 0) $metodos[] = "Debito:{$pago_debito}";
if ($pago_credito       > 0) $metodos[] = "Credito:{$pago_credito}";
$metodo_pago = $metodos ? implode('|', $metodos) : 'Sin pagar ahora';

/* Valores para columnas de formas de pago en membresias */
$monto_efectivo      = $pago_efectivo;
$monto_transferencia = $pago_transferencia;
$pago_cc_col         = $pago_cc_manual; // lo que el recepcionista mandó explícitamente a CC

try {
  $conexion->begin_transaction();

  // 7.1) Insertar NUEVA Membresía (igual que en guardar_membresia.php)
  $stmt = $conexion->prepare("
    INSERT INTO membresias
      (cliente_id, plan_id, fecha_inicio, fecha_vencimiento, clases_disponibles,
       precio, otros_pagos, descuento,
       pago_efectivo, pago_transferencia, pago_debito, pago_credito, pago_cuenta_corriente,
       monto_efectivo, monto_transferencia,
       total_pagado, metodo_pago, saldo_cc, total, gimnasio_id)
    VALUES
      (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
  ");
  if (!$stmt) { throw new Exception("Prepare membresias: ".$conexion->error); }

  $tmp_saldo_cc = 0.0;

  // tipos (20): i,i,s,s,i, d,d,d,d,d,d,d,d,d,d, d,s,d,d,i
  $types = "iissidddddddddddsddi";
  $stmt->bind_param(
    $types,
    $cliente_id,
    $plan_id,
    $fecha_inicio,
    $fecha_vencimiento,
    $clases_plan,
    $precio_plan,
    $otros_pagos,
    $descuento_pct,
    $pago_efectivo,
    $pago_transferencia,
    $pago_debito,
    $pago_credito,
    $pago_cc_col,
    $monto_efectivo,
    $monto_transferencia,
    $total_abonado_hoy,   // total_pagado
    $metodo_pago,
    $tmp_saldo_cc,
    $total_final,
    $gimnasio_id
  );
  if (!$stmt->execute()) { throw new Exception("Exec membresias: ".$stmt->error); }
  $membresia_id = (int)$stmt->insert_id;
  $stmt->close();

  // 7.1.b) Desactivar membresía anterior si llega el id
  if ($vieja_membresia_id > 0) {
    $vieja_membresia_id = (int)$vieja_membresia_id;
    $conexion->query("
      UPDATE membresias
      SET activa = 0
      WHERE id = {$vieja_membresia_id}
        AND gimnasio_id = {$gimnasio_id}
    ");
  }

  // 7.2) Vincular adicionales (misma tabla que guardar_membresia)
  if (!empty($adicionales_ids)) {
    $stmtAd = $conexion->prepare("INSERT INTO membresia_adicionales (membresia_id, adicional_id) VALUES (?, ?)");
    if (!$stmtAd) { throw new Exception("Prepare adicionales: ".$conexion->error); }
    foreach ($adicionales_ids as $aid) {
      $aid = (int)$aid;
      $stmtAd->bind_param("ii", $membresia_id, $aid);
      if (!$stmtAd->execute()) { throw new Exception("Exec adicionales: ".$stmtAd->error); }
    }
    $stmtAd->close();
  }

  // ===== 7.3) Cuenta Corriente en cc_movimientos (DEBE/HABER) =====
  $fecha_cc = date('Y-m-d H:i:s');
  $debe_total  = 0.0;
  $haber_total = 0.0;

  // a) Asiento manual a CC (DEBE)
  if ($pago_cc_manual > 0.009) {
    $concepto = "Membresía #{$membresia_id} - CC manual";
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

  // b) Remanente a CC
  if (abs($dif) > 0.009) {
    if ($dif > 0) {
      // deuda
      $concepto = "Membresía #{$membresia_id} - deuda (remanente)";
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
      // saldo a favor
      $haber = abs($dif);
      $concepto = "Membresía #{$membresia_id} - saldo a favor (remanente)";
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

  // 7.4) Actualizar saldo_cc en membresías como (DEBE - HABER)
  $saldo_cc = round($debe_total - $haber_total, 2); // >0 deuda, <0 a favor
  $stmtUpd = $conexion->prepare("UPDATE membresias SET saldo_cc = ? WHERE id = ? AND gimnasio_id = ?");
  if (!$stmtUpd) { throw new Exception("Prepare update saldo_cc: ".$conexion->error); }
  $stmtUpd->bind_param("dii", $saldo_cc, $membresia_id, $gimnasio_id);
  if (!$stmtUpd->execute()) { throw new Exception("Exec update saldo_cc: ".$stmtUpd->error); }
  $stmtUpd->close();

  /* ===== 7.5) TURNOS FIJOS + gym_clientes_plan (igual que guardar_membresia) ===== */
  if (!empty($turnos_arr)) {
    // --- (A) Guardar en tabla de fijos si existe ---
    $fixed_table = pick_fixed_table($conexion);
    if ($fixed_table) {
      $col_prof = null;
      foreach (['profesor_id','profe_id','instructor_id','entrenador_id'] as $cp) {
        if (hcol($conexion, $fixed_table, $cp)) { $col_prof = $cp; break; }
      }

      $cols = [];
      $typesIns = '';
      $valsIns  = [];

      if (hcol($conexion,$fixed_table,'gimnasio_id')) { $cols[]='gimnasio_id';   $typesIns.='i'; $valsIns[]=$gimnasio_id; }
      if (hcol($conexion,$fixed_table,'cliente_id'))  { $cols[]='cliente_id';    $typesIns.='i'; $valsIns[]=$cliente_id; }
      if (hcol($conexion,$fixed_table,'membresia_id')){ $cols[]='membresia_id';  $typesIns.='i'; $valsIns[]=$membresia_id; }
      if ($col_prof)                                   { $cols[]=$col_prof;       $typesIns.='i'; }

      $cols = array_merge($cols, ['dow','hora','desde','hasta','creado_at']);
      $ph = implode(',', array_fill(0, count($cols)-1, '?')) . ',NOW()';
      $sqlFix = "INSERT INTO `$fixed_table` (".implode(',', $cols).") VALUES ($ph)";
      $stmtFix = $conexion->prepare($sqlFix);
      if (!$stmtFix) { throw new Exception("Prepare $fixed_table: ".$conexion->error); }

      foreach ($turnos_arr as $t) {
        $dow   = isset($t['dow']) ? (int)$t['dow'] : -1;
        $hora  = isset($t['hora']) ? preg_replace('/[^0-9:]/','', (string)$t['hora']) : '';
        if (strlen($hora)===5) $hora .= ':00';
        $desde = isset($t['desde']) ? preg_replace('/[^0-9\-]/','', (string)$t['desde']) : '';
        $hasta = isset($t['hasta']) ? preg_replace('/[^0-9\-]/','', (string)$t['hasta']) : '';
        $prof  = isset($t['profesor_id']) && $t['profesor_id'] !== '' ? (int)$t['profesor_id'] : 0;

        if ($dow < 0 || $dow > 6 || !$hora || !$desde || !$hasta) continue;

        $vals = $valsIns; $types = $typesIns;
        if ($col_prof) { $vals[] = $prof; $types .= 'i'; }
        $vals[] = $dow;   $types .= 'i';
        $vals[] = $hora;  $types .= 's';
        $vals[] = $desde; $types .= 's';
        $vals[] = $hasta; $types .= 's';

        $refs=[]; $refs[]=&$types;
        foreach ($vals as $k=>$v){ $refs[]=&$vals[$k]; }
        if (!call_user_func_array([$stmtFix,'bind_param'],$refs)) {
          throw new Exception("bind_param $fixed_table falló");
        }
        if (!$stmtFix->execute()) {
          if ((int)$stmtFix->errno === 1062) { continue; } // duplicado: ignorar
          throw new Exception("Exec $fixed_table: ".$stmtFix->error);
        }
      }
      $stmtFix->close();
    }

    // --- (B) SINCRONIZAR a gym_clientes_plan ---
    $mapDowToEs = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado']; // 0..6
    $grupos = [];
    foreach ($turnos_arr as $t) {
      $dow   = isset($t['dow']) ? (int)$t['dow'] : -1;
      $hora  = isset($t['hora']) ? preg_replace('/[^0-9:]/','', (string)$t['hora']) : '';
      if (strlen($hora)===5) $hora .= ':00';
      $desde = isset($t['desde']) ? preg_replace('/[^0-9\-]/','', (string)$t['desde']) : '';
      $hasta = isset($t['hasta']) ? preg_replace('/[^0-9\-]/','', (string)$t['hasta']) : '';
      $profRaw  = isset($t['profesor_id']) ? $t['profesor_id'] : '';
      $prof = ($profRaw === '' || $profRaw === null) ? null : (int)$profRaw;

      if ($dow < 0 || $dow > 6 || !$hora || !$desde || !$hasta) continue;

      $diaEs = $mapDowToEs[$dow];
      $key = $hora.'|'.($prof===null?'NULL':$prof).'|'.$desde.'|'.$hasta;
      if (!isset($grupos[$key])) {
        $grupos[$key] = ['hora'=>$hora,'prof'=>$prof,'desde'=>$desde,'hasta'=>$hasta,'dias'=>[]];
      }
      $grupos[$key]['dias'][$diaEs] = true;
    }

    foreach ($grupos as $gk => $ginfo) {
      $dias_json = json_encode(array_keys($ginfo['dias']), JSON_UNESCAPED_UNICODE);
      if ($ginfo['prof'] === null) {
        $sqlG = "INSERT INTO gym_clientes_plan (gimnasio_id, cliente_id, plan_id, desde, hasta, hora, dias_json, profesor_id)
                 VALUES (?,?,?,?,?,?,?,NULL)
                 ON DUPLICATE KEY UPDATE dias_json=VALUES(dias_json), hora=VALUES(hora)";
        $stG = $conexion->prepare($sqlG);
        if (!$stG) { throw new Exception("Prepare gym_clientes_plan (NULL): ".$conexion->error); }
        $stG->bind_param(
          "iiissss",
          $gimnasio_id, $cliente_id, $plan_id, $ginfo['desde'], $ginfo['hasta'], $ginfo['hora'], $dias_json
        );
      } else {
        $sqlG = "INSERT INTO gym_clientes_plan (gimnasio_id, cliente_id, plan_id, desde, hasta, hora, dias_json, profesor_id)
                 VALUES (?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE dias_json=VALUES(dias_json), hora=VALUES(hora), profesor_id=VALUES(profesor_id)";
        $stG = $conexion->prepare($sqlG);
        if (!$stG) { throw new Exception("Prepare gym_clientes_plan (prof): ".$conexion->error); }
        $stG->bind_param(
          "iiissssi",
          $gimnasio_id, $cliente_id, $plan_id, $ginfo['desde'], $ginfo['hasta'], $ginfo['hora'], $dias_json, $ginfo['prof']
        );
      }
      if (!$stG->execute()) {
        if ((int)$stG->errno !== 1062) {
          throw new Exception("Exec gym_clientes_plan: ".$stG->error);
        }
      }
      $stG->close();
    }
  }

  $conexion->commit();
  header("Location: ver_membresias.php?ok=1");
  exit;

} catch (Throwable $e) {
  $conexion->rollback();
  http_response_code(500);
  echo "Error al guardar la renovación: ".h($e->getMessage());
}
