<?php
// guardar_renovacion.php — versión con cc_movimientos (DEBE/HABER)
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/conexion.php';

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? ($_POST['gimnasio_id'] ?? 0));
if ($gimnasio_id <= 0) { http_response_code(403); die("Acceso denegado"); }

$cliente_id         = (int)($_POST['cliente_id'] ?? 0);
$plan_id            = (int)($_POST['plan_id'] ?? 0);
$fecha_inicio       = $_POST['fecha_inicio'] ?? date('Y-m-d');
$fecha_vencimiento  = $_POST['fecha_vencimiento'] ?? '';
$clases_disponibles = (int)($_POST['clases_disponibles'] ?? 0);
$precio             = (float)($_POST['precio'] ?? 0);
$otros_pagos        = (float)($_POST['otros_pagos'] ?? 0);
$descuento          = (float)($_POST['descuento'] ?? 0);
$duracion_meses     = (int)($_POST['duracion_meses'] ?? 0);
$fecha_actual       = date('Y-m-d H:i:s');

// Pagos individuales (no incluyen CC)
$pago_efectivo      = (float)($_POST['pago_efectivo'] ?? 0);
$pago_transferencia = (float)($_POST['pago_transferencia'] ?? 0);
$pago_debito        = (float)($_POST['pago_debito'] ?? 0);
$pago_credito       = (float)($_POST['pago_credito'] ?? 0);
// Parte que va explícitamente a CC (DEBE)
$pago_cc_manual     = (float)($_POST['pago_cuenta_corriente'] ?? 0);

// Métodos de pago (texto)
$metodos = [];
if ($pago_efectivo      > 0) $metodos[] = "Efectivo:$pago_efectivo";
if ($pago_transferencia > 0) $metodos[] = "Transferencia:$pago_transferencia";
if ($pago_debito        > 0) $metodos[] = "Débito:$pago_debito";
if ($pago_credito       > 0) $metodos[] = "Crédito:$pago_credito";
$metodo_pago = $metodos ? implode(' | ', $metodos) : 'Sin pagar ahora';

// Total bruto/final
$total_bruto = $precio + $otros_pagos;
$total_final = round($total_bruto - ($total_bruto * ($descuento/100)), 2);

// Total pagado hoy (no incluye CC manual)
$total_abonado_hoy = round($pago_efectivo + $pago_transferencia + $pago_debito + $pago_credito, 2);

// Diferencia: si >0 falta pagar; si <0 sobra
$dif = round($total_final - $total_abonado_hoy, 2);

// Si no viene fecha de vencimiento, calcular con duracion_meses
if ($fecha_vencimiento === '' || $fecha_vencimiento === null) {
    $fecha_vencimiento = date('Y-m-d', strtotime("+{$duracion_meses} month", strtotime($fecha_inicio)));
}

try {
    $conexion->begin_transaction();

    // 1) Pasar membresías anteriores a historial
    $stmtSel = $conexion->prepare("SELECT * FROM membresias WHERE cliente_id = ? AND gimnasio_id = ?");
    $stmtSel->bind_param("ii", $cliente_id, $gimnasio_id);
    $stmtSel->execute();
    $anterior = $stmtSel->get_result();
    $stmtSel->close();

    if ($anterior && $anterior->num_rows > 0) {
        $stmtHist = $conexion->prepare("
            INSERT INTO membresias_historial 
                (cliente_id, gimnasio_id, plan_id, precio, clases_disponibles, fecha_inicio, fecha_vencimiento, 
                 otros_pagos, metodo_pago, total, duracion_meses) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$stmtHist) { throw new Exception("Prepare historial: ".$conexion->error); }

        while ($m = $anterior->fetch_assoc()) {
            $stmtHist->bind_param(
                "iiidissssdi",
                $m['cliente_id'], $m['gimnasio_id'], $m['plan_id'], $m['precio'], $m['clases_disponibles'],
                $m['fecha_inicio'], $m['fecha_vencimiento'], $m['otros_pagos'], $m['metodo_pago'],
                $m['total'], $m['duracion_meses']
            );
            if (!$stmtHist->execute()) { throw new Exception("Exec historial: ".$stmtHist->error); }
        }
        $stmtHist->close();
    }

    // 2) Eliminar membresías activas anteriores
    $stmtDel = $conexion->prepare("DELETE FROM membresias WHERE cliente_id = ? AND gimnasio_id = ?");
    $stmtDel->bind_param("ii", $cliente_id, $gimnasio_id);
    $stmtDel->execute();
    $stmtDel->close();

    // 3) Insertar nueva membresía
    $stmt = $conexion->prepare("
        INSERT INTO membresias
          (cliente_id, plan_id, fecha_inicio, fecha_vencimiento, clases_disponibles,
           precio, otros_pagos, descuento, total_pagado, metodo_pago, saldo_cc, total,
           pago_efectivo, pago_transferencia, pago_debito, pago_credito, pago_cuenta_corriente,
           gimnasio_id, duracion_meses, activa)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");
    if (!$stmt) { throw new Exception("Prepare membresias: ".$conexion->error); }

    $saldo_cc = 0.0; // se recalcula abajo con (DEBE-HABER)

    $stmt->bind_param(
        "iissiddddsddddddii",
        $cliente_id, $plan_id, $fecha_inicio, $fecha_vencimiento, $clases_disponibles,
        $precio, $otros_pagos, $descuento, $total_abonado_hoy, $metodo_pago,
        $saldo_cc, $total_final,
        $pago_efectivo, $pago_transferencia, $pago_debito, $pago_credito, $pago_cc_manual,
        $gimnasio_id, $duracion_meses
    );
    if (!$stmt->execute()) { throw new Exception("Exec membresias: ".$stmt->error); }
    $membresia_id = (int)$stmt->insert_id;
    $stmt->close();

    // 4) Registrar CC en cc_movimientos (DEBE/HABER)
    $fecha_cc = $fecha_actual;
    $debe_total  = 0.0;
    $haber_total = 0.0;

    // a) CC manual (DEBE)
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

        // restar del remanente para no duplicar
        $dif = round($dif - $pago_cc_manual, 2);
    }

    // b) Remanente: falta pagar → DEBE; sobra → HABER
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

    // 5) Actualizar saldo_cc en membresías (DEBE - HABER)
    $saldo_cc_final = round($debe_total - $haber_total, 2); // >0 deuda, <0 a favor
    $stmtUpd = $conexion->prepare("UPDATE membresias SET saldo_cc = ? WHERE id = ? AND gimnasio_id = ?");
    $stmtUpd->bind_param("dii", $saldo_cc_final, $membresia_id, $gimnasio_id);
    $stmtUpd->execute();
    $stmtUpd->close();

    $conexion->commit();
    header("Location: ver_membresias.php?exito=1");
    exit;

} catch (Throwable $e) {
    $conexion->rollback();
    http_response_code(500);
    echo "Error al guardar renovación: " . htmlspecialchars($e->getMessage());
}
