<?php
// overlay_obs.php — Marcador simple y transparente para OBS (lee combate_en_vivo.php?ajax=estado)
if (session_status() === PHP_SESSION_NONE) session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$pelea_id = isset($_GET['pelea_id']) ? (int)$_GET['pelea_id'] : 0;
if ($pelea_id<=0){ http_response_code(400); echo 'Falta pelea_id'; exit; }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Overlay OBS</title>
<style>
  html,body{margin:0;padding:0;background:transparent;overflow:hidden}
  .wrap{position:absolute;inset:0;display:flex;flex-direction:column;justify-content:space-between;font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif}
  .top{display:flex;gap:16px;align-items:center;justify-content:center;margin:12px}
  .card{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:14px;border:1px solid #ffffff33;background:#0b102099;backdrop-filter:blur(6px);color:#eef6ff}
  .red{border-color:#ff455a99;background:#2b0d1299}
  .blue{border-color:#4aa3ff99;background:#0d1a2b99}
  .name{font-weight:800;font-size:22px;letter-spacing:.2px}
  .esc{font-size:12px;opacity:.85}
  .tag{font-size:12px;padding:4px 8px;border-radius:999px;border:1px solid #ffffff22;background:#ffffff10;margin-left:6px}
  .timerBox{display:flex;flex-direction:column;align-items:center;gap:6px}
  .timer{font-size:96px;font-weight:900;line-height:1;color:#fff;text-shadow:0 2px 10px #000a}
  .ronda{font-size:18px;color:#d8e6ff}
  .footer{display:flex;justify-content:center;margin:10px}
  .badge{padding:6px 10px;border-radius:999px;border:1px solid #ffffff22;background:#0b102099;color:#cfe2ff}
  .logo{width:42px;height:42px;object-fit:contain;border-radius:8px;background:#0006}
</style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <div id="red" class="card red">
      <img id="redLogo" class="logo" alt="">
      <div>
        <div id="redName" class="name">Rojo</div>
        <div id="redEsc" class="esc"></div>
        <div><span id="redTags" class="tag"></span></div>
      </div>
    </div>
    <div class="timerBox">
      <div id="timer" class="timer">0:00</div>
      <div id="rondaLbl" class="ronda">Round 1</div>
    </div>
    <div id="blue" class="card blue">
      <img id="blueLogo" class="logo" alt="">
      <div>
        <div id="blueName" class="name">Azul</div>
        <div id="blueEsc" class="esc"></div>
        <div><span id="blueTags" class="tag"></span></div>
      </div>
    </div>
  </div>
  <div class="footer">
    <div id="estado" class="badge">EN VIVO</div>
  </div>
</div>
<script>
const peleaId = <?= (int)$pelea_id ?>;
const T = {
  timer: document.getElementById('timer'),
  ronda: document.getElementById('rondaLbl'),
  redName: document.getElementById('redName'),
  redEsc: document.getElementById('redEsc'),
  redLogo: document.getElementById('redLogo'),
  redTags: document.getElementById('redTags'),
  blueName: document.getElementById('blueName'),
  blueEsc: document.getElementById('blueEsc'),
  blueLogo: document.getElementById('blueLogo'),
  blueTags: document.getElementById('blueTags'),
  estado: document.getElementById('estado'),
};
let last = null;

function fmt(s){s=Math.max(0,Math.floor(s)); const m=Math.floor(s/60), ss=String(s%60).padStart(2,'0'); return `${m}:${ss}`;}

async function fetchEstado(){
  try{
    const r = await fetch('combate_en_vivo.php?ajax=estado&pelea_id='+peleaId, {cache:'no-store'});
    const j = await r.json();
    if(!j || !j.ok) return;
    last = j.data;
    paint();
  }catch(e){}
}

function paint(){
  if(!last) return;
  const A = last.azul||{}, R = last.rojo||{}, tm = last.timer||{};
  // Nombres/escuelas/logos
  T.redName.textContent  = R.nombre || 'Rojo';
  T.redEsc.textContent   = R.escuela || '';
  T.redLogo.src = R.logo || '';
  T.redLogo.style.display = R.logo ? 'block':'none';
  T.redTags.textContent = [R.division, R.peso, R.modalidad].filter(Boolean).join(' • ');

  T.blueName.textContent = A.nombre || 'Azul';
  T.blueEsc.textContent  = A.escuela || '';
  T.blueLogo.src = A.logo || '';
  T.blueLogo.style.display = A.logo ? 'block':'none';
  T.blueTags.textContent = [A.division, A.peso, A.modalidad].filter(Boolean).join(' • ');

  // Timer (acepta dos esquemas: epoch o remaining)
  let remain = null;
  if (typeof tm.remaining === 'number'){ // esquema nuevo
    remain = tm.remaining;
  } else if (tm.epoch_inicio && (tm.dur_round || tm.duracion)){ // compat epoch
    const dur = tm.en_descanso ? (tm.dur_descanso||tm.descanso||60) : (tm.dur_round||tm.duracion||180);
    const elapsed = Math.floor(Date.now()/1000) - (tm.epoch_inicio||0);
    remain = Math.max(0, dur - Math.max(0,elapsed));
  }
  T.timer.textContent = fmt(remain==null?0:remain);
  T.ronda.textContent = 'Round ' + (tm.ronda || tm.ronda_actual || 1);

  // Estado
  let lbl = 'EN VIVO';
  if (tm.en_descanso) lbl = 'DESCANSO';
  if (tm.paused) lbl = 'PAUSADO';
  if (!tm.activo) lbl = 'LISTO';
  T.estado.textContent = lbl;
}

// Pull/paint loop
fetchEstado();
setInterval(fetchEstado, 1000);

// Resiliencia: si se corta el poll, mantené el repaint con último estado
setInterval(paint, 250);
</script>
</body>
</html>
