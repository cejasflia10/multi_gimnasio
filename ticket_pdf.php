<?php
/* ============================================================
   ticket_pdf.php — PDF de entrada con QR
   Uso: ticket_pdf.php?code=XXXXXXXXXXXX
   - Requiere: conexion.php, qr.php (genera PNG)
   - FPDF opcional: lib/fpdf186/fpdf.php o fpdf.php
   ============================================================ */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
if (function_exists('mysqli_report')) mysqli_report(MYSQLI_REPORT_OFF);
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function has_col(mysqli $db, string $t, string $c): bool {
  $t=$db->real_escape_string($t); $c=$db->real_escape_string($c);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}

/* ------- Entrada ------- */
$code = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
if ($code===''){ http_response_code(400); exit('Falta code'); }

/* ------- Traer ticket + pedido + tipo + evento ------- */
$sql = "SELECT
          t.id AS ticket_id, t.code, t.qr_path, t.tipo_id,
          p.id AS pedido_id, p.evento_id, p.estado AS pedido_estado,
          p.comprador_nombre, p.comprador_email, p.comprador_tel, p.created_at AS pedido_fecha,
          tt.nombre AS tipo_nombre, tt.precio,
          e.titulo AS evento_titulo, e.fecha AS evento_fecha, e.hora AS evento_hora, e.lugar AS evento_lugar,
          e.flyer
        FROM tickets t
        JOIN pedidos p      ON p.id = t.pedido_id
        LEFT JOIN tickets_tipos tt ON tt.id = t.tipo_id
        LEFT JOIN eventos_deportivos e ON e.id = p.evento_id
        WHERE t.code = ?
        LIMIT 1";
$st=$conexion->prepare($sql);
if(!$st){ http_response_code(500); exit('SQL error'); }
$st->bind_param('s',$code); $st->execute();
$info = $st->get_result()->fetch_assoc();
$st->close();

if (!$info) { http_response_code(404); exit('Ticket no encontrado'); }

