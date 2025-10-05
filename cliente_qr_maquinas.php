<?php
// cliente_scan_qr.php — Panel del Cliente: escanear QR con la cámara y abrir la rutina
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
@include __DIR__ . '/menu_cliente.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

$cliente_id  = (int)($_SESSION['cliente_id'] ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($cliente_id <= 0 || $gimnasio_id <= 0) {
  header('Location: cliente_acceso.php'); exit;
}

/* Helpers */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function base_url(): string {
  $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $path  = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
  return $proto.'://'.$host.($path ? $path.'/' : '/');
}

$public_base = base_url().'maquinas_qr.php?t='; // por si el usuario pega solo el token
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Escanear QR de máquinas — Panel Cliente</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{
      --bg:#0b1220; --card:#0f172a; --muted:#94a3b8; --line:#1f2937; --acc:#22d3ee; --bad:#ef4444; --ok:#22c55e;
    }
    *{box-sizing:border-box}
    body{ margin:0; font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif; background:var(--bg); color:#e5e7eb; }
    .wrap{ max-width:960px; margin:0 auto; padding:18px; }
    h1{ margin:6px 0 10px; font-size:1.6rem; }
    .muted{ color:var(--muted); }
    .card{ background:var(--card); border:1px solid var(--line); border-radius:16px; padding:16px; }
    .grid{ display:grid; grid-template-columns: 1.1fr 1fr; gap:16px; }
    @media (max-width: 900px){ .grid{ grid-template-columns:1fr; } }
    .video-box{ position:relative; border-radius:14px; overflow:hidden; background:#000; }
    video{ width:100%; height:auto; display:block; transform:scaleX(-1); } /* espejo: mejor experiencia */
    .scanline{ position:absolute; left:0; right:0; top:0; height:2px; background:linear-gradient(90deg,transparent,#22d3ee,transparent); animation:scan 2.2s linear infinite; opacity:.8 }
    @keyframes scan{ 0%{top:10%} 50%{top:90%} 100%{top:10%} }
    .status{ margin-top:8px; font-size:.95rem; }
    .ok{ color:var(--ok); } .bad{ color:#fecaca; }
    .row{ display:grid; grid-template-columns: 1fr 1fr; gap:10px; }
    @media (max-width: 600px){ .row{ grid-template-columns:1fr; } }
    input[type="text"]{ width:100%; padding:10px; border-radius:10px; border:1px solid #334155; background:#0b1220; color:#e5e7eb; }
    button, .btn{ background:var(--acc); border:0; color:#111; font-weight:800; padding:10px 14px; border-radius:10px; cursor:pointer; text-decoration:none; display:inline-block; }
    .ghost{ background:#111; color:#fff; }
    .danger{ background:var(--bad); color:#fff; }
    .actions{ display:flex; gap:8px; flex-wrap:wrap; }
    .help{ font-size:.9rem; margin-top:8px }
    .center{ text-align:center; }
    .hint{ font-size:.85rem; color:#cbd5e1; }
    .file{ background:#1f2937; border:1px dashed #334155; padding:10px; border-radius:10px; }
  </style>
</head>
<body>
  <div class="wrap">
    <h1>Escanear QR de máquinas</h1>
    <p class="muted">Apuntá la cámara al QR pegado en la máquina para abrir la rutina con tu <strong>nivel</strong> (Principiante / Medio / Avanzado) en la siguiente pantalla.</p>

    <div class="grid">
      <!-- Cámara y estado -->
      <div class="card">
        <div class="video-box">
          <video id="video" playsinline></video>
          <div class="scanline"></div>
        </div>
        <div class="status" id="status">Preparando cámara…</div>
        <div class="actions" style="margin-top:10px">
          <button id="btnStart">Iniciar cámara</button>
          <button id="btnStop" class="ghost">Detener</button>
          <button id="btnSwitch" class="ghost">Cambiar cámara</button>
          <button id="btnTorch" class="ghost">Linterna</button>
        </div>
        <div class="help hint">Consejo: acercá el código hasta que ocupe buena parte de la pantalla.</div>
      </div>

      <!-- Fallbacks: subir foto / pegar enlace -->
      <div class="card">
        <h3 style="margin-top:0">Si la cámara no funciona</h3>
        <div class="file" style="margin-bottom:10px">
          <label for="file">Escanear desde foto (abre cámara si tu equipo lo permite)</label>
          <input id="file" type="file" accept="image/*" capture="environment">
          <div class="hint">Tomá una foto clara del QR. Si la foto contiene la URL, vamos a abrirla.</div>
        </div>

        <div>
          <label for="manual">Pegar enlace o token</label>
          <div class="row">
            <input id="manual" type="text" placeholder="Ej: <?php echo h($public_base); ?>TOKEN  — o solo TOKEN">
            <button id="btnOpen" class="btn">Abrir rutina</button>
          </div>
          <div class="hint">Si pegás solo el TOKEN, te llevamos a <?php echo h($public_base); ?>TOKEN</div>
        </div>
      </div>
    </div>
  </div>

  <canvas id="canvas" style="display:none"></canvas>

  <script>
    // Utilidades
    function $(id){ return document.getElementById(id); }
    const video = $('video');
    const statusEl = $('status');
    const btnStart = $('btnStart'), btnStop=$('btnStop'), btnSwitch=$('btnSwitch'), btnTorch=$('btnTorch');
    const inputFile = $('file');
    const manual = $('manual'), btnOpen = $('btnOpen');

    const PUBLIC_BASE = <?php echo json_encode($public_base, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;

    let stream = null;
    let currentDeviceId = null;
    let devices = [];
    let scanning = false;
    let detector = null;
    let track = null;
    let torchOn = false;

    // Comprueba soporte de BarcodeDetector
    async function ensureDetector(){
      if ('BarcodeDetector' in window) {
        try{
          const formats = await BarcodeDetector.getSupportedFormats();
          if (formats.includes('qr_code')){
            detector = new BarcodeDetector({ formats: ['qr_code'] });
            return true;
          }
        }catch(e){}
      }
      return false;
    }

    async function listCameras(){
      try{
        const all = await navigator.mediaDevices.enumerateDevices();
        devices = all.filter(d => d.kind === 'videoinput');
      }catch(e){ devices = []; }
    }

    async function startCamera(){
      if (!location.protocol.startsWith('https')){
        status('Necesitás HTTPS para usar la cámara (o app instalada).', true);
        return;
      }
      await listCameras();
      // intenta trasera por label o la última
      if (devices.length){
        const back = devices.find(d => /back|trasera|rear/i.test(d.label));
        currentDeviceId = (back || devices[devices.length-1]).deviceId;
      }
      try{
        stream = await navigator.mediaDevices.getUserMedia({
          video: currentDeviceId ? { deviceId: { exact: currentDeviceId } } : { facingMode: { ideal: 'environment' } },
          audio: false
        });
        video.srcObject = stream;
        await video.play();
        track = stream.getVideoTracks()[0] || null;
        status('Cámara activa. Buscando QR…');
        scanning = true;
        scanLoop();
      }catch(e){
        console.error(e);
        status('No pudimos acceder a la cámara. Usá la foto o pegá el enlace/token.', true);
      }
    }

    function stopCamera(){
      scanning = false;
      if (stream){
        stream.getTracks().forEach(t=>t.stop());
        stream = null;
        track = null;
      }
      status('Cámara detenida.');
    }

    async function switchCamera(){
      if (!devices.length){ await listCameras(); }
      if (!devices.length) return;
      const idx = devices.findIndex(d => d.deviceId === currentDeviceId);
      const next = devices[(idx+1) % devices.length];
      currentDeviceId = next.deviceId;
      stopCamera();
      startCamera();
    }

    async function toggleTorch(){
      if (!track) return;
      const caps = track.getCapabilities?.();
      if (!caps || !caps.torch){ status('Tu cámara no soporta linterna.', true); return; }
      torchOn = !torchOn;
      try{
        await track.applyConstraints({ advanced: [{ torch: torchOn }] });
        status(torchOn ? 'Linterna encendida.' : 'Linterna apagada.');
      }catch(e){ status('No se pudo cambiar la linterna.', true); }
    }

    function status(msg, error=false){
      statusEl.textContent = msg;
      statusEl.className = 'status ' + (error ? 'bad' : 'ok');
    }

    async function scanLoop(){
      const hasDetector = detector || await ensureDetector();
      if (!hasDetector){ status('Escaneo nativo no disponible. Probá por foto o pegá el enlace/token.', true); return; }

      const canvas = $('canvas');
      const ctx = canvas.getContext('2d');
      const fps = 12;
      (async function loop(){
        if (!scanning) return;
        try{
          const w = video.videoWidth, h = video.videoHeight;
          if (w && h){
            canvas.width = w; canvas.height = h;
            ctx.drawImage(video, 0, 0, w, h);
            const barcodes = await detector.detect(canvas);
            if (barcodes && barcodes.length){
              const raw = (barcodes[0].rawValue || '').trim();
              if (raw){ onResult(raw); return; }
            }
          }
        }catch(e){ /* ignore frame errors */ }
        setTimeout(loop, 1000 / fps);
      })();
    }

    function onResult(raw){
      scanning = false;
      stopCamera();
      // Puede ser URL completa o token. Si no es URL, la construimos.
      let url = raw;
      try{
        const u = new URL(raw, location.origin);
        // Si es un token suelto, URL() lo va a tratar como ruta; detectamos token alfanumérico corto
        if (!/maquinas_qr\.php/i.test(u.pathname) && /^[a-f0-9]{8,64}$/i.test(raw)){
          url = PUBLIC_BASE + encodeURIComponent(raw);
        }else{
          url = u.href;
        }
      }catch(_){
        // No parsea como URL; asumimos que es token
        if (/^[a-f0-9]{8,64}$/i.test(raw)){
          url = PUBLIC_BASE + encodeURIComponent(raw);
        }else{
          // último intento: si es texto con ?t=, usamos eso
          const m = raw.match(/t=([A-Za-z0-9_\-]+)/);
          url = m ? (PUBLIC_BASE + encodeURIComponent(m[1])) : raw;
        }
      }
      status('QR detectado. Abriendo rutina…');
      location.href = url;
    }

    // Fallback: abrir por foto (no decodificamos manualmente el QR; muchos celulares reconocen URL en la foto)
    inputFile.addEventListener('change', async ()=>{
      const file = inputFile.files && inputFile.files[0];
      if (!file) return;
      try{
        // Intentamos leer metadatos de la imagen rápido por si el SO ya detecta URL (no estándar).
        // Como fallback simple, mostramos cómo abrir manualmente si el visor del sistema la detecta.
        status('Foto cargada. Si el sistema reconoce un enlace, vas a poder abrirlo desde la vista previa del sistema.', false);
        // Alternativa: simplemente ofrecer subir y luego pedir que pegue manual (simple, sin librerías externas)
        alert('Si tu dispositivo no detecta el enlace automáticamente, copiá el texto del QR y pegalo abajo.');
      }catch(e){
        status('No se pudo procesar la imagen. Pegá el enlace/token abajo.', true);
      }
    });

    // Manual
    btnOpen.addEventListener('click', ()=>{
      let v = (manual.value || '').trim();
      if (!v){ alert('Pegá un enlace o token.'); return; }
      if (/^https?:\/\//i.test(v)){
        location.href = v;
      }else{
        // si parece token, armamos URL
        v = PUBLIC_BASE + encodeURIComponent(v);
        location.href = v;
      }
    });

    // Botones
    btnStart.addEventListener('click', startCamera);
    btnStop.addEventListener('click', stopCamera);
    btnSwitch.addEventListener('click', switchCamera);
    btnTorch.addEventListener('click', toggleTorch);

    // Auto-inicio (si el navegador deja)
    document.addEventListener('visibilitychange', ()=>{ if (document.visibilityState==='visible' && !stream && !scanning) {/* opcional reintentar */} });
    startCamera();
  </script>
</body>
</html>
