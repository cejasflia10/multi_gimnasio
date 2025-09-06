<?php
if (session_status()===PHP_SESSION_NONE) session_start();

/* Guardia de sesión con return_to */
if (empty($_SESSION['evento_usuario_id'])) {
  $return_to = $_SERVER['REQUEST_URI'] ?? 'scan_tickets.php';
  header('Location: login_evento.php?return_to=' . urlencode($return_to));
  exit;
}

require_once __DIR__.'/conexion.php';
if (!isset($conexion)||!($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$evento_id = isset($_GET['evento_id']) ? (int)$_GET['evento_id'] : 0;
if ($evento_id<=0){ http_response_code(400); exit('Falta evento_id'); }

/* (Opcional) Traer título para mostrarlo */
$ev = null;
if ($st=$conexion->prepare("SELECT titulo FROM eventos_deportivos WHERE id=? LIMIT 1")){
  $st->bind_param('i',$evento_id); $st->execute(); $ev=$st->get_result()->fetch_assoc(); $st->close();
}
$titulo = $ev['titulo'] ?? ('Evento #'.$evento_id);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Escanear tickets — <?= h($titulo) ?></title>
<style>
  :root{
    --bg:#0a0a0a; --fg:#e6eef4; --mut:#9ecbff; --brand:#d4af37;
    --card:#0f1720; --bd:#1f2a33; --line:#222;
    --okbg:#0f251b; --okbd:#164b31; --oktx:#b6f3d1;
    --badbg:#2a1414; --badbd:#5e2626; --badt:#ffb4b4;
  }
  html,body{background:var(--bg);color:var(--fg);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif;margin:0}
  a{color:var(--brand);text-decoration:none}
  a:focus,button:focus,select:focus,input:focus{outline:2px dashed var(--brand); outline-offset:2px}

  .wrap{max-width:1100px;margin:12px auto;padding:12px}
  .header{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:10px}
  .pill{display:inline-block;padding:.25rem .6rem;border-radius:999px;border:1px solid #3b3b3b;font-size:.85rem;color:#ddd}
  .btn{display:inline-flex;align-items:center;gap:.45rem;padding:.58rem .9rem;border-radius:10px;border:1px solid var(--line);background:#151515;color:var(--brand);text-decoration:none;font-weight:600;cursor:pointer}
  .btn.gray{background:#1b1b1b;color:#ddd}
  .btn.red{background:#7a1f1f;color:#fff;border-color:#8f2a2a}
  .btn.full{width:100%;justify-content:center}

  .grid{display:grid;grid-template-columns:1.2fr .8fr;gap:12px;align-items:start}
  @media(max-width:950px){ .grid{grid-template-columns:1fr} }

  .card{background:var(--card);border:1px solid var(--bd);border-radius:12px;padding:14px}
  h2{margin:0 0 6px}

  /* Video + overlay */
  .video-wrap{position:relative}
  video{width:100%;border-radius:12px;border:1px solid var(--line);background:#000}
  .overlay{
    position:absolute; inset:0; pointer-events:none;
    display:grid; place-items:center;
  }
  .frame{
    width:72%; max-width:520px; aspect-ratio:1/1;
    border:3px solid rgba(212,175,55,.8); border-radius:18px;
    box-shadow:0 0 0 9999px rgba(0,0,0,.25) inset;
  }

  .row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
  .controls select, .controls input{
    padding:.56rem .7rem;border-radius:10px;border:1px solid var(--line);background:#101010;color:var(--fg)
  }
  .controls .btn{height:42px}

  .log{display:grid;gap:8px;margin-top:10px}
  .ok{background:var(--okbg);border:1px solid var(--okbd);color:var(--oktx);border-radius:10px;padding:10px}
  .bad{background:var(--badbg);border:1px solid var(--badbd);color:var(--badt);border-radius:10px;padding:10px}

  /* Cards (mobile) para historial, si agregás tabla en el futuro */
  @media(max-width:580px){
    .btn{flex:1 1 48%}
  }
</style>
</head>
<body>
<div class="wrap">
  <?php @include __DIR__ . '/menu_eventos.php'; ?>

  <div class="header">
    <a class="btn gray" href="ver_evento.php?id=<?= (int)$evento_id ?>">← Volver al evento</a>
    <span class="pill">Evento #<?= (int)$evento_id ?></span>
    <span class="pill"><?= h($titulo) ?></span>
  </div>

  <div class="grid">
    <!-- Columna izq: video y controles -->
    <section class="card">
      <h2>🔎 Escanear tickets</h2>

      <div class="video-wrap">
        <video id="vid" playsinline muted></video>
        <div class="overlay"><div class="frame" aria-hidden="true"></div></div>
      </div>

      <div class="controls" style="margin-top:10px">
        <div class="row">
          <select id="camera"></select>
          <button class="btn" id="start">▶ Iniciar cámara</button>
          <button class="btn gray" id="stop">⏹ Detener</button>
          <button class="btn" id="torch" aria-pressed="false">🔦 Linterna</button>
        </div>
        <div class="row" style="margin-top:8px">
          <select id="gate">
            <option value="Acceso principal" selected>Acceso principal</option>
            <option value="Platea">Platea</option>
            <option value="VIP">VIP</option>
            <option value="Backstage">Backstage</option>
          </select>
          <input id="manual" placeholder="Pegá o escribí el código del ticket y presioná Enter" />
          <button class="btn" id="validarManual">Validar</button>
        </div>
      </div>

      <div class="log" id="log" aria-live="polite" aria-atomic="false"></div>
    </section>

    <!-- Columna der: ayuda rápida -->
    <aside class="card">
      <h3 style="margin:0 0 6px">Consejos</h3>
      <ul style="margin:.2rem 0 0 1.2rem; line-height:1.5">
        <li>Preferí la <b>cámara trasera</b> en celulares (mejor enfoque).</li>
        <li>Si hay poca luz, probá la <b>linterna</b>.</li>
        <li>Podés pegar un link con <code>?code=XXXX</code>; se extrae el código.</li>
        <li>Al validar, se envía también la <b>puerta</b> seleccionada.</li>
      </ul>
      <div style="margin-top:10px">
        <a class="btn full" href="ver_entradas_vendidas.php?evento_id=<?= (int)$evento_id ?>">🎟️ Ver entradas vendidas</a>
      </div>
    </aside>
  </div>
</div>

<!-- ZXing browser library -->
<script src="https://unpkg.com/@zxing/browser@latest"></script>
<script>
const eventoId = <?= (int)$evento_id ?>;
const $ = (q)=>document.querySelector(q);
const logBox = $('#log');
const video = $('#vid');
const camSel = $('#camera');
const torchBtn = $('#torch');
const gateSel = $('#gate');
const manual = $('#manual');
const validarBtn = $('#validarManual');

let codeReader = null;
let controls = null;
let currentStreamTrack = null;
let torchOn = false;
let lastCode = '';
let lastTime = 0;

/* Soniditos (WebAudio) */
const ctx = (window.AudioContext? new AudioContext() : null);
function beep(ok=true){
  if(!ctx) return;
  const o = ctx.createOscillator(); const g = ctx.createGain();
  o.connect(g); g.connect(ctx.destination);
  o.type = ok ? 'triangle' : 'sawtooth';
  o.frequency.value = ok ? 880 : 220;
  g.gain.value = .05;
  o.start(); setTimeout(()=>{o.stop()}, 120);
}

/* Utilidades UI */
function addLog(html, ok=false){
  const div = document.createElement('div');
  div.className = ok ? 'ok' : 'bad';
  div.innerHTML = html;
  logBox.prepend(div);
}
function vibra(ms){ if(navigator.vibrate) navigator.vibrate(ms); }

/* Cargar cámaras */
async function loadCams(){
  camSel.innerHTML = '';
  try{
    const devices = await navigator.mediaDevices.enumerateDevices();
    const cams = devices.filter(d=>d.kind==='videoinput');
    if(cams.length===0){
      const opt = document.createElement('option');
      opt.value=''; opt.textContent='(No hay cámaras)';
      camSel.appendChild(opt);
      return;
    }
    cams.forEach((d,i)=>{
      const opt = document.createElement('option');
      opt.value = d.deviceId;
      const label = d.label || `Cámara ${i+1}`;
      opt.textContent = label;
      camSel.appendChild(opt);
    });
    // Elegir la trasera si existe
    const back = cams.find(d => /back|rear/i.test(d.label));
    if(back) camSel.value = back.deviceId;
  }catch(e){
    const opt = document.createElement('option');
    opt.value=''; opt.textContent='(Permitir cámara y reintentar)';
    camSel.appendChild(opt);
  }
}

/* Iniciar lectura */
async function start(){
  try{
    if(codeReader){ await stop(); }
    codeReader = new ZXing.BrowserMultiFormatReader();
    const deviceId = camSel.value || null;
    controls = await codeReader.decodeFromVideoDevice(deviceId, video, async (res, err)=>{
      if(res){
        const txt = res.getText();
        handleScan(txt);
      }
    });
    // Guardar track para linterna
    const stream = video.srcObject;
    currentStreamTrack = stream ? stream.getVideoTracks()[0] : null;
    // Intentar activar continuous autofocus
    if(currentStreamTrack && currentStreamTrack.getCapabilities){
      const caps = currentStreamTrack.getCapabilities();
      if(caps.focusMode && caps.focusMode.includes('continuous')){
        await currentStreamTrack.applyConstraints({ advanced:[{ focusMode:'continuous' }] });
      }
      // Si torch estaba ON y existe, restaurarlo
      if(torchOn && caps.torch){
        await currentStreamTrack.applyConstraints({ advanced:[{ torch:true }] });
      }
    }
  }catch(e){
    addLog('No se pudo iniciar la cámara: '+e, false);
  }
}

/* Detener */
async function stop(){
  try{
    if(controls){ controls.stop(); controls=null; }
    if(codeReader){ codeReader.reset(); codeReader = null; }
    if(video.srcObject){
      video.srcObject.getTracks().forEach(t=>t.stop());
      video.srcObject = null;
    }
    currentStreamTrack = null;
  }catch(_){}
}

/* Linterna */
async function toggleTorch(){
  if(!currentStreamTrack || !currentStreamTrack.getCapabilities){ return; }
  const caps = currentStreamTrack.getCapabilities();
  if(!caps.torch){ addLog('Este dispositivo no soporta linterna.', false); return; }
  torchOn = !torchOn;
  try{
    await currentStreamTrack.applyConstraints({ advanced:[{ torch: torchOn }] });
    torchBtn.setAttribute('aria-pressed', torchOn ? 'true' : 'false');
  }catch(e){
    torchOn = !torchOn; // revert
    addLog('No se pudo cambiar linterna: '+e, false);
  }
}

/* Normalizar extracción de code (acepta URLs ?code=XXX) */
function extractCode(texto){
  let code = (texto||'').trim();
  try{
    const u = new URL(code);
    const c = u.searchParams.get('code');
    if(c) code = c;
  }catch(_){}
  return code;
}

/* Debounce de lecturas repetidas */
function shouldProcess(code){
  const now = Date.now();
  if(code === lastCode && (now - lastTime) < 2000){ return false; } // 2s
  lastCode = code; lastTime = now; return true;
}

/* Validar en backend */
async function validar(code){
  const form = new FormData();
  form.append('evento_id', <?= (int)$evento_id ?>);
  form.append('code', code);
  form.append('gate', gateSel.value || 'Acceso principal');

  try{
    const r = await fetch('validar_ticket.php', { method:'POST', body: form });
    const j = await r.json();

    if(j && j.ok){
      beep(true); vibra(60);
      addLog(`✅ ${j.msg} · Tipo: ${j.tipo ?? '—'} · Gate: <b>${gateSel.value}</b> · Code: <b>${code}</b>`, true);
    }else{
      beep(false); vibra([30,40,30]);
      const msg = j && j.msg ? j.msg : 'Respuesta inválida';
      addLog(`⛔ ${msg} · Code: <b>${code}</b>`, false);
    }
  }catch(e){
    beep(false); vibra([30,40,30]);
    addLog('Error de red: ' + e, false);
  }
}

/* Gestión al escanear */
async function handleScan(texto){
  const code = extractCode(texto);
  if(!code){ addLog('Código vacío', false); return; }
  if(!shouldProcess(code)) return;
  await validar(code);
}

/* Eventos UI */
$('#start').addEventListener('click', start);
$('#stop').addEventListener('click', stop);
torchBtn.addEventListener('click', toggleTorch);
validarBtn.addEventListener('click', ()=>{ const v = manual.value.trim(); if(v){ handleScan(v); manual.value=''; manual.focus(); }});
manual.addEventListener('keydown', (e)=>{ if(e.key==='Enter'){ e.preventDefault(); validarBtn.click(); }});
camSel.addEventListener('change', start);

/* Init */
(async ()=>{
  await loadCams();
})();
</script>
</body>
</html>
