<?php
/* ===========================================================
   vivo.php — Página pública para compartir el “Vivo” del evento (estilo UFC)
   Prioridad del player:
     1) HLS local (/hls/stream_{evento_id}.m3u8)
     2) FORCE (URL): ?embed=... / ?ytid=...
     3) evento_transmision (activo=1): embed_url -> youtube_url
     4) eventos_deportivos: streamyard_embed -> youtube_live_id
   Parámetros:
     - ?evento_id=XX  (requerido)
     - ?embed=URL_EMBED
     - ?ytid=VIDEO_ID
     - ?autoplay=0|1   (default 1)
     - ?mute=0|1       (default 1)
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

/* ===== Helpers ===== */
if (!function_exists('str_ends_with')) {
  function str_ends_with(string $haystack, string $needle): bool {
    if ($needle === '') return true;
    $len = strlen($needle);
    return substr($haystack, -$len) === $needle;
  }
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function table_exists(mysqli $db, string $name): bool {
  $name = $db->real_escape_string($name);
  $sql  = "SELECT 1 FROM information_schema.TABLES
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$name}' LIMIT 1";
  $r = $db->query($sql); $ok = $r && (bool)$r->num_rows; if ($r instanceof mysqli_result) $r->free(); return $ok;
}
function col_exists(mysqli $db, string $table, string $col): bool {
  $t=$db->real_escape_string($table); $c=$db->real_escape_string($col);
  $sql="SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1";
  $r=$db->query($sql); $ok=$r && (bool)$r->num_rows; if ($r instanceof mysqli_result) $r->free(); return $ok;
}
/* Alias por compat */
if (!function_exists('has_col')) {
  function has_col(mysqli $db, string $table, string $col): bool { return col_exists($db, $table, $col); }
}
function allowed_embed_src(?string $url): ?string {
  $u = trim((string)$url);
  if ($u==='') return null;
  $parts = @parse_url($u);
  if (!$parts || empty($parts['scheme']) || empty($parts['host'])) return null;
  $host = strtolower($parts['host']);
  $allowed = [
    'streamyard.com','www.streamyard.com',
    'youtube.com','www.youtube.com','youtube-nocookie.com','www.youtube-nocookie.com',
    'youtu.be',
    'player.twitch.tv','twitch.tv','www.twitch.tv',
    'player.vimeo.com','vimeo.com','www.vimeo.com'
  ];
  foreach ($allowed as $ok) {
    if ($host === $ok) return $u;
    if (str_ends_with($host, '.'.$ok)) return $u; // subdominios
  }
  return null;
}
function youtube_id_from_url(string $url): ?string {
  $u = trim($url);
  if ($u==='') return null;
  if (preg_match('~[?&]v=([A-Za-z0-9_-]{6,})~', $u, $m)) return $m[1];
  if (preg_match('~youtu\.be/([A-Za-z0-9_-]{6,})~', $u, $m)) return $m[1];
  if (preg_match('~youtube\.com/(?:live|embed)/([A-Za-z0-9_-]{6,})~', $u, $m)) return $m[1];
  return null;
}

/* ===== Input ===== */
$evento_id = isset($_GET['evento_id']) ? (int)$_GET['evento_id'] : 0;
if ($evento_id <= 0) { echo '<div style="padding:20px;background:#ffebee;color:#b71c1c;border-radius:8px">Falta <b>evento_id</b> en la URL.</div>'; exit; }

$forceYtId  = isset($_GET['ytid'])  ? preg_replace('~[^A-Za-z0-9_-]~','', (string)$_GET['ytid'])   : null;
$forceEmbed = isset($_GET['embed']) ? allowed_embed_src((string)$_GET['embed'])                     : null;
$autoplay   = isset($_GET['autoplay']) ? (int)($_GET['autoplay']) : 1;
$mute       = isset($_GET['mute'])     ? (int)($_GET['mute'])     : 1;

/* ===== eventos_deportivos ===== */
$evt = null;
$youtube_id = null;
$embed_src  = $forceEmbed; // prioridad absoluta a ?embed
$hls_url    = null;
$sourceBadge = ''; // para diagnóstico

if (table_exists($conexion,'eventos_deportivos')) {
  $colStreamyard = col_exists($conexion,'eventos_deportivos','streamyard_embed');
  $sql = "SELECT id, titulo, fecha, lugar, youtube_live_id".($colStreamyard?", streamyard_embed":"")." FROM eventos_deportivos WHERE id=? LIMIT 1";
  if ($st = $conexion->prepare($sql)){
    $st->bind_param('i', $evento_id);
    $st->execute(); $r = $st->get_result();
    $evt = $r->fetch_assoc();
    $st->close();
  }
}
if (!$evt) { echo '<div style="padding:20px;background:#ffebee;color:#b71c1c;border-radius:8px">Evento no encontrado.</div>'; exit; }

