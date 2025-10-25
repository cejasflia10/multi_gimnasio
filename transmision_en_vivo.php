<?php
/* ==========================================================
   transmision_en_vivo.php — Vista pública de transmisión
   - Player YouTube + HUD (banda inferior) sincronizable
   - ?debug=1 → muestra JSON de la API en vivo (diagnóstico)
   - ?force=1 → fuerza mostrar HUD aunque activo=0 (pruebas)
   - ?lag=6  → retrasa la APLICACIÓN del estado X segundos
   - Botones: Pantalla completa, Cast, Abrir YouTube, Rotar, Espejo
   - Lista de peleas siempre visible; en share=1: video arriba + lista abajo
   ========================================================== */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
if (function_exists('opcache_invalidate')) { @opcache_invalidate(__FILE__, true); }

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function getI($k){ return isset($_GET[$k]) ? (int)$_GET[$k] : 0; }
function getS($k){ return isset($_GET[$k]) ? trim((string)$_GET[$k]) : ''; }
function pick(array $row, array $cands, $def=''){
  foreach($cands as $c){ if(array_key_exists($c,$row) && $row[$c]!=='' && $row[$c]!==null) return $row[$c]; }
  return $def;
}
function table_exists(mysqli $cx, string $name): bool {
  $name = $cx->real_escape_string($name);
  $res = $cx->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$name}' LIMIT 1");
  $ok  = $res && (bool)$res->fetch_row();
  if ($res instanceof mysqli_result) $res->free();
  return $ok;
}
function col_exists(mysqli $cx, string $t, string $c): bool {
  $t = $cx->real_escape_string($t); $c = $cx->real_escape_string($c);
  $res = $cx->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}' LIMIT 1");
  $ok  = $res && (bool)$res->fetch_row();
  if ($res instanceof mysqli_result) $res->free();
  return $ok;
}
function yt_id_from_url($url){
  $url = trim((string)$url);
  if ($url==='') return null;
  if (preg_match('~youtu\.be/([A-Za-z0-9_-]{6,})~', $url, $m)) return $m[1];
  if (preg_match('~[?&]v=([A-Za-z0-9_-]{6,})~', $url, $m)) return $m[1];
  if (preg_match('~/(live|embed)/([A-Za-z0-9_-]{6,})~', $url, $m)) return $m[2];
  return null;
}

/* ===== Params ===== */
$evento_id = getI('evento_id');
if ($evento_id<=0){ http_response_code(400); exit('Falta ?evento_id'); }
$pelea_id_req = getI('pelea_id');
$share = (getS('share')==='1');

/* ===== Evento ===== */
$ev = null;
$r = $conexion->query("SELECT id, titulo, fecha, lugar FROM eventos_deportivos WHERE id={$evento_id} LIMIT 1");
if ($r && $r->num_rows) { $ev=$r->fetch_assoc(); } if ($r instanceof mysqli_result) $r->free();
if (!$ev){ http_response_code(404); exit('Evento no encontrado'); }
$ev_titulo = trim($ev['titulo'] ?? '') ?: ('Evento #'.$ev['id']);
$ev_fecha  = $ev['fecha'] ?? '';
$ev_lugar  = $ev['lugar'] ?? '';

/* ===== Transmisión del evento ===== */
$tx = null;
$r = $conexion->query("SELECT youtube_url, pelea_inicio_id, activo FROM evento_transmision WHERE evento_id={$evento_id} LIMIT 1");
if ($r && $r->num_rows) { $tx=$r->fetch_assoc(); } if ($r instanceof mysqli_result) $r->free();
$youtube_url = $tx['youtube_url'] ?? '';
$youtube_id  = yt_id_from_url($youtube_url);

