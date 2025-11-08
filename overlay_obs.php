<?php
// overlay_obs.php — Overlay OBS (UFC/ESPN style) con normalización de flags y timer suave
if (session_status() === PHP_SESSION_NONE) session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$pelea_id = isset($_GET['pelea_id']) ? (int)$_GET['pelea_id'] : 0;
if ($pelea_id <= 0) { http_response_code(400); echo 'Falta pelea_id'; exit; }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
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

let last=null;           // último payload del server
let errCount=0;
let clientRemain=null;   // cuenta local en segundos
let lastTick=Date.now(); // para descontar suave
let enDesc=false, paused=false, activo=false;

function asBool(v){
  // 1/0, "1"/"0", true/false
  return v===true || v===1 || v==="1";
}
function asNum(v, def=0){
  const n = Number(v);
  return Number.isFinite(n) ? n : def;
}
function fmt(sec){
  sec = Math.max(0, Math.floor(sec||0));
  const m = Math.floor(sec/60);
  const s = String(sec%60).padStart(2,'0');
  return `${m}:${s}`;
}
function textOrHide(el,val){
  if(!el) return;
  if(val && String(val).trim()!==''){el.textContent=val; el.style.display='';}
  else {el.textContent=''; el.style.display='none';}
}

function paintStatic(){
  if(!last) return;
  const A=last.azul||{}, R=last.rojo||{}, tm=last.timer||{};

  UI.redName.textContent = R.nombre || 'ROJO';
  UI.blueName.textContent= A.nombre || 'AZUL';
  textOrHide(UI.redEsc, R.escuela);
  textOrHide(UI.blueEsc,A.escuela);

  if(R.logo){ UI.redLogo.src=R.logo; UI.left.classList.add('has-logo'); } else { UI.left.classList.remove('has-logo'); }
  if(A.logo){ UI.blueLogo.src=A.logo; UI.right.classList.add('has-logo'); } else { UI.right.classList.remove('has-logo'); }

  const rTags = [R.division,R.peso,R.modalidad].filter(Boolean).join(' • ');
  const aTags = [A.division,A.peso,A.modalidad].filter(Boolean).join(' • ');
  textOrHide(UI.redTags,rTags);
  textOrHide(UI.blueTags,aTags);

  UI.round.textContent = 'Round ' + (tm.ronda || tm.ronda_actual || 1);
}

function updateStatus(){
  let lbl='EN VIVO', cls='live';
  if(enDesc){ lbl='DESCANSO'; cls='rest'; }
  if(paused){ lbl='PAUSADO'; cls='paused'; }
  if(!activo){ lbl='LISTO'; cls='wait'; }
  UI.status.className = 'status '+cls;
  UI.status.textContent = lbl;
}

async function pull(){
  // MISMO HOST: evita CORS y proxies que cacheen
  const base = location.origin;
  const url  = `${base}/combate_en_vivo.php?ajax=estado&pelea_id=${peleaId}&_=${Date.now()}`;
  try{
    const r = await fetch(url, {cache:'no-store', credentials:'omit'});
    if(!r.ok) throw new Error('HTTP '+r.status);
    const j = await r.json();
    if(!j || !j.ok) throw new Error('payload');

    last = j.data || {};
    const tm = last.timer || {};

    // Normalizar flags
    enDesc = asBool(tm.en_descanso);
    paused = asBool(tm.paused);
    // "activo" puede venir como 'activo' o 'running'
    activo = asBool(tm.activo) || asBool(tm.running);

    // Calcular remaining base
    if (typeof tm.remaining === 'number') {
      clientRemain = tm.remaining;
    } else {
      const durRound = asNum(tm.dur_round || tm.duracion, 180);
      const durDesc  = asNum(tm.dur_descanso || tm.descanso, 60);
      const epoch    = asNum(tm.epoch_inicio, 0); // segundos desde 1970
      if (epoch > 0 && activo && !paused) {
        const now = Math.floor(Date.now()/1000);
        const dur = enDesc ? durDesc : durRound;
        clientRemain = Math.max(0, dur - Math.max(0, now - epoch));
      } else {
        // Fallback si está pausado o sin epoch: mostrar tope del período actual
        clientRemain = enDesc ? durDesc : (asNum(tm.remaining, durRound));
      }
    }

    lastTick = Date.now();
    paintStatic();
    updateStatus();
    errCount = 0;
  }catch(e){
    errCount++;
    UI.status.className = 'status err';
    UI.status.textContent = 'ERROR ('+errCount+')';
  }
}

// Ticker local de 60 FPS aprox para que el número corra suave
function tick(){
  const now = Date.now();
  const dt = (now - lastTick)/1000; // segundos
  lastTick = now;

  if (clientRemain != null && activo && !paused) {
    clientRemain = Math.max(0, clientRemain - dt);
  }
  UI.time.textContent = fmt(clientRemain==null?0:clientRemain);

  requestAnimationFrame(tick);
}

// Primeros disparos
pull();
setInterval(pull, 1000); // actualizar desde servidor cada 1s (con cache-buster)
requestAnimationFrame(tick);
</script>
</body>
</html>