/* ===== HLS si existe archivo ===== */
$hls_path = $_SERVER['DOCUMENT_ROOT'] . "/hls/stream_{$evento_id}.m3u8";
if (file_exists($hls_path)) {
  $hls_url = "/hls/stream_{$evento_id}.m3u8";
  $sourceBadge = 'HLS local';
}

/* ===== evento_transmision (activo=1) ===== */
$tr = null;
if (table_exists($conexion,'evento_transmision')) {
  $sqlTr = "SELECT youtube_url, embed_url, activo FROM evento_transmision WHERE evento_id=? LIMIT 1";
  if ($st = $conexion->prepare($sqlTr)){
    $st->bind_param('i', $evento_id);
    $st->execute(); $res = $st->get_result();
    $tr = $res->fetch_assoc();
    $st->close();
  }
}

/* ===== Resolver fuentes ===== */
// 1) HLS (si existe)
// 2) Forzados
if (!$hls_url && $embed_src) { $sourceBadge = 'EMBED (forzado)'; }
if (!$hls_url && !$embed_src && $forceYtId) { $youtube_id = $forceYtId; $sourceBadge = 'YouTube (forzado)'; }
// 3) evento_transmision
if (!$hls_url && !$embed_src && !$youtube_id && $tr && (int)($tr['activo'] ?? 0) === 1) {
  if (!empty($tr['embed_url'])) {
    $cand = allowed_embed_src((string)$tr['embed_url']);
    if ($cand) { $embed_src = $cand; $sourceBadge = 'EMBED (evento_transmision)'; }
  }
  if (!$embed_src && !empty($tr['youtube_url'])) {
    $ytFromUrl = youtube_id_from_url((string)$tr['youtube_url']);
    if ($ytFromUrl) { $youtube_id = $ytFromUrl; $sourceBadge = 'YouTube (evento_transmision)'; }
  }
}
// 4) eventos_deportivos
if (!$hls_url && !$embed_src && !$youtube_id) {
  if (!empty($evt['streamyard_embed'] ?? '')) {
    $cand = allowed_embed_src((string)$evt['streamyard_embed']);
    if ($cand) { $embed_src = $cand; $sourceBadge = 'EMBED (eventos_deportivos)'; }
  }
}
if (!$hls_url && !$embed_src && !$youtube_id && !empty($evt['youtube_live_id'] ?? '')) {
  $youtube_id = preg_replace('~[^A-Za-z0-9_-]~','', (string)$evt['youtube_live_id']);
  $sourceBadge = 'YouTube (eventos_deportivos)';
}

