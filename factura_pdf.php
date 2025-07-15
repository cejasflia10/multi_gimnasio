<?php
require('fpdf/fpdf.php');
include 'conexion.php';

$id = intval($_GET['id'] ?? 0);
$imprimir = isset($_GET['imprimir']);

if ($id <= 0) {
    exit("Factura inválida.");
}

// Obtener datos de la factura
$res = $conexion->query("SELECT * FROM facturas WHERE id = $id");
$factura = $res->fetch_assoc();

// Obtener productos de la factura
$detalle = $conexion->query("SELECT * FROM ventas_productos WHERE factura_id = $id");

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);

// Encabezado
$pdf->Cell(0, 10, 'Factura de Venta', 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, "Fecha: " . $factura['fecha_pago'], 0, 1);
$pdf->Cell(0, 10, "Cliente ID: " . $factura['cliente_id'], 0, 1);
$pdf->Cell(0, 10, "Método de Pago:", 0, 1);
$pdf->MultiCell(0, 8, $factura['metodo_pago']);
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
    $pdf->Cell(80, 10, utf8_decode($row['producto_nombre']), 1);
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
    $pdf->Output(); // Vista previa
} else {
    $pdf->Output('D', 'Factura_' . $id . '.pdf'); // Forzar descarga
}
