<?php
// factura_pdf.php (versión sin utf8_decode y a prueba de output previo)
require_once __DIR__ . '/fpdf/fpdf.php';

/**
 * Convierte UTF-8 a ISO-8859-1 (Latin-1) para FPDF.
 * Si no puede convertir un carácter, lo translitera o lo reemplaza por '?'
 */
function to_latin1(string $s): string {
    // Primero intentamos transliterar
    $t = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
    if ($t === false) {
        // Fallback simple: quita bytes no válidos y reemplaza
        $t = @iconv('UTF-8', 'ISO-8859-1//IGNORE', $s);
        if ($t === false) {
            // como último recurso devolvemos una versión ASCII básica
            $t = preg_replace('/[^\x20-\x7E]/', '?', $s);
        }
    }
    return $t;
}

function money_ars($n){ return '$ ' . number_format((float)$n, 2, ',', '.'); }

/**
 * Genera y guarda/descarga una factura PDF.
 *
 * @param array  $gym   ['nombre','direccion','cuit','logo']
 * @param array  $cli   ['nombre','dni'=>?, 'id'=>?]
 * @param array  $venta ['id','fecha','hora','descuento'=>0,'total'=>0,'cc'=>0]
 * @param array  $items [['nombre','cantidad','unitario','subtotal'], ...]
 * @param array  $pagos ['efectivo'=>0,'transferencia'=>0,'debito'=>0,'credito'=>0,'vuelto'=>0]
 * @param string $rutaGuardar   Ruta absoluta: ej. __DIR__.'/facturas/factura_123.pdf'
 * @param string $nombreDescarga Nombre del archivo al descargar: ej. 'factura_123.pdf'
 */
