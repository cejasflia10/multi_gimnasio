<?php
// qr_evento.php — PDF al vuelo con QR por número de venta (pedido)
require_once __DIR__.'/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('Sin BD'); }
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ⚠️ Requiere TCPDF (puro PHP). Colocá tcpdf.php en vendor/tcpdf/tcpdf.php
$tcpdf = __DIR__.'/vendor/tcpdf/tcpdf.php';
if (!is_file($tcpdf)) {
  header('Content-Type: text/plain; charset=utf-8', true, 500);
  exit("Falta TCPDF. Colocá vendor/tcpdf/tcpdf.php o instalá via Composer (te puedo pasar vendor).");
}
require_once $tcpdf;

$pedido_id = isset($_GET['pedido_id']) && ctype_digit($_GET['pedido_id']) ? (int)$_GET['pedido_id'] : 0;
if ($pedido_id <= 0) { http_response_code(400); exit('Pedido inválido'); }

// Traemos pedido + evento, incluído el token QR
$st=$conexion->prepare("SELECT p.*, e.nombre AS evento, e.fecha, e.hora, e.lugar, e.ciudad
                        FROM pedidos p
                        JOIN eventos_publicos e ON e.id=p.evento_id
                        WHERE p.id=? LIMIT 1");
$st->bind_param('i',$pedido_id);
$st->execute();
$ped = $st->get_result()->fetch_assoc();
$st->close();
if (!$ped) { http_response_code(404); exit('No existe el pedido'); }

if ($ped['qr_token'] === null || $ped['qr_token'] === '') {
  // Si por algún motivo no tiene token, lo generamos ahora (no se guarda nada en disco)
  $token = bin2hex(random_bytes(20));
  $up=$conexion->prepare("UPDATE pedidos SET qr_token=? WHERE id=?");
  $up->bind_param('si',$token,$pedido_id); $up->execute(); $up->close();
  $ped['qr_token'] = $token;
}

// Datos
$venta_num   = sprintf('PED-%06d', (int)$ped['id']);
$evento_nom  = (string)$ped['evento'];
$fecha_hora  = date('d/m/Y', strtotime($ped['fecha'])) . ($ped['hora'] ? ' · '.substr($ped['hora'],0,5) : '');
$lugar       = trim(($ped['lugar'] ?? '').(($ped['ciudad']??'') ? ' · '.$ped['ciudad'] : ''));
$comprador   = (string)$ped['comprador_nombre'];
$token       = (string)$ped['qr_token'];

// URL que se escanea (valida y marca "usado" si corresponde)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https' : 'http';
$base   = $scheme.'://'.$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['SCRIPT_NAME']),'/');
$validateURL = $base.'/validar_qr_evento.php?token='.$token;

// ===== PDF TCPDF =====
$pdf = new TCPDF('P','mm','A4', true, 'UTF-8', false);
$pdf->SetCreator('Entradas');
$pdf->SetAuthor('Sistema de Entradas');
$pdf->SetTitle('Entrada '.$venta_num);
$pdf->SetMargins(12, 12, 12, true);
$pdf->SetAutoPageBreak(true, 12);
$pdf->AddPage();

// Título / datos
$html = '
  <h1 style="font-family:sans-serif; font-weight:700; margin:0 0 6px 0;">'.$pdf->escapeHTML($evento_nom).'</h1>
  <div style="font-size:12pt; color:#333; margin-bottom:8px;">'.$pdf->escapeHTML($fecha_hora).'</div>
  <div style="font-size:12pt; color:#333; margin-bottom:10px;">'.$pdf->escapeHTML($lugar).'</div>
  <table cellpadding="6" cellspacing="0" border="0" style="font-size:12pt">
    <tr>
      <td><b>Nro. de venta:</b> '.$pdf->escapeHTML($venta_num).'</td>
      <td><b>Comprador:</b> '.$pdf->escapeHTML($comprador).'</td>
    </tr>
    <tr>
      <td colspan="2"><b>Estado:</b> '.($ped['qr_status']==='usado' ? 'USADO' : 'ACTIVO').'</td>
    </tr>
  </table>
  <div style="height:6mm"></div>
';
$pdf->writeHTML($html, true, false, true, false, '');

// QR grande (80x80 mm). TCPDF dibuja el QR directo, no se guarda archivo.
$style = [
  'border' => 0,
  'padding' => 2,
  'fgcolor' => [0,0,0],
  'bgcolor' => false
];
$pdf->write2DBarcode($validateURL, 'QRCODE,M', 12, 90, 80, 80, $style, 'N');

// Caja con instrucciones y el link (como texto)
$pdf->SetXY(100, 90);
$pdf->SetFont('helvetica','',12);
$pdf->MultiCell(98, 10, "Mostrá este QR en la entrada.\nEste QR es válido para una sola validación.\n\nSi tienen problemas:\n".$validateURL, 1, 'L', false, 1, '', '', true);

// Pie
$pdf->SetY(-25);
$pdf->SetFont('helvetica','I',8);
$pdf->Cell(0, 10, 'Entrada generada por el sistema - '.$venta_num, 0, 0, 'C');

// Descargar
$filename = 'Entrada_'.$venta_num.'.pdf';
$pdf->Output($filename, 'D'); // 'D' fuerza descarga
