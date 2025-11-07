<?php
// overlay_obs.php — Overlay ESPN/UFC para OBS (barra inferior)
// Lee estado de combate_en_vivo.php?ajax=estado&pelea_id=...
if (session_status() === PHP_SESSION_NONE) session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$pelea_id = isset($_GET['pelea_id']) ? (int)$_GET['pelea_id'] : 0;
if ($pelea_id <= 0) { http_response_code(400); echo 'Falta pelea_id'; exit; }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Overlay ESPN</title>
<style>
  html,body{margin:0;padding:0;background:transparent;overflow:hidden}
  body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;color:#fff}
  .wrap{position:absolute;inset:0;display:flex;align-items:flex-end;justify-content:center;pointer-events:none}
  .bar{width:min(92vw,1800px);margin-bottom:min(3vh,36px);display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:10px}
  .plate{display:flex;align-items:center;gap:12px;height:min(9vh,90px);padding:10px 16px;border-radius:14px;border:1px solid #ffffff26;background:#0b1020cc;backdrop-filter:blur(6px);box-shadow:0 6px 26px #0006}
  .plate.right{justify-content:flex-end}
  .corner{font-weight:900;font-size:clamp(12px,1.2vw,16px);opacity:.9;letter-spacing:.4px}
  .corner.red{color:#ff6b81}.corner.blue{color:#7abbff}
  .name{font-weight:900;font-size:clamp(18px,2.2vw,32px);letter-spacing:.5px;text-transform:uppercase;text-shadow:0 2px 10px #000a;max-width:38vw;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .meta{font-size:clamp(10px,1vw,14px);opacity:.85;display:flex;gap:8px;margin-top:2px}
  .pill{padding:2px 8px;border-radius:999px;border:1px solid #ffffff22;background:#ffffff14}
  .center{display:flex;align-items:center;justify-content:center;flex-direction:column;gap:4px;min-width:min(20vw,380px)}
  .time{font-weight:900;line-height:1;font-size:clamp(38px,6.6vw,110px);letter-spacing:1px;text-shadow:0 6px 22px #000c}
  .round{font-size:clamp(12px,1.1vw,16px);letter-spacing:.3px;padding:4px 10px;border-radius:999px;border:1px solid #ffffff22;background:#0b1020cc}
  .status{position:absolute;left:min(2.2vw,42px);top:min(2.2vh,42px);font-weight:800;font-size:clamp(12px,1vw,16px);padding:6px 12px;border-radius:999px;border:1px solid #ffffff22;background:#0b1020cc;backdrop-filter:blur(6px)}
  .paused{color:#ffd36b}.rest{color:#7abbff}.live{color:#7dffa3}.err{color:#ff7d7d}.wait{color:#ffd36b}
  .debug{position:absolute;right:min(2.2vw,42px);top:min(2.2vh,42px);font-size:12px;opacity:.75;background:#0b1020cc;border:1px solid #ffffff22;border-radius:10px;padding:6px 10px;max-width:min(40vw,520px)}
  .logo{width:min(7vh,64px);height:min(7vh,64px);object-fit:contain;border-radius:8px;background:#0006;display:none}
  .has-logo .logo{display:block}
</style>
</head>
<body>
<div class="wrap">
  <div id="status" class="status">CONECTANDO…</div>
  <div id="dbg" class="debug"></div>

  <div class="bar">
    <div id="left" class="plate">
      <img id="redLogo" class="logo" alt="">
      <div>
        <div class="corner red">RINCÓN ROJO</div>
        <div id="redName" class="name">ROJO</div>
        <div class="meta"><span id="redEsc" class="pill" style="display:none"></span><span id="redTags" class="pill" style="display:none"></span></div>
      </div>
    </div>

    <div class="center">
      <div id="time" class="time">3:00</div>
      <div id="round" class="round">Round 1</div>
    </div>

    <div id="right" class="plate right">
      <div>
        <div class="corner blue" style="text-align:right">RINCÓN AZUL</div>
        <div id="blueName" class="name" style="text-align:right">AZUL</div>
        <div class="meta" style="justify-content:flex-end"><span id="blueTags" class="pill" style="display:none"></span><span id="blueEsc" class="pill" style="display:none"></span></div>
      </div>
      <img id="blueLogo" class="logo" alt="">
    </div>
  </div>
</div>

<script>
const peleaId = <?= (int)$pelea_id ?>;

const UI = {
  status:document.getElementById('status'),
  dbg:document.getElementById('dbg'),
  time:document.getElementById('time'),
  round:document.getElementById('round'),
  left:document.getElementById('left'),
  right:document.getElementById('right'),
  redName:document.getElementById('redName'),
  redEsc:document.getElementById('redEsc'),
  redLogo:document.getElementById('redLogo'),
  redTags:document.getElementById('redTags'),
  blueName:document.getElementById('blueName'),
  blueEsc:document.getElementById('blueEsc'),
  blueLogo:document.getElementById('blueLogo'),
  blueTags:document.getElementById('blueTags'),
};

let last=null, errCount=0;

function fmt(sec){sec=Math.max(0,Math.floor(sec||0));const m=Math.floor(sec/60),s=String(sec%60).padStart(2,'0');return `${m}:${s}`;}
function textOrHide(el,val){ if(!el)return; if(val&&String(val).trim()!==''){el.textContent=val;el.style.display='';}else{el.textContent='';el.style.display='none';} }

// ——— Estado amigable (prioriza running/activo sobre paused) ———
function computeFlags(tm){
  const running = !!(tm.running ?? tm.activo ?? 0);
  const rest    = !!tm.en_descanso;
  // si viene paused=1 pero running=1, lo tomamos como EN VIVO
  const paused  = !!tm.paused && !running && !rest;
  return { running, rest, paused };
}

function paint(){
  if(!last) return;
  const A=last.azul||{}, R=last.rojo||{}, tm=last.timer||{};
  const flags = computeFlags(tm);

  UI.redName.textContent=R.nombre||'ROJO';
  UI.blueName.textContent=A.nombre||'AZUL';
  textOrHide(UI.redEsc,R.escuela); textOrHide(UI.blueEsc,A.escuela);

  if(R.logo){ UI.redLogo.src=R.logo; UI.left.classList.add('has-logo'); } else { UI.left.classList.remove('has-logo'); }
  if(A.logo){ UI.blueLogo.src=A.logo; UI.right.classList.add('has-logo'); } else { UI.right.classList.remove('has-logo'); }

  const rTags=[R.division,R.peso,R.modalidad].filter(Boolean).join(' • ');
  const aTags=[A.division,A.peso,A.modalidad].filter(Boolean).join(' • ');
  textOrHide(UI.redTags,rTags); textOrHide(UI.blueTags,aTags);

  // —— Tiempo restante ——
  let remain=null;
  if (typeof tm.remaining === 'number') {
    // servidor ya lo calcula
    remain = tm.remaining;
  } else {
    const dur = flags.rest ? (tm.dur_descanso||tm.descanso||60) : (tm.dur_round||tm.duracion||180);
    if (typeof tm.elapsed === 'number') {
      // si el servidor da elapsed, lo usamos
      remain = Math.max(0, dur - tm.elapsed);
    } else if (tm.epoch_inicio) {
      const now = Math.floor(Date.now()/1000);
      const elapsed = Math.max(0, now - (tm.epoch_inicio||0));
      // si está pausado y hay remaining_at_pause, lo congelamos ahí
      if (flags.paused && typeof tm.remaining_at_pause === 'number') {
        remain = tm.remaining_at_pause;
      } else {
        remain = Math.max(0, dur - elapsed);
      }
    } else {
      remain = dur; // fallback
    }
  }

  UI.time.textContent = fmt(remain);
  UI.round.textContent = 'Round ' + (tm.ronda || tm.ronda_actual || 1);

  // —— Etiqueta de estado ——
  let lbl='EN VIVO', cls='live';
  if (flags.rest){ lbl='DESCANSO'; cls='rest'; }
  else if (flags.paused){ lbl='PAUSADO'; cls='paused'; }
  else if (!flags.running){ lbl='LISTO'; cls='wait'; }
  UI.status.className='status '+cls;
  UI.status.textContent=lbl;

  // Debug visible para diagnosticar
  if (UI.dbg){
    UI.dbg.textContent =
      `running:${flags.running?'1':'0'}  paused:${tm.paused?'1':'0'}  descanso:${flags.rest?'1':'0'}  `+
      `rem:${typeof remain==='number'?remain:'?'}  dur:${tm.dur_round||tm.duracion||180}  `+
      `epoch:${tm.epoch_inicio||'-'}  pull:${new Date().toLocaleTimeString()}`;
  }
}

// ——— Polling a la API (URL relativa a esta misma carpeta) ———
function apiUrl(){
  const u = new URL('combate_en_vivo.php', location.href);
  u.searchParams.set('ajax','estado');
  u.searchParams.set('pelea_id', String(peleaId));
  u.searchParams.set('_', String(Date.now()));
  return u.toString();
}

async function pull(){
  try{
    const r = await fetch(apiUrl(), {cache:'no-store', credentials:'same-origin'});
    if(!r.ok) throw new Error('HTTP '+r.status);
    const j = await r.json();
    if(!j || !j.ok) throw new Error('payload');
    last = j.data; errCount = 0;
    if(UI.status && (UI.status.textContent==='CONECTANDO…' || UI.status.classList.contains('err'))){
      UI.status.textContent='EN VIVO'; UI.status.className='status live';
    }
    paint();
  }catch(e){
    errCount++;
    if(UI.status){ UI.status.textContent='ERROR ('+errCount+')'; UI.status.className='status err'; }
    if(UI.dbg){ UI.dbg.textContent='API ERROR • '+ new Date().toLocaleTimeString(); }
  }
}

pull();
setInterval(pull, 1000);   // 1s
setInterval(paint, 250);   // seguridad
</script>
</body>
</html>
