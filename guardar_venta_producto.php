<?php
session_start();
include 'conexion.php';
require('fpdf/fpdf.php'); // Asegurate que esté en la carpeta "fpdf/"

// ==== ENTRADAS ====
$cliente_id   = (int)($_POST['cliente_id'] ?? 0);
$producto_id  = (int)($_POST['producto_id'] ?? 0);
$cantidad     = (int)($_POST['cantidad'] ?? 0);
$metodo_pago  = trim($_POST['metodo_pago'] ?? '');
$fecha_venta  = date("Y-m-d");
$gimnasio_id  = (int)($_SESSION['gimnasio_id'] ?? 0);

// (Opcional) para soportar pagos mixtos: resto a CC que envíes desde el form
$pago_cc_post = isset($_POST['pago_cuenta_corriente']) ? (float)$_POST['pago_cuenta_corriente'] : 0.0;

// ==== VALIDACIONES BÁSICAS ====
if ($cliente_id <= 0 || $producto_id <= 0 || $gimnasio_id <= 0 || $cantidad <= 0) {
    exit("❌ Faltan datos obligatorios para registrar la venta.");
}

// Normalizar método de pago (permitidos)
$metodos_permitidos = ['efectivo','transferencia','débito','debito','crédito','credito','otro','cuenta_corriente'];
if (!in_array(mb_strtolower($metodo_pago), $metodos_permitidos, true)) {
    exit("❌ Método de pago no válido.");
}
// Unificar acentos / variantes
$map = ['debito'=>'débito','credito'=>'crédito'];
$metodo_pago = $map[mb_strtolower($metodo_pago)] ?? mb_strtolower($metodo_pago);

// ==== BUSCAR PRODUCTO EN CUALQUIERA DE LAS TABLAS ====
$query = "
    SELECT nombre, precio_venta FROM productos_proteccion   WHERE id = $producto_id
    UNION ALL
    SELECT nombre, precio_venta FROM productos_indumentaria WHERE id = $producto_id
    UNION ALL
    SELECT nombre, precio_venta FROM productos_suplemento   WHERE id = $producto_id
    LIMIT 1
";
$result = $conexion->query($query);

if (!$result || !$result->num_rows) {
    exit("❌ Producto no encontrado.");
}

$row = $result->fetch_assoc();
$producto_nombre = $row['nombre'];
$precio_unitario = (float)$row['precio_venta'];
$total = $precio_unitario * $cantidad;

