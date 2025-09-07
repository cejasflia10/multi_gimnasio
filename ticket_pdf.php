<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('Sin BD'); }
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }

$code = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
if ($code===''){ http_response_code(400); exit('Falta code'); }

$sql = "SELECT
          t.id AS ticket_id, t.code, t.used_at,
          p.id AS pedido_id, p.evento_id, p.estado, p.comprador_nombre, p.comprador_email, p.created_at,
          e.titulo, e.fecha, e.hora, e.lugar, e.flyer,
          tt.nombre AS tipo_nombre, tt.precio
        FROM tickets t
        JOIN pedidos p ON p.id=t.pedido_id
        JOIN eventos_deportivos e ON e.id=p.evento_id
        LEFT JOIN tickets_tipos tt ON tt.id=t.tipo_id
        WHERE t.code = ?
        LIMIT 1";
$st=$conexion->prepare($sql);
$st->bind_param('s',$code);
$st->execute();
$row=$st->get_result()->fetch_assoc();
$st->close();

if (!$row){ http_response_code(404); exit('Ticket no encontrado'); }

$estado = strtolower((string)$row['estado']);
$aprobado = in_array($estado, ['aprobado','pagado'], true);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Ticket #<?= (int)$row['ticket_id'] ?> — <?= h($row['titulo']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{ --bg:#0b1115; --fg:#e6eef4; --mut:#9ecbff; --brand:#d4af37; --bd:#1f2a33; --line:#222; }
  html,body{margin:0;background:#111;color:var(--fg);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
  .wrap{max-width:760px;margin:18px auto;padding:16px}
  .ticket{background:#0f1720;border:1px solid var(--bd);border-radius:12px;padding:16px}
  .head{display:flex;gap:14px;align-items:flex-start}
  .flyer{width:240px;max-width:40%;height:auto;border-radius:10px;border:1px solid var(--line);background:#000}
  .info{flex:1}
  .muted{color:var(--mut)}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  @media(max-width:680px){
    .head{flex-direction:column}
    .flyer{max-width:100%;width:100%}
    .grid{grid-template-columns:1fr}
  }
  .btn{display:inline-block;padding:10px 14px;border-radius:10px;border:1px solid #27455c;background:#0e7ad1;color:#fff;text-decoration:none}
  .note{margin-top:10px;font-size:.95rem}

  /* QR */
  .qr{display:flex;justify-content:center;align-items:center;margin-top:12px}
  .qr-wrap{position:relative;width:260px}
  .qr-wrap img{display:block;width:100%;height:100%;image-rendering:pixelated;border-radius:12px;border:1px solid #1d2a36;background:#fff}
  .qr-fallback{display:none}
  .qr-watermark{
    position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
    font-weight:900; font-size:22px; text-align:center; letter-spacing:1px;
    color:#fff; background:rgba(180,0,0,.28); mix-blend-mode:multiply; transform:rotate(-18deg);
    border-radius:12px;
  }
  @media print{
    body{background:#fff;color:#000}
    .ticket{border:0}
    .no-print{display:none!important}
    .qr-watermark{ background:rgba(180,0,0,.35); color:#900 }
  }

  .pill{display:inline-block;padding:.25rem .6rem;border:1px solid #3b4b5a;border-radius:999px}
  .warn{background:#2a1414;border:1px solid #5e2626;color:#ffb4b4;padding:10px;border-radius:10px;margin-top:8px}
</style>
</head>
<body>
<div class="wrap">
  <div class="ticket">
    <div class="head">
      <?php if(!empty($row['flyer'])): ?>
        <img class="flyer" src="<?= h($row['flyer']) ?>" alt="Flyer">
      <?php endif; ?>
      <div class="info">
        <h2 style="margin:0 0 6px"><?= h($row['titulo']) ?></h2>
        <div class="muted">📅 <?= h($row['fecha']) ?> · ⏰ <?= h(substr((string)$row['hora'],0,5)) ?> · 📍 <?= h($row['lugar']) ?></div>
        <p style="margin:.6rem 0 0"><b>Tipo:</b> <?= h($row['tipo_nombre'] ?? '—') ?> — <b>Ticket #</b><?= (int)$row['ticket_id'] ?></p>
        <p style="margin:.2rem 0 0"><b>Código:</b> <code><?= h($row['code']) ?></code></p>
        <p class="muted" style="margin:.2rem 0 0"><small>Pedido #<?= (int)$row['pedido_id'] ?> — <?= h((string)$row['estado']) ?> — <?= h((string)$row['comprador_email']) ?></small></p>
      </div>
    </div>

    <!-- QR SIEMPRE visible (si no está habilitado, se ve con watermark "NO HABILITADO") -->
    <div class="qr">
      <div class="qr-wrap">
        <img id="qrimg" alt="QR del ticket">
        <!-- Fallback por si falla la librería -->
        <img class="qr-fallback"
             src="https://api.qrserver.com/v1/create-qr-code/?size=520x520&data=<?= urlencode($row['code']) ?>"
             alt="QR del ticket (fallback)">
        <?php if(!$aprobado): ?>
          <div class="qr-watermark">NO HABILITADO</div>
        <?php endif; ?>
      </div>
    </div>

    <?php if(!$aprobado): ?>
      <div class="warn">
        🚫 Este ticket aún no está habilitado. Estado del pedido: <b><?= h($row['estado']) ?></b>.<br>
        El QR se muestra para impresión, pero <b>no será válido</b> hasta que el organizador confirme el pago.
      </div>
    <?php endif; ?>

    <div style="margin-top:10px" class="no-print">
      <a class="btn" href="javascript:window.print()">🖨️ Imprimir / Guardar como PDF</a>
    </div>

    <p class="note">
      <b>Importante:</b> El QR se valida <b>una sola vez</b>. No compartir capturas. El organizador puede solicitar DNI.
    </p>
  </div>
</div>

<!-- Generación del QR en el navegador (sin guardar archivos en el servidor) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"
        integrity="sha512-M1ymwLw+vC0K7hF2w7p7k2qS1gJ9Y6f7z4VY9wN3N8x0i1vQHc8zQxT9b0FqfY0kQDYf8bJ0+0qM0YkZ2ZrT6g=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
(function(){
  var code = <?= json_encode($row['code']) ?>;
  var showedFallback = false;
  function showFallback(){
    if (showedFallback) return;
    showedFallback = true;
    var f = document.querySelector('.qr-fallback');
    if (f) f.style.display = 'block';
  }
  function render(){
    try{
      var tmp = document.createElement('div');
      new QRCode(tmp, { text: code, width:520, height:520, correctLevel: QRCode.CorrectLevel.M });
      var el = tmp.querySelector('img,canvas');
      var img = document.getElementById('qrimg');
      if (!el || !img) return showFallback();
      img.src = (el.tagName.toLowerCase()==='img') ? el.src : el.toDataURL('image/png');
    }catch(e){
      showFallback();
    }
  }
  // Si en 1.5s no cargó la librería, mostramos fallback
  var t = setTimeout(showFallback, 1500);
  if (window.QRCode) { clearTimeout(t); render(); }
  else {
    window.addEventListener('load', function(){ clearTimeout(t); if (window.QRCode) render(); else showFallback(); });
  }
})();
</script>
</body>
</html>
