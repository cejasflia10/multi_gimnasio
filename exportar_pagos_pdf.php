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

// Datos del gimnasio
$info = $conexion->query("SELECT nombre, cuit, logo FROM gimnasios WHERE id = $gimnasio_id")->fetch_assoc();
$nombre_gimnasio = $info['nombre'] ?? 'Gimnasio';
$cuit_gimnasio = $info['cuit'] ?? 'CUIT no registrado';
$logo_gimnasio = $info['logo'] ?? ''; // debe estar en carpeta logos/

// Consulta de pagos
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

// Clase PDF personalizada
class PDF extends FPDF {
    public $angle = 0;
    public $marca_agua = '';

    function Header() {
        global $nombre_gimnasio, $logo_gimnasio, $cuit_gimnasio;

        if ($logo_gimnasio && file_exists("logos/$logo_gimnasio")) {
            $this->Image("logos/$logo_gimnasio", 10, 8, 30);
        }

        $this->SetFont('Arial','B',12);
        $this->Cell(0,6, mb_convert_encoding($nombre_gimnasio, 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
        $this->SetFont('Arial','',10);
        $this->Cell(0,5, 'CUIT: ' . $cuit_gimnasio, 0, 1, 'C');
        $this->Ln(5);

        // Marca de agua
        if (!empty($this->marca_agua)) {
            $this->SetFont('Arial','B',35);
            $this->SetTextColor(240,240,240);
            $this->Rotate(45, 55, 190);
            $this->Text(35, 200, mb_convert_encoding($this->marca_agua, 'ISO-8859-1', 'UTF-8'));
            $this->Rotate(0);
            $this->SetTextColor(0);
        }
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,'Página '.$this->PageNo().'/{nb}',0,0,'C');
    }

    function Rotate($angle, $x=-1, $y=-1) {
        if ($x == -1) $x = $this->x;
        if ($y == -1) $y = $this->y;
        if ($this->angle != 0)
            $this->_out('Q');
        $this->angle = $angle;
        if ($angle != 0) {
            $angle *= M_PI/180;
            $c = cos($angle);
            $s = sin($angle);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm',
                $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
        }
    }

    function _endpage() {
        if ($this->angle != 0) {
            $this->angle = 0;
            $this->_out('Q');
        }
        parent::_endpage();
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->marca_agua = $nombre_gimnasio;
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

    $pdf->Cell(50, 8, mb_convert_encoding($fila['apellido'].' '.$fila['nombre'], 'ISO-8859-1', 'UTF-8'), 1);
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
