<?php
// overlay_obs.php — Overlay ESPN/UFC para OBS con animación local del timer.
// Funciona aunque el endpoint no actualice remaining en cada request.
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
  .status{position:absolute;right:min(2.2vw,42px);top:min(2.2vh,42px);font-weight:800;font-size:clamp(12px,1vw,16px);padding:6px 12px;border-radius:999px;border:1px solid #ffffff22;background:#0b1020cc;backdrop-filter:blur(6px)}
  .paused{color:#ffd36b}.rest{color:#7abbff}.live{color:#7dffa3}.err{color:#ff7d7d}.wait{color:#ffd36b}
  .logo{width:min(7vh,64px);height:min(7vh,64px);object-fit:contain;border-radius:8px;background:#0006;display:none}
  .has-logo .logo{display:block}
</style>
</head>
<body>
<div class="wrap">
  <div id="status" class="status">CONECTANDO…</div>

  <div class="bar">
    <!-- ROJO -->
    <div id="left" class="plate">
      <img id="redLogo" class="logo" alt="">
      <div>
        <div class="corner red">RINCÓN ROJO</div>
        <div id="redName" class="name">ROJO</div>
        <div class="meta"><span id="redEsc" class="pill" style="display:none"></span><span id="redTags" class="pill" style="display:none"></span></div>
      </div>
    </div>

    <!-- Centro -->
    <div class="center">
      <div id="time" class="time">3:00</div>
      <div id="round" class="round">Round 1</div>
    </div>

    <!-- AZUL -->
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

// UI refs
const UI = {
  status:document.getElementById('status'),
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

// snapshot del server para animar local
let lastPayload=null;
let snap = {
  serverAt: 0,      // epoch ms de última lectura
  remainAt: 180,    // segundos que reportó el server
  running: false,
  rest: false,
  paused: false,
  durRound: 180,
  durRest: 60,
  round: 1
};

function fmt(sec){sec=Math.max(0,Math.floor(sec||0));const m=Math.floor(sec/60),s=String(sec%60).padStart(2,'0');return `${m}:${s}`;}
function textOrHide(el,val){ if(!el)return; if(val&&String(val).trim()!==''){el.textContent=val;el.style.display='';}else{el.textContent='';el.style.display='none';} }
function flag(o,...k){ for(const x of k){ if(o && Object.prototype.hasOwnProperty.call(o,x)) return !!o[x]; } return false; }
function num(o,...k){ for(const x of k){ if(o && o[x]!=null && !isNaN(o[x])) return Number(o[x]); } return null; }

// pinta nombres/logos y estado textual
function paintStatic(data){
  const A=data.azul||{}, R=data.rojo||{};
  UI.redName.textContent=R.nombre||'ROJO';
  UI.blueName.textContent=A.nombre||'AZUL';
  textOrHide(UI.redEsc,R.escuela); textOrHide(UI.blueEsc,A.escuela);

  if(R.logo){ UI.redLogo.src=R.logo; UI.left.classList.add('has-logo'); } else { UI.left.classList.remove('has-logo'); }
  if(A.logo){ UI.blueLogo.src=A.logo; UI.right.classList.add('has-logo'); } else { UI.right.classList.remove('has-logo'); }

  const rTags=[R.division,R.peso,R.modalidad].filter(Boolean).join(' • ');
  const aTags=[A.division,A.peso,A.modalidad].filter(Boolean).join(' • ');
  textOrHide(UI.redTags,rTags); textOrHide(UI.blueTags,aTags);
}

// calcula snapshot desde payload del server (tolerante a claves)
function updateSnapshot(data){
  const tm = data.timer || {};
  const running = flag(tm,'running','activo','en_juego');
  const rest    = flag(tm,'en_descanso','descanso');
  const paused  = flag(tm,'paused','pausado') && !running && !rest;

  const durRound = num(tm,'dur_round','duracion') ?? 180;
  const durRest  = num(tm,'dur_descanso','descanso') ?? 60;
  const roundN   = num(tm,'ronda','ronda_actual') ?? 1;

  // de dónde saco remaining “base”
  let remain = num(tm,'remaining','restante');
  const elapsed = num(tm,'elapsed','transcurrido');
  const epoch   = num(tm,'epoch_inicio','epoch');

  if (remain == null && elapsed != null){
    remain = Math.max(0, (rest?durRest:durRound) - elapsed);
  } else if (remain == null && epoch){
    const now = Math.floor(Date.now()/1000);
    remain = Math.max(0, (rest?durRest:durRound) - Math.max(0, now - epoch));
  }
  if (remain == null) remain = rest?durRest:durRound;

  snap.serverAt = performance.now();  // alta precisión
  snap.remainAt = remain;
  snap.running  = running;
  snap.rest     = rest;
  snap.paused   = paused;
  snap.durRound = durRound;
  snap.durRest  = durRest;
  snap.round    = roundN;

  // Estado visual
  let lbl='EN VIVO', cls='live';
  if (snap.rest){ lbl='DESCANSO'; cls='rest'; }
  else if (snap.paused){ lbl='PAUSADO'; cls='paused'; }
  else if (!snap.running){ lbl='LISTO'; cls='wait'; }
  UI.status.className='status '+cls;
  UI.status.textContent=lbl;

  UI.round.textContent = 'Round ' + snap.round;
}

// anima el cronómetro localmente
function paintClock(){
  let remain = snap.remainAt;
  if (snap.running && !snap.paused){
    const elapsedMs = Math.max(0, performance.now() - snap.serverAt);
    remain = Math.max(0, snap.remainAt - Math.floor(elapsedMs/1000));
  }
  UI.time.textContent = fmt(remain);
}

// endpoints a probar (ajax viejo y API estado)
function endpoints(){
  const base = new URL(location.href);
  const list = [];
  const u1 = new URL('combate_en_vivo.php', base);
  u1.searchParams.set('ajax','estado');
  u1.searchParams.set('pelea_id', String(peleaId));
  u1.searchParams.set('_', String(Date.now()));
  list.push(u1.toString());

  const u2 = new URL('api_combate_estado.php', base);
  u2.searchParams.set('pelea_id', String(peleaId));
  u2.searchParams.set('_', String(Date.now()));
  list.push(u2.toString());

  return list;
}

async function pull(){
  const urls = endpoints();
  for (const url of urls){
    try{
      const r = await fetch(url, {cache:'no-store', credentials:'same-origin'});
      if(!r.ok) continue;
      const raw = await r.json();
      const data = (raw && raw.ok && raw.data) ? raw.data
                   : (raw && (raw.timer || raw.azul || raw.rojo)) ? raw
                   : null;
      if (!data) continue;

      lastPayload = data;
      paintStatic(data);
      updateSnapshot(data);
      return; // listo
    }catch(e){ /* probar siguiente */ }
  }
  // si nada respondió:
  UI.status.className='status err';
  UI.status.textContent='ERROR';
}

// ciclos: poll al server + animación local a 4 Hz
pull();
setInterval(pull, 1000);
setInterval(paintClock, 250);
</script>
</body>
</html>
