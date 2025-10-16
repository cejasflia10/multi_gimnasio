<?php
/* ==========================================================
   transmision_en_vivo.php — Vista pública de transmisión
   - HUD estilo TV (banda inferior) dentro del player
   - Fullscreen del contenedor (HUD incluido), fs=0 en YouTube
   - Botón "📺 Transmitir a TV (beta)" con Google Cast Sender SDK
   - Poll a api_combate_estado_poll.php para round/timer/descanso
   ========================================================== */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

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
  if (preg_match('~v=([A-Za-z0-9_-]{6,})~', $url, $m)) return $m[1];
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
    if (!table_exists($conexion,$c['t']) || !col_exists($conexion,$c['t'],$c['id'])) continue;
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

/* ===== Selección de pelea ===== */
$pelea_sel = null;
if ($pelea_id_req>0){
  foreach($peleas as $p){ if ((int)$p['id']===$pelea_id_req){ $pelea_sel=$p; break; } }
}
if (!$pelea_sel){
  $ini = isset($tx['pelea_inicio_id']) ? (int)$tx['pelea_inicio_id'] : 0;
  if ($ini>0){ foreach($peleas as $p){ if ((int)$p['id']===$ini){ $pelea_sel=$p; break; } } }
}
if (!$pelea_sel && $peleas){ $pelea_sel = $peleas[0]; }

