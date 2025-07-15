<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

date_default_timezone_set('America/Argentina/Buenos_Aires');
$fecha = date('Y-m-d');

$cliente_id = intval($_POST['cliente_id'] ?? 0);
$cliente_temporal = intval($_POST['cliente_temporal'] ?? 0);
$productos = $_POST['producto_nombre'] ?? [];
$precios = $_POST['precio'] ?? [];
$cantidades = $_POST['cantidad'] ?? [];
$tipo_venta = $_POST['tipo_venta'] ?? '';
$gimnasio_id = intval($_POST['gimnasio_id'] ?? 0);

// Métodos de pago
$pago_efectivo = floatval($_POST['pago_efectivo'] ?? 0);
$pago_transferencia = floatval($_POST['pago_transferencia'] ?? 0);
$pago_debito = floatval($_POST['pago_debito'] ?? 0);
$pago_credito = floatval($_POST['pago_credito'] ?? 0);
$pago_cuenta_corriente = floatval($_POST['pago_cuenta_corriente'] ?? 0);

$total_con_descuento = floatval($_POST['total_con_descuento'] ?? 0);
$descuento = floatval($_POST['descuento'] ?? 0);

// Validación básica
if ($cliente_id <= 0 && !$cliente_temporal) {
    echo "❌ Cliente no válido.";
    exit;
}
if (empty($productos)) {
    echo "❌ No se seleccionaron productos.";
    exit;
}

// Crear factura
$tipo = "venta";
$detalle = "Venta de $tipo_venta";
$metodo_pago = "Efectivo: $pago_efectivo, Transf: $pago_transferencia, Débito: $pago_debito, Crédito: $pago_credito, Cuenta Corriente: $pago_cuenta_corriente";

$stmt = $conexion->prepare("INSERT INTO facturas (tipo, cliente_id, total, metodo_pago, detalle, gimnasio_id, fecha_pago) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("sidssis", $tipo, $cliente_id, $total_con_descuento, $metodo_pago, $detalle, $gimnasio_id, $fecha);
$stmt->execute();
$factura_id = $stmt->insert_id;

// Guardar detalle de productos vendidos
for ($i = 0; $i < count($productos); $i++) {
    $nombre = $productos[$i];
    $precio = floatval($precios[$i]);
    $cantidad = intval($cantidades[$i]);
    $subtotal = $precio * $cantidad;

    // Insertar en tabla ventas_productos
    $conexion->query("INSERT INTO ventas_productos (
        cliente_id, producto_nombre, precio, cantidad, subtotal, total, metodo_pago, tipo_venta, fecha, gimnasio_id, factura_id
    ) VALUES (
        $cliente_id, '$nombre', $precio, $cantidad, $subtotal, $total_con_descuento, '$metodo_pago', '$tipo_venta', '$fecha', $gimnasio_id, $factura_id
    )");

    // Actualizar stock solo para protecciones
    if ($tipo_venta === 'protecciones') {
        $conexion->query("UPDATE productos SET stock = stock - $cantidad WHERE nombre = '$nombre' AND gimnasio_id = $gimnasio_id");
    }
}

// Redirigir con mensaje de éxito
header("Location: ventas_productos.php?ok=1");
exit;
