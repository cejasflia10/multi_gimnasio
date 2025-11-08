<?php
// overlay_obs.php — Overlay para OBS leyendo estado “tal cual” del panel
// Fuente del estado: combate_en_vivo.php?ajax=estado&pelea_id=...
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
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Overlay ESPN</title>
<style>
  html,body{margin:0;padding:0;background:transparent;overflow:hidden}
  body{font-family:system-ui, Segoe UI, Roboto, Arial, sans-serif; color:#fff}
  .wrap{position:absolute; inset:0; display:flex; align-items:flex-end; justify-content:center; pointer-events:none;}
  .bar{
    width:min(92vw,1800px); margin-bottom:min(3vh,36px);
    display:grid; grid-template-columns:1fr auto 1fr; align-items:center; gap:10px;
  }
  .plate{display:flex;align-items:center;gap:12px;height:min(9vh,90px);padding:10px 16px;border-radius:14px;border:1px solid #ffffff26;background:#0b1020cc;backdrop-filter: blur(6px);box-shadow: 0 6px 26px #0006}
  .plate.right{justify-content:flex-end}
  .corner{font-weight:900;font-size:clamp(12px,1.2vw,16px);opacity:.9;letter-spacing:.4px}
  .corner.red{color:#ff6b81}.corner.blue{color:#7abbff}
  .name{font-weight:900;font-size:clamp(18px,2.2vw,32px);letter-spacing:.5px;text-transform:uppercase;text-shadow:0 2px 10px #000a;max-width:38vw;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .center{display:flex;align-items:center;justify-content:center;flex-direction:column;gap:4px;min-width:min(20vw,380px)}
  .time{font-weight:900;line-height:1;font-size:clamp(38px,6.6vw,110px);letter-spacing:1px;text-shadow:0 6px 22px #000c}
  .round{font-size:clamp(12px,1.1vw,16px);letter-spacing:.3px;padding:4px 10px;border-radius:999px;border:1px solid #ffffff22;background:#0b1020cc}
  .status{position:absolute;right:min(2.2vw,42px);top:min(2.2vh,42px);font-weight:800;font-size:clamp(12px,1vw,16px);padding:6px 12px;border-radius:999px;border:1px solid #ffffff22;background:#0b1020cc;backdrop-filter: blur(6px)}
  .paused{color:#ffd36b}.rest{color:#7abbff}.live{color:#7dffa3}.wait{color:#ffd36b}
</style>
</head>
<body>
<div class="wrap">
  <div id="status" class="status">CONECTANDO…</div>
  <div class="bar">
    <div class="plate">
      <div>
        <div class="corner red">RINCÓN ROJO</div>
        <div id="redName" class="name">ROJO</div>
      </div>
    </div>
    <div class="center">
      <div id="time" class="time">3:00</div>
      <div id="round" class="round">Round 1</div>
    </div>
    <div class="plate right">
      <div>
        <div class="corner blue" style="text-align:right">RINCÓN AZUL</div>
        <div id="blueName" class="name" style="text-align:right">AZUL</div>
      </div>
    </div>
  </div>
</div>

<script>
const PELEA_ID = <?= (int)$pelea_id ?>;

const UI = {
  status: document.getElementById('status'),
  time:   document.getElementById('time'),
  round:  document.getElementById('round'),
  red:    document.getElementById('redName'),
  blue:   document.getElementById('blueName'),
};

const b = v => (v===true || v===1 || v==="1" || v==="true");         // coerción booleana
const n = v => { const x = parseInt(v,10); return Number.isFinite(x)?x:0; };

function fmt(sec){ sec=Math.max(0,Math.floor(sec||0)); const m=Math.floor(sec/60), s=String(sec%60).padStart(2,'0'); return `${m}:${s}`; }

// el overlay NO calcula reloj; prioriza valores del servidor
function getRemaining(tm){
  if (tm==null || typeof tm!=='object') return 180;
  const keys = ['remaining','remain','restante','seg_restantes'];
  for (const k of keys){ if (k in tm) return n(tm[k]); }
  // fallback solo si no viene nada: calcula por epoch_inicio
  if (tm.epoch_inicio){
    const dur = b(tm.en_descanso) ? n(tm.dur_descanso||tm.descanso||60) : n(tm.dur_round||tm.duracion||180);
    const elapsed = Math.floor(Date.now()/1000) - n(tm.epoch_inicio);
    return Math.max(0, dur - Math.max(0, elapsed));
  }
  return n(tm.dur_round||tm.duracion||180);
}

function paint(j){
  if (!j || !j.ok) return;
  const d  = j.data || j.estado || {};
  const tm = d.timer || d; // a veces viene plano

  // Nombres (si vienen)
  if (d.rojo && d.rojo.nombre) UI.red.textContent  = String(d.rojo.nombre).toUpperCase();
  if (d.azul && d.azul.nombre) UI.blue.textContent = String(d.azul.nombre).toUpperCase();

  // Reloj y round
  const rem = getRemaining(tm);
  UI.time.textContent  = fmt(rem);
  UI.round.textContent = 'Round ' + (n(tm.ronda || tm.ronda_actual || 1));

  // Estado visual robusto
  const running = b(tm.running) || (b(tm.activo) && !b(tm.paused));
  let lbl='EN VIVO', cls='live';
  if (b(tm.en_descanso))                 { lbl='DESCANSO'; cls='rest';   }
  else if (b(tm.paused) && !running)     { lbl='PAUSADO';  cls='paused'; }
  else if (!running)                     { lbl='LISTO';    cls='wait';   }
  UI.status.className = 'status ' + cls;
  UI.status.textContent = lbl;
}

async function pull(){
  try{
    const url = `combate_en_vivo.php?ajax=estado&pelea_id=${PELEA_ID}&_=${Date.now()}`;
    const r = await fetch(url, {cache:'no-store', credentials:'omit'});
    if(!r.ok) throw new Error('HTTP '+r.status);
    const j = await r.json();
    paint(j);
  }catch(e){
    UI.status.className = 'status wait';
    UI.status.textContent = 'RECONEXIÓN…';
  }
}

pull();
setInterval(pull, 1000); // cada segundo
</script>
</body>
</html>