/* ------- Restringir a aprobados/pagados ------- */
$estado = (string)($info['pedido_estado'] ?? '');
if (!in_array($estado, ['aprobado','pagado'], true)) {
  header('Content-Type: text/html; charset=utf-8');
  ?>
  <!doctype html><html lang="es"><head><meta charset="utf-8"><title>Ticket pendiente</title>
  <style>body{font-family:system-ui;-apple-system,Segoe UI,Roboto,sans-serif;background:#111;color:#eee;padding:20px} .card{max-width:680px;margin:auto;background:#1a1a1a;border:1px solid #333;border-radius:10px;padding:16px}</style></head>
  <body><div class="card">
    <h2>⏳ Este ticket aún no está disponible</h2>
    <p>El pedido #<?= (int)$info['pedido_id'] ?> se encuentra con estado <b><?= h($estado) ?></b>.</p>
    <p>Te enviaremos el PDF cuando el organizador confirme tu pago.</p>
    <p><small>Código: <code><?= h($code) ?></code></small></p>
  </div></body></html>
  <?php
  exit;
}

/* ------- Asegurar/generar QR PNG ------- */
$qrPngAbs = null;
$qrRel = (string)($info['qr_path'] ?? '');
if ($qrRel && is_file(__DIR__.'/'.$qrRel)) {
  $qrPngAbs = __DIR__.'/'.$qrRel;
} else {
  // Generar al vuelo en /tmp
  $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http')
           . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']),'/');
  $qrData = $baseUrl . '/mi_entrada.php?code=' . urlencode($code);
  $tmpPng = sys_get_temp_dir() . '/qr_' . preg_replace('/\W+/','',$code) . '.png';
  @file_get_contents($baseUrl.'/qr.php?text='.urlencode($qrData).'&save='.urlencode($tmpPng));
  if (is_file($tmpPng)) $qrPngAbs = $tmpPng;
}

/* ------- Intentar cargar FPDF ------- */
$fpdfLoaded = false;
if (is_file(__DIR__.'/lib/fpdf186/fpdf.php')) { require_once __DIR__.'/lib/fpdf186/fpdf.php'; $fpdfLoaded=true; }
elseif (is_file(__DIR__.'/fpdf.php'))       { require_once __DIR__.'/fpdf.php'; $fpdfLoaded=true; }

/* ------- Datos armados ------- */
$evTitulo = (string)($info['evento_titulo'] ?? 'Evento');
$evFecha  = (string)($info['evento_fecha'] ?? '');
$evHora   = (string)($info['evento_hora'] ?? '');
$evLugar  = (string)($info['evento_lugar'] ?? '');
$tipoNom  = (string)($info['tipo_nombre'] ?? 'Entrada');
$precio   = (float)($info['precio'] ?? 0);
$compNom  = (string)($info['comprador_nombre'] ?? '');
$compEmail= (string)($info['comprador_email'] ?? '');
$flyer    = (string)($info['flyer'] ?? '');
$ticketId = (int)$info['ticket_id'];
$pedidoId = (int)$info['pedido_id'];

/* ------- PDF (si está FPDF) ------- */
if ($fpdfLoaded) {
  class PDF extends FPDF {
    function Header(){ /* sin bordes */ }
    function Footer(){
      $this->SetY(-12);
      $this->SetFont('Arial','',8);
      $this->SetTextColor(130,130,130);
      $this->Cell(0,8,utf8_decode('Válido para un solo ingreso · '.date('d/m/Y H:i')),0,0,'R');
    }
  }

  // Tamaño recomendado: A6 horizontal (148 × 105 mm) → en puntos: 420 × 297 aprox (FPDF usa mm)
  $pdf = new PDF('L', 'mm', 'A6');
  $pdf->SetTitle(utf8_decode("Ticket ".$code));
  $pdf->AddPage();

  // Fondo
  $pdf->SetFillColor(17,17,17);
  $pdf->Rect(0,0,148,105,'F');

  // Barra superior
  $pdf->SetFillColor(34,34,34);
  $pdf->Rect(0,0,148,16,'F');

  // Título evento
  $pdf->SetTextColor(255,215,0);
  $pdf->SetFont('Arial','B',14);
  $pdf->SetXY(8,4);
  $pdf->Cell(120,8,utf8_decode($evTitulo),0,1,'L');

  // Info evento
  $pdf->SetTextColor(230,230,230);
  $pdf->SetFont('Arial','',10);
  $pdf->SetXY(8,20);
  $pdf->MultiCell(90,6,utf8_decode(
    "📅 ".($evFecha?date('d/m/Y', strtotime($evFecha)):'-').
    "  ⏰ ".($evHora?substr($evHora,0,5):'-').
    "\n📍 ".$evLugar
  ),0,'L');

  // Foto/flyer (opcional)
  if ($flyer && (preg_match('/^https?:\/\//i',$flyer) || is_file(__DIR__.'/'.$flyer))) {
    $flyerPath = $flyer;
    if (!preg_match('/^https?:\/\//i',$flyer) && is_file(__DIR__.'/'.$flyer)) $flyerPath = __DIR__.'/'.$flyer;
    // Columna izquierda: 8..100 de X, área 60×36 aprox
    @ $pdf->Image($flyerPath, 8, 44, 80, 0, '', '', true);
  }

  // Panel derecho para QR
  $pdf->SetFillColor(27,40,54);
  $pdf->Rect(100,18,44,44,'F');
  if ($qrPngAbs && is_file($qrPngAbs)) {
    // margen dentro del panel
    @ $pdf->Image($qrPngAbs, 104, 22, 36, 36, 'PNG');
  } else {
    $pdf->SetTextColor(255,255,255);
    $pdf->SetFont('Arial','B',11);
    $pdf->SetXY(100,38);
    $pdf->Cell(44,6,utf8_decode('QR no disponible'),0,0,'C');
  }

  // Datos de la entrada
  $pdf->SetXY(8,34);
  $pdf->SetFont('Arial','B',12);
  $pdf->SetTextColor(255,215,0);
  $pdf->Cell(90,6,utf8_decode("Tipo: ".$tipoNom),0,2,'L');
  $pdf->SetFont('Arial','',11);
  $pdf->SetTextColor(230,230,230);
  $pdf->Cell(90,6,utf8_decode("Precio: $".number_format($precio,2,',','.')),0,2,'L');
  $pdf->Cell(90,6,utf8_decode("Comprador: ".$compNom),0,2,'L');

  // Código grande
  $pdf->SetTextColor(255,255,255);
  $pdf->SetXY(100,66);
  $pdf->SetFont('Arial','B',13);
  $pdf->Cell(44,6,utf8_decode($code),0,2,'C');

  // IDs y disclaimer
  $pdf->SetFont('Arial','',9);
  $pdf->SetTextColor(180,180,180);
  $pdf->SetXY(8,86);
  $pdf->MultiCell(135,5,utf8_decode(
    "Pedido #{$pedidoId}  ·  Ticket #{$ticketId}\n".
    "Presentar en acceso. Prohibida su reproducción. Un solo ingreso por código."
  ),0,'L');

  // Descargar
  $fname = 'ticket_'.$code.'.pdf';
  $pdf->Output('I', $fname);
  exit;
}

/* ------- Fallback HTML si no hay FPDF ------- */
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Ticket <?= h($code) ?></title>
  <style>
    body{margin:0;background:#0b1115;color:#e6eef4;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
    .wrap{max-width:900px;margin:18px auto;padding:16px}
    .ticket{background:#0f1720;border:1px solid #1f2a33;border-radius:12px;padding:16px;display:grid;grid-template-columns:1fr 280px;gap:16px}
    .muted{color:#9ecbff}
    .pill{display:inline-block;padding:4px 8px;border-radius:999px;border:1px solid #3b4b5a;font-size:12px}
    .right{background:#13202e;border:1px solid #203243;border-radius:12px;padding:10px;display:flex;flex-direction:column;align-items:center;justify-content:center}
    .qr{background:#fff;border-radius:8px;padding:6px}
    img{max-width:100%}
    @media(max-width:760px){ .ticket{grid-template-columns:1fr} }
  </style>
</head>
<body>
  <div class="wrap">
    <h2 style="margin:0 0 10px">🎟️ Ticket — <?= h($evTitulo) ?></h2>
    <div class="ticket">
      <div>
        <div class="pill">Código: <?= h($code) ?></div>
        <h3 style="margin:10px 0 6px;color:#ffd700"><?= h($evTitulo) ?></h3>
        <div class="muted">📅 <?= $info['evento_fecha']?date('d/m/Y',strtotime($info['evento_fecha'])):'-' ?>
          &nbsp;·&nbsp; ⏰ <?= $info['evento_hora']?substr($info['evento_hora'],0,5):'-' ?>
          &nbsp;·&nbsp; 📍 <?= h($evLugar) ?>
        </div>
        <p style="margin-top:10px"><b>Tipo:</b> <?= h($tipoNom) ?> &nbsp; — &nbsp; <b>Precio:</b> $<?= number_format($precio,2,',','.') ?></p>
        <p><b>Comprador:</b> <?= h($compNom) ?> <br><span class="muted"><?= h($compEmail) ?></span></p>
        <?php if (!empty($flyer) && (preg_match('/^https?:\/\//i',$flyer) || is_file(__DIR__.'/'.$flyer))): ?>
          <div style="margin-top:8px">
            <img src="<?= h(preg_match('/^https?:\/\//i',$flyer)?$flyer:($flyer)) ?>" alt="Flyer">
          </div>
        <?php endif; ?>
        <p class="muted" style="margin-top:12px">Pedido #<?= (int)$pedidoId ?> · Ticket #<?= (int)$ticketId ?><br>
        Válido para un solo ingreso. Prohibida su reproducción.</p>
        <div style="margin-top:10px">
          <a href="?code=<?= urlencode($code) ?>" class="pill">Reintentar en PDF (instalá FPDF)</a>
        </div>
      </div>
      <div class="right">
        <div class="qr">
          <?php
            $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http')
                     . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']),'/');
            $qrData = $baseUrl . '/mi_entrada.php?code=' . urlencode($code);
          ?>
          <img src="qr.php?text=<?= urlencode($qrData) ?>" alt="QR" width="240" height="240">
        </div>
        <div style="margin-top:8px" class="muted">Mostrá este QR en el acceso</div>
      </div>
    </div>
  </div>
</body>
</html>
