<?php
// guardar_membresia.php — registra deuda/saldo en cc_movimientos (DEBE/HABER) + turnos fijos
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/conexion.php';

if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($gimnasio_id <= 0) { http_response_code(403); die("Acceso denegado."); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405); die("Acceso no permitido.");
}

/* ===== Helpers ===== */
function table_exists(mysqli $db, string $table): bool {
  $stmt = $db->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
  if (!$stmt) return false;
  $stmt->bind_param('s', $table);
  $ok = $stmt->execute();
  $stmt->close();
  return (bool)$ok && $db->affected_rows >= 0; // si ejecuta, ya luego probaremos insert
}
function pick_fixed_table(mysqli $db): ?string {
  // Preferimos clientes_fijos; si no existe, probamos turnos_personalizados
  $cands = ['clientes_fijos','turnos_personalizados'];
  foreach ($cands as $t) {
    $q = $db->query("SHOW TABLES LIKE '".$db->real_escape_string($t)."'");
    if ($q && $q->num_rows > 0) return $t;
  }
  return null;
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ===== 1) Entradas ===== */
$cliente_id         = (int)($_POST['cliente_id'] ?? 0);
$plan_id            = (int)($_POST['plan_id'] ?? 0);
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

// turnos personalizados (JSON)
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
  $adicionales_ids = array_map('intval', $adicionales);
  $adicionales_ids = array_values(array_filter($adicionales_ids, fn($x)=>$x>0));
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

/* ===== 6) Diferencia respecto al total =====
   dif = total_final - abonado_hoy
   (si >0 falta pagar, si <0 sobra dinero a favor)
*/
$dif = round($total_final - $total_abonado_hoy, 2);

/* Detalle de métodos pagados hoy (texto) */
$metodos = [];
if ($pago_efectivo      > 0) $metodos[] = "Efectivo:{$pago_efectivo}";
if ($pago_transferencia > 0) $metodos[] = "Transferencia:{$pago_transferencia}";
if ($pago_debito        > 0) $metodos[] = "Debito:{$pago_debito}";
if ($pago_credito       > 0) $metodos[] = "Credito:{$pago_credito}";
$metodo_pago = $metodos ? implode('|', $metodos) : 'Sin pagar ahora';

try {
  $conexion->begin_transaction();

  // 7.1) Insertar Membresía
  $stmt = $conexion->prepare("
    INSERT INTO membresias
      (cliente_id, plan_id, fecha_inicio, fecha_vencimiento, clases_disponibles,
       precio, otros_pagos, descuento, total_pagado, metodo_pago, saldo_cc, total, gimnasio_id)
    VALUES
      (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
  ");
  if (!$stmt) { throw new Exception("Prepare membresias: ".$conexion->error); }

  // saldo_cc se actualizará abajo con (debe - haber) real
  $tmp_saldo_cc = 0.0;

  $types = "iissiddddsddi";
  $stmt->bind_param(
    $types,
    $cliente_id, $plan_id, $fecha_inicio, $fecha_vencimiento, $clases_plan,
    $precio_plan, $otros_pagos, $descuento_pct, $total_abonado_hoy, $metodo_pago,
    $tmp_saldo_cc, $total_final, $gimnasio_id
  );
  if (!$stmt->execute()) { throw new Exception("Exec membresias: ".$stmt->error); }
  $membresia_id = (int)$stmt->insert_id;
  $stmt->close();

  // 7.2) Vincular adicionales (si corresponde)
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

  // a) Asiento manual a CC (DEBE) si vino en el form
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

    // ese manual forma parte del total adeudado; ya está contemplado en total_final
    $dif = round($dif - $pago_cc_manual, 2);
  }

  // b) Remanente: si falta pagar -> DEBE; si sobra -> HABER
  if (abs($dif) > 0.009) {
    if ($dif > 0) {
      // Falta pagar: DEBE
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
      // Sobra dinero: HABER
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

  /* ===== 7.5) Insertar TURNOS FIJOS (si vinieron) =====
     Estructura esperada por ítem: {dia, dow(0..6), hora(HH:MM), desde(YYYY-MM-DD), hasta(YYYY-MM-DD)}
     Tabla preferida: clientes_fijos. Alternativa: turnos_personalizados.
     Campos: gimnasio_id, cliente_id, membresia_id, dow, hora, desde, hasta, creado_at
  */
  if (!empty($turnos_arr)) {
    $fixed_table = pick_fixed_table($conexion);
    if ($fixed_table) {
      // Usamos TIME/DATE como strings "HH:MM" / "YYYY-MM-DD". El motor castea a TIME/DATE si corresponde.
      $sqlFix = "
        INSERT INTO `$fixed_table` (gimnasio_id, cliente_id, membresia_id, dow, hora, desde, hasta, creado_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
      ";
      $stmtFix = $conexion->prepare($sqlFix);
      if (!$stmtFix) { throw new Exception("Prepare $fixed_table: ".$conexion->error); }

      foreach ($turnos_arr as $t) {
        $dow   = isset($t['dow']) ? (int)$t['dow'] : -1;
        $hora  = isset($t['hora']) ? preg_replace('/[^0-9:]/','', (string)$t['hora']) : '';
        $desde = isset($t['desde']) ? preg_replace('/[^0-9\-]/','', (string)$t['desde']) : '';
        $hasta = isset($t['hasta']) ? preg_replace('/[^0-9\-]/','', (string)$t['hasta']) : '';

        if ($dow < 0 || $dow > 6 || !$hora || !$desde || !$hasta) continue;

        $stmtFix->bind_param("iiiisss",
          $gimnasio_id, $cliente_id, $membresia_id, $dow, $hora, $desde, $hasta
        );
        if (!$stmtFix->execute()) {
          // Si hay índice único por duplicado exacto, seguimos; si es otro error, lanzamos
          if ((int)$stmtFix->errno === 1062) { continue; }
          throw new Exception("Exec $fixed_table: ".$stmtFix->error);
        }
      }
      $stmtFix->close();
    }
    // Si no existe ninguna tabla, simplemente se omite sin romper guardado.
  }

  $conexion->commit();
  header("Location: nueva_membresia.php?exito=1");
  exit;

} catch (Throwable $e) {
  $conexion->rollback();
  http_response_code(500);
  echo "Error al guardar la membresía: ".h($e->getMessage());
}
