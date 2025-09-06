<?php
if (session_status() === PHP_SESSION_NONE) session_start();

/* -------- Conexión -------- */
require_once __DIR__ . '/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500);
  exit('❌ No hay conexión a la base de datos.');
}
if (function_exists('mysqli_report')) mysqli_report(MYSQLI_REPORT_OFF);
@$conexion->set_charset('utf8mb4');

/* -------- Helpers -------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function has_table(mysqli $db, string $t): bool {
  $t = $db->real_escape_string($t);
  $sql = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function has_col(mysqli $db, string $t, string $c): bool {
  $t=$db->real_escape_string($t); $c=$db->real_escape_string($c);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
/* Devuelve "alias.`col`" si existe, o "NULL" si no. */
function pick_col_alias(mysqli $db, string $table, string $alias, array $cands, string $fallback='NULL'): string {
  foreach ($cands as $c) { if (has_col($db,$table,$c)) return "{$alias}.`{$c}`"; }
  return $fallback;
}
function base_url(): string {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $path   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
  return $scheme.'://'.$host.$path;
}
function ensure_dir($path){ if (!is_dir($path)) @mkdir($path,0775,true); }

/* -------- Input -------- */
$code = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
if ($code === '') { http_response_code(400); exit('⚠️ Falta el parámetro code.'); }

/* -------- Detectar tabla de eventos y columnas (alias e) -------- */
$eventTable = null;
if (has_table($conexion, 'eventos_deportivos'))      $eventTable = 'eventos_deportivos';
elseif (has_table($conexion, 'eventos_publicos'))    $eventTable = 'eventos_publicos';

/* Branding del evento: intentamos encontrar columnas habituales */
$selEvTitulo = 'NULL';
$selEvFecha  = 'NULL';
$selEvHora   = 'NULL';
$selEvLugar  = 'NULL';
$selEvLogo   = 'NULL';
$selEvFlyer  = 'NULL';

if ($eventTable) {
  $selEvTitulo = pick_col_alias($conexion, $eventTable, 'e', ['titulo','title','nombre','name'], 'NULL');
  $selEvFecha  = pick_col_alias($conexion, $eventTable, 'e', ['fecha','date','dia','day'], 'NULL');
  $selEvHora   = pick_col_alias($conexion, $eventTable, 'e', ['hora','time'], 'NULL');
  $selEvLugar  = pick_col_alias($conexion, $eventTable, 'e', ['lugar','ubicacion','direccion','venue','site'], 'NULL');
  $selEvLogo   = pick_col_alias($conexion, $eventTable, 'e', ['logo','isologo','logotipo'], 'NULL');
  $selEvFlyer  = pick_col_alias($conexion, $eventTable, 'e', ['flyer','banner','imagen','cover','poster'], 'NULL');
}

/* -------- Buscar ticket + pedido + evento -------- */
$sql = "SELECT
          t.id, t.code, t.qr_path, t.tipo_id,
          tt.nombre AS tipo_nombre,
          p.id AS pedido_id, p.estado AS pedido_estado, p.comprador_nombre, p.comprador_email,".
        (
          $eventTable
          ? " {$selEvTitulo} AS evento_titulo,
              {$selEvFecha}  AS evento_fecha,
              {$selEvHora}   AS evento_hora,
              {$selEvLugar}  AS evento_lugar,
              {$selEvLogo}   AS evento_logo,
              {$selEvFlyer}  AS evento_flyer
              FROM tickets t
              LEFT JOIN pedidos p      ON p.id = t.pedido_id
              LEFT JOIN tickets_tipos tt ON tt.id = t.tipo_id
              LEFT JOIN `{$eventTable}` e ON e.id = t.evento_id
              WHERE t.code = ?
              LIMIT 1"
          : " NULL AS evento_titulo, NULL AS evento_fecha, NULL AS evento_hora, NULL AS evento_lugar,
              NULL AS evento_logo, NULL AS evento_flyer
              FROM tickets t
              LEFT JOIN pedidos p      ON p.id = t.pedido_id
              LEFT JOIN tickets_tipos tt ON tt.id = t.tipo_id
              WHERE t.code = ?
              LIMIT 1"
        );

$st = $conexion->prepare($sql);
if (!$st) {
  http_response_code(500);
  echo "<div style='margin:16px;padding:12px;border:1px solid #600;border-radius:8px;background:#300;color:#fbb'>";
  echo "<div><strong>SQL prepare error:</strong> ".h($conexion->error)."</div>";
  echo "<div><small><code>".h($sql)."</code></small></div>";
  echo "</div>";
  exit;
}
$st->bind_param('s',$code);
$st->execute();
$tk = $st->get_result()->fetch_assoc();
$st->close();

if (!$tk) {
  http_response_code(404);
  exit('⚠️ Entrada no encontrada.');
}

