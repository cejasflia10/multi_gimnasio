<?php
// guardar_membresia.php — versión negativa = deuda (compatible con ver_cuentas_corrientes.php)
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/conexion.php';

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($gimnasio_id <= 0) { http_response_code(403); die("Acceso denegado."); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405); die("Acceso no permitido.");
}

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

// parte que el recepcionista decide mandar explícitamente a cuenta corriente
$pago_cc_manual     = (float)($_POST['pago_cuenta_corriente'] ?? 0);

if ($cliente_id <= 0) die("Cliente inválido.");
if ($plan_id    <= 0) die("Plan inválido.");

/* ===== 2) Datos del plan ===== */
$qPlan = $conexion->query("
  SELECT precio, clases_disponibles, duracion_meses
  FROM planes
  WHERE id = {$plan_id} AND gimnasio_id = {$gimnasio_id}
");
$plan = $qPlan ? $qPlan->fetch_assoc() : null;
if (!$plan) die("Plan no encontrado.");

$precio_plan = (float)$plan['precio'];
$clases_plan = (int)$plan['clases_disponibles'];
$duracion    = (int)$plan['duracion_meses'];

/* Fecha de vencimiento (si no vino del form, calcular en backend) */
$fecha_vencimiento = ($fecha_venc_post === '' || $fecha_venc_post === null)
  ? date('Y-m-d', strtotime("+{$duracion} month", strtotime($fecha_inicio)))
  : $fecha_venc_post;

/* ===== 3) Adicionales (desde DB) ===== */
$total_adicionales = 0.0;
$adicionales_ids   = [];
if (!empty($adicionales) && is_array($adicionales)) {
  $adicionales_ids = array_map('intval', $adicionales);
  $ids_list = implode(',', $adicionales_ids);
  if ($ids_list !== '') {
    $resAd = $conexion->query("
      SELECT id, precio
      FROM planes_adicionales
      WHERE id IN ($ids_list) AND gimnasio_id = {$gimnasio_id}
    ");
    while ($r = $resAd->fetch_assoc()) {
      $total_adicionales += (float)$r['precio'];
    }
  }
}

/* ===== 4) Total en servidor ===== */
$total_bruto = $precio_plan + $total_adicionales + $otros_pagos;
$total_final = round($total_bruto - ($total_bruto * ($descuento_pct / 100)), 2);

/* ===== 5) Total abonado HOY (solo medios inmediatos) ===== */
$total_abonado_hoy = round($pago_efectivo + $pago_transferencia + $pago_debito + $pago_credito, 2);

/* ===== 6) Diferencia => deuda/saldo a favor =====
   deuda_base = total_final - abonado_hoy
   si se indicó "pago_cuenta_corriente", registrar SIEMPRE ese asiento por separado
   y además calcular el remanente (dif) para otro asiento si corresponde.
*/
$dif = round($total_final - $total_abonado_hoy, 2);

/* Para membresías guardamos el saldo con signo (negativo = deuda) */
$saldo_cc_signed = 0.0; // lo ajustaremos al final

/* Detalle de métodos pagados hoy (texto) */
$metodos = [];
if ($pago_efectivo      > 0) $metodos[] = "Efectivo:{$pago_efectivo}";
if ($pago_transferencia > 0) $metodos[] = "Transferencia:{$pago_transferencia}";
if ($pago_debito        > 0) $metodos[] = "Debito:{$pago_debito}";
if ($pago_credito       > 0) $metodos[] = "Credito:{$pago_credito}";
$metodo_pago = $metodos ? implode('|', $metodos) : 'Sin pagar ahora';

try {
  $conexion->begin_transaction();

  // 7.1) Membresía
  $stmt = $conexion->prepare("
    INSERT INTO membresias
      (cliente_id, plan_id, fecha_inicio, fecha_vencimiento, clases_disponibles,
       precio, otros_pagos, descuento, total_pagado, metodo_pago, saldo_cc, total, gimnasio_id)
    VALUES
      (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
  ");
  if (!$stmt) { throw new Exception("Prepare membresias: ".$conexion->error); }

  // Por ahora saldo_cc = 0; tras asientos CC lo actualizamos si querés.
  $tmp_saldo_cc = 0.0;

  $types = "iissiddddsddi";
  $stmt->bind_param(
    $types,
    $cliente_id, $plan_id, $fecha_inicio, $fecha_vencimiento, $clases_plan,
    $precio_plan, $otros_pagos, $descuento_pct, $total_abonado_hoy, $metodo_pago,
    $tmp_saldo_cc, $total_final, $gimnasio_id
  );
  if (!$stmt->execute()) { throw new Exception("Exec membresias: ".$stmt->error); }
  $membresia_id = $stmt->insert_id;
  $stmt->close();

  // 7.2) Adicionales (ajusta el nombre si tu tabla es "membresias_adicionales")
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

  // 7.3) Cuenta Corriente
  // a) asiento manual si cargaron "Cuenta Corriente" en el form
  if ($pago_cc_manual > 0.009) {
    $fecha_cc = date('Y-m-d H:i:s');
    $monto_cc = -$pago_cc_manual; // negativo = deuda
    $desc_cc  = "Membresía #{$membresia_id} - cuenta corriente (manual)";

    $stmtCC = $conexion->prepare("
      INSERT INTO cuentas_corrientes
        (cliente_id, gimnasio_id, fecha, descripcion, monto)
      VALUES (?, ?, ?, ?, ?)
    ");
    if (!$stmtCC) { throw new Exception("Prepare cta_cte manual: ".$conexion->error); }
    $stmtCC->bind_param("iissd", $cliente_id, $gimnasio_id, $fecha_cc, $desc_cc, $monto_cc);
    if (!$stmtCC->execute()) { throw new Exception("Exec cta_cte manual: ".$stmtCC->error); }
    $stmtCC->close();

    // Descontamos del remanente para no duplicar
    $dif = round($dif - $pago_cc_manual, 2);
  }

  // b) asiento por remanente (si todavía falta/sobra algo)
  if (abs($dif) > 0.009) {
    $fecha_cc = date('Y-m-d H:i:s');
    $monto_cc = ($dif > 0) ? -$dif : abs($dif); // negativo si falta pagar, positivo si sobra
    $desc_cc  = ($dif > 0)
      ? "Membresía #{$membresia_id} - deuda (remanente)"
      : "Membresía #{$membresia_id} - saldo a favor (remanente)";

    $stmtCC2 = $conexion->prepare("
      INSERT INTO cuentas_corrientes
        (cliente_id, gimnasio_id, fecha, descripcion, monto)
      VALUES (?, ?, ?, ?, ?)
    ");
    if (!$stmtCC2) { throw new Exception("Prepare cta_cte remanente: ".$conexion->error); }
    $stmtCC2->bind_param("iissd", $cliente_id, $gimnasio_id, $fecha_cc, $desc_cc, $monto_cc);
    if (!$stmtCC2->execute()) { throw new Exception("Exec cta_cte remanente: ".$stmtCC2->error); }
    $stmtCC2->close();
  }

  // 7.4) Actualizar saldo_cc en membresías con el total que quedó (con signo)
  // saldo_cc_signed: si dif>0 ó hubo manual => negativo (deuda); si dif<0 => positivo
  // Lo recomputamos: saldo final en CC = (asiento manual negativo) + (remanente con signo)
  $saldo_final_cc = 0.0;
  if ($pago_cc_manual > 0.009) $saldo_final_cc += -$pago_cc_manual;
  if (abs($dif) > 0.009)       $saldo_final_cc += ($dif > 0 ? -$dif : abs($dif));

  $stmtUpd = $conexion->prepare("UPDATE membresias SET saldo_cc = ? WHERE id = ? AND gimnasio_id = ?");
  $stmtUpd->bind_param("dii", $saldo_final_cc, $membresia_id, $gimnasio_id);
  $stmtUpd->execute();
  $stmtUpd->close();

  $conexion->commit();
  header("Location: ver_membresias.php?exito=1");
  exit;

} catch (Throwable $e) {
  $conexion->rollback();
  http_response_code(500);
  echo "Error al guardar la membresía: ".$e->getMessage();
}
