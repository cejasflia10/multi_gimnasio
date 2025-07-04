<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$cliente_nombre = $_POST['cliente_nombre'] ?? '';
$es_temporal = $_POST['cliente_temporal'] ?? 0;
$tipo_venta = $_POST['tipo_venta'] ?? 'productos';
$gimnasio_id = intval($_POST['gimnasio_id'] ?? 0);
$total = floatval($_POST['total'] ?? 0);

// Métodos de pago
$pago_efectivo = floatval($_POST['pago_efectivo'] ?? 0);
$pago_transferencia = floatval($_POST['pago_transferencia'] ?? 0);
$pago_tarjeta = floatval($_POST['pago_tarjeta'] ?? 0);
$pago_cc = floatval($_POST['pago_cc'] ?? 0);
$total_pagado = $pago_efectivo + $pago_transferencia + $pago_tarjeta + $pago_cc;

// Listado de productos vendidos
$productos = $_POST['producto_nombre'] ?? [];
$precios = $_POST['precio'] ?? [];
$cantidades = $_POST['cantidad'] ?? [];

// Validación mínima
if ($total_pagado < $total) {
    echo "<div style='color:red; font-size:20px;'>⚠️ El total pagado es menor al total. Se registrará saldo en cuenta corriente.</div>";
}

// Registrar venta (puede ir todo en una tabla 'ventas' o separadas, en este ejemplo lo hacemos por tipo)
$tabla = ($tipo_venta == 'suplementos') ? 'ventas_suplementos' : 'ventas_productos';

$conexion->query("INSERT INTO $tabla (cliente_nombre, total, pago_efectivo, pago_transferencia, pago_tarjeta, pago_cc, gimnasio_id, fecha)
                  VALUES ('$cliente_nombre', $total, $pago_efectivo, $pago_transferencia, $pago_tarjeta, $pago_cc, $gimnasio_id, NOW())");

$venta_id = $conexion->insert_id;

// Guardar detalles de productos vendidos
for ($i = 0; $i < count($productos); $i++) {
    $nombre = $conexion->real_escape_string($productos[$i]);
    $precio = floatval($precios[$i]);
    $cantidad = intval($cantidades[$i]);
    $subtotal = $precio * $cantidad;

    $conexion->query("INSERT INTO {$tabla}_detalle (venta_id, producto, precio, cantidad, subtotal)
                      VALUES ($venta_id, '$nombre', $precio, $cantidad, $subtotal)");

    // Actualizar stock (suponiendo que el nombre es único o lo buscás por ID en una versión futura)
    $tabla_stock = ($tipo_venta == 'suplementos') ? 'suplementos' : 'productos';
    $conexion->query("UPDATE $tabla_stock SET stock = stock - $cantidad
                      WHERE nombre = '$nombre' AND gimnasio_id = $gimnasio_id");
}

echo "<div style='color:lightgreen; font-size:20px;'>✅ Venta registrada correctamente.</div>";
echo "<br><a href='ventas_$tipo_venta.php' style='color:gold;'>← Volver</a>";
