<?php
// cliente_scan_qr.php — Escanear QR (nativo + fallback WebView + foto con jsQR)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
@include __DIR__ . '/menu_cliente.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* Ayuda a no bloquear cámara (si el host lo permite): */
@header('Permissions-Policy: camera=(self)');

$cliente_id  = (int)($_SESSION['cliente_id'] ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($cliente_id <= 0 || $gimnasio_id <= 0) { header('Location: cliente_acceso.php'); exit; }

/* Helpers */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function base_url(): string {
  $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $path  = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
  return $proto.'://'.$host.($path ? $path.'/' : '/');
}
$public_base = 'https://multi-gimnasio-51bq.onrender.com/maquinas_qr.php?t=';
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

    .notice{ display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); color:#fff; align-items:center; justify-content:center; padding:20px; z-index:50 }
    .notice .box{ background:#111827; border:1px solid #374151; border-radius:16px; padding:18px; width:min(560px, 92vw); }
    .notice.show{ display:flex; }
    .badge{ display:inline-block; padding:4px 8px; border-radius:999px; background:#1f2937; color:#e5e7eb; font-size:.8rem; }
  </style>

  <!-- Fallback WebView -->
  <script src="https://unpkg.com/html5-qrcode/minified/html5-qrcode.min.js"></script>
  <!-- Decodificar desde foto (sin cámara) -->
  <script src="https://unpkg.com/jsqr/dist/jsQR.js"></script>
</head>
<body>
  <div class="wrap">
    <h1>Escanear QR de máquinas</h1>
    <p class="muted">Apuntá la cámara al QR pegado en la máquina. Si tu app no permite usar la cámara, podés <strong>tomar una foto</strong> del QR y la decodificamos igual.</p>

    <div class="grid">
      <div class="card" aria-labelledby="camTitle">
        <h2 id="camTitle" style="margin:0 0 8px; font-size:1.05em">Cámara <span id="envTag" class="badge" style="display:none"></span></h2>
        <div class="video-box" role="group" aria-label="Vista previa de cámara">
          <div class="video-frame"><video id="video" playsinline muted></video></div>
          <div class="scanline" aria-hidden="true"></div>
        </div>
        <div class="status" id="status" aria-live="polite">Preparando cámara…</div>
        <div class="actions">
          <button id="btnStart">Iniciar cámara</button>
          <button id="btnStop" class="ghost">Detener</button>
          <button id="btnSwitch" class="ghost">Cambiar cámara</button>
          <button id="btnTorch" class="ghost">Linterna</button>
          <button id="btnExternal" class="danger" title="Abrir en el navegador del sistema">Abrir en navegador</button>
        </div>
        <div class="help hint">Tip: acercá el código hasta ocupar buena parte de la pantalla y mantené el pulso.</div>
      </div>

      <div class="card" aria-labelledby="fbTitle">
        <h2 id="fbTitle" style="margin:0 0 8px; font-size:1.05em">Si la cámara no funciona (o está bloqueada)</h2>

        <div id="reader" style="display:none; width:100%; max-width:460px; margin:6px auto 16px;"></div>

        <div class="file" style="margin-bottom:12px">
          <label for="file">Escanear desde foto (tocar para abrir cámara en modo foto)</label>
          <input id="file" type="file" accept="image/*" capture="environment">
          <div class="hint">Tomá una foto clara del QR. La decodificamos localmente aunque la cámara en vivo esté bloqueada.</div>
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

  <div id="notice" class="notice" role="dialog" aria-modal="true" aria-labelledby="nTitle" aria-describedby="nMsg">
    <div class="box">
      <h3 id="nTitle" style="margin:0 0 8px; font-size:1.1em">Atención</h3>
      <div id="nMsg" class="hint" style="margin-bottom:12px">Mensaje</div>
      <div class="actions">
        <button id="nRetry" class="btn">Reintentar</button>
        <button id="nClose" class="ghost">Cerrar</button>
      </div>
    </div>
  </div>

  <canvas id="canvas" style="display:none"></canvas>

  <script>
    function $(id){ return document.getElementById(id); }
    const video=$('video'), statusEl=$('status');
    const btnStart=$('btnStart'), btnStop=$('btnStop'), btnSwitch=$('btnSwitch'), btnTorch=$('btnTorch'), btnExternal=$('btnExternal');
    const inputFile=$('file'), manual=$('manual'), btnOpen=$('btnOpen');
    const notice=$('notice'), nMsg=$('nMsg'), nClose=$('nClose'), nRetry=$('nRetry'), envTag=$('envTag');

    const PUBLIC_BASE = <?php echo json_encode($public_base, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;

    let stream=null, currentDeviceId=null, devices=[], scanning=false, detector=null, track=null, torchOn=false, html5q=null;

    function isInAppWebView(){
      const ua = navigator.userAgent || '';
      const androidWV = /\b; wv\b/i.test(ua) || /\bVersion\/\d+\.\d+ Chrome\/\d+/i.test(ua);
      const iOSWV = (/\b(iPhone|iPad|iPod)\b/i.test(ua) && !/Safari\//i.test(ua));
      return androidWV || iOSWV;
    }
    function status(msg, bad=false){ statusEl.textContent=msg; statusEl.className='status '+(bad?'bad':'ok'); }

    /* --------- Fallback WebView: html5-qrcode --------- */
    async function startHtml5(){
      const box = document.getElementById('reader'); box.style.display='block';
      try{
        if (!window.Html5Qrcode) throw new Error('html5-qrcode no cargado');
        html5q = new Html5Qrcode('reader');
        await html5q.start(
          { facingMode:'environment' },
          { fps:12, qrbox:(vw,vh)=>Math.min(300, Math.floor(Math.min(vw,vh)*0.7)) },
          decoded => decoded && onResult(decoded),
          _err => {}
        );
        status('Cámara activa (modo compatible). Apuntá al código…');
      }catch(e){
        console.error('html5-qrcode error', e);
        status('No se pudo iniciar la cámara en este entorno.', true);
        alert('No pudimos usar la cámara en esta app. Usá el modo de foto o el botón "Abrir en navegador".');
      }
    }
    async function stopHtml5(){ try{ if (html5q){ await html5q.stop(); await html5q.clear(); html5q=null; } }catch(_){}; $('reader').style.display='none'; }

    /* --------- Nativo: getUserMedia + BarcodeDetector --------- */
    async function listCameras(){
      try{
        const all = await navigator.mediaDevices.enumerateDevices();
        devices = all.filter(d => d.kind==='videoinput');
      }catch{ devices=[]; }
    }

    async function ensureDetector(){
      if ('BarcodeDetector' in window){
        try{
          const formats = await BarcodeDetector.getSupportedFormats();
          if (formats && formats.includes('qr_code')){ detector=new BarcodeDetector({formats:['qr_code']}); return true; }
        }catch{}
      }
      return false;
    }

    async function startCamera(){
      const inApp = isInAppWebView();
      envTag.style.display='inline-block';
      envTag.textContent = inApp ? 'App/WebView' : 'Navegador';

      if (inApp){
        status('Inicializando cámara (modo compatible)…');
        await stopCamera();
        return startHtml5();
      }

      if (!location.protocol.startsWith('https') && location.hostname!=='localhost'){
        status('Necesitás HTTPS para cámara en navegador. Usando modo compatible…', true);
        return startHtml5();
      }

      await listCameras();
      if (devices.length){
        const back = devices.find(d => /back|trasera|rear/i.test(d.label));
        currentDeviceId = (back || devices[devices.length-1]).deviceId;
      }

      try{
        stream = await navigator.mediaDevices.getUserMedia({
          video: currentDeviceId ? { deviceId:{ exact: currentDeviceId } } : { facingMode:{ ideal:'environment' } }, audio:false
        });
        video.srcObject=stream; await video.play();
        track = stream.getVideoTracks()[0] || null;
        status('Cámara activa. Buscando QR…');
        scanning=true; scanLoop();
      }catch(e){
        console.error('getUserMedia error:', e && e.name, e && e.message);
        status('No pudimos acceder a la cámara. Probando modo compatible…', true);
        return startHtml5();
      }
    }

    function stopCamera(){
      scanning=false; stopHtml5();
      if (stream){ stream.getTracks().forEach(t=>t.stop()); stream=null; track=null; }
      status('Cámara detenida.');
    }

    async function switchCamera(){
      if (html5q){ await stopHtml5(); return startHtml5(); }
      if (!devices.length) await listCameras();
      if (!devices.length) return;
      const idx = devices.findIndex(d => d.deviceId===currentDeviceId);
      currentDeviceId = devices[(idx+1)%devices.length].deviceId;
      stopCamera(); startCamera();
    }

    async function toggleTorch(){
      if (html5q) return alert('La linterna no está disponible en el modo compatible.');
      if (!track) return status('Cámara no disponible.', true);
      const caps = track.getCapabilities?.(); if (!caps || !caps.torch) return status('Tu cámara no soporta linterna.', true);
      try{ const on = !(track.getConstraints()?.advanced?.[0]?.torch); await track.applyConstraints({ advanced:[{ torch: on }] }); status(on?'Linterna encendida.':'Linterna apagada.'); }
      catch{ status('No se pudo cambiar la linterna.', true); }
    }

    async function scanLoop(){
      const has = detector || await ensureDetector();
      if (!has){ status('Escaneo nativo no disponible. Usando modo compatible…'); return startHtml5(); }
      const canvas=$('canvas'), ctx=canvas.getContext('2d'), fps=12;
      (async function loop(){
        if (!scanning) return;
        try{
          const w=video.videoWidth, h=video.videoHeight;
          if (w && h){
            canvas.width=w; canvas.height=h; ctx.drawImage(video, 0, 0, w, h);
            const barcodes = await detector.detect(canvas);
            if (barcodes && barcodes.length){
              const raw=(barcodes[0].rawValue||'').trim(); if (raw) return onResult(raw);
            }
          }
        }catch(_){}
        setTimeout(loop, 1000/fps);
      })();
    }

    function onResult(raw){
      scanning=false; stopCamera();
      let url=raw;
      try{
        const u=new URL(raw, location.origin);
        if (!/maquinas_qr\.php/i.test(u.pathname) && /^[a-f0-9]{8,64}$/i.test(raw)) url = PUBLIC_BASE+encodeURIComponent(raw);
        else url=u.href;
      }catch(_){
        if (/^[a-f0-9]{8,64}$/i.test(raw)) url = PUBLIC_BASE+encodeURIComponent(raw);
        else { const m=raw.match(/t=([A-Za-z0-9_\-]+)/); url = m ? (PUBLIC_BASE+encodeURIComponent(m[1])) : raw; }
      }
      status('QR detectado. Abriendo rutina…'); location.href=url;
    }

    /* --------- Foto → Decodificar con jsQR (sin cámara en vivo) --------- */
    inputFile.addEventListener('change', ()=>{
      const file = inputFile.files && inputFile.files[0];
      if (!file) return;

      const img = new Image();
      img.onload = ()=>{
        const canvas = $('canvas');
        const ctx = canvas.getContext('2d');
        const maxSide = 1280;
        let w = img.width, h = img.height;
        if (Math.max(w,h) > maxSide){
          const ratio = maxSide / Math.max(w,h);
          w = Math.round(w*ratio); h = Math.round(h*ratio);
        }
        canvas.width = w; canvas.height = h;
        ctx.drawImage(img, 0, 0, w, h);
        const imageData = ctx.getImageData(0, 0, w, h);
        try{
          const result = jsQR(imageData.data, w, h, { inversionAttempts: 'dontInvert' });
          if (result && result.data){
            status('QR detectado en la foto. Abriendo…');
            onResult(result.data.trim());
          } else {
            status('No se detectó un QR en la foto. Probá más cerca y con buena luz.', true);
            alert('No se detectó QR en la foto. Asegurate de que ocupe buena parte de la imagen y esté nítido.');
          }
        }catch(e){
          console.error('jsQR error', e);
          status('No se pudo procesar la imagen. Probá otra foto más nítida.', true);
        }
      };
      img.onerror = ()=>{ status('No se pudo leer la imagen seleccionada.', true); };
      img.src = URL.createObjectURL(file);
    });

    // Abrir esta misma página en el navegador del sistema (Android)
    btnExternal.addEventListener('click', ()=>{
      const proto = location.protocol.replace(':',''); // https
      const intent = `intent://${location.host}${location.pathname}${location.search}#Intent;scheme=${proto};package=com.android.chrome;end`;
      const win = window.open(intent, '_self');
      if (!win) window.open(location.href, '_blank');
    });

    // Manual
    btnOpen.addEventListener('click', ()=>{
      let v=(manual.value||'').trim(); if (!v) return alert('Pegá un enlace o token.');
      if (/^https?:\/\//i.test(v)) location.href=v; else location.href=PUBLIC_BASE+encodeURIComponent(v);
    });

    // Diálogo
    $('nClose').addEventListener('click', ()=>notice.classList.remove('show'));
    $('nRetry').addEventListener('click', ()=>{ notice.classList.remove('show'); stopCamera(); startCamera(); });

    // Arranque
    startCamera();
  </script>
</body>
</html>
