<?php
session_start();
require('fpdf/fpdf.php');
include 'conexion.php';

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

// Guardar venta
$stmt = $conexion->prepare("INSERT INTO ventas_productos (cliente_nombre, cliente_temporal, descuento, total, fecha, hora, efectivo, transferencia, debito, credito, cuenta_corriente, gimnasio_id) 
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("sidssssddddi", $cliente_nombre, $cliente_temporal, $descuento, $total_final, $fecha, $hora, $pago_efectivo, $pago_transferencia, $pago_debito, $pago_credito, $pago_cuenta_corriente, $gimnasio_id);
$stmt->execute();
$venta_id = $stmt->insert_id;

// Generar factura PDF
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',14);
$pdf->Cell(0,10,utf8_decode("Factura de Venta - $gimnasio_nombre"),0,1,'C');
$pdf->SetFont('Arial','',12);
$pdf->Cell(0,10,"Fecha: $fecha $hora",0,1);
$pdf->Cell(0,10,"Cliente: $cliente_nombre",0,1);
$pdf->Cell(0,10,"Total: $" . number_format($total_final, 2),0,1);
$pdf->Ln(5);
$pdf->Cell(0,10,"Descuento aplicado: $descuento%",0,1);
$pdf->Ln(5);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,10,"Metodos de Pago:",0,1);
$pdf->SetFont('Arial','',12);
$pdf->Cell(0,8,"Efectivo: $" . number_format($pago_efectivo, 2),0,1);
$pdf->Cell(0,8,"Transferencia: $" . number_format($pago_transferencia, 2),0,1);
$pdf->Cell(0,8,"Debito: $" . number_format($pago_debito, 2),0,1);
$pdf->Cell(0,8,"Credito: $" . number_format($pago_credito, 2),0,1);
$pdf->Cell(0,8,"Cuenta Corriente: $" . number_format($pago_cuenta_corriente, 2),0,1);

$nombre_archivo = "factura_venta_$venta_id.pdf";
$ruta = "facturas/$nombre_archivo";

if (!file_exists("facturas")) mkdir("facturas");
$pdf->Output("F", $ruta); // Guarda en servidor
$pdf->Output("D", $nombre_archivo); // Descarga
exit;
?>