// ==== REGISTRAR VENTA (ventas_productos) ====
$stmtVenta = $conexion->prepare("
    INSERT INTO ventas_productos (cliente_id, producto_id, cantidad, total, metodo_pago, fecha_venta, gimnasio_id)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
if (!$stmtVenta) { exit("❌ Error de preparación venta: " . $conexion->error); }
$stmtVenta->bind_param('iiidssi', $cliente_id, $producto_id, $cantidad, $total, $metodo_pago, $fecha_venta, $gimnasio_id);
$okVenta = $stmtVenta->execute();
if (!$okVenta) { exit("❌ Error al registrar la venta: " . $stmtVenta->error); }
$venta_id = (int)$stmtVenta->insert_id;
$stmtVenta->close();

// ==== REGISTRAR FACTURA (facturas) ====
$detalle_fact = 'Venta de productos';
$stmtFac = $conexion->prepare("
    INSERT INTO facturas (tipo, cliente_id, total, metodo_pago, detalle, gimnasio_id)
    VALUES ('venta', ?, ?, ?, ?, ?)
");
if (!$stmtFac) { exit("❌ Error de preparación factura: " . $conexion->error); }
$stmtFac->bind_param('idssi', $cliente_id, $total, $metodo_pago, $detalle_fact, $gimnasio_id);
$okFac = $stmtFac->execute();
if (!$okFac) { exit("❌ Error al registrar la factura: " . $stmtFac->error); }
$stmtFac->close();

// ==== CUENTA CORRIENTE (DEUDA) ====
// Regla: si el método es cuenta_corriente => la DEUDA es el total.
//        si viene pago_cuenta_corriente (mixto) => la DEUDA es ese importe (capado a [0, total]).
$deuda_cc = 0.0;
if ($metodo_pago === 'cuenta_corriente') {
    $deuda_cc = max(0.0, round($total, 2));
} elseif ($pago_cc_post > 0.009) {
    $deuda_cc = min(max(0.0, round($pago_cc_post, 2)), round($total, 2));
}

if ($deuda_cc > 0.0) {
    // Inserta movimiento DEBE en cc_movimientos
    $concepto = "Deuda por venta #{$venta_id}";
    $stmtCC = $conexion->prepare("
        INSERT INTO cc_movimientos (gimnasio_id, cliente_id, venta_id, fecha, concepto, debe, haber)
        VALUES (?, ?, ?, NOW(), ?, ?, 0)
    ");
    if ($stmtCC) {
        $stmtCC->bind_param('iiisd', $gimnasio_id, $cliente_id, $venta_id, $concepto, $deuda_cc);
        $stmtCC->execute();
        $stmtCC->close();
    } else {
        // No frenamos la venta si falla CC, pero avisamos
        error_log("No se pudo insertar deuda CC: " . $conexion->error);
    }
}

// ==== DATOS PARA PDF ====
$res_cliente = $conexion->query("SELECT nombre, apellido FROM clientes WHERE id = $cliente_id");
$cliente = $res_cliente ? $res_cliente->fetch_assoc() : ['nombre'=>'','apellido'=>''];

$res_gym = $conexion->query("SELECT nombre FROM gimnasios WHERE id = $gimnasio_id");
$gimnasio = $res_gym ? $res_gym->fetch_assoc() : null;
$nombre_gimnasio = $gimnasio['nombre'] ?? 'Gimnasio';

// Texto amigable del método
$metodo_legible = ($metodo_pago === 'cuenta_corriente')
    ? 'Cuenta Corriente (Deuda)'
    : ucfirst($metodo_pago);

// ==== GENERAR PDF ====
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, $nombre_gimnasio, 0, 1, 'C');

$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, 'Factura de Venta - ' . $fecha_venta, 0, 1);
$pdf->Ln(5);
$pdf->Cell(0, 10, 'Cliente: ' . ($cliente['apellido'] ?? '') . ', ' . ($cliente['nombre'] ?? ''), 0, 1);
$pdf->Ln(5);

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(100, 10, 'Producto', 1);
$pdf->Cell(30, 10, 'Cantidad', 1);
$pdf->Cell(30, 10, 'Unitario', 1);
$pdf->Cell(30, 10, 'Total', 1);
$pdf->Ln();

$pdf->SetFont('Arial', '', 11);
$pdf->Cell(100, 10, $producto_nombre, 1);
$pdf->Cell(30, 10, $cantidad, 1);
$pdf->Cell(30, 10, '$' . number_format($precio_unitario, 2, ',', '.'), 1);
$pdf->Cell(30, 10, '$' . number_format($total, 2, ',', '.'), 1);
$pdf->Ln(10);

$pdf->Cell(0, 8, 'Metodo de pago: ' . $metodo_legible, 0, 1);

if ($deuda_cc > 0.0) {
    $pdf->SetTextColor(200, 0, 0);
    $pdf->Cell(0, 8, 'Registrado a Cuenta Corriente: $' . number_format($deuda_cc, 2, ',', '.'), 0, 1);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial','',9);
    $pdf->Cell(0, 6, 'Concepto CC: Deuda por venta #' . $venta_id, 0, 1);
    $pdf->SetFont('Arial','',11);
}

$pdf->Ln(5);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Total: $' . number_format($total, 2, ',', '.'), 0, 1);

$pdf->Output('I', 'Factura_venta_' . $cliente_id . '.pdf'); // Mostrar en navegador
