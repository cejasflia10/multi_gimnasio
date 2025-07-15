<?php
require('fpdf/fpdf.php');
include 'conexion.php';

if (session_status() === PHP_SESSION_NONE) session_start();

function limpiar($texto) {
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $texto);
}

$id = intval($_GET['id'] ?? 0);
$imprimir = isset($_GET['imprimir']);
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($id <= 0 || $gimnasio_id <= 0) {
    exit("Factura inválida.");
}

// Obtener datos de la factura
$res = $conexion->query("SELECT * FROM facturas WHERE id = $id AND gimnasio_id = $gimnasio_id");
$factura = $res->fetch_assoc();

// Obtener productos de la factura
$detalle = $conexion->query("SELECT * FROM ventas_productos WHERE factura_id = $id");

// Datos del gimnasio
$gimnasio = $conexion->query("SELECT nombre, direccion FROM gimnasios WHERE id = $gimnasio_id")->fetch_assoc();

// Datos del cliente
$cliente = $conexion->query("SELECT nombre, apellido FROM clientes WHERE id = {$factura['cliente_id']}")->fetch_assoc();
$cliente_nombre = $cliente ? $cliente['apellido'] . ' ' . $cliente['nombre'] : 'Cliente eliminado';

// Procesar forma de pago
$metodo = $factura['metodo_pago'];
$formapago = 'Desconocido';
if (strpos($metodo, 'Efectivo:') !== false && strpos($metodo, 'Efectivo: 0') === false) {
    $formapago = 'Efectivo';
} elseif (strpos($metodo, 'Transf:') !== false && strpos($metodo, 'Transf: 0') === false) {
    $formapago = 'Transferencia';
} elseif (strpos($metodo, 'Débito:') !== false && strpos($metodo, 'Débito: 0') === false) {
    $formapago = 'Débito';
} elseif (strpos($metodo, 'Crédito:') !== false && strpos($metodo, 'Crédito: 0') === false) {
    $formapago = 'Crédito';
} elseif (strpos($metodo, 'Cuenta Corriente:') !== false && strpos($metodo, 'Cuenta Corriente: 0') === false) {
    $formapago = 'Cuenta Corriente';
}

// Iniciar PDF
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);

// Encabezado
$pdf->Cell(0, 10, limpiar($gimnasio['nombre']), 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 8, limpiar("Dirección: " . $gimnasio['direccion']), 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'Factura de Venta', 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 8, "Fecha: " . $factura['fecha_pago'], 0, 1);
$pdf->Cell(0, 8, "Cliente: " . limpiar($cliente_nombre), 0, 1);
$pdf->Cell(0, 8, "Método de Pago: " . limpiar($formapago), 0, 1);
$pdf->Ln(5);

// Tabla de productos
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(80, 10, 'Producto', 1);
$pdf->Cell(30, 10, 'Precio', 1);
$pdf->Cell(30, 10, 'Cantidad', 1);
$pdf->Cell(40, 10, 'Subtotal', 1);
$pdf->Ln();

$pdf->SetFont('Arial', '', 12);
while ($row = $detalle->fetch_assoc()) {
    $pdf->Cell(80, 10, limpiar($row['producto_nombre']), 1);
    $pdf->Cell(30, 10, '$' . number_format($row['precio'], 2), 1);
    $pdf->Cell(30, 10, $row['cantidad'], 1);
    $pdf->Cell(40, 10, '$' . number_format($row['subtotal'], 2), 1);
    $pdf->Ln();
}

$pdf->Ln(5);
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'Total: $' . number_format($factura['total'], 2), 0, 1, 'R');

// Salida
if ($imprimir) {
    $pdf->Output(); // vista previa
} else {
    $pdf->Output('D', 'Factura_' . $id . '.pdf'); // descarga
}