/* -------- Permitir ver solo si aprobado/pagado -------- */
$estado = (string)($tk['pedido_estado'] ?? '');
if (!in_array($estado, ['aprobado','pagado'], true)) {
  // Mostrar un mensaje amable pero no entregar PDF final
  ?>
  <!DOCTYPE html>
  <html lang="es"><head><meta charset="utf-8"><title>Entrada pendiente</title>
  <style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial;background:#0b1115;color:#e6eef4;padding:24px}
  .card{background:#0f1720;border:1px solid #1f2a33;border-radius:12px;padding:16px;max-width:720px;margin:30px auto}
  .pill{display:inline-block;padding:4px 8px;border-radius:999px;border:1px solid #3b4b5a;font-size:12px}
  .muted{color:#9ecbff}</style></head><body>
  <div class="card">
    <h2 style="margin:0 0 6px">🎟️ Entrada #<?= (int)$tk['id'] ?> — <code><?= h($tk['code']) ?></code></h2>
    <p>Pedido <strong>#<?= (int)$tk['pedido_id'] ?></strong> en estado <span class="pill"><?= h(strtoupper($estado)) ?></span>.</p>
    <p class="muted">Aún no está aprobada por el organizador. Cuando impacte el pago, vas a poder descargar la entrada con QR.</p>
  </div>
  </body></html>
  <?php
  exit;
}

/* -------- Preparar datos visuales -------- */
$evento_titulo = $tk['evento_titulo'] ?? 'Evento';
$evento_fecha  = $tk['evento_fecha']  ?? '';
$evento_hora   = $tk['evento_hora']   ?? '';
$evento_lugar  = $tk['evento_lugar']  ?? '';
$tipo_nombre   = $tk['tipo_nombre']   ?? 'Entrada';
$comprador     = $tk['comprador_nombre'] ?? '';
$code_h        = $tk['code'];
$qr_path       = $tk['qr_path'] ?? '';

/* URL que embebe el QR (puede ser un verificador) */
$qrData = base_url().'/mi_entrada.php?code='.urlencode($code_h);

/* Si no hay qr_path, intentamos generarlo en el disco para PDF/HTML */
if (empty($qr_path)) {
  $baseDir = __DIR__.'/tickets_qr/evento_tmp';
  ensure_dir($baseDir);
  $tmpPng = $baseDir.'/'.$code_h.'.png';
  // Llamamos a qr.php para guardar
  $qrSave = 'tickets_qr/evento_tmp/'.$code_h.'.png';
  @file_get_contents(base_url().'/qr.php?text='.urlencode($qrData).'&save='.urlencode($qrSave));
  if (is_file($tmpPng)) {
    $qr_path = 'tickets_qr/evento_tmp/'.$code_h.'.png';
  }
}

/* Intentar PDF con FPDF si existe; si no, versión HTML imprimible */
if (class_exists('FPDF')) {
  require_once __DIR__.'/fpdf.php'; // si tu FPDF no se carga aut., asegurá este include

  class TicketPDF extends FPDF {
    function Header(){}
    function Footer(){}
  }

  $pdf = new TicketPDF('P','mm','A4');
  $pdf->AddPage();
  $pdf->SetAutoPageBreak(true, 15);

  // Fondo suave
  $pdf->SetFillColor(245,245,245);
  $pdf->Rect(10, 10, 190, 277, 'F');

  // Logo / Flyer si existen físicamente
  $y = 14;
  $logo = $tk['evento_logo'] ?? '';
  $flyer = $tk['evento_flyer'] ?? '';
  if (!empty($logo) && is_file(__DIR__.'/'.$logo)) {
    $pdf->Image(__DIR__.'/'.$logo, 14, $y, 30);
  }
  if (!empty($flyer) && is_file(__DIR__.'/'.$flyer)) {
    $pdf->Image(__DIR__.'/'.$flyer, 50, $y, 150);
    $y += 80;
  } else {
    $y += 20;
  }

  // Título
  $pdf->SetXY(14, $y); $pdf->SetFont('Arial','B',18);
  $pdf->Cell(0, 10, mb_convert_encoding($evento_titulo,'ISO-8859-1','UTF-8'), 0, 1, 'L');
  $pdf->SetFont('Arial','',12);
  $pdf->SetX(14);
  $info = trim(($evento_fecha?:'').'  '.($evento_hora?:'').'  '.($evento_lugar?:''));
  $pdf->Cell(0, 7, mb_convert_encoding($info,'ISO-8859-1','UTF-8'), 0, 1, 'L');

  // Caja de datos
  $y += 8;
  $pdf->SetXY(12, $y);
  $pdf->SetFillColor(255,255,255);
  $pdf->SetDrawColor(200,200,200);
  $pdf->Rect(12, $y, 186, 80, 'DF');

  $pdf->SetXY(18, $y+8);
  $pdf->SetFont('Arial','B',14);
  $pdf->Cell(120, 8, mb_convert_encoding("Entrada: ".$tipo_nombre,'ISO-8859-1','UTF-8'), 0, 1, 'L');

  $pdf->SetFont('Arial','',12);
  $pdf->SetX(18);
  $pdf->Cell(120, 7, mb_convert_encoding("Titular: ".$comprador,'ISO-8859-1','UTF-8'), 0, 1, 'L');

  $pdf->SetX(18);
  $pdf->Cell(120, 7, mb_convert_encoding("Código: ".$code_h,'ISO-8859-1','UTF-8'), 0, 1, 'L');

  // QR a la derecha
  if (!empty($qr_path) && is_file(__DIR__.'/'.$qr_path)) {
    $pdf->Image(__DIR__.'/'.$qr_path, 150, $y+8, 45, 45);
  } else {
    // Generamos on the fly a la salida del navegador
    // Como fallback, podemos incrustar una imagen remota (menos ideal para PDF)
    // Lo omitimos si no hay archivo local.
  }

  // Nota
  $pdf->SetXY(18, $y+62);
  $pdf->SetFont('Arial','',10);
  $nota = "Presentar en el acceso. El QR es válido por única vez. No compartir capturas.";
  $pdf->MultiCell(120, 5, mb_convert_encoding($nota,'ISO-8859-1','UTF-8'), 0, 'L');

  $pdf->Output('I', 'Entrada_'.$code_h.'.pdf');
  exit;
}

/* ---------- HTML imprimible si no hay FPDF ---------- */
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Entrada — <?= h($evento_titulo) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{ --bg:#0b1115; --tx:#111; --card:#fff; --bd:#ddd; }
    @media print {
      .noprint { display:none !important; }
      body { background:#fff; }
    }
    body{ background:var(--bg); margin:0; font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial; }
    .wrap{ max-width:900px; margin:20px auto; padding:16px; }
    .card{ background:var(--card); border:1px solid var(--bd); border-radius:12px; padding:16px; color:var(--tx); }
    .top{ display:flex; gap:12px; align-items:center; }
    .top img.logo{ max-height:70px; }
    .fly{ width:100%; border-radius:10px; border:1px solid #eee; }
    .grid{ display:grid; grid-template-columns: 1fr 220px; gap:14px; }
    .qr{ width:100%; background:#fff; border:1px solid #eee; border-radius:10px; padding:8px; }
    .muted{ color:#666; }
    .btn{ display:inline-block; padding:10px 14px; background:#0e7ad1; color:#fff; text-decoration:none; border-radius:10px; border:0; cursor:pointer; }
    h1{ margin:.2rem 0 .4rem; font-size:24px; }
  </style>
</head>
<body>
  <div class="wrap noprint">
    <button class="btn" onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>
  </div>

  <div class="wrap">
    <div class="card">
      <div class="top">
        <?php if (!empty($tk['evento_logo']) && is_file(__DIR__.'/'.$tk['evento_logo'])): ?>
          <img class="logo" src="<?= h($tk['evento_logo']) ?>" alt="Logo">
        <?php endif; ?>
        <div>
          <h1><?= h($evento_titulo) ?></h1>
          <div class="muted"><?= h($evento_fecha) ?> · <?= h($evento_hora) ?> — <?= h($evento_lugar) ?></div>
        </div>
      </div>

      <?php if (!empty($tk['evento_flyer']) && is_file(__DIR__.'/'.$tk['evento_flyer'])): ?>
        <div style="margin-top:10px">
          <img class="fly" src="<?= h($tk['evento_flyer']) ?>" alt="Flyer">
        </div>
      <?php endif; ?>

      <div class="grid" style="margin-top:14px">
        <div>
          <p><strong>Tipo:</strong> <?= h($tipo_nombre) ?></p>
          <p><strong>Titular:</strong> <?= h($comprador) ?></p>
          <p><strong>Código:</strong> <code><?= h($code_h) ?></code></p>
          <p class="muted">El QR es válido por única vez. No compartir capturas. El organizador puede solicitar DNI.</p>
        </div>
        <div>
          <div class="qr">
            <?php if (!empty($qr_path) && is_file(__DIR__.'/'.$qr_path)): ?>
              <img src="<?= h($qr_path) ?>" alt="QR" style="width:100%; border-radius:6px;">
            <?php else: ?>
              <!-- Fallback: QR on-the-fly (no se guarda en disco) -->
              <img src="qr.php?text=<?= urlencode($qrData) ?>" alt="QR" style="width:100%; border-radius:6px;">
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="muted" style="margin-top:10px">Pedido #<?= (int)$tk['pedido_id'] ?> — Entrada #<?= (int)$tk['id'] ?> · <?= h($tipo_nombre) ?></div>
    </div>
  </div>
</body>
</html>
