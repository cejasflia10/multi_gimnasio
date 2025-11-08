<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$evento_id = isset($_GET['evento_id']) ? (int)$_GET['evento_id'] : 0;
if ($evento_id<=0){ http_response_code(400); echo "Falta evento_id"; exit; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Overlay — Evento #<?= (int)$evento_id ?></title>
<style>
  body{margin:0;background:transparent;color:#fff;font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif}
  .wrap{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 16px;background:rgba(0,0,0,.35);backdrop-filter:blur(4px);border:1px solid #ffffff22;border-radius:14px}
  .corner{display:flex;align-items:center;gap:10px;max-width:38%}
  .corner img{width:54px;height:54px;object-fit:contain;background:#000;border:1px solid #ffffff22;border-radius:10px}
  .name{font-weight:900;font-size:22px;line-height:1.05}
  .esc{font-size:13px;opacity:.85}
  .timer{font-size:72px;font-weight:1000;letter-spacing:1px;min-width:180px;text-align:center}
  .round{font-size:14px;text-align:center;opacity:.9;margin-top:-4px}
  .red  .name{color:#ff8a80}
  .blue .name{color:#80c0ff}
</style>
</head>
<body>
<div class="wrap" id="overlay">
  <div class="corner red" id="redC">
    <img id="logoRojo" alt="">
    <div>
      <div class="name" id="nomRojo">Rojo</div>
      <div class="esc" id="escRojo"></div>
    </div>
  </div>

  <div>
    <div class="timer" id="timer">0:00</div>
    <div class="round" id="round">R1</div>
  </div>

  <div class="corner blue" id="blueC">
    <img id="logoAzul" alt="">
    <div>
      <div class="name" id="nomAzul">Azul</div>
      <div class="esc" id="escAzul"></div>
    </div>
  </div>
</div>

<script>
(function(){
  const eventoId = <?= (int)$evento_id ?>;
  let ver = 0;              // versión (UNIX de actualizado_en)
  let state = null;         // última foto de estado
  let smoothTick = null;    // intervalo local para pintar suave

  const nomRojo = document.getElementById('nomRojo');
  const escRojo = document.getElementById('escRojo');
  const logoRojo= document.getElementById('logoRojo');

  const nomAzul = document.getElementById('nomAzul');
  const escAzul = document.getElementById('escAzul');
  const logoAzul= document.getElementById('logoAzul');

  const timerEl = document.getElementById('timer');
  const roundEl = document.getElementById('round');

  function setLogo(imgEl, url){
    if (url && typeof url==='string' && url.trim()!==''){
      imgEl.src = url; imgEl.style.visibility='visible';
    } else {
      imgEl.removeAttribute('src'); imgEl.style.visibility='hidden';
    }
  }
  function fmt(s){
    s = Math.max(0, s|0);
    const m = Math.floor(s/60), ss = String(s%60).padStart(2,'0');
    return `${m}:${ss}`;
  }

  // Calcula el REMAINING "reflejado" del MISMO reloj:
  // - Si running=1 => usamos epoch_inicio + (duración/descanso) para derivar remaining
  // - Si paused=1  => usamos remaining enviado por mesa
  function calcRemaining(timer){
    if (!timer) return 0;
    const now = Math.floor(Date.now()/1000);
    if (timer.running && timer.epoch_inicio){
      const base = timer.en_descanso ? (timer.descanso|0) : (timer.duracion|0);
      const elapsed = Math.max(0, now - (timer.epoch_inicio|0));
      return Math.max(0, base - elapsed);
    }
    return Math.max(0, (timer.remaining|0));
  }

  function repaint(){
    if (!state) return;
    const t = state.timer;
    const rem = calcRemaining(t);
    timerEl.textContent = fmt(rem);
    const rlabel = `R${(t?.ronda||t?.ronda_actual||1)}${t?.en_descanso? ' (descanso)':''}`;
    roundEl.textContent = rlabel;
  }

  function applyState(j){
    state = j.data;
    // nombres/logos
    const az = state.azul || {}; const ro = state.rojo || {};
    nomAzul.textContent = az.nombre || 'Azul';
    escAzul.textContent = az.escuela || '';
    setLogo(logoAzul, az.logo || '');

    nomRojo.textContent = ro.nombre || 'Rojo';
    escRojo.textContent = ro.escuela || '';
    setLogo(logoRojo, ro.logo || '');

    // round/timer
    repaint();

    // refresco suave (cada 200ms) SOLO para pintar usando epoch/remaining
    if (smoothTick) clearInterval(smoothTick);
    smoothTick = setInterval(repaint, 200);
  }

  async function poll(){
    try{
      const ctrl = new AbortController(); const to = setTimeout(()=>ctrl.abort(), 30000);
      const r = await fetch(`combate_sync.php?ajax=poll&evento_id=${encodeURIComponent(eventoId)}&since=${ver}`, {cache:'no-store', signal:ctrl.signal});
      clearTimeout(to);
      const j = await r.json();
      if (j && j.ok){
        if (j.changed && j.data){
          ver = j.version|0;
          applyState(j);
        }
      }
    }catch(e){}
    // Repetir inmediatamente (long-poll continuo)
    setTimeout(poll, 10);
  }

  // Inicio
  poll();
})();
</script>
</body>
</html>
