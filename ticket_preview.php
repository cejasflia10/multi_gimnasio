<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
if (!isset($conexion)||!($conexion instanceof mysqli)) { http_response_code(500); exit('Sin BD'); }
if (function_exists('mysqli_report')) mysqli_report(MYSQLI_REPORT_OFF);
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }

$code = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
$ticket_id = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;
if($code===''){
  if($ticket_id>0){
    $st=$conexion->prepare("SELECT code FROM tickets WHERE id=? LIMIT 1");
    $st->bind_param('i',$ticket_id); $st->execute(); $r=$st->get_result()->fetch_assoc(); $st->close();
    $code = $r['code'] ?? '';
  }
}
if($code===''){ http_response_code(400); exit('Falta code'); }

$sql="SELECT t.id,t.code,t.evento_id,t.tipo_id,t.used_at,
             tt.nombre AS tipo_nombre,
             p.comprador_nombre,p.comprador_email,
             e.titulo,e.fecha,e.hora,e.lugar,e.flyer
      FROM tickets t
      LEFT JOIN tickets_tipos tt ON tt.id=t.tipo_id
      LEFT JOIN pedidos p ON p.id=t.pedido_id
      LEFT JOIN eventos_deportivos e ON e.id=t.evento_id
      WHERE t.code=? LIMIT 1";
$st=$conexion->prepare($sql);
$st->bind_param('s',$code); $st->execute();
$tk=$st->get_result()->fetch_assoc(); $st->close();
if(!$tk){ http_response_code(404); exit('Ticket no encontrado.'); }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Entrada — <?= h($tk['titulo']??'Evento') ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>
<style>
  :root{--bg:#0b1115;--card:#0f1720;--bd:#1f2a33;--tx:#e6eef4;--mut:#9ecbff;}
  body{margin:0;background:var(--bg);color:var(--tx);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial}
  .wrap{max-width:800px;margin:20px auto;padding:14px}
  .ticket{background:#101821;border:1px solid #1d2a35;border-radius:14px;padding:16px}
  .top{display:flex;gap:16px;align-items:center}
  .flyer{width:160px;height:160px;object-fit:cover;border-radius:12px;border:1px solid #263341;background:#000}
  .title{font-size:1.4rem;margin:0} .mut{color:#9ecbff}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:10px}
  .card{border:1px dashed #2a3a4a;border-radius:10px;padding:12px}
  .qr{width:220px;height:220px;margin:auto}
  .code{font-family:ui-monospace,Consolas,Menlo,monospace;font-size:1.1rem}
  .btn{display:inline-block;margin-top:12px;padding:10px 14px;border-radius:10px;background:#0e7ad1;color:#fff;text-decoration:none;border:1px solid #27455c}
  @media print {.btn{display:none} body{background:#fff;color:#000} .ticket{border:0}}
</style>
</head>
<body>
<div class="wrap">
  <div class="ticket">
    <div class="top">
      <?php if(!empty($tk['flyer'])): ?>
        <img class="flyer" src="<?= h($tk['flyer']) ?>" alt="Flyer">
      <?php else: ?>
        <div class="flyer" style="display:flex;align-items:center;justify-content:center">EVENTO</div>
      <?php endif; ?>
      <div>
        <h2 class="title"><?= h($tk['titulo']??'Evento') ?></h2>
        <div class="mut">
          📅 <?= h($tk['fecha']??'') ?> &nbsp;|&nbsp; ⏰ <?= h($tk['hora']??'') ?> &nbsp;|&nbsp; 📍 <?= h($tk['lugar']??'') ?>
        </div>
        <div style="margin-top:6px">Tipo: <strong><?= h($tk['tipo_nombre']??'-') ?></strong></div>
        <div>Comprador: <strong><?= h($tk['comprador_nombre']??'-') ?></strong> <span class="mut">(<?= h($tk['comprador_email']??'') ?>)</span></div>
      </div>
    </div>

    <div class="grid">
      <div class="card">
        <div id="qrcode" class="qr"></div>
      </div>
      <div class="card">
        <div>Estado de uso: <?= !empty($tk['used_at']) ? '<b>USADA</b> ('.$tk['used_at'].')' : '<b>NO USADA</b>' ?></div>
        <div style="margin-top:8px">Código de validación</div>
        <div class="code"><?= h($tk['code']) ?></div>
        <div class="mut" style="margin-top:6px">Presentar en el acceso. Un (1) ingreso por ticket.</div>
        <a class="btn" href="#" onclick="window.print();return false;">🖨️ Imprimir / Guardar PDF</a>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  // El contenido del QR puede ser solo el "code".
  // Si preferís una URL, podrías usar: const content = location.origin + '/mi_entrada.php?code=<?= rawurlencode($tk['code']) ?>';
  const content = "<?= addslashes($tk['code']) ?>";
  new QRCode(document.getElementById("qrcode"), {
    text: content,
    width: 220,
    height: 220,
    correctLevel: QRCode.CorrectLevel.M
  });
});
</script>
</body>
</html>
