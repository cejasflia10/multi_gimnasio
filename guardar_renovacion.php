<?php
// guardar_renovacion.php — versión corregida con lógica de deuda/a favor
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/conexion.php';

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? ($_POST['gimnasio_id'] ?? 0));
if ($gimnasio_id <= 0) { http_response_code(403); die("Acceso denegado"); }

$cliente_id        = (int)($_POST['cliente_id'] ?? 0);
$plan_id           = (int)($_POST['plan_id'] ?? 0);
$fecha_inicio      = $_POST['fecha_inicio'] ?? date('Y-m-d');
$fecha_vencimiento = $_POST['fecha_vencimiento'] ?? '';
$clases_disponibles= (int)($_POST['clases_disponibles'] ?? 0);
$precio            = (float)($_POST['precio'] ?? 0);
$otros_pagos       = (float)($_POST['otros_pagos'] ?? 0);
$descuento         = (float)($_POST['descuento'] ?? 0);
$duracion_meses    = (int)($_POST['duracion_meses'] ?? 0);
$fecha_actual      = date('Y-m-d H:i:s');

// Pagos individuales
$pago_efectivo      = (float)($_POST['pago_efectivo'] ?? 0);
$pago_transferencia = (float)($_POST['pago_transferencia'] ?? 0);
$pago_debito        = (float)($_POST['pago_debito'] ?? 0);
$pago_credito       = (float)($_POST['pago_credito'] ?? 0);
$pago_cc_manual     = (float)($_POST['pago_cuenta_corriente'] ?? 0);

// Métodos de pago (para texto)
$metodos = [];
if ($pago_efectivo > 0)      $metodos[] = "Efectivo:$pago_efectivo";
if ($pago_transferencia > 0) $metodos[] = "Transferencia:$pago_transferencia";
if ($pago_debito > 0)        $metodos[] = "Débito:$pago_debito";
if ($pago_credito > 0)       $metodos[] = "Crédito:$pago_credito";
$metodo_pago = $metodos ? implode(' | ', $metodos) : 'Sin pagar ahora';

// Total bruto/final
$total_bruto = $precio + $otros_pagos;
$total_final = round($total_bruto - ($total_bruto * ($descuento/100)), 2);

// Total pagado hoy (no incluye CC manual)
$total_abonado_hoy = round($pago_efectivo + $pago_transferencia + $pago_debito + $pago_credito, 2);

// Diferencia
$dif = round($total_final - $total_abonado_hoy, 2);

try {
    $conexion->begin_transaction();

    // 1) Pasar membresía(s) anterior(es) a historial
    $anterior = $conexion->query("SELECT * FROM membresias WHERE cliente_id = $cliente_id AND gimnasio_id = $gimnasio_id");
    if ($anterior && $anterior->num_rows > 0) {
        while ($m = $anterior->fetch_assoc()) {
            $conexion->query("INSERT INTO membresias_historial 
                (cliente_id, gimnasio_id, plan_id, precio, clases_disponibles, fecha_inicio, fecha_vencimiento, 
                 otros_pagos, metodo_pago, total, duracion_meses) 
                VALUES (
                    {$m['cliente_id']}, {$m['gimnasio_id']}, {$m['plan_id']}, {$m['precio']}, {$m['clases_disponibles']},
                    '{$m['fecha_inicio']}', '{$m['fecha_vencimiento']}', {$m['otros_pagos']}, 
                    '{$conexion->real_escape_string($m['metodo_pago'])}', {$m['total']}, {$m['duracion_meses']}
                )");
        }
    }

    // 2) Eliminar membresía(s) anterior(es)
    $conexion->query("DELETE FROM membresias WHERE cliente_id = $cliente_id AND gimnasio_id = $gimnasio_id");

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

    // saldo_cc con signo se calcula más abajo después de insertar en cuentas_corrientes
    $saldo_cc = 0.0;

    $stmt->bind_param(
        "iissiddddsddddddii",
        $cliente_id, $plan_id, $fecha_inicio, $fecha_vencimiento, $clases_disponibles,
        $precio, $otros_pagos, $descuento, $total_abonado_hoy, $metodo_pago,
        $saldo_cc, $total_final,
        $pago_efectivo, $pago_transferencia, $pago_debito, $pago_credito, $pago_cc_manual,
        $gimnasio_id, $duracion_meses
    );
    if (!$stmt->execute()) { throw new Exception("Exec membresias: ".$stmt->error); }
    $membresia_id = $stmt->insert_id;
    $stmt->close();

    // 4) Registrar en cuentas_corrientes
    $saldo_final_cc = 0.0;

    // a) asiento manual si hay CC
    if ($pago_cc_manual > 0.009) {
        $monto_cc = -$pago_cc_manual;
        $desc_cc  = "Renovación membresía #{$membresia_id} - cuenta corriente (manual)";
        $stmtCC = $conexion->prepare("INSERT INTO cuentas_corrientes (cliente_id, gimnasio_id, fecha, descripcion, monto) VALUES (?, ?, ?, ?, ?)");
        $stmtCC->bind_param("iissd", $cliente_id, $gimnasio_id, $fecha_actual, $desc_cc, $monto_cc);
        $stmtCC->execute(); $stmtCC->close();
        $saldo_final_cc += $monto_cc;
        $dif = round($dif - $pago_cc_manual, 2);
    }

    // b) asiento por remanente
    if (abs($dif) > 0.009) {
        $monto_cc = ($dif > 0) ? -$dif : abs($dif);
        $desc_cc  = ($dif > 0)
            ? "Renovación membresía #{$membresia_id} - deuda (remanente)"
            : "Renovación membresía #{$membresia_id} - saldo a favor (remanente)";
        $stmtCC2 = $conexion->prepare("INSERT INTO cuentas_corrientes (cliente_id, gimnasio_id, fecha, descripcion, monto) VALUES (?, ?, ?, ?, ?)");
        $stmtCC2->bind_param("iissd", $cliente_id, $gimnasio_id, $fecha_actual, $desc_cc, $monto_cc);
        $stmtCC2->execute(); $stmtCC2->close();
        $saldo_final_cc += $monto_cc;
    }

    // 5) Actualizar saldo_cc en membresías
    $stmtUpd = $conexion->prepare("UPDATE membresias SET saldo_cc = ? WHERE id = ? AND gimnasio_id = ?");
    $stmtUpd->bind_param("dii", $saldo_final_cc, $membresia_id, $gimnasio_id);
    $stmtUpd->execute(); $stmtUpd->close();

    $conexion->commit();
    header("Location: ver_membresias.php?exito=1");
    exit;

} catch (Throwable $e) {
    $conexion->rollback();
    http_response_code(500);
    echo "Error al guardar renovación: ".$e->getMessage();
}