/* Decide player */
$PLAYER = 'none'; // hls|embed|youtube|none
if     ($hls_url)     { $PLAYER='hls'; }
elseif ($embed_src)   { $PLAYER='embed'; }
elseif ($youtube_id)  { $PLAYER='youtube'; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>🔴 Vivo — <?= h($evt['titulo'] ?? 'Evento') ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  :root { --bg:#0d0f13; --card:#141a22; --ink:#e5ecf3; --muted:#9bb3c9; --txt:#fff; --red:#e53935; --blue:#1e88e5; --gold:#ffd54f; }
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--txt);font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif}
  .wrap{max-width:1200px;margin:0 auto;padding:16px}
  .player{position:relative;padding-top:56.25%;background:#000;border-radius:12px;overflow:hidden}
  .player iframe,.player video{position:absolute;top:0;left:0;width:100%;height:100%}
  h1{font-size:24px;margin:16px 0 8px}
  .sub{color:var(--muted);font-size:14px;margin-bottom:8px}
  .share{margin:14px 0;display:flex;flex-wrap:wrap;justify-content:flex-end;gap:10px}
  .btn{background:#263238;border:none;color:#fff;padding:10px 14px;border-radius:8px;cursor:pointer}
  .btn:hover{background:#37474f}
  .badge{display:inline-block;padding:.25rem .6rem;border:1px solid #415269;border-radius:999px;color:#cde;}
  /* ====== UFC-style ====== */
  #peleaActual{margin-top:18px;border-radius:14px;overflow:hidden;background:linear-gradient(180deg,#0f141b,#0b0e12);border:1px solid #1e2630}
  .ufc-bar{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:12px;padding:14px}
  .ufc-name{font-weight:900;letter-spacing:.5px;text-transform:uppercase;line-height:1.1}
  .ufc-name.red{color:var(--red);text-align:left;font-size:clamp(18px,5vw,34px)}
  .ufc-name.blue{color:var(--blue);text-align:right;font-size:clamp(18px,5vw,34px)}
  .ufc-vs{font-weight:900;color:#fff;background:#111;border:2px solid #2b3440;border-radius:12px;padding:8px 14px;font-size:clamp(16px,3.5vw,22px)}
  .ufc-meta{display:flex;justify-content:center;gap:8px;color:var(--ink);font-size:13px;padding:8px 12px;border-top:1px solid #1e2630}
  .tag{border:1px solid #3a4656;border-radius:999px;padding:.25rem .55rem;color:#bcd}
  .subline{display:flex;justify-content:space-between;gap:8px;color:#b8c6d6;font-size:12px;padding:8px 12px}
  /* Fight list */
  #listpeleas{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px}
  .fight-card{background:var(--card);border:1px solid #263240;border-radius:12px;padding:12px}
  .fight-card .row{display:flex;justify-content:space-between;gap:8px}
  .fight-card .red{color:var(--red);font-weight:700}
  .fight-card .blue{color:var(--blue);font-weight:700}
  .fight-card .meta{margin-top:6px;color:#a9bbcf;font-size:12px;display:flex;gap:6px;flex-wrap:wrap}
</style>
</head>
<body>
<div class="wrap">
  <h1>🔴 En Vivo — <?= h($evt['titulo']) ?></h1>
  <div class="sub">📅 <?= h($evt['fecha']) ?> · 📍 <?= h($evt['lugar']) ?></div>

  <div class="player" id="player">
    <?php if ($PLAYER==='hls'): ?>
      <video id="hlsPlayer" controls <?= $autoplay? 'autoplay':'' ?> <?= $mute? 'muted':'' ?> playsinline></video>
    <?php elseif ($PLAYER==='embed'): ?>
      <iframe src="<?= h($embed_src) ?>" frameborder="0" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>
    <?php elseif ($PLAYER==='youtube'): ?>
      <iframe
        src="https://www.youtube.com/embed/<?= h($youtube_id) ?>?autoplay=<?= (int)$autoplay ?>&mute=<?= (int)$mute ?>&playsinline=1&enablejsapi=1"
        frameborder="0" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>
    <?php else: ?>
      <div style="color:#ccc;font-size:14px;padding:12px;text-align:center">No hay transmisión activa.</div>
    <?php endif; ?>
  </div>

  <div class="share">
    <button class="btn" onclick="navigator.clipboard.writeText(location.href)">📋 Copiar link</button>
    <button class="btn" onclick="window.open('https://api.qrserver.com/v1/create-qr-code/?data='+encodeURIComponent(location.href), '_blank')">📱 QR</button>
    <?php if ($youtube_id): ?>
      <a class="btn" href="<?= h('https://www.youtube.com/watch?v='.$youtube_id) ?>" target="_blank" rel="noopener">▶️ Ver en YouTube</a>
    <?php endif; ?>
    <?php if ($PLAYER!=='none' && $sourceBadge): ?>
      <span class="badge"><?= h($sourceBadge) ?></span>
    <?php endif; ?>
  </div>

  <!-- ===== Pelea actual (UFC style) ===== -->
  <section id="peleaActual">
    <div class="ufc-bar">
      <div class="ufc-name red" id="paRed">Rojo</div>
      <div class="ufc-vs" id="paCenter">VS</div>
      <div class="ufc-name blue" id="paBlue">Azul</div>
    </div>
    <div class="ufc-meta" id="paMeta">
      <span class="tag" id="paModalidad">—</span>
      <span class="tag" id="paCategoria">—</span>
      <span class="tag" id="paRound">Round 1</span>
      <span class="tag" id="paTiempo">00:00</span>
    </div>
    <div class="subline" id="paSub">
      <span id="paRedInfo"> </span>
      <span id="paBlueInfo" style="text-align:right"> </span>
    </div>
  </section>

  <h3 style="margin-top:24px">Fight card</h3>
  <div id="listpeleas"><div class="sub" style="padding:8px">Cargando peleas…</div></div>
</div>

<script>
// ===== Estado de pelea actual (1s) =====
const eventoId = <?= (int)$evento_id ?>;
const elRed = document.getElementById('paRed');
const elBlue = document.getElementById('paBlue');
const elModalidad = document.getElementById('paModalidad');
const elCategoria = document.getElementById('paCategoria');
const elRound = document.getElementById('paRound');
const elTiempo = document.getElementById('paTiempo');
const elRedInfo = document.getElementById('paRedInfo');
const elBlueInfo = document.getElementById('paBlueInfo');

async function loadPeleaActual(){
  try{
    const r = await fetch(`combate_en_vivo.php?ajax=estado_evento&evento_id=${eventoId}`, {cache:'no-store'});
    const j = await r.json();

    if (j.ok && j.data && j.data.pelea){
      const p  = j.data.pelea;
      const az = j.data.azul || {}, ro = j.data.rojo || {};
      const rem = Math.max(0, parseInt(j.data.timer?.remaining ?? 0, 10));
      const mm = String(Math.floor(rem/60)).padStart(2,'0');
      const ss = String(rem%60).padStart(2,'0');
      const ronda = j.data.timer?.ronda || 1;

      elRed.textContent  = (ro.nombre || 'Rojo').toUpperCase();
      elBlue.textContent = (az.nombre || 'Azul').toUpperCase();

      elModalidad.textContent = (p.modalidad || '—');
      elCategoria.textContent = (p.categoria || p.categoria_peso || '—');
      elRound.textContent     = `Round ${ronda}`;
      elTiempo.textContent    = `${mm}:${ss}`;

      const roExtra = [ro.escuela||ro.team, ro.peso?(`${ro.peso} kg`):null, ro.record].filter(Boolean).join(' · ');
      const azExtra = [az.escuela||az.team, az.peso?(`${az.peso} kg`):null, az.record].filter(Boolean).join(' · ');
      elRedInfo.textContent  = roExtra || '';
      elBlueInfo.textContent = azExtra || '';
    } else {
      elRed.textContent = 'EN ESPERA';
      elBlue.textContent = 'SIGUIENTE';
      elModalidad.textContent = '—';
      elCategoria.textContent = '—';
      elRound.textContent = 'Round 1';
      elTiempo.textContent = '00:00';
      elRedInfo.textContent = '';
      elBlueInfo.textContent = '';
    }
  }catch(e){ console.log(e); }
}
setInterval(loadPeleaActual, 1000);
loadPeleaActual();

// ===== Fight card (reusa tu ver_evento_publico.php) =====
async function loadPeleas(){
  try{
    const r = await fetch(`ver_evento_publico.php?evento_id=${eventoId}`, {cache:'no-store'});
    const html = await r.text();
    const parser = new DOMParser();
    const dom = parser.parseFromString(html, 'text/html');

    const fights = dom.querySelectorAll('.fight'); // si tu HTML ya marca .fight
    const list = document.getElementById('listpeleas');
    list.innerHTML = '';

    if (fights.length) {
      fights.forEach(f=>{
        // Intentamos detectar nombres/metadata comunes:
        const redName  = f.querySelector('.red, .rojo, .corner-red, .red-name, .rojo-nombre');
        const blueName = f.querySelector('.blue, .azul, .corner-blue, .blue-name, .azul-nombre');
        const modal    = f.querySelector('.modalidad, [data-modalidad]');
        const cat      = f.querySelector('.categoria, .peso, [data-categoria]');
        const rnd      = f.querySelector('.rondas, [data-rounds]');

        const card = document.createElement('div');
        card.className = 'fight-card';
        card.innerHTML = `
          <div class="row">
            <div class="red">${(redName?.textContent || redName?.getAttribute?.('data-name') || 'Rojo').trim()}</div>
            <div class="blue" style="text-align:right">${(blueName?.textContent || blueName?.getAttribute?.('data-name') || 'Azul').trim()}</div>
          </div>
          <div class="meta">
            ${ modal ? `<span class="tag">${(modal.getAttribute?.('data-modalidad') || modal.textContent || '').trim()}</span>` : '' }
            ${ cat   ? `<span class="tag">${(cat.getAttribute?.('data-categoria') || cat.textContent || '').trim()}</span>` : '' }
            ${ rnd   ? `<span class="tag">Rnds: ${(rnd.getAttribute?.('data-rounds') || rnd.textContent || '').trim()}</span>` : '' }
          </div>
        `;
        list.appendChild(card);
      });
    } else {
      list.innerHTML = '<div class="sub" style="padding:8px">No hay peleas publicadas.</div>';
    }
  }catch(e){
    document.getElementById('listpeleas').innerHTML = '<div class="sub" style="padding:8px">No se pudieron cargar las peleas.</div>';
  }
}
loadPeleas();
</script>

<?php if ($PLAYER==='hls'): ?>
<!-- HLS.js player -->
<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
  const video = document.getElementById('hlsPlayer');
  if (!video) return;
  const src = <?= json_encode((string)$hls_url) ?>;
  if (Hls.isSupported()) {
    const hls = new Hls({liveDurationInfinity:true});
    hls.loadSource(src);
    hls.attachMedia(video);
  } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
    video.src = src;
  }
});
</script>
<?php endif; ?>

</body>
</html>
