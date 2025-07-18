<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$id = intval($_POST['id'] ?? 0);
$cliente_id = intval($_POST['cliente_id'] ?? 0);
$plan_id = intval($_POST['plan_id'] ?? 0);
$precio = floatval($_POST['precio'] ?? 0);
$clases = intval($_POST['clases_disponibles'] ?? 0);
$fecha_inicio = $_POST['fecha_inicio'] ?? '';
$fecha_vencimiento = $_POST['fecha_vencimiento'] ?? '';
$otros_pagos = floatval($_POST['otros_pagos'] ?? 0);
$total = floatval($_POST['total'] ?? 0);
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Nuevos campos de forma de pago
$pago_efectivo = floatval($_POST['pago_efectivo'] ?? 0);
$pago_transferencia = floatval($_POST['pago_transferencia'] ?? 0);
$pago_debito = floatval($_POST['pago_debito'] ?? 0);
$pago_credito = floatval($_POST['pago_credito'] ?? 0);
$pago_cuenta_corriente = floatval($_POST['pago_cuenta_corriente'] ?? 0);

if ($id > 0 && $cliente_id > 0 && $plan_id > 0 && $fecha_inicio && $fecha_vencimiento) {
    $stmt = $conexion->prepare("UPDATE membresias SET cliente_id=?, plan_id=?, precio=?, clases_restantes=?, fecha_inicio=?, fecha_vencimiento=?, otros_pagos=?, total=?, 
        pago_efectivo=?, pago_transferencia=?, pago_debito=?, pago_credito=?, pago_cuenta_corriente=?
        WHERE id=? AND gimnasio_id=?");
    $stmt->bind_param("iidissddddddii", 
        $cliente_id, $plan_id, $precio, $clases, $fecha_inicio, $fecha_vencimiento, $otros_pagos, $total,
        $pago_efectivo, $pago_transferencia, $pago_debito, $pago_credito, $pago_cuenta_corriente,
        $id, $gimnasio_id
    );
    $stmt->execute();
}

header("Location: ver_membresias.php");
exit;
