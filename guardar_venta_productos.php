<?php
session_start();
require('fpdf/fpdf.php');
include 'conexion.php';

// Solo lo usamos para mostrar en la factura, no se guarda en la DB
$cliente_nombre = $_POST['cliente_nombre'] ?? 'Cliente temporal';
$cliente_temporal = $_POST['cliente_temporal'] ?? 0;
$descuento = floatval($_POST['descuento'] ?? 0);
$total_original = floatval($_POST['total_original'] ?? 0);
$total_final = floatval($_POST['total_con_descuento'] ?? 0);
$fecha = date("Y-m-d");
$hora = date("H:i");
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$gimnasio_nombre = $_SESSION['gimnasio_nombre'] ?? 'Mi Gimnasio';

// Métodos de pago
$pago_efectivo = floatval($_POST['pago_efectivo'] ?? 0);
$pago_transferencia = floatval($_POST['pago_transferencia'] ?? 0);
$pago_debito = floatval($_POST['pago_debito'] ?? 0);
$pago_credito = floatval($_POST['pago_credito'] ?? 0);
$pago_cuenta_corriente = floatval($_POST['pago_cuenta_corriente'] ?? 0);

// Guardar venta principal (sin cliente_nombre)
$stmt = $conexion->prepare("INSERT INTO ventas_productos (
    cliente_temporal, descuento, total, fecha, hora,
    efectivo, transferencia, debito, credito, cuenta_corriente, gimnasio_id
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$stmt->bind_param("idssssddddi", $cliente_temporal, $descuento, $total_final, $fecha, $hora,
    $pago_efectivo, $pago_transferencia, $pago_debito, $pago_credito, $pago_cuenta_corriente, $gimnasio_id);

$stmt->execute();
$venta_id = $stmt->insert_id;

// Acá sigue el código para guardar detalle y generar PDF (usa $cliente_nombre para mostrar en la factura)
?>
