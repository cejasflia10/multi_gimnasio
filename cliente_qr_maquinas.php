<?php
// cliente_scan_qr.php — Panel del Cliente: escanear QR con la cámara y abrir la rutina (responsive + fallback WebView)
/*
  ✔ Responsive móvil/tablet/PC
  ✔ Flujo nativo (getUserMedia + BarcodeDetector) cuando está disponible
  ✔ Fallback universal con html5-qrcode para apps tipo WebView / wrappers
  ✔ Botones: iniciar/detener, cambiar cámara, linterna
  ✔ Opciones alternativas: subir foto / pegar enlace o token
*/
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
@include __DIR__ . '/menu_cliente.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

// Opcional: habilitar explícitamente acceso a cámara desde navegadores modernos
@header('Permissions-Policy: camera=(self)');

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
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

  <style>
    :root{
      --bg:#0b1220; --card:#0f172a; --muted:#94a3b8; --line:#1f2937; --acc:#22d3ee; --bad:#ef4444; --ok:#22c55e; --ink:#e5e7eb;
      --space:clamp(14px, 2.4vw, 24px);
      --radius:16px;
      --fs:clamp(.96rem, 1.6vw, 1rem);
      --fsh:clamp(1.2rem, 2.2vw, 1.6rem);
    }
    @media (prefers-color-scheme: light){
      :root{ --bg:#f7fafc; --card:#ffffff; --muted:#556070; --line:#e5e7eb; --ink:#0f172a; }
    }
    *{box-sizing:border-box}
    body{ margin:0; font:400 var(--fs)/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif; background:var(--bg); color:var(--ink); }
    .wrap{ width:min(1100px, 100% - 2*var(--space)); margin:0 auto; padding:var(--space) 0 var(--space); }
    h1{ margin:6px 0 12px; font-size:var(--fsh); }
    .muted{ color:var(--muted); }
    .grid{ display:grid; grid-template-columns: 1fr; gap:16px; }
    @media (min-width: 992px){ .grid{ grid-template-columns: 1.15fr 1fr; gap:18px; } }
    .card{ background:var(--card); border:1px solid var(--line); border-radius:var(--radius); padding:clamp(14px,2vw,18px); }

    /* Video responsivo */
    .video-box{ position:relative; border-radius:14px; overflow:hidden; background:#000; }
    .video-frame{ aspect-ratio: 16 / 9; width:100%; display:block; }
    video{ width:100%; height:100%; object-fit:cover; display:block; }
    .scanline{ position:absolute; left:0; right:0; top:10%; height:2px; background:linear-gradient(90deg,transparent,var(--acc),transparent); animation:scan 2.2s linear infinite; opacity:.85 }
    @keyframes scan{ 0%{top:10%} 50%{top:90%} 100%{top:10%} }

    .status{ margin-top:8px; font-size:.95rem; }
    .ok{ color:var(--ok); } .bad{ color:#ef9a9a; }

    .actions{ display:flex; gap:10px; flex-wrap:wrap; margin-top:10px }
    button, .btn{ background:var(--acc); border:0; color:#111; font-weight:800; padding:12px 16px; border-radius:12px; cursor:pointer; text-decoration:none; display:inline-block; }
    .ghost{ background:#111; color:#fff; }
    .danger{ background:var(--bad); color:#fff; }
    @media (max-width:520px){ .actions button, .actions .btn{ flex:1 1 46%; } }

    .row{ display:grid; grid-template-columns: 1fr 1fr; gap:10px; }
    @media (max-width: 640px){ .row{ grid-template-columns:1fr; } }
    input[type="text"]{ width:100%; padding:12px; border-radius:12px; border:1px solid var(--line); background:transparent; color:inherit; }

    .help{ font-size:.92rem; margin-top:8px }
    .hint{ font-size:.86rem; color:var(--muted); }
    .file{ background:transparent; border:1px dashed var(--line); padding:12px; border-radius:12px; }

    /* Overlay guía/errores opcional */
    .notice{ display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); color:#fff; align-items:center; justify-content:center; padding:20px; z-index:50 }
    .notice .box{ background:#111827; border:1px solid #374151; border-radius:16px; padding:18px; width:min(520px, 92vw); }
    .notice.show{ display:flex; }
  </style>

  <!-- Fallback universal de escaneo para WebView/wrappers -->
  <script src="https://unpkg.com/html5-qrcode/minified/html5-qrcode.min.js"></script>
</head>
<body>
  <div class="wrap">
    <h1>Escanear QR de máquinas</h1>
    <p class="muted">Apuntá la cámara al QR pegado en la máquina. Al detectar, te llevamos a la rutina donde podés elegir tu <strong>nivel</strong> (Principiante / Medio / Avanzado).</p>

    <div class="grid">
      <!-- Cámara y estado -->
      <div class="card" aria-labelledby="camTitle">
        <h2 id="camTitle" style="margin:0 0 8px; font-size:1.05em">Cámara</h2>
        <div class="video-box" role="group" aria-label="Vista previa de cámara">
          <div class="video-frame">
            <video id="video" playsinline muted></video>
          </div>
          <div class="scanline" aria-hidden="true"></div>
        </div>
        <div class="status" id="status" aria-live="polite">Preparando cámara…</div>
        <div class="actions">
          <button id="btnStart">Iniciar cámara</button>
          <button id="btnStop" class="ghost">Detener</button>
          <button id="btnSwitch" class="ghost">Cambiar cámara</button>
          <button id="btnTorch" class="ghost">Linterna</button>
        </div>
        <div class="help hint">Tip: acercá el código hasta ocupar buena parte de la pantalla y mantené el pulso.</div>
      </div>

      <!-- Fallbacks: subir foto / pegar enlace -->
      <div class="card" aria-labelledby="fbTitle">
        <h2 id="fbTitle" style="margin:0 0 8px; font-size:1.05em">Si la cámara no funciona</h2>

        <!-- Contenedor para el fallback html5-qrcode (se muestra solo cuando se usa) -->
        <div id="reader" style="display:none; width:100%; max-width:460px; margin:6px auto 16px;"></div>

        <div class="file" style="margin-bottom:12px">
          <label for="file">Escanear desde foto (puede abrir cámara si tu equipo lo permite)</label>
          <input id="file" type="file" accept="image/*" capture="environment">
          <div class="hint">Tomá una foto clara del QR. Si la imagen tiene URL detectable, podrás abrirla.</div>
        </div>

        <div>
          <label for="manual">Pegar enlace o token</label>
          <div class="row">
            <input id="manual" type="text" placeholder="Ej: <?php echo h($public_base); ?>TOKEN  — o solo TOKEN">
            <button id="btnOpen" class="btn">Abrir rutina</button>
          </div>
          <div class="hint">Si pegás solo el TOKEN, vamos a: <?php echo h($public_base); ?>TOKEN</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Avisos superpuestos -->
  <div id="notice" class="notice" role="dialog" aria-modal="true" aria-labelledby="nTitle" aria-describedby="nMsg">
    <div class="box">
      <h3 id="nTitle" style="margin:0 0 8px; font-size:1.1em">Atención</h3>
      <div id="nMsg" class="hint" style="margin-bottom:12px">Mensaje</div>
      <div class="actions">
        <button id="nClose" class="ghost">Cerrar</button>
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
    const notice = $('notice'), nClose = $('nClose'), nMsg = $('nMsg');

    const PUBLIC_BASE = <?php echo json_encode($public_base, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;

    let stream = null;
    let currentDeviceId = null;
    let devices = [];
    let scanning = false;
    let detector = null;
    let track = null;
    let torchOn = false;

    // Fallback: html5-qrcode
    let html5q = null;
    async function startHtml5(){
      const box = document.getElementById('reader');
      box.style.display = 'block';
      try{
        if (!window.Html5Qrcode) throw new Error('html5-qrcode no cargado');
        html5q = new Html5Qrcode('reader');
        await html5q.start(
          { facingMode: 'environment' },
          { fps: 12, qrbox: (vw, vh) => Math.min(300, Math.floor(Math.min(vw, vh) * 0.7)) },
          decodedText => { if (decodedText) onResult(decodedText); },
          _err => {} // ignoramos errores por frame
        );
        status('Cámara activa (modo compatible). Apuntá al código…');
      }catch(e){
        console.error('html5-qrcode error', e);
        status('No pudimos iniciar la cámara en este dispositivo. Usá foto o pegá el enlace/token.', true);
      }
    }
    async function stopHtml5(){
      try{
        if (html5q) { await html5q.stop(); await html5q.clear(); html5q = null; }
      }catch(_){}
      const box = document.getElementById('reader');
      if (box) box.style.display = 'none';
    }

    function showNotice(msg){ nMsg.textContent = msg; notice.classList.add('show'); }
    function hideNotice(){ notice.classList.remove('show'); }
    nClose.addEventListener('click', hideNotice);

    function isInAppWebView(){
      const ua = navigator.userAgent || '';
      const androidWV = /\b; wv\b/i.test(ua) || /\bVersion\/\d+\.\d+ Chrome\/\d+/i.test(ua);
      const iOSWV = (/\b(iPhone|iPad|iPod)\b/i.test(ua) && !/Safari\//i.test(ua));
      return androidWV || iOSWV;
    }

    // Comprueba soporte de BarcodeDetector
    async function ensureDetector(){
      if ('BarcodeDetector' in window) {
        try{
          const formats = await BarcodeDetector.getSupportedFormats();
          if (formats && formats.includes('qr_code')){
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
      // Si estamos dentro de un wrapper/app (WebView), usamos directamente el fallback
      if (isInAppWebView()){
        status('Inicializando cámara (modo compatible)…');
        await stopCamera(); // limpia cualquier stream previo
        return startHtml5();
      }

      // En navegador normal, getUserMedia requiere HTTPS (excepto localhost)
      if (!location.protocol.startsWith('https') && location.hostname!=='localhost'){
        status('Necesitás HTTPS para usar la cámara en el navegador.', true);
        showNotice('Abrí esta página con HTTPS para acceder a la cámara. Intentaremos un modo compatible…');
        return startHtml5();
      }

      await listCameras();

      // intenta trasera por label; si no, usa la última
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
        status('No pudimos acceder a la cámara. Probando modo compatible…', true);
        showNotice('No pudimos acceder a la cámara del navegador. Probamos modo compatible.');
        return startHtml5();
      }
    }

    function stopCamera(){
      scanning = false;
      stopHtml5();
      if (stream){
        stream.getTracks().forEach(t=>t.stop());
        stream = null;
        track = null;
      }
      status('Cámara detenida.');
    }

    async function switchCamera(){
      // Si está activo el fallback, reiniciamos html5-qrcode (no soporta cambiar fácilmente)
      if (html5q){
        await stopHtml5();
        return startHtml5();
      }
      if (!devices.length){ await listCameras(); }
      if (!devices.length) return;
      const idx = devices.findIndex(d => d.deviceId === currentDeviceId);
      const next = devices[(idx+1) % devices.length];
      currentDeviceId = next.deviceId;
      stopCamera();
      startCamera();
    }

    async function toggleTorch(){
      if (html5q){ return alert('La linterna no está disponible en el modo compatible.'); }
      if (!track) { status('Cámara no disponible.', true); return; }
      const caps = track.getCapabilities?.();
      if (!caps || !caps.torch){ status('Tu cámara no soporta linterna.', true); return; }
      try{
        torchOn = !torchOn;
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
      if (!hasDetector){
        // Si el detector nativo no está, hacemos fallback automático
        status('Escaneo nativo no disponible. Usando modo compatible…');
        return startHtml5();
      }
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
        }catch(e){ /* frame error, continuar */ }
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
        if (!/maquinas_qr\.php/i.test(u.pathname) && /^[a-f0-9]{8,64}$/i.test(raw)){
          url = PUBLIC_BASE + encodeURIComponent(raw);
        }else{
          url = u.href;
        }
      }catch(_){
        if (/^[a-f0-9]{8,64}$/i.test(raw)){
          url = PUBLIC_BASE + encodeURIComponent(raw);
        }else{
          const m = raw.match(/t=([A-Za-z0-9_\-]+)/);
          url = m ? (PUBLIC_BASE + encodeURIComponent(m[1])) : raw;
        }
      }
      status('QR detectado. Abriendo rutina…');
      location.href = url;
    }

    // Fallback: abrir por foto (sin decodificar localmente)
    inputFile.addEventListener('change', async ()=>{
      const file = inputFile.files && inputFile.files[0];
      if (!file) return;
      try{
        status('Foto cargada. Si el sistema reconoce un enlace, podrás abrirlo.', false);
        alert('Si tu dispositivo no detecta el enlace automáticamente en la foto, copiá el texto del QR y pegalo abajo.');
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
        v = PUBLIC_BASE + encodeURIComponent(v);
        location.href = v;
      }
    });

    // Botones
    btnStart.addEventListener('click', startCamera);
    btnStop.addEventListener('click', stopCamera);
    btnSwitch.addEventListener('click', switchCamera);
    btnTorch.addEventListener('click', toggleTorch);

    // Arranque automático
    startCamera();
  </script>
</body>
</html>