/* ===== Peleas del evento ===== */
$peleas = [];
$r = $conexion->query("SELECT id, orden, competidor_rojo_id, competidor_azul_id, modalidad_id
                       FROM peleas_evento
                       WHERE evento_id={$evento_id}
                       ORDER BY COALESCE(orden,9999), id ASC");
while($r && $row=$r->fetch_assoc()) $peleas[] = $row;
if ($r instanceof mysqli_result) $r->free();

/* ===== Colectar IDs ===== */
$idsR=[]; $idsA=[]; $idsMod=[];
foreach($peleas as $p){
  if (!empty($p['competidor_rojo_id'])) $idsR[] = (int)$p['competidor_rojo_id'];
  if (!empty($p['competidor_azul_id'])) $idsA[] = (int)$p['competidor_azul_id'];
  if (!empty($p['modalidad_id']))       $idsMod[] = (int)$p['modalidad_id'];
}
$idsR = array_values(array_unique($idsR));
$idsA = array_values(array_unique($idsA));
$idsMod = array_values(array_unique($idsMod));

/* ===== Mapa competidores ===== */
$mapComp = [];
if ($idsR || $idsA){
  $todos = implode(',', array_map('intval', array_values(array_unique(array_merge($idsR,$idsA)))));
  $sqlCE = "
    SELECT id, nombre, apellido, escuela, escuela_nombre, escuela_logo, peso_kg, modalidad
    FROM competidores_evento
    WHERE evento_id={$evento_id} AND id IN ({$todos})
  ";
  $r = $conexion->query($sqlCE);
  while($r && $row=$r->fetch_assoc()){
    $id   = (int)$row['id'];
    $nom  = trim(($row['nombre']??'').' '.($row['apellido']??'')); if ($nom==='') $nom = $row['nombre'] ?? ("Competidor #{$id}");
    $esc  = trim(($row['escuela']??'') ?: ($row['escuela_nombre']??''));
    $peso = $row['peso_kg']; $peso = ($peso!==null && $peso!=='') ? (0+$peso) : '';
    $modtxt = trim($row['modalidad'] ?? '');
    $mapComp[$id] = ['nom'=>$nom, 'esc'=>$esc, 'peso'=>$peso, 'modtxt'=>$modtxt];
  }
  if ($r instanceof mysqli_result) $r->free();
}

/* ===== Mapa modalidades ===== */
$mapMod = [];
if ($idsMod){
  $nameFields = ['nombre','titulo','descripcion','detalle','modalidad'];
  $cands = [
    ['t'=>'modalidades_evento','id'=>'id'],
    ['t'=>'modalidades','id'=>'id'],
  ];
  foreach($cands as $c){
    if (!table_exists($conexion, $c['t']) || !col_exists($conexion, $c['t'], $c['id'])) continue;
    $ids_sql = implode(',', array_map('intval',$idsMod));
    $r = $conexion->query("SELECT * FROM {$c['t']} WHERE {$c['id']} IN ({$ids_sql})");
    while($r && $row=$r->fetch_assoc()){
      $id = (int)$row[$c['id']];
      if (!isset($mapMod[$id])) $mapMod[$id] = pick($row,$nameFields,"Modalidad #{$id}");
    }
    if ($r instanceof mysqli_result) $r->free();
    if (count($mapMod)===count($idsMod)) break;
  }
}

/* ===== Selección de pelea actual ===== */
$pelea_sel = null;
if ($pelea_id_req>0){
  foreach($peleas as $p){ if ((int)$p['id']===$pelea_id_req){ $pelea_sel=$p; break; } }
}
if (!$pelea_sel){
  $ini = isset($tx['pelea_inicio_id']) ? (int)$tx['pelea_inicio_id'] : 0;
  if ($ini>0){ foreach($peleas as $p){ if ((int)$p['id']===$ini){ $pelea_sel=$p; break; } } }
}
if (!$pelea_sel && $peleas){ $pelea_sel = $peleas[0]; }

/* ===== Etiquetas pelea ===== */
$orden_txt = $pelea_sel ? ($pelea_sel['orden'] ?? '') : '';
$rojo_nom=''; $rojo_esc=''; $rojo_peso='';
$azul_nom=''; $azul_esc=''; $azul_peso='';
$mod_txt=''; $pills_peso_txt='';

if ($pelea_sel){
  $rid = (int)$pelea_sel['competidor_rojo_id'];
  $aid = (int)$pelea_sel['competidor_azul_id'];

  $rojo_nom  = $mapComp[$rid]['nom']  ?? "Competidor #{$rid}";
  $rojo_esc  = $mapComp[$rid]['esc']  ?? '';
  $rojo_peso = $mapComp[$rid]['peso'] ?? '';
  $azul_nom  = $mapComp[$aid]['nom']  ?? "Competidor #{$aid}";
  $azul_esc  = $mapComp[$aid]['esc']  ?? '';
  $azul_peso = $mapComp[$aid]['peso'] ?? '';

  $mid = (int)($pelea_sel['modalidad_id'] ?? 0);
  if ($mid>0 && isset($mapMod[$mid])) $mod_txt = $mapMod[$mid];
  else $mod_txt = trim(($mapComp[$rid]['modtxt'] ?? '').' '.($mapComp[$aid]['modtxt'] ?? '')); // ✅ fix 'modtxt'
  
  $pL = is_numeric($rojo_peso) ? (0+$rojo_peso).' kg' : '';
  $pR = is_numeric($azul_peso) ? (0+$azul_peso).' kg' : '';
  if ($pL && $pR)      $pills_peso_txt = "{$pL} vs {$pR}";
  elseif ($pL || $pR)  $pills_peso_txt = $pL ?: $pR;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title><?=h($ev_titulo)?> — Transmisión en vivo</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex,nofollow">
  <style>
    :root { --bg:#0b0b0b; --ink:#eee; --muted:#aaa; --brand:#ffd600; --card:#151515; --line:#262626; }
    html,body{background:var(--bg);color:var(--ink);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;margin:0}
    header{<?= $share ? 'display:none;' : '' ?> position:sticky;top:0;background:#000a;padding:12px 16px;border-bottom:1px solid var(--line)}
    header h1{margin:0;font-size:18px}
    .wrap{max-width:1200px;margin:0 auto;padding:16px}
    .grid{display:grid;grid-template-columns:2fr 1fr;gap:16px}
    @media (max-width:920px){ .grid{grid-template-columns:1fr} }
    .card{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:14px}

    /* share=1: video arriba + lista abajo */
    <?php if($share): ?>
    .grid{ grid-template-columns:1fr; }
    .card--compact{ padding:10px 12px; }
    <?php endif; ?>

    /* Player 16:9 */
    .video{position:relative; border-radius:12px; overflow:hidden; background:#000; border:1px solid var(--line); transition:transform .2s ease;}
    .video .ratio{ width:100%; aspect-ratio:16/9; }
    .video iframe{ position:absolute; inset:0; width:100%; height:100%; border:0; z-index:1; }

    /* HUD - Banda inferior */
    .liveHud{ position:absolute; left:0; right:0; bottom:0; z-index:2; pointer-events:none; padding:0 10px calc(10px + env(safe-area-inset-bottom)) 10px; }
    .liveHud .bar{
      display:grid; grid-template-columns: 1fr auto 1fr; align-items:center; gap:10px;
      background:linear-gradient(180deg, rgba(0,0,0,0.0), rgba(0,0,0,.55) 30%, rgba(0,0,0,.65) 100%);
      border-top:1px solid rgba(255,255,255,.08); border-radius:12px; padding:10px; backdrop-filter: blur(2px);
    }
    .left, .center, .right { display:flex; align-items:center; gap:10px; }
    .center{ justify-content:center; }
    .left{ justify-content:flex-start; }
    .right{ justify-content:flex-end; }

    .pill{
      pointer-events:auto; display:inline-flex; align-items:center; gap:8px;
      background:rgba(0,0,0,.55); border:1px solid #2a2a2a; border-radius:999px;
      padding:6px 10px; backdrop-filter: blur(2px); font-size:clamp(11px, 1.6vw, 14px);
      color:#fff; font-weight:700;
    }
    .dot{width:8px; height:8px; border-radius:50%; background:#ff4040; box-shadow:0 0 10px #ff4040;}
    .timerBig{
      font-size:clamp(22px, 4vw, 36px); line-height:1; padding:6px 14px; border-radius:10px;
      background:rgba(0,0,0,.65); border:1px solid #2a2a2a; backdrop-filter: blur(2px);
      letter-spacing:1px; min-width:120px; text-align:center; font-weight:900;
    }
    .badgeRest{ background:rgba(255,199,0,.18); border-color:#5a4900; }
    .badgeRound{ background:rgba(0,0,0,.55); }

    .meta{font-size:13px;color:var(--muted)}
    .title{font-size:18px;font-weight:800;margin:6px 0}
    .pills{margin:6px 0 0}
    .pill.tag{display:inline-block;background:#222;border:1px solid var(--line);border-radius:999px;padding:4px 10px;font-size:12px;margin:4px 6px 0 0}
    .list{max-height:60vh;overflow:auto}
    .fight{border:1px solid var(--line);border-radius:10px;padding:10px;margin-bottom:8px}
    .fight a{color:var(--brand);text-decoration:none}
    .fight small{color:var(--muted)}
    .vs{display:flex;gap:10px;align-items:flex-start}
    .corner{flex:1}
    .corner h4{margin:0 0 4px;font-size:16px}
    .corner .sub{font-size:12px;color:var(--muted)}
    .divider{height:1px;background:var(--line);margin:10px 0}

    .controls{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; justify-content:center; margin-top:10px; }
    .btn{ padding:10px 14px; border-radius:10px; border:1px solid #304351; background:#1a2530; color:#e9f2fb; cursor:pointer; }
    .btn:hover{ filter:brightness(1.06); }
    .btn-primary{ background:#0e8dff; border-color:#0e8dff; color:#fff; }

    /* Rotaciones/Espejo aplicadas al contenedor del player (video + HUD) */
    .video.rot-90  { transform: rotate(90deg);  transform-origin: center center; }
    .video.rot-180 { transform: rotate(180deg); transform-origin: center center; }
    .video.rot-270 { transform: rotate(270deg); transform-origin: center center; }
    .video.mirrorH { transform: scaleX(-1);    transform-origin: center center; }
    .video.rot-90 .ratio,
    .video.rot-270 .ratio { aspect-ratio: 9/16; }

    /* Overlay orientación (vertical) */
    #orientHint{ position:fixed; inset:0; display:none; align-items:center; justify-content:center; z-index:9999;
      background:rgba(0,0,0,.86); text-align:center; padding:24px; backdrop-filter:blur(2px); }
    #orientHint .box{ max-width:520px; background:#12161b; border:1px solid #2a3946; border-radius:16px; padding:22px; }
    #orientHint h3{ margin:0 0 8px; }
    #orientHint p{ margin:0 0 12px; color:var(--muted); }
    @media (orientation: portrait){ #orientHint{ display:flex; } }
  </style>
</head>
<body>

<!-- Overlay orientación -->
<div id="orientHint" aria-hidden="true">
  <div class="box">
    <h3>📱 Girá tu teléfono</h3>
    <p>Para ver la transmisión mejor, usalo en <b>horizontal</b>. También podés entrar en pantalla completa.</p>
    <button id="btnFSOverlay" class="btn btn-primary">⛶ Ver en pantalla completa</button>
  </div>
</div>

<header>
  <h1>🎥 <?=h($ev_titulo)?></h1>
  <div class="wrap meta">
    <?= $ev_fecha ? '📅 '.h($ev_fecha).' · ' : '' ?>
    <?= $ev_lugar ? '📍 '.h($ev_lugar) : '' ?>
  </div>
</header>

<div class="wrap">
  <div class="grid">
    <div class="card">
      <?php if (!$youtube_id): ?>
        <div class="meta" style="padding:8px 0">⚠️ Este evento no tiene un enlace de YouTube configurado. Configuralo en <code>youtube_live_set.php</code>.</div>
      <?php else: ?>
        <div class="video" id="playerWrap">
          <div class="ratio"></div>
          <iframe
            id="ytFrame"
            src="https://www.youtube.com/embed/<?=h($youtube_id)?>?rel=0&modestbranding=1&playsinline=1&fs=0"
            title="YouTube Live"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            allowfullscreen
            referrerpolicy="strict-origin-when-cross-origin"></iframe>

          <!-- HUD: BANDA INFERIOR -->
          <div class="liveHud" id="liveHud" style="display:none">
            <div class="bar">
              <div class="left">
                <span class="pill"><span class="dot"></span> EN VIVO</span>
                <span class="pill" id="hudFight">—</span>
              </div>
              <div class="center">
                <span class="timerBig" id="hudTimer">0:00</span>
              </div>
              <div class="right">
                <span class="pill badgeRound" id="hudRound">R1</span>
                <span class="pill badgeRest" id="hudRest" style="display:none">DESCANSO</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Controles principales -->
        <div class="controls">
          <button id="btnFullscreen" class="btn">⛶ Pantalla completa</button>
          <button id="btnCastTV" class="btn">📺 Transmitir a TV (beta)</button>
          <button id="btnOpenApp" class="btn">▶️ Abrir en YouTube</button>
          <span class="meta">Tip: usá ⛶ o “Transmitir pestaña” para ver video + HUD + lista en el TV.</span>
        </div>

        <!-- Controles de rotación/espejo -->
        <div class="controls">
          <button id="btnRotateCycle" class="btn">⤴️ Rotar 0°</button>
          <button id="btnRotate180" class="btn">↕️ Voltear 180°</button>
          <button id="btnMirrorH"  class="btn">🪞 Espejo H</button>
        </div>
      <?php endif; ?>

      <?php if ($pelea_sel): ?>
        <div class="title" style="margin-top:10px">
          <?= ($orden_txt!=='' ? h("#".$orden_txt) : "Pelea") ?>
        </div>

        <?php if($mod_txt || $pills_peso_txt): ?>
          <div class="pills">
            <?php if($mod_txt):        ?><span class="pill tag">🏷️ <?=h($mod_txt)?></span><?php endif; ?>
            <?php if($pills_peso_txt): ?><span class="pill tag">⚖️ <?=h($pills_peso_txt)?></span><?php endif; ?>
          </div>
        <?php endif; ?>

        <div class="vs" style="margin-top:8px">
          <div class="corner">
            <h4>🟥 <?=h($rojo_nom)?></h4>
            <?php if($rojo_esc!=='' || is_numeric($rojo_peso)): ?>
              <div class="sub">
                <?= $rojo_esc!=='' ? h($rojo_esc) : '' ?>
                <?= (is_numeric($rojo_peso) ? ($rojo_esc!=='' ? ' · ' : '').h((0+$rojo_peso).' kg') : '') ?>
              </div>
            <?php endif; ?>
          </div>
          <div class="corner">
            <h4>🟦 <?=h($azul_nom)?></h4>
            <?php if($azul_esc!=='' || is_numeric($azul_peso)): ?>
              <div class="sub">
                <?= $azul_esc!=='' ? h($azul_esc) : '' ?>
                <?= (is_numeric($azul_peso) ? ($azul_esc!=='' ? ' · ' : '').h((0+$azul_peso).' kg') : '') ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php else: ?>
        <div class="meta" style="padding-top:10px">No hay peleas cargadas aún para este evento.</div>
      <?php endif; ?>
    </div>

    <!-- Lista de peleas -->
    <div class="card <?= $share ? 'card--compact' : '' ?>">
      <div class="title" style="margin:0 0 8px">
        <?= $share ? 'Peleas del evento (vista TV)' : 'Peleas del evento' ?>
      </div>
      <div class="list">
        <?php if(!$peleas): ?>
          <div class="meta">No hay peleas para listar.</div>
        <?php else: foreach($peleas as $p):
          $rid=(int)$p['competidor_rojo_id']; $aid=(int)$p['competidor_azul_id'];
          $rn = $mapComp[$rid]['nom'] ?? "Competidor #{$rid}";
          $re = $mapComp[$rid]['esc'] ?? '';
          $rp = $mapComp[$rid]['peso'] ?? '';
          $an = $mapComp[$aid]['nom'] ?? "Competidor #{$aid}";
          $ae = $mapComp[$aid]['esc'] ?? '';
          $ap = $mapComp[$aid]['peso'] ?? '';
          $num = $p['orden'] ?? '';
          $lab = ($num? "#$num · ":'')."$rn vs $an";
          $subs = [];
          if ($re) $subs[] = "🟥 $re";
          if ($ae) $subs[] = "🟦 $ae";
          if (is_numeric($rp) || is_numeric($ap)) {
            $spL = is_numeric($rp) ? (0+$rp).' kg' : '';
            $spR = is_numeric($ap) ? (0+$ap).' kg' : '';
            $subs[] = '⚖️ '.trim($spL.($spL&&$spR?' vs ':'').$spR);
          }
          $url = 'transmision_en_vivo.php?evento_id='.$evento_id.'&pelea_id='.(int)$p['id'].($share?'&share=1':'');
        ?>
          <div class="fight">
            <div><a href="<?=h($url)?>"><?=h($lab)?></a></div>
            <?php if($subs): ?><small><?=h(implode(' · ', $subs))?></small><?php endif; ?>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <?php if(!$share): ?>
        <div class="divider"></div>
        <div class="meta">
          Vista limpia para TV: <a href="transmision_en_vivo.php?evento_id=<?=$evento_id?>&pelea_id=<?= $pelea_sel?(int)$pelea_sel['id']:0 ?>&share=1">abrir en modo pantalla</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Controles de orientación / fullscreen -->
<script>
(function(){
  const frame = document.getElementById('ytFrame');
  const wrap  = document.getElementById('playerWrap');
  const btnFS = document.getElementById('btnFullscreen');
  const btnFSOverlay = document.getElementById('btnFSOverlay');
  const btnOpenApp = document.getElementById('btnOpenApp');

  function isPortrait(){ return window.matchMedia('(orientation: portrait)').matches; }
  function updateOrientUI(){
    const hint = document.getElementById('orientHint');
    if (!hint) return;
    hint.style.display = isPortrait() ? 'flex' : 'none';
  }

  async function goFullscreenAndLock(){
    const el = wrap || document.documentElement;
    try{
      if (!document.fullscreenElement && el.requestFullscreen){
        await el.requestFullscreen(); // HUD incluido
      }
      if (screen.orientation && screen.orientation.lock){
        try{ await screen.orientation.lock('landscape'); }catch(e){}
      }
    }catch(e){}
  }

  if (wrap){
    wrap.addEventListener('dblclick', goFullscreenAndLock);
    wrap.addEventListener('touchend', (e)=>{ if(e.touches?.length===0){ goFullscreenAndLock(); } }, {passive:true});
  }

  const src = frame ? (frame.getAttribute('src')||'') : '';
  const vidMatch = src.match(/\/embed\/([^?&/]+)/);

  if (btnFS) btnFS.addEventListener('click', goFullscreenAndLock);
  if (btnFSOverlay) btnFSOverlay.addEventListener('click', goFullscreenAndLock);
  if (btnOpenApp){
    btnOpenApp.addEventListener('click', ()=>{
      if (vidMatch){
        const videoId = vidMatch[1];
        window.location.href = `https://www.youtube.com/watch?v=${encodeURIComponent(videoId)}`;
      } else {
        window.location.href = `https://www.youtube.com`;
      }
    });
  }

  document.addEventListener('click', ()=>{
    if (isPortrait()) goFullscreenAndLock();
  }, { once:true });

  window.addEventListener('resize', updateOrientUI);
  window.addEventListener('orientationchange', updateOrientUI);
  updateOrientUI();
})();
</script>

<!-- Google Cast Sender SDK -->
<script src="https://www.gstatic.com/cv/js/sender/v1/cast_sender.js?loadCastFramework"></script>
<script>
  const btnCast = document.getElementById('btnCastTV');

  function isSecureOrigin() {
    return location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1';
  }

  window.__onGCastApiAvailable = function(isAvailable) {
    if (!isAvailable) {
      attachGuideHandler();
      return;
    }
    try {
      const context = cast.framework.CastContext.getInstance();
      context.setOptions({
        receiverApplicationId: chrome.cast.media.DEFAULT_MEDIA_RECEIVER_APP_ID,
        autoJoinPolicy: chrome.cast.AutoJoinPolicy.TAB_AND_ORIGIN_SCOPED
      });
      btnCast?.addEventListener('click', openCastOrGuide, { once:false });
    } catch (e) {
      attachGuideHandler();
    }
  };

  async function openCastOrGuide() {
    if (!isSecureOrigin()) return showGuide('Para usar Cast directo desde la web, abrí esta página en HTTPS o en http://localhost. Igual podés transmitir la pestaña desde el menú del navegador.');
    try {
      const context = cast.framework.CastContext.getInstance();
      await context.requestSession();
    } catch (e) {
      showGuide('No se pudo iniciar Cast desde el botón. Podés usar "Transmitir esta pestaña" del navegador.');
    }
  }

  function attachGuideHandler() {
    btnCast?.addEventListener('click', () => { showGuide(); }, { once:false });
  }

  function showGuide(extraMsg) {
    const msg = (extraMsg ? (extraMsg + '\n\n') : '') +
`Transmitir esta pestaña:
• En Chrome (PC/Mac): Menú ⋮ → "Transmitir…" → "Esta pestaña".
• En Android: Panel rápido → "Transmitir" → elegí tu TV/Chromecast.
• En iPhone/iPad/Mac: Duplicar pantalla / AirPlay al TV.

Sugerencia: activá "⛶ Pantalla completa" en la página antes de transmitir para que se vea video + HUD + lista.`;
    alert(msg);
  }
</script>

<!-- HUD: poll del estado de combate (con DEBUG/FORCE/LAG) + Rotación/Espejo -->
<script>
(function(){
  const eventoId = <?= (int)$evento_id ?>;
  const peleaIdActual = <?= $pelea_sel ? (int)$pelea_sel['id'] : 0 ?>;

  // Flags por querystring
  const qs = new URLSearchParams(location.search);
  const DEBUG = qs.get('debug') === '1';
  const FORCE = qs.get('force') === '1';
  const LAG   = parseInt(qs.get('lag') || '0', 10) || 0;

  const hud = document.getElementById('liveHud');
  const lblFight = document.getElementById('hudFight');
  const lblRound = document.getElementById('hudRound');
  const lblTimer = document.getElementById('hudTimer');
  const badgeRest = document.getElementById('hudRest');

  // Cajita DEBUG
  const dbgFlag = document.createElement('div');
  if (DEBUG){
    Object.assign(dbgFlag.style, {
      position:'fixed', right:'8px', bottom:'8px', zIndex:99999,
      background:'#111c', color:'#0f0', font:'12px/1.4 monospace',
      border:'1px solid #0f0', padding:'6px 8px', borderRadius:'6px', maxWidth:'48vw', whiteSpace:'pre-wrap'
    });
    document.body.appendChild(dbgFlag);
  }

  function fmt(s){
    s = Math.max(0, Math.floor(s||0));
    const m = Math.floor(s/60), ss = String(s%60).padStart(2,'0');
    return `${m}:${ss}`;
  }

  let phase = { inRest:false, startEpoch:0, dur:0, peleaId: peleaIdActual, activo:0 };
  let tickInt = null;

  function startTick(){
    if (tickInt) return;
    tickInt = setInterval(()=>{
      if (!phase.startEpoch || !phase.dur || (!phase.activo && !FORCE)){ lblTimer.textContent = '0:00'; return; }
      const now = Math.floor(Date.now()/1000);
      const elapsed = Math.max(0, now - phase.startEpoch);
      const remain = Math.max(0, phase.dur - elapsed);
      lblTimer.textContent = fmt(remain);
    }, 1000);
  }

  function applyState(d){
    const activo = parseInt(d?.activo||0,10);
    phase.activo = activo;

    // HUD visible: si activo=1 o si forzado
    hud.style.display = (activo || FORCE) ? 'block' : 'none';
    if (!activo && !FORCE) return;

    // Cambio de pelea por mesa
    const mesaPeleaId = parseInt(d?.pelea_actual_id||0,10);
    if (mesaPeleaId && mesaPeleaId !== phase.peleaId){
      const params = new URLSearchParams(window.location.search);
      params.set('pelea_id', String(mesaPeleaId));
      if (<?= $share ? 'true' : 'false' ?>) params.set('share','1');
      window.location.search = params.toString();
      return;
    }

    const rN = parseInt(d?.ronda_actual||1,10);
    const inRest = (String(d?.en_descanso||'0') === '1');
    const epochInicio = parseInt(d?.epoch_inicio||0,10);
    const durRound = parseInt(d?.dur_round||0,10) || 120;
    const durRest = parseInt(d?.dur_descanso||0,10) || 60;

    lblRound.textContent = 'R' + (rN>0 ? rN : 1);
    badgeRest.style.display = inRest ? '' : 'none';

    phase.inRest = inRest;
    // Usamos el epoch real; solo "demoramos" la aplicación con LAG
    phase.startEpoch = epochInicio || Math.floor(Date.now()/1000);
    phase.dur = inRest ? durRest : durRound;

    try{
      const rojo = document.querySelector('.vs .corner:nth-child(1) h4')?.textContent?.replace(/^🟥\s*/,'') || 'Rojo';
      const azul = document.querySelector('.vs .corner:nth-child(2) h4')?.textContent?.replace(/^🟦\s*/,'') || 'Azul';
      lblFight.textContent = `${rojo} vs ${azul}`;
    }catch(e){}

    startTick();
  }

  async function pollEstado(){
    try{
      const r = await fetch(`api_combate_estado_poll.php?evento_id=${encodeURIComponent(eventoId)}`, {cache:'no-store'});
      const j = await r.json();
      if(DEBUG) dbgFlag.textContent = 'DEBUG ON\n' + JSON.stringify(j, null, 2);

      if(!j || !j.ok){ return; }
      const d = j.data;
      if(!d){ hud.style.display = (FORCE ? 'block' : 'none'); return; }

      // Aplicamos LAG si está configurado
      if (LAG > 0) setTimeout(()=>applyState(d), LAG * 1000);
      else applyState(d);
    }catch(e){
      if(DEBUG) dbgFlag.textContent = 'ERROR\n' + (e?.message||String(e));
    }
  }

  setInterval(pollEstado, 2000);
  pollEstado();

  // ==== Rotación / Espejo ====
  const playerWrap = document.getElementById('playerWrap');
  const btnRotCycle = document.getElementById('btnRotateCycle');
  const btnRot180   = document.getElementById('btnRotate180');
  const btnMirrorH  = document.getElementById('btnMirrorH');

  function clearRot(){
    playerWrap?.classList.remove('rot-90','rot-180','rot-270','mirrorH');
    // reset styles manuales
    playerWrap?.querySelector('.ratio')?.style?.setProperty('aspect-ratio','');
  }

  let rotStep = 0; // 0,90,180,270
  function applyCycle(){
    if (!playerWrap) return;
    clearRot();
    rotStep = (rotStep + 1) % 4;
    const labels = ['⤴️ Rotar 0°','⤴️ Rotar 90°','⤴️ Rotar 180°','⤴️ Rotar 270°'];
    if (btnRotCycle) btnRotCycle.textContent = labels[rotStep];
    switch(rotStep){
      case 0: break;
      case 1: playerWrap.classList.add('rot-90'); break;
      case 2: playerWrap.classList.add('rot-180'); break;
      case 3: playerWrap.classList.add('rot-270'); break;
    }
  }

  function set180(){
    if (!playerWrap) return;
    clearRot();
    rotStep = 2;
    playerWrap.classList.add('rot-180');
    if (btnRotCycle) btnRotCycle.textContent = '⤴️ Rotar 180°';
  }

  let mirrorOn = false;
  function toggleMirror(){
    if (!playerWrap) return;
    mirrorOn = !mirrorOn;
    if (mirrorOn) {
      playerWrap.classList.add('mirrorH');
      if (btnMirrorH) btnMirrorH.textContent = '🪞 Espejo H (ON)';
    } else {
      playerWrap.classList.remove('mirrorH');
      if (btnMirrorH) btnMirrorH.textContent = '🪞 Espejo H';
    }
  }

  btnRotCycle?.addEventListener('click', applyCycle);
  btnRot180  ?.addEventListener('click', set180);
  btnMirrorH ?.addEventListener('click', toggleMirror);
})();
</script>
</body>
</html>
