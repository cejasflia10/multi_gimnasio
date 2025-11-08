<?php
/* ===========================================================
   vivo.php — Página pública para compartir el “Vivo” del evento
   Parámetros requeridos:
     - ?evento_id=XX → ID del evento deportivo
   Funciona con HLS o con youtube_live_id (fallback)
   =========================================================== */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma', 'no-cache');
header('Expires', '0');

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
@$conexion->set_charset('utf8mb4');

/* Helpers */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function bt($c){ return '`'.str_replace('`','``',(string)$c).'`'; }
function table_exists(mysqli $db, string $name): bool {
  $name = $db->real_escape_string($name);
  if ($r = $db->query("SHOW TABLES LIKE '$name'")) { $ok = (bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function has_col(mysqli $db, string $table, string $col): bool {
  $t=$db->real_escape_string($table); $c=$db->real_escape_string($col);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}

/* Input */
$evento_id = isset($_GET['evento_id']) ? (int)$_GET['evento_id'] : 0;
if ($evento_id <= 0) {
  echo '<div style="padding:20px;background:#ffebee;color:#b71c1c;border-radius:8px">Falta <b>evento_id</b> en la URL.</div>';
  exit;
}

/* Datos del evento */
$evt = null;
$youtube_id = null;
$hls_url = null;

if (table_exists($conexion,'eventos_deportivos')) {
  $sql = "SELECT id, titulo, fecha, lugar, youtube_live_id FROM eventos_deportivos WHERE id=? LIMIT 1";
  if ($st = $conexion->prepare($sql)){
    $st->bind_param('i', $evento_id);
    $st->execute(); $r = $st->get_result();
    $evt = $r->fetch_assoc();
    $st->close();
  }
}

if (!$evt) {
  echo '<div style="padding:20px;background:#ffebee;color:#b71c1c;border-radius:8px">Evento no encontrado.</div>';
  exit;
}

$youtube_id = $evt['youtube_live_id'] ?? null;

/* Primero intentar HLS si existe el archivo del stream */
$hls_path = $_SERVER['DOCUMENT_ROOT'] . "/hls/stream_{$evento_id}.m3u8";
if (file_exists($hls_path)) {
  $hls_url = "/hls/stream_{$evento_id}.m3u8";
} elseif ($youtube_id) {
  $hls_url = null; // usar YouTube como fallback
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>🔴 Vivo — <?= h($evt['titulo'] ?? 'Evento') ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  body{margin:0;background:#0d0f13;color:#fff;font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif}
  .wrap{max-width:1200px;margin:0 auto;padding:16px}
  .player{position:relative;padding-top:56.25%;background:#000;border-radius:12px;overflow:hidden}
  .player iframe,.player video{position:absolute;top:0;left:0;width:100%;height:100%}
  h1{font-size:24px;margin:16px 0 8px}
  .sub{color:#9bb3c9;font-size:14px;margin-bottom:8px}
  .flex{display:flex;gap:12px;flex-wrap:wrap;margin-top:12px}
  #peleaActual{padding:12px;border-radius:10px;background:#141a22;margin-top:16px}
  .red{border-left:5px solid #c62828;padding-left:10px}
  .blue{border-left:5px solid #1565c0;padding-left:10px}
  .share{margin:14px 0;display:flex;justify-content:flex-end;gap:10px}
  .btn-share{background:#263238;border:none;color:#fff;padding:10px 14px;border-radius:8px;cursor:pointer}
  .btn-share:hover{background:#37474f}
</style>
</head>
<body>
<div class="wrap">
  <h1>🔴 En Vivo — <?= h($evt['titulo']) ?></h1>
  <div class="sub">📅 <?= h($evt['fecha']) ?> · 📍 <?= h($evt['lugar']) ?></div>

  <div class="player" id="player">
    <?php if ($hls_url): ?>
      <video id="hlsPlayer" controls autoplay muted></video>
    <?php elseif ($youtube_id): ?>
      <iframe src="https://www.youtube.com/embed/<?= h($youtube_id) ?>?autoplay=1&mute=1"
              frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>
    <?php else: ?>
      <div style="color:#ccc;font-size:14px;padding:12px;text-align:center">No hay transmisión activa.</div>
    <?php endif; ?>
  </div>

  <div class="share">
    <button class="btn-share" onclick="navigator.clipboard.writeText(location.href)">📋 Copiar link</button>
    <button class="btn-share" onclick="window.open('https://api.qrserver.com/v1/create-qr-code/?data='+encodeURIComponent(location.href), '_blank')">📱 QR</button>
  </div>

  <h2>Pelea actual</h2>
  <div id="peleaActual">Cargando...</div>

  <h3 style="margin-top:24px">Peleas del evento</h3>
  <div id="listpeleas">Cargando...</div>
</div>

<script>
// Lee estado de pelea actual cada 1s
const eventoId = <?= (int)$evento_id ?>;
async function loadPeleaActual(){
  try{
    const r = await fetch(`combate_en_vivo.php?ajax=estado_evento&evento_id=${eventoId}`);
    const j = await r.json();
    if (j.ok && j.data && j.data.pelea){
      const p = j.data.pelea;
      const az = j.data.azul || {}, ro = j.data.rojo || {};
      document.getElementById('peleaActual').innerHTML = `
        <div class="flex" style="justify-content:space-between">
          <div class="red">
            <strong>${ro.nombre||'Rojo'}</strong><br>
            <small>${ro.escuela||''}</small>
          </div>
          <div style="text-align:center">
            <div><b>Round:</b> ${j.data.timer?.ronda || 1}</div>
            <div><b>Tiempo:</b> ${Math.floor((j.data.timer?.remaining||0)/60)}:${String((j.data.timer?.remaining||0)%60).padStart(2,'0')}</div>
          </div>
          <div class="blue">
            <strong>${az.nombre||'Azul'}</strong><br>
            <small>${az.escuela||''}</small>
          </div>
        </div>`;
    }
  }catch(e){ console.log(e); }
}
setInterval(loadPeleaActual,1000);
loadPeleaActual();

// Lista de peleas
async function loadPeleas(){
  try{
    const r = await fetch(`ver_evento_publico.php?evento_id=${eventoId}`);
    const txt = await r.text();
    const parser=new DOMParser();
    const dom=parser.parseFromString(txt,'text/html');
    const fights = dom.querySelectorAll('.fight');
    const list=document.getElementById('listpeleas');
    fights.forEach(f=> list.appendChild(f.cloneNode(true)) );
  }catch(e){
    document.getElementById('listpeleas').innerHTML='<div style="padding:8px;color:#ccc">No se pudieron cargar las peleas.</div>';
  }
}
loadPeleas();
</script>

<?php if ($hls_url): ?>
<!-- HLS.js player -->
<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
  const video = document.getElementById('hlsPlayer');
  if (Hls.isSupported()) {
    const hls = new Hls();
    hls.loadSource('<?= h($hls_url) ?>');
    hls.attachMedia(video);
  } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
    video.src = '<?= h($hls_url) ?>';
  }
});
</script>
<?php endif; ?>

</body>
</html>
