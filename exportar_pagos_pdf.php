<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require('fpdf/fpdf.php');
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$mes = $_GET['mes'] ?? date('m');
$anio = $_GET['anio'] ?? date('Y');

$meses = [
    '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo',
    '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio',
    '07' => 'Julio', '08' => 'Agosto', '09' => 'Septiembre',
    '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
];

// Consulta de pagos del mes
$query = $conexion->query("
    SELECT m.fecha_inicio, m.fecha_vencimiento,
           m.pago_efectivo, m.pago_transferencia, m.pago_debito, m.pago_credito, m.pago_cuenta_corriente,
           IFNULL(m.otros_pagos, 0) AS otros_pagos,
           IFNULL(m.total_pagado, 0) AS total,
           c.apellido, c.nombre
    FROM membresias m
    INNER JOIN clientes c ON m.cliente_id = c.id
    WHERE MONTH(m.fecha_inicio) = $mes AND YEAR(m.fecha_inicio) = $anio
      AND m.gimnasio_id = $gimnasio_id
    ORDER BY m.fecha_inicio DESC
");

function obtenerMetodoPago($fila) {
    $metodos = [];
    if ($fila['pago_efectivo'] > 0) $metodos[] = 'Efectivo';
    if ($fila['pago_transferencia'] > 0) $metodos[] = 'Transferencia';
    if ($fila['pago_debito'] > 0) $metodos[] = 'Débito';
    if ($fila['pago_credito'] > 0) $metodos[] = 'Crédito';
    if ($fila['pago_cuenta_corriente'] > 0) $metodos[] = 'Cuenta Corriente';
    return implode(' + ', $metodos);
}

// Crear PDF
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',14);
$pdf->Cell(0,10,"Reporte de Pagos - {$meses[$mes]} $anio",0,1,'C');
$pdf->Ln(5);

// Cabecera de tabla
$pdf->SetFont('Arial','B',10);
$pdf->SetFillColor(200,200,200);
$pdf->Cell(50, 8, 'Cliente', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Inicio', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Venc.', 1, 0, 'C', true);
$pdf->Cell(40, 8, 'Metodo Pago', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'Otros', 1, 0, 'C', true);
$pdf->Cell(30, 8, 'Total ($)', 1, 1, 'C', true);

$pdf->SetFont('Arial','',9);
$total_mes = 0;

while ($fila = $query->fetch_assoc()) {
    $metodo_pago = obtenerMetodoPago($fila);
    $total_mes += floatval($fila['total']);

    $pdf->Cell(50, 8, $fila['apellido'].' '.$fila['nombre'], 1);
    $pdf->Cell(25, 8, $fila['fecha_inicio'], 1);
    $pdf->Cell(25, 8, $fila['fecha_vencimiento'], 1);
    $pdf->Cell(40, 8, $metodo_pago ?: 'Sin especificar', 1);
    $pdf->Cell(20, 8, '$' . number_format($fila['otros_pagos'], 0, ',', '.'), 1, 0, 'R');
    $pdf->Cell(30, 8, '$' . number_format($fila['total'], 0, ',', '.'), 1, 1, 'R');
}

// Total
$pdf->SetFont('Arial','B',11);
$pdf->Cell(160, 10, 'Total del mes:', 1, 0, 'R');
$pdf->Cell(30, 10, '$' . number_format($total_mes, 0, ',', '.'), 1, 1, 'R');

// Salida del PDF
$pdf->Output('I', 'reporte_pagos.pdf');
exit;
