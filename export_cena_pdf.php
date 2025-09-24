<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD'); }
@$conexion->set_charset('utf8mb4');

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($gimnasio_id<=0) { http_response_code(403); exit('No autorizado'); }

$evento_id = (int)($_GET['evento_id'] ?? 0);
if ($evento_id<=0) { http_response_code(400); exit('Evento inválido'); }

/* Datos evento */
$s=$conexion->prepare("SELECT titulo,fecha,hora,lugar FROM cenas_eventos WHERE id=? AND gimnasio_id=? LIMIT 1");
if (!$s) { die('❌ SQL evento: '.$conexion->error); }
$s->bind_param('ii',$evento_id,$gimnasio_id);
$s->execute();
$ev=$s->get_result()->fetch_assoc();
$s->close();
if(!$ev){ http_response_code(404); exit('Evento no encontrado'); }

/* Reservas */
$sql = "SELECT r.id,
               CASE
                 WHEN TRIM(CONCAT(COALESCE(c.nombre,''),' ',COALESCE(c.apellido,''))) <> ''
                   THEN TRIM(CONCAT(COALESCE(c.nombre,''),' ',COALESCE(c.apellido,'')))
                 ELSE CONCAT('Cliente #', c.id)
               END AS cliente,
               r.cantidad, r.total, r.estado_pago, r.asistio, r.creado_en
        FROM cenas_reservas r
        JOIN clientes c ON c.id=r.cliente_id
        WHERE r.gimnasio_id=? AND r.evento_id=?
        ORDER BY r.creado_en DESC";
$s=$conexion->prepare($sql);
if (!$s) { die('❌ SQL reservas: '.$conexion->error); }
$s->bind_param('ii',$gimnasio_id,$evento_id);
$s->execute();
$rows=$s->get_result()->fetch_all(MYSQLI_ASSOC);
$s->close();

$fpdf_path = __DIR__.'/fpdf/fpdf.php';
if (is_file($fpdf_path)) {
  require_once $fpdf_path;
  class PDF extends FPDF{
    function Header(){
      global $ev;
      $this->SetFont('Arial','B',14);
      $this->Cell(0,8,utf8_decode('Listado de Reservas — '.$ev['titulo']),0,1,'L');
      $this->SetFont('Arial','',10);
      $info = 'Fecha: '.date('d/m/Y', strtotime($ev['fecha'])).'  Hora: '.substr($ev['hora'],0,5).'  Lugar: '.$ev['lugar'];
      $this->Cell(0,6,utf8_decode($info),0,1,'L');
      $this->Ln(2);
      $this->SetFont('Arial','B',9);
      $this->Cell(12,7,'#',1,0,'C');
      $this->Cell(60,7,utf8_decode('Cliente'),1,0,'L');
      $this->Cell(18,7,'Cant.',1,0,'C');
      $this->Cell(28,7,'Total',1,0,'R');
      $this->Cell(25,7,'Pago',1,0,'C');
      $this->Cell(20,7,'Asistio',1,0,'C');
      $this->Cell(27,7,'Creado',1,1,'C');
    }
    function Footer(){
      $this->SetY(-15);
      $this->SetFont('Arial','I',8);
      $this->Cell(0,10,utf8_decode('Generado '.date('d/m/Y H:i')),0,0,'R');
    }
  }
  $pdf=new PDF('P','mm','A4');
  $pdf->SetMargins(10,10,10);
  $pdf->AddPage();
  $pdf->SetFont('Arial','',9);
  $sum_cant=0; $sum_total=0;
  foreach($rows as $r){
    $pdf->Cell(12,6,$r['id'],1,0,'C');
    $pdf->Cell(60,6,utf8_decode(substr($r['cliente'],0,38)),1,0,'L');
    $pdf->Cell(18,6,$r['cantidad'],1,0,'C');
    $pdf->Cell(28,6,number_format($r['total'],2,',','.'),1,0,'R');
    $pdf->Cell(25,6,strtoupper($r['estado_pago']),1,0,'C');
    $pdf->Cell(20,6,$r['asistio']?'SI':'NO',1,0,'C');
    $pdf->Cell(27,6,date('d/m H:i', strtotime($r['creado_en'])),1,1,'C');
    $sum_cant += (int)$r['cantidad'];
    $sum_total+= (float)$r['total'];
  }
  $pdf->SetFont('Arial','B',9);
  $pdf->Cell(72,7,'TOTAL',1,0,'R');
  $pdf->Cell(18,7,$sum_cant,1,0,'C');
  $pdf->Cell(28,7,number_format($sum_total,2,',','.'),1,0,'R');
  $pdf->Cell(72,7,'',1,1,'R');
  $pdf->Output('I','cena_reservas.pdf');
  exit;
} else {
  // Fallback imprimible
  ?><!doctype html><html><head><meta charset="utf-8"><title>Listado (imprimible)</title>
  <style>
    body{font-family:system-ui,Arial;margin:20px}
    table{width:100%;border-collapse:collapse}
    th,td{border:1px solid #ccc;padding:6px}
    th{background:#eee}
  </style></head><body>
  <h2>Listado de Reservas — <?=htmlspecialchars($ev['titulo'])?></h2>
  <p><strong>Fecha:</strong> <?=date('d/m/Y', strtotime($ev['fecha']))?>
     <strong>Hora:</strong> <?=substr($ev['hora'],0,5)?>
     <strong>Lugar:</strong> <?=htmlspecialchars($ev['lugar'])?></p>
  <table>
    <thead><tr><th>#</th><th>Cliente</th><th>Cant.</th><th>Total</th><th>Pago</th><th>Asistió</th><th>Creado</th></tr></thead>
    <tbody>
      <?php $sum_cant=0;$sum_total=0;
      foreach($rows as $r){ $sum_cant+=$r['cantidad']; $sum_total+=$r['total']; ?>
        <tr>
          <td><?=$r['id']?></td>
          <td><?=htmlspecialchars($r['cliente'])?></td>
          <td><?=$r['cantidad']?></td>
          <td>$<?=number_format($r['total'],2,',','.')?></td>
          <td><?=strtoupper($r['estado_pago'])?></td>
          <td><?=$r['asistio']?'SI':'NO'?></td>
          <td><?=date('d/m/Y H:i', strtotime($r['creado_en']))?></td>
        </tr>
      <?php } ?>
    </tbody>
    <tfoot>
      <tr><th colspan="2" style="text-align:right">TOTAL</th><th><?=$sum_cant?></th><th>$<?=number_format($sum_total,2,',','.')?></th><th colspan="3"></th></tr>
    </tfoot>
  </table>
  <script>window.print()</script>
  </body></html><?php
  exit;
}
