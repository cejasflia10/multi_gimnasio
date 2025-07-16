<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$cliente_id = intval($_POST['cliente_id']);
$gimnasio_id = intval($_POST['gimnasio_id']);
$plan_id = intval($_POST['plan_id']);
$fecha_inicio = $_POST['fecha_inicio'];
$fecha_vencimiento = $_POST['fecha_vencimiento'];
$clases_disponibles = intval($_POST['clases_disponibles']);
$precio = floatval($_POST['precio']);
$otros_pagos = floatval($_POST['otros_pagos'] ?? 0);
$descuento = floatval($_POST['descuento'] ?? 0);
$duracion_meses = intval($_POST['duracion_meses']);
$fecha_actual = date('Y-m-d');

// Pagos individuales
$pago_efectivo = floatval($_POST['pago_efectivo'] ?? 0);
$pago_transferencia = floatval($_POST['pago_transferencia'] ?? 0);
$pago_debito = floatval($_POST['pago_debito'] ?? 0);
$pago_credito = floatval($_POST['pago_credito'] ?? 0);
$pago_cuenta_corriente = floatval($_POST['pago_cuenta_corriente'] ?? 0);

// Total abonado (todo menos cuenta corriente)
$total_pagado = $pago_efectivo + $pago_transferencia + $pago_debito + $pago_credito;
$saldo_cc = $pago_cuenta_corriente;

// Detectar método de pago principal (visual)
$metodos = [];
if ($pago_efectivo > 0) $metodos[] = "efectivo";
if ($pago_transferencia > 0) $metodos[] = "transferencia";
if ($pago_debito > 0) $metodos[] = "débito";
if ($pago_credito > 0) $metodos[] = "crédito";
if ($pago_cuenta_corriente > 0) $metodos[] = "cuenta corriente";
$metodo_pago = implode(" + ", $metodos);

// 1. Guardar la membresía anterior en historial
$anterior = $conexion->query("SELECT * FROM membresias WHERE cliente_id = $cliente_id AND gimnasio_id = $gimnasio_id");
if ($anterior && $anterior->num_rows > 0) {
    while ($m = $anterior->fetch_assoc()) {
        $conexion->query("INSERT INTO membresias_historial 
            (cliente_id, gimnasio_id, plan_id, precio, clases_disponibles, fecha_inicio, fecha_vencimiento, otros_pagos, forma_pago, total, duracion_meses) 
            VALUES (
                {$m['cliente_id']}, {$m['gimnasio_id']}, {$m['plan_id']}, {$m['precio']}, {$m['clases_disponibles']},
                '{$m['fecha_inicio']}', '{$m['fecha_vencimiento']}', {$m['otros_pagos']}, '{$m['forma_pago']}', {$m['total']}, {$m['duracion_meses']}
            )");
    }
}

// 2. Eliminar membresía anterior
$conexion->query("DELETE FROM membresias WHERE cliente_id = $cliente_id AND gimnasio_id = $gimnasio_id");

// 3. Insertar nueva membresía renovada
$stmt = $conexion->prepare("INSERT INTO membresias 
    (cliente_id, plan_id, fecha_inicio, fecha_vencimiento, clases_disponibles, precio, otros_pagos, descuento, 
     total_pagado, metodo_pago, saldo_cc, pago_efectivo, pago_transferencia, pago_debito, pago_credito, 
     pago_cuenta_corriente, gimnasio_id, duracion_meses, activa) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");

$stmt->bind_param("iissiddddsddddddii",
    $cliente_id, $plan_id, $fecha_inicio, $fecha_vencimiento, $clases_disponibles,
    $precio, $otros_pagos, $descuento, $total_pagado, $metodo_pago, $saldo_cc,
    $pago_efectivo, $pago_transferencia, $pago_debito, $pago_credito,
    $pago_cuenta_corriente, $gimnasio_id, $duracion_meses);

$stmt->execute();

// 4. Si hay cuenta corriente, registrar deuda
if ($saldo_cc > 0) {
    $conexion->query("INSERT INTO cuentas_corrientes 
        (cliente_id, gimnasio_id, fecha, descripcion, monto)
        VALUES ($cliente_id, $gimnasio_id, '$fecha_actual', 'Deuda por renovación de membresía', -$saldo_cc)");
}

header("Location: ver_membresias.php");
exit;
?>
