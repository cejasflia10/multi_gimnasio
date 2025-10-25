<?php
// guardar_renovacion.php — crea una NUEVA membresía (renovación) y registra DEBE/HABER en cc_movimientos
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/conexion.php';

if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function col_exists(mysqli $db, string $table, string $col): bool {
  $sql = "SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1";
  $st = $db->prepare($sql);
  if(!$st) return false;
  $st->bind_param('ss', $table, $col);
  $ok = $st->execute();
  $res = $st->get_result();
  $exists = $ok && $res && $res->num_rows > 0;
  $st->close();
  return $exists;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Método no permitido'); }

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($gimnasio_id <= 0) { http_response_code(403); exit('Acceso denegado'); }

/* ===== 1) Entradas del formulario ===== */
$cliente_id   = (int)($_POST['cliente_id'] ?? 0);
$plan_id      = (int)($_POST['plan_id'] ?? 0);
$fecha_inicio = $_POST['fecha_inicio'] ?? date('Y-m-d');
$fecha_vto_in = $_POST['fecha_vencimiento'] ?? '';
$otros_pagos  = (float)($_POST['otros_pagos'] ?? 0);
$desc_pct     = (float)($_POST['descuento'] ?? 0);

// pagos hoy (no CC)
$pago_efectivo      = (float)($_POST['pago_efectivo'] ?? 0);
$pago_transferencia = (float)($_POST['pago_transferencia'] ?? 0);
$pago_debito        = (float)($_POST['pago_debito'] ?? 0);
$pago_credito       = (float)($_POST['pago_credito'] ?? 0);

// a CC (DEBE explícito)
$pago_cc_manual     = (float)($_POST['pago_cuenta_corriente'] ?? 0);

if ($cliente_id <= 0) { http_response_code(400); exit('Cliente inválido'); }
if ($plan_id    <= 0) { http_response_code(400); exit('Plan inválido'); }

/* ===== 2) Plan desde DB ===== */
$stPlan = $conexion->prepare("SELECT precio, clases_disponibles, duracion_meses FROM planes WHERE id=? AND gimnasio_id=?");
$stPlan->bind_param('ii', $plan_id, $gimnasio_id);
$stPlan->execute();
$plan = $stPlan->get_result()->fetch_assoc();
$stPlan->close();
if (!$plan) { http_response_code(404); exit('Plan no encontrado'); }

$precio_plan = (float)$plan['precio'];
$clases_plan = (int)$plan['clases_disponibles'];
$duracion    = (int)($plan['duracion_meses'] ?? 1);

/* Vencimiento (si no vino del form, calcular) */
$fi_ts = strtotime($fecha_inicio ?: date('Y-m-d')); if ($fi_ts === false) $fi_ts = time();
$fecha_vencimiento = ($fecha_vto_in === '' || $fecha_vto_in === null)
  ? date('Y-m-d', strtotime("+{$duracion} month", $fi_ts))
  : $fecha_vto_in;

/* ===== 3) Adicionales ===== */
$adicionales = $_POST['adicionales'] ?? [];
$adicionales_ids = [];
$total_adic = 0.0;

if (!empty($adicionales) && is_array($adicionales)) {
  $adicionales_ids = array_map('intval', $adicionales);
  $adicionales_ids = array_values(array_filter($adicionales_ids, fn($x)=>$x>0));
  if ($adicionales_ids) {
    $ids = implode(',', $adicionales_ids);
    $stAd = $conexion->prepare("SELECT id, precio FROM planes_adicionales WHERE id IN ($ids) AND gimnasio_id=?");
    $stAd->bind_param('i', $gimnasio_id);
    $stAd->execute();
    $rsAd = $stAd->get_result();
    while ($r = $rsAd->fetch_assoc()) { $total_adic += (float)$r['precio']; }
    $stAd->close();
  }
}

/* ===== 4) Totales en servidor ===== */
$subtotal   = $precio_plan + $otros_pagos + $total_adic;
$total_final= round($subtotal - ($subtotal * ($desc_pct/100)), 2);

$abonado_hoy= round($pago_efectivo + $pago_transferencia + $pago_debito + $pago_credito, 2);
$dif        = round($total_final - $abonado_hoy, 2); // >0 falta pagar (DEBE), <0 sobra (HABER)

$metodos = [];
if ($pago_efectivo      > 0) $metodos[] = "Efectivo:{$pago_efectivo}";
if ($pago_transferencia > 0) $metodos[] = "Transferencia:{$pago_transferencia}";
if ($pago_debito        > 0) $metodos[] = "Debito:{$pago_debito}";
if ($pago_credito       > 0) $metodos[] = "Credito:{$pago_credito}";
$metodo_pago = $metodos ? implode('|',$metodos) : 'Sin pagar ahora';

/* ===== 5) (Opcional) ID de membresía previa para marcar relación de renovación =====
   Si querés pasar el id anterior en el form, agregá: <input type="hidden" name="membresia_anterior_id" ...>
*/
$membresia_anterior_id = (int)($_POST['membresia_anterior_id'] ?? 0);

try {
  $conexion->begin_transaction();

  // 6) Insertar NUEVA membresía (renovación)
  //   Si existe la columna renueva_de_id, la guardamos; si no, la omitimos.
  $tiene_rel = col_exists($conexion,'membresias','renueva_de_id');
  if ($tiene_rel) {
    $sql = "INSERT INTO membresias
      (cliente_id, plan_id, fecha_inicio, fecha_vencimiento, clases_disponibles,
       precio, otros_pagos, descuento, total_pagado, metodo_pago, saldo_cc, total, gimnasio_id, renueva_de_id)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
  } else {
    $sql = "INSERT INTO membresias
      (cliente_id, plan_id, fecha_inicio, fecha_vencimiento, clases_disponibles,
       precio, otros_pagos, descuento, total_pagado, metodo_pago, saldo_cc, total, gimnasio_id)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
  }

  $st = $conexion->prepare($sql);
  if (!$st) throw new Exception("Prepare membresias: ".$conexion->error);

  $saldo_cc_tmp = 0.0;

  if ($tiene_rel) {
    $types = "iissiddddsddii";
    $st->bind_param(
      $types,
      $cliente_id, $plan_id, $fecha_inicio, $fecha_vencimiento, $clases_plan,
      $precio_plan, $otros_pagos, $desc_pct, $abonado_hoy, $metodo_pago,
      $saldo_cc_tmp, $total_final, $gimnasio_id, $membresia_anterior_id
    );
  } else {
    $types = "iissiddddsddi";
    $st->bind_param(
      $types,
      $cliente_id, $plan_id, $fecha_inicio, $fecha_vencimiento, $clases_plan,
      $precio_plan, $otros_pagos, $desc_pct, $abonado_hoy, $metodo_pago,
      $saldo_cc_tmp, $total_final, $gimnasio_id
    );
  }

  if (!$st->execute()) throw new Exception("Exec membresias: ".$st->error);
  $membresia_nueva_id = (int)$st->insert_id;
  $st->close();

  // 7) Vincular adicionales
  if (!empty($adicionales_ids)) {
    $stAd = $conexion->prepare("INSERT INTO membresia_adicionales (membresia_id, adicional_id) VALUES (?, ?)");
    if (!$stAd) throw new Exception("Prepare adicionales: ".$conexion->error);
    foreach ($adicionales_ids as $aid) {
      $aid = (int)$aid;
      $stAd->bind_param("ii", $membresia_nueva_id, $aid);
      if (!$stAd->execute()) throw new Exception("Exec adicionales: ".$stAd->error);
    }
    $stAd->close();
  }

  // 8) Cuenta Corriente — cc_movimientos
  $fecha_cc = date('Y-m-d H:i:s');
  $debe_total  = 0.0;
  $haber_total = 0.0;

  // a) CC manual (DEBE)
  if ($pago_cc_manual > 0.009) {
    $concepto = "Membresía #{$membresia_nueva_id} - CC manual (renovación)";
    $stCC = $conexion->prepare("
      INSERT INTO cc_movimientos (gimnasio_id, cliente_id, venta_id, fecha, concepto, debe, haber)
      VALUES (?, ?, ?, ?, ?, ?, 0)
    ");
    if (!$stCC) throw new Exception("Prepare cc_movimientos (manual): ".$conexion->error);
    $stCC->bind_param("iiissd", $gimnasio_id, $cliente_id, $membresia_nueva_id, $fecha_cc, $concepto, $pago_cc_manual);
    if (!$stCC->execute()) throw new Exception("Exec cc_movimientos (manual): ".$stCC->error);
    $stCC->close();
    $debe_total += $pago_cc_manual;

    // descuento del remanente que calcularemos abajo
    $dif = round($dif - $pago_cc_manual, 2);
  }

  // b) Remanente: si falta -> DEBE; si sobra -> HABER
  if (abs($dif) > 0.009) {
    if ($dif > 0) {
      $concepto = "Membresía #{$membresia_nueva_id} - deuda (remanente)";
      $stCC2 = $conexion->prepare("
        INSERT INTO cc_movimientos (gimnasio_id, cliente_id, venta_id, fecha, concepto, debe, haber)
        VALUES (?, ?, ?, ?, ?, ?, 0)
      ");
      if (!$stCC2) throw new Exception("Prepare cc_movimientos (remanente debe): ".$conexion->error);
      $stCC2->bind_param("iiissd", $gimnasio_id, $cliente_id, $membresia_nueva_id, $fecha_cc, $concepto, $dif);
      if (!$stCC2->execute()) throw new Exception("Exec cc_movimientos (remanente debe): ".$stCC2->error);
      $stCC2->close();
      $debe_total += $dif;
    } else {
      $haber = abs($dif);
      $concepto = "Membresía #{$membresia_nueva_id} - saldo a favor (remanente)";
      $stCC3 = $conexion->prepare("
        INSERT INTO cc_movimientos (gimnasio_id, cliente_id, venta_id, fecha, concepto, debe, haber)
        VALUES (?, ?, ?, ?, ?, 0, ?)
      ");
      if (!$stCC3) throw new Exception("Prepare cc_movimientos (remanente haber): ".$conexion->error);
      $stCC3->bind_param("iiissd", $gimnasio_id, $cliente_id, $membresia_nueva_id, $fecha_cc, $concepto, $haber);
      if (!$stCC3->execute()) throw new Exception("Exec cc_movimientos (remanente haber): ".$stCC3->error);
      $stCC3->close();
      $haber_total += $haber;
    }
  }

  // 9) Actualizar saldo_cc de la NUEVA membresía
  $saldo_cc = round($debe_total - $haber_total, 2);
  $stUpd = $conexion->prepare("UPDATE membresias SET saldo_cc=? WHERE id=? AND gimnasio_id=?");
  if (!$stUpd) throw new Exception("Prepare update saldo_cc: ".$conexion->error);
  $stUpd->bind_param("dii", $saldo_cc, $membresia_nueva_id, $gimnasio_id);
  if (!$stUpd->execute()) throw new Exception("Exec update saldo_cc: ".$stUpd->error);
  $stUpd->close();

  // 10) (Opcional) Cerrar la membresía anterior, si existe columna estado
  if ($membresia_anterior_id > 0 && col_exists($conexion, 'membresias', 'estado')) {
    $stOld = $conexion->prepare("UPDATE membresias SET estado='renovada' WHERE id=? AND gimnasio_id=?");
    if ($stOld) {
      $stOld->bind_param("ii", $membresia_anterior_id, $gimnasio_id);
      $stOld->execute();
      $stOld->close();
    }
  }

  $conexion->commit();

  // Redirigimos al listado (coincide con el botón "Cancelar" del form)
  header("Location: ver_membresias.php?exito=1&renovada={$membresia_nueva_id}");
  exit;

} catch (Throwable $e) {
  $conexion->rollback();
  http_response_code(500);
  echo "Error al renovar la membresía: " . h($e->getMessage());
}