/* ===== Etiquetas header pelea ===== */
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
  else $mod_txt = trim(($mapComp[$rid]['modtxt'] ?? '').' '.($mapComp[$aid]['modtxt'] ?? ''));

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
    .card{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:14px}

    /* Player 16:9 */
    .video{position:relative; border-radius:12px; overflow:hidden; background:#000; border:1px solid var(--line); }
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

    @media (max-width:920px){ .grid{grid-template-columns:1fr} }
    <?php if($share): ?>
    .wrap{padding:0}
    .card{border:none;border-radius:0}
    <?php endif; ?>

    /* Overlay orientación */
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
  <div class="<?= $share ? '' : 'grid' ?>">
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

        <!-- Controles -->
        <div class="controls">
          <button id="btnFullscreen" class="btn">⛶ Pantalla completa</button>
          <button id="btnCastTV" class="btn" style="display:none">📺 Transmitir a TV (beta)</button>
          <button id="btnOpenApp" class="btn">▶️ Abrir en YouTube</button>
          <span class="meta">Tip: usá ⛶ o transmití la pestaña para que el HUD salga en el TV.</span>
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

    <?php if(!$share): ?>
    <div class="card">
      <div class="title" style="margin:0 0 8px">Peleas del evento</div>
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
          $url = 'transmision_en_vivo.php?evento_id='.$evento_id.'&pelea_id='.(int)$p['id'];
        ?>
          <div class="fight">
            <div><a href="<?=h($url)?>"><?=h($lab)?></a></div>
            <?php if($subs): ?><small><?=h(implode(' · ', $subs))?></small><?php endif; ?>
          </div>
        <?php endforeach; endif; ?>
      </div>
      <div class="divider"></div>
      <div class="meta">
        Vista limpia para TV: <a href="transmision_en_vivo.php?evento_id=<?=$evento_id?>&pelea_id=<?= $pelea_sel?(int)$pelea_sel['id']:0 ?>&share=1">abrir en modo pantalla</a>
      </div>
    </div>
    <?php endif; ?>
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

  // Doble tap/click sobre el área de video → fullscreen del contenedor
  wrap.addEventListener('dblclick', goFullscreenAndLock);
  wrap.addEventListener('touchend', (e)=>{ if(e.touches?.length===0){ goFullscreenAndLock(); } }, {passive:true});

  // Abrir en app de YouTube (recomendado para Cast/AirPlay con YouTube)
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
  // Mostrar el botón sólo si el SDK está disponible
  window.__onGCastApiAvailable = function(isAvailable) {
    if (isAvailable) initCast();
  };

  function initCast(){
    try{
      const context = cast.framework.CastContext.getInstance();
      // Opción 1 (por defecto): Default Media Receiver (para streams HLS/DASH propios)
      context.setOptions({
        receiverApplicationId: chrome.cast.media.DEFAULT_MEDIA_RECEIVER_APP_ID,
        autoJoinPolicy: chrome.cast.AutoJoinPolicy.TAB_AND_ORIGIN_SCOPED
      });
      const btn = document.getElementById('btnCastTV');
      if (btn) btn.style.display = 'inline-block';
      btn?.addEventListener('click', castThisTabOrShowHelp);
    }catch(e){}
  }

  async function castThisTabOrShowHelp(){
    try{
      const context = cast.framework.CastContext.getInstance();
      // Abre diálogo de Cast: en Chrome puede permitir "Transmitir esta pestaña".
      const session = await context.requestSession();

      // === Si en el futuro tenés tu PROPIO stream HLS/DASH, podés cargarlo acá: ===
      // const mediaInfo = new chrome.cast.media.MediaInfo('https://tu-servidor/stream.m3u8', 'application/x-mpegurl');
      // mediaInfo.metadata = new chrome.cast.media.GenericMediaMetadata();
      // mediaInfo.metadata.title = 'Evento en vivo';
      // const request = new chrome.cast.media.LoadRequest(mediaInfo);
      // await session.loadMedia(request);
      // ==========================================================================

      // Con YouTube en iframe no podemos "enviar" el video directo desde acá.
      // Tip al usuario:
      alert('Si tu dispositivo no ofrece “Transmitir esta pestaña” automáticamente, tocá “Abrir en YouTube” y casteá desde la app (icono Cast). Es la forma más estable para YouTube.');
    }catch(e){
      alert('No se pudo iniciar Cast. Como alternativa, podés usar "Abrir en YouTube" y castear desde la app.');
    }
  }
</script>

<!-- HUD: poll del estado de combate -->
<script>
(function(){
  const eventoId = <?= (int)$evento_id ?>;
  const peleaIdActual = <?= $pelea_sel ? (int)$pelea_sel['id'] : 0 ?>;

  const hud = document.getElementById('liveHud');
  const lblFight = document.getElementById('hudFight');
  const lblRound = document.getElementById('hudRound');
  const lblTimer = document.getElementById('hudTimer');
  const badgeRest = document.getElementById('hudRest');

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
      if (!phase.startEpoch || !phase.dur || !phase.activo){ lblTimer.textContent = '0:00'; return; }
      const now = Math.floor(Date.now()/1000);
      const elapsed = Math.max(0, now - phase.startEpoch);
      const remain = Math.max(0, phase.dur - elapsed);
      lblTimer.textContent = fmt(remain);
    }, 1000);
  }

  async function pollEstado(){
    try{
      const r = await fetch(`api_combate_estado_poll.php?evento_id=${encodeURIComponent(eventoId)}`, {cache:'no-store'});
      const j = await r.json();
      if(!j || !j.ok){ return; }
      const d = j.data;
      if(!d){
        hud.style.display = 'none';
        return;
      }

      const activo = parseInt(d.activo||0,10);
      phase.activo = activo;
      hud.style.display = activo ? 'block' : 'none';
      if(!activo) return;

      const mesaPeleaId = parseInt(d.pelea_actual_id||0,10);
      if (mesaPeleaId && mesaPeleaId !== phase.peleaId){
        const params = new URLSearchParams(window.location.search);
        params.set('pelea_id', String(mesaPeleaId));
        window.location.search = params.toString();
        return;
      }

      const rN = parseInt(d.ronda_actual||1,10);
      const inRest = (String(d.en_descanso||'0') === '1');
      const epochInicio = parseInt(d.epoch_inicio||0,10);
      const durRound = parseInt(d.dur_round||0,10) || 120;
      const durRest = parseInt(d.dur_descanso||0,10) || 60;

      lblRound.textContent = 'R' + (rN>0 ? rN : 1);
      badgeRest.style.display = inRest ? '' : 'none';

      phase.inRest = inRest;
      phase.startEpoch = epochInicio || Math.floor(Date.now()/1000);
      phase.dur = inRest ? durRest : durRound;

      try{
        const rojo = document.querySelector('.vs .corner:nth-child(1) h4')?.textContent?.replace(/^🟥\s*/,'') || 'Rojo';
        const azul = document.querySelector('.vs .corner:nth-child(2) h4')?.textContent?.replace(/^🟦\s*/,'') || 'Azul';
        lblFight.textContent = `${rojo} vs ${azul}`;
      }catch(e){}

      startTick();
    }catch(e){
      // silencioso
    }
  }

  setInterval(pollEstado, 2000);
  pollEstado();
})();
</script>
</body>
</html>