function generar_y_entregar_factura_pdf(array $gym, array $cli, array $venta, array $items, array $pagos, string $rutaGuardar, string $nombreDescarga){
    // ===== Preparar PDF =====
    $pdf = new FPDF('P','mm','A4');
    $pdf->AddPage();

    // ===== Encabezado con logo =====
    $logoPath = '';
    if (!empty($gym['logo'])) {
        $cand = [];
        // Si viene absoluta o relativa
        $cand[] = $gym['logo'];
        $cand[] = __DIR__ . '/' . ltrim((string)$gym['logo'], '/');
        $cand[] = __DIR__ . '/uploads/' . ltrim((string)$gym['logo'], '/');
        $cand[] = __DIR__ . '/images/'  . ltrim((string)$gym['logo'], '/');
        foreach ($cand as $c) {
            if (is_string($c) && @is_file($c)) { $logoPath = $c; break; }
        }
    }
    if ($logoPath) {
        // logo a la izquierda (ancho 35mm)
        $pdf->Image($logoPath, 10, 10, 35);
    }

    $pdf->SetFont('Arial','B',15);
    $pdf->Cell(0,8, to_latin1($gym['nombre'] ?? 'Gimnasio'), 0,1,'R');
    $pdf->SetFont('Arial','',10);
    if (!empty($gym['direccion'])) $pdf->Cell(0,6, to_latin1('Dirección: '.$gym['direccion']),0,1,'R');
    if (!empty($gym['cuit']))      $pdf->Cell(0,6, 'CUIT: '.to_latin1((string)$gym['cuit']),0,1,'R');

    $pdf->Ln(6);
    $pdf->SetFont('Arial','B',13);
    $pdf->Cell(0,8, to_latin1('Factura de Venta'), 0,1,'C');

    // Datos de cabecera
    $pdf->SetFont('Arial','',11);
    $pdf->Cell(95,6, to_latin1('Fecha: '.($venta['fecha'] ?? date('Y-m-d')).' '.($venta['hora'] ?? date('H:i'))),0,0);
    $pdf->Cell(95,6, to_latin1('Factura N°: '.($venta['id'] ?? '-')),0,1,'R');

    $nombre_cli = $cli['nombre'] ?? 'Consumidor Final';
    $pdf->Cell(95,6, to_latin1('Cliente: '.$nombre_cli),0,0);
    $rightBits = [];
    if (!empty($cli['dni'])) $rightBits[] = 'DNI: '.$cli['dni'];
    if (!empty($cli['id']))  $rightBits[] = 'ID: '.$cli['id'];
    if ($rightBits) {
        $pdf->Cell(95,6, to_latin1(implode('  |  ', $rightBits)),0,1,'R');
    } else {
        $pdf->Ln(6);
    }

    if (!empty($venta['descuento'])) {
        $pdf->Cell(0,6, to_latin1('Descuento aplicado: '.(float)$venta['descuento'].'%'),0,1);
    }

    $pdf->Ln(3);

    // ===== Tabla de items =====
    $pdf->SetFont('Arial','B',11);
    $pdf->Cell(100,8, to_latin1('Producto'),1,0);
    $pdf->Cell(20,8, to_latin1('Cant.'),1,0,'C');
    $pdf->Cell(35,8, to_latin1('Unitario'),1,0,'R');
    $pdf->Cell(35,8, to_latin1('Subtotal'),1,1,'R');

    $pdf->SetFont('Arial','',10);
    $total_calc = 0.0;
    foreach ($items as $it) {
        $nom = (string)($it['nombre'] ?? '');
        $cant = (float)($it['cantidad'] ?? 0);
        $uni  = (float)($it['unitario'] ?? 0);
        $sub  = (float)($it['subtotal'] ?? ($cant*$uni));
        $total_calc += $sub;

        // Truncar si es necesario (FPDF no hace wrap dentro de Cell)
        $nombreCorto = mb_strimwidth($nom, 0, 60, '...', 'UTF-8');

        $pdf->Cell(100,7, to_latin1($nombreCorto),1,0);
        $pdf->Cell(20,7, to_latin1(number_format($cant,2,',','.')),1,0,'C');
        $pdf->Cell(35,7, to_latin1(number_format($uni,2,',','.')),1,0,'R');
        $pdf->Cell(35,7, to_latin1(number_format($sub,2,',','.')),1,1,'R');
    }

    $pdf->Ln(1);

    // ===== Pagos y totales =====
    $y_inicio = $pdf->GetY();

    // Columna izquierda: métodos de pago
    $pdf->SetFont('Arial','',11);
    $pdf->MultiCell(110,6, to_latin1('Métodos de pago:'));
    $pdf->SetFont('Arial','',10);

    $lineas = [];
    if (isset($pagos['efectivo']))      $lineas[] = 'Efectivo: '.money_ars($pagos['efectivo']);
    if (isset($pagos['transferencia'])) $lineas[] = 'Transferencia: '.money_ars($pagos['transferencia']);
    if (isset($pagos['debito']))        $lineas[] = 'Débito: '.money_ars($pagos['debito']);
    if (isset($pagos['credito']))       $lineas[] = 'Crédito: '.money_ars($pagos['credito']);
    if (!empty($venta['cc']))           $lineas[] = 'Cuenta Corriente: '.money_ars($venta['cc']);
    if (!empty($pagos['vuelto']))       $lineas[] = 'Vuelto: '.money_ars($pagos['vuelto']);
    foreach ($lineas as $L) {
        $pdf->MultiCell(110,6, to_latin1($L));
    }

    // Columna derecha: totales
    $pdf->SetXY(130, $y_inicio);
    $pdf->SetFont('Arial','B',11);
    $pdf->Cell(40,7, to_latin1('Subtotal:'),0,0,'L');
    $pdf->SetFont('Arial','',11);
    $pdf->Cell(40,7, to_latin1(money_ars($total_calc)),0,1,'R');

    if (!empty($venta['descuento'])) {
        $pdf->SetFont('Arial','B',11);
        $pdf->Cell(40,7, to_latin1('Descuento:'),0,0,'L');
        $pdf->SetFont('Arial','',11);
        $pdf->Cell(40,7, to_latin1(number_format((float)$venta['descuento'],2,',','.').' %'),0,1,'R');
    }

    $total_final = (float)($venta['total'] ?? $total_calc);
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(40,8, to_latin1('TOTAL:'),0,0,'L');
    $pdf->Cell(40,8, to_latin1(money_ars($total_final)),0,1,'R');

    if (!empty($venta['cc'])) {
        $pdf->SetFont('Arial','',10);
        $pdf->SetTextColor(180,0,0);
        $pdf->Cell(0,6, to_latin1('Registrado a Cuenta Corriente: '.money_ars((float)$venta['cc'])),0,1,'L');
        $pdf->SetTextColor(0,0,0);
    }

    // ===== Guardar y Descargar =====
    // Asegurar carpeta destino
    $dir = dirname($rutaGuardar);
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }

    // Si ya hubo alguna salida (warnings, espacios), limpiamos buffer para evitar el fatal
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }

    // Content-Type por las dudas
    if (!headers_sent()) {
        header('Content-Type: application/pdf');
    }

    $pdf->Output('F', $rutaGuardar);      // guarda en servidor
    $pdf->Output('D', $nombreDescarga);   // descarga al navegador
}
