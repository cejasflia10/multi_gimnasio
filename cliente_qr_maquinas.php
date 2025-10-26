<?php
// cliente_scan_qr.php — Escanear QR (nativo + fallback WebView + foto con jsQR) con MENÚ UNIFICADO (cliente)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

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
/* Base pública (solo lectura) para abrir rutina por token */
$public_base = 'https://multi-gimnasio-51bq.onrender.com/maquinas_qr.php?t=';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>📷 Escanear QR de Máquinas — Panel Cliente</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

  <style>
    /* ================== MENÚ UNIFICADO (idéntico al panel) ================== */
    :root{
      --mnu-bg-bar: rgba(15,19,32,.78);
      --mnu-bg-drawer: rgba(10,12,20,.94);
      --mnu-fg: #fff;
      --mnu-fg-dim: #cbd5e1;
      --mnu-accent: #ffd600;
      --mnu-border: rgba(255,255,255,.16);
      --mnu-shadow: 0 10px 30px rgba(0,0,0,.45);

      /* Base panel */
      --bg:#0b0b0b; --surface:#0f1115; --card:#12141a; --fg:#f1f5f9; --muted:#a0a7b4; --acc:#f5c542; --border:rgba(255,255,255,.12);

      --ok:#10371f; --okb:#2e7d32; --err:#3a1010; --errb:#7a2d2d;
    }
    .mnu-bar{ position:sticky; top:0; z-index:1000; display:flex; align-items:center; gap:12px; padding:10px 14px; background:var(--mnu-bg-bar); -webkit-backdrop-filter: blur(10px) saturate(1.05); backdrop-filter: blur(10px) saturate(1.05); border-bottom:1px solid var(--mnu-border); }
    .mnu-title{ font-weight:800; color:var(--mnu-accent); }
    .mnu-spacer{ flex:1; }
    .mnu-btn{ display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border-radius:999px; cursor:pointer; background:var(--mnu-accent); color:#111; border:none; font-weight:700; text-decoration:none }
    .mnu-btn--ghost{ background:transparent; color:var(--mnu-fg); border:1px solid var(--mnu-border); }
    .mnu-inline{ display:flex; gap:10px; flex-wrap:wrap; padding:10px 14px; background:transparent; border-bottom:1px solid var(--mnu-border); }
    .mnu-tab{ padding:10px 14px; border-radius:14px; border:1px solid var(--mnu-border); color:var(--mnu-fg); text-decoration:none; }
    .mnu-tab:hover{ background:rgba(255,255,255,.06); }
    @media (max-width:920px){ .mnu-inline{ display:none !important; } }
    .mnu-backdrop{ position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:10005; display:none; }
    .mnu-drawer{ position:fixed; top:0; bottom:0; left:0; width:86vw; max-width:360px; background:var(--mnu-bg-drawer); border-right:1px solid var(--mnu-border); box-shadow:var(--mnu-shadow); transform:translateX(-100%); transition:transform .25s ease; z-index:10010; padding:14px; display:flex; flex-direction:column; gap:12px; }
    .mnu-drawer.open{ transform:translateX(0); }
    .mnu-backdrop.show{ display:block; }
    .mnu-head{ display:flex; align-items:center; gap:10px; margin-bottom:6px; }
    .mnu-close{ width:44px; height:44px; border-radius:50%; display:grid; place-items:center; cursor:pointer; background:var(--mnu-accent); color:#111; font-weight:900; border:none; }
    .mnu-list{ display:flex; flex-direction:column; gap:12px; margin:0; padding:0; list-style:none; }
    .mnu-item{ display:flex; align-items:center; gap:12px; padding:14px; border-radius:14px; border:1px solid var(--mnu-border); color:#fff; text-decoration:none; background:transparent; }
    .mnu-item:hover{ background:rgba(255,255,255,.10); border-color:rgba(255,255,255,.30); }
    .mnu-item__icon{ width:24px; display:inline-grid; place-items:center; color:#fff; }
    .mnu-item__text{ font-size:18px; }
    .mnu-bar *, .mnu-drawer *, .mnu-inline *, .mnu-item, .mnu-item *{ color:#fff !important; -webkit-text-fill-color:#fff !important; text-shadow:none !important; background-clip:initial !important; -webkit-background-clip:initial !IMPORTANT; }

    /* ================== BASE / PÁGINA ================== */
    *{box-sizing:border-box}
    html,body{height:100%}
    body{ margin:0; background: radial-gradient(1000px 600px at 20% -10%, #1c1f28 0%, #0b0b0b 60%), var(--bg); color:var(--fg); font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; }
    .container{ max-width:1100px; margin:0 auto; padding:16px 16px 48px; }

    /* ===== Estilos propios del escáner ===== */
    .grid{ display:grid; grid-template-columns: 1fr; gap:16px; }
    @media (min-width: 992px){ .grid{ grid-template-columns: 1.15fr 1fr; gap:18px; } }
    .glass{ background: rgba(255,255,255,.05); border:1px solid var(--border); border-radius:20px; backdrop-filter: blur(10px); box-shadow: 0 8px 30px rgba(0,0,0,.35); }
    .card{ padding:18px }
    h2{ margin:8px 0 6px; }
    .muted{ color:var(--muted); }

    .video-box{ position:relative; border-radius:14px; overflow:hidden; background:#000; }
    .video-frame{ aspect-ratio: 16 / 9; width:100%; display:block; }
    video{ width:100%; height:100%; object-fit:cover; display:block; }
    .scanline{ position:absolute; left:0; right:0; top:10%; height:2px; background:linear-gradient(90deg,transparent,var(--acc),transparent); animation:scan 2.2s linear infinite; opacity:.85 }
    @keyframes scan{ 0%{top:10%} 50%{top:90%} 100%{top:10%} }

    .status{ margin-top:8px; font-size:.95rem; }
    .ok{ color:#22c55e } .bad{ color:#f2bcbc; }

    .actions{ display:flex; gap:10px; flex-wrap:wrap; margin-top:10px }
    button, .btn{ background:var(--acc); border:0; color:#111; font-weight:800; padding:12px 16px; border-radius:12px; cursor:pointer; text-decoration:none; display:inline-block; }
    .ghost{ background:#111; color:#fff; border:1px solid rgba(255,255,255,.16); }
    .danger{ background:#ef4444; color:#fff; }
    @media (max-width:520px){ .actions button, .actions .btn{ flex:1 1 46%; } }

    .row{ display:grid; grid-template-columns: 1fr 1fr; gap:10px; }
    @media (max-width: 640px){ .row{ grid-template-columns:1fr; } }
    input[type="text"], input[type="file"]{ width:100%; padding:12px; border-radius:12px; border:1px solid rgba(255,255,255,.12); background:#0f1115; color:var(--fg); }

    .file{ background:transparent; border:1px dashed rgba(255,255,255,.18); padding:12px; border-radius:12px; }
    .hint{ font-size:.86rem; color:var(--muted); }

    .notice{ display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); color:#fff; align-items:center; justify-content:center; padding:20px; z-index:50 }
    .notice .box{ background:#111827; border:1px solid #374151; border-radius:16px; padding:18px; width:min(560px, 92vw); }
    .notice.show{ display:flex; }
    .badge{ display:inline-block; padding:4px 8px; border-radius:999px; background:#1f2937; color:#e5e7eb; font-size:.8rem; }

    .alert-err{background:var(--err);border:1px solid var(--errb);color:#f2bcbc;padding:10px;border-radius:10px;margin:8px 0}
  </style>

  <!-- Fallback WebView -->
  <script src="https://unpkg.com/html5-qrcode/minified/html5-qrcode.min.js"></script>
  <!-- Decodificar desde foto (sin cámara) -->
  <script src="https://unpkg.com/jsqr/dist/jsQR.js"></script>
</head>
<body>

  <noscript><div class="alert-err">⚠️ Activá JavaScript para usar la cámara y decodificar QRs.</div></noscript>

  <!-- ===== Menú Unificado (mismo markup y enlaces) ===== -->
  <header>
    <div class="mnu-bar">
      <button class="mnu-btn mnu-open" type="button" aria-label="Abrir menú">☰ Menú</button>
      <div class="mnu-title">Panel Cliente</div>
      <div class="mnu-spacer"></div>
      <a class="mnu-btn mnu-btn--ghost" href="cliente_acceso.php?logout=1">Salir</a>
    </div>

    <!-- Tabs inline (PC) -->
    <nav class="mnu-inline" aria-label="Navegación principal">
      <a class="mnu-tab" href="panel_cliente.php">🏠 Inicio</a>
      <a class="mnu-tab" href="ver_turnos_cliente.php">📅 Ver Turnos</a>
      <a class="mnu-tab" href="ver_mis_pagos.php">💳 Mis Pagos</a>
      <a class="mnu-tab" href="pago_online.php">⚡ Pago Online</a>
      <a class="mnu-tab" href="form_progreso.php">📈 Ver Progreso</a>
      <a class="mnu-tab" href="evolucion_cliente.php">📊 Evolución</a>
      <a class="mnu-tab" href="tienda_indumentaria.php">🛍️ Indumentaria</a>
      <a class="mnu-tab" href="asistente_ia.php">🤖 Asistente IA</a>
      <a class="mnu-tab" href="cena_fin_anio.php">🍽️ Cena Fin de Año</a>
      <a class="mnu-tab" href="cliente_scan_qr.php">📷 Escanear QR</a>
      <a class="mnu-tab" href="cliente_qr_maquinas.php">🧰 QR de Máquinas</a>
    </nav>

    <!-- Drawer (móvil) -->
    <div class="mnu-backdrop" id="mnu-backdrop"></div>
    <aside class="mnu-drawer" id="mnu-drawer" aria-label="Menú lateral" aria-hidden="true">
      <div class="mnu-head">
        <button class="mnu-close" id="mnu-close" type="button" aria-label="Cerrar">✕</button>
        <div class="mnu-title">Menú</div>
      </div>
      <ul class="mnu-list">
        <li><a class="mnu-item" href="panel_cliente.php"><span class="mnu-item__icon">🏠</span><span class="mnu-item__text">Inicio</span></a></li>
        <li><a class="mnu-item" href="ver_turnos_cliente.php"><span class="mnu-item__icon">📅</span><span class="mnu-item__text">Ver Turnos</span></a></li>
        <li><a class="mnu-item" href="ver_mis_pagos.php"><span class="mnu-item__icon">💳</span><span class="mnu-item__text">Mis Pagos</span></a></li>
        <li><a class="mnu-item" href="pago_online.php"><span class="mnu-item__icon">⚡</span><span class="mnu-item__text">Pago Online</span></a></li>
        <li><a class="mnu-item" href="form_progreso.php"><span class="mnu-item__icon">📈</span><span class="mnu-item__text">Ver Progreso</span></a></li>
        <li><a class="mnu-item" href="evolucion_cliente.php"><span class="mnu-item__icon">📊</span><span class="mnu-item__text">Evolución</span></a></li>
        <li><a class="mnu-item" href="tienda_indumentaria.php"><span class="mnu-item__icon">🛍️</span><span class="mnu-item__text">Indumentaria</span></a></li>
        <li><a class="mnu-item" href="asistente_ia.php"><span class="mnu-item__icon">🤖</span><span class="mnu-item__text">Asistente IA</span></a></li>
        <li><a class="mnu-item" href="cena_fin_anio.php"><span class="mnu-item__icon">🍽️</span><span class="mnu-item__text">Cena Fin de Año</span></a></li>
        <li><a class="mnu-item" href="cliente_scan_qr.php"><span class="mnu-item__icon">📷</span><span class="mnu-item__text">Escanear QR</span></a></li>
        <li><a class="mnu-item" href="cliente_qr_maquinas.php"><span class="mnu-item__icon">🧰</span><span class="mnu-item__text">QR de Máquinas</span></a></li>
        <li><a class="mnu-item" href="cliente_acceso.php?logout=1"><span class="mnu-item__icon">🚪</span><span class="mnu-item__text">Salir</span></a></li>
      </ul>
    </aside>
  </header>

  <div class="container">
    <h2>📷 Escanear QR de máquinas</h2>
    <p class="muted">Apuntá la cámara al QR pegado en la máquina. Si tu app no permite usar la cámara, podés <strong>tomar una foto</strong> del QR y la decodificamos igual.</p>

    <div class="grid">
      <section class="glass card" aria-labelledby="camTitle">
        <h3 id="camTitle" style="margin:0 0 8px;">Cámara <span id="envTag" class="badge" style="display:none"></span></h3>
        <div class="video-box" role="group" aria-label="Vista previa de cámara">
          <div class="video-frame"><video id="video" playsinline muted></video></div>
          <div class="scanline" aria-hidden="true"></div>
        </div>
        <div class="status" id="status" aria-live="polite">Preparando cámara…</div>
        <div class="actions">
          <button id="btnStart" type="button">Iniciar cámara</button>
          <button id="btnStop" type="button" class="ghost">Detener</button>
          <button id="btnSwitch" type="button" class="ghost">Cambiar cámara</button>
          <button id="btnTorch" type="button" class="ghost">Linterna</button>
          <button id="btnExternal" type="button" class="danger" title="Abrir en el navegador del sistema">Abrir en navegador</button>
        </div>
        <div class="hint" style="margin-top:8px">Tip: acercá el código hasta ocupar buena parte de la pantalla y mantené el pulso.</div>
      </section>

      <aside class="glass card" aria-labelledby="fbTitle">
        <h3 id="fbTitle" style="margin:0 0 8px;">Si la cámara no funciona (o está bloqueada)</h3>

        <div id="reader" style="display:none; width:100%; max-width:460px; margin:6px auto 16px;"></div>

        <div class="file" style="margin-bottom:12px">
          <label for="file">Escanear desde foto (tocar para abrir cámara en modo foto)</label>
          <input id="file" type="file" accept="image/*" capture="environment">
          <div class="hint">Tomá una foto clara del QR. La decodificamos localmente aunque la cámara en vivo esté bloqueada.</div>
        </div>

        <div>
          <label for="manual">Pegar enlace o token</label>
          <div class="row">
            <input id="manual" type="text" placeholder="Ej: <?php echo h($public_base); ?>TOKEN  — o solo TOKEN" autocomplete="off" inputmode="text">
            <button id="btnOpen" class="btn" type="button">Abrir rutina</button>
          </div>
          <div class="hint">Si pegás solo el TOKEN, vamos a: <?php echo h($public_base); ?>TOKEN</div>
        </div>
      </aside>
    </div>
  </div>

  <!-- Diálogo -->
  <div id="notice" class="notice" role="dialog" aria-modal="true" aria-labelledby="nTitle" aria-describedby="nMsg">
    <div class="box">
      <h3 id="nTitle" style="margin:0 0 8px;">Atención</h3>
      <div id="nMsg" class="hint" style="margin-bottom:12px">Mensaje</div>
      <div class="actions">
        <button id="nRetry" class="btn" type="button">Reintentar</button>
        <button id="nClose" class="ghost" type="button">Cerrar</button>
      </div>
    </div>
  </div>

  <canvas id="canvas" style="display:none"></canvas>

  <script>
    // ===== Menú (abrir/cerrar + bloquear scroll) =====
    (function(){
      const drawer   = document.getElementById('mnu-drawer');
      const backdrop = document.getElementById('mnu-backdrop');
      const openBtn  = document.querySelector('.mnu-open');
      const closeBtn = document.getElementById('mnu-close');
      const lock = (on)=>{ document.documentElement.style.overflow = document.body.style.overflow = on?'hidden':''; }
      function open(){ drawer.classList.add('open'); drawer.setAttribute('aria-hidden','false'); backdrop.classList.add('show'); lock(true); }
      function close(){ drawer.classList.remove('open'); drawer.setAttribute('aria-hidden','true'); backdrop.classList.remove('show'); lock(false); }
      openBtn?.addEventListener('click', open);
      closeBtn?.addEventListener('click', close);
      backdrop?.addEventListener('click', close);
      window.addEventListener('keydown', e=>{ if(e.key==='Escape') close(); });
    })();
  </script>

  <script>
    function $(id){ return document.getElementById(id); }
    const video=$('video'), statusEl=$('status');
    const btnStart=$('btnStart'), btnStop=$('btnStop'), btnSwitch=$('btnSwitch'), btnTorch=$('btnTorch'), btnExternal=$('btnExternal');
    const inputFile=$('file'), manual=$('manual'), btnOpen=$('btnOpen');
    const notice=$('notice'), nMsg=$('nMsg'), nClose=$('nClose'), nRetry=$('nRetry'), envTag=$('envTag');

    const PUBLIC_BASE = <?php echo json_encode($public_base, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;

    let stream=null, currentDeviceId=null, devices=[], scanning=false, detector=null, track=null, html5q=null, torchOn=false, visStop=false;

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
          { fps:12, qrbox:(vw,vh)=>Math.min(320, Math.floor(Math.min(vw,vh)*0.72)) },
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
          const formats = (await BarcodeDetector.getSupportedFormats()) || [];
          const ok = formats.includes('qr_code') || formats.includes('qr');
          if (ok){ detector=new BarcodeDetector({formats:['qr_code','qr'].filter(f=>formats.includes(f))}); return true; }
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
        const back = devices.find(d => /back|trasera|rear|environment/i.test(d.label));
        currentDeviceId = (back || devices[devices.length-1]).deviceId;
      }

      try{
        stream = await navigator.mediaDevices.getUserMedia({
          video: currentDeviceId ? { deviceId:{ exact: currentDeviceId } } : { facingMode:{ ideal:'environment' } }, audio:false
        });
        video.srcObject=stream; await video.play();
        track = stream.getVideoTracks()[0] || null;
        torchOn = false;
        status('Cámara activa. Buscando QR…');
        scanning=true; scanLoop();
      }catch(e){
        console.error('getUserMedia error:', e && e.name, e && e.message);
        status('No pudimos acceder a la cámara. Probando modo compatible…', true);
        return startHtml5();
      }
    }

    async function stopCamera(){
      scanning=false; await stopHtml5();
      if (track && torchOn){
        try{ await track.applyConstraints({advanced:[{torch:false}]}); }catch(_){}
      }
      if (stream){ stream.getTracks().forEach(t=>t.stop()); stream=null; track=null; }
      status('Cámara detenida.');
    }

    async function switchCamera(){
      if (html5q){ await stopHtml5(); return startHtml5(); }
      if (!devices.length) await listCameras();
      if (!devices.length) return;
      const idx = devices.findIndex(d => d.deviceId===currentDeviceId);
      currentDeviceId = devices[(idx+1)%devices.length].deviceId;
      await stopCamera(); startCamera();
    }

    async function toggleTorch(){
      if (html5q) return alert('La linterna no está disponible en el modo compatible.');
      if (!track) return status('Cámara no disponible.', true);
      const caps = track.getCapabilities?.(); if (!caps || !caps.torch) return status('Tu cámara no soporta linterna.', true);
      try{
        torchOn = !torchOn;
        await track.applyConstraints({ advanced:[{ torch: torchOn }] });
        status(torchOn?'Linterna encendida.':'Linterna apagada.');
      }catch{
        status('No se pudo cambiar la linterna.', true);
      }
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

    function buildUrlFromValue(raw){
      // Aceptar: URL completa, ?t=TOKEN, /maquinas_qr.php?t=TOKEN, solo TOKEN (hex/base64-url)
      const tokenRe = /(?:[?&#]t=|\/t\/|^)([A-Za-z0-9_\-]{8,128})/;
      try{
        const u=new URL(raw, location.origin);
        const m = u.search.match(/(?:^|[?&#])t=([^&]+)/);
        if (m && m[1]) return PUBLIC_BASE+encodeURIComponent(m[1]);
        if (/maquinas_qr\.php/i.test(u.pathname)) return u.href;
      }catch(_){}
      const m2 = raw.match(tokenRe);
      if (m2 && m2[1]) return PUBLIC_BASE+encodeURIComponent(m2[1]);
      if (/^[A-Za-z0-9_\-]{8,128}$/.test(raw)) return PUBLIC_BASE+encodeURIComponent(raw);
      return raw; // último recurso: abrir tal cual
    }

    function onResult(raw){
      scanning=false; stopCamera();
      const url = buildUrlFromValue(raw);
      status('QR detectado. Abriendo rutina…');
      location.href=url;
    }

    /* --------- Foto → Decodificar con jsQR (sin cámara en vivo) --------- */
    inputFile.addEventListener('change', ()=>{
      const file = inputFile.files && inputFile.files[0];
      if (!file) return;

      const img = new Image();
      img.onload = ()=>{
        const canvas = $('canvas');
        const ctx = canvas.getContext('2d');
        const maxSide = 1600;
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

    // Abrir esta misma página en el navegador del sistema (Android / iOS)
    btnExternal.addEventListener('click', ()=>{
      const isIOS = /iPhone|iPad|iPod/i.test(navigator.userAgent);
      if (isIOS){
        // Forzar abrir en Safari
        window.location.href = location.href.replace(/^https?:\/\//,'http://') // iOS hace handoff mejor con http->https
          .replace(/^http:\/\//,'https://');
        return;
      }
      const proto = location.protocol.replace(':',''); // https
      const intent = `intent://${location.host}${location.pathname}${location.search}#Intent;scheme=${proto};package=com.android.chrome;end`;
      const win = window.open(intent, '_self');
      if (!win) window.open(location.href, '_blank');
    });

    // Manual
    btnOpen.addEventListener('click', ()=>{
      let v=(manual.value||'').trim(); if (!v) return alert('Pegá un enlace o token.');
      const url = buildUrlFromValue(v);
      location.href = url;
    });
    manual.addEventListener('keydown', (e)=>{ if(e.key==='Enter'){ e.preventDefault(); btnOpen.click(); } });

    // Botones cámara
    $('btnStart').addEventListener('click', startCamera);
    $('btnStop').addEventListener('click', stopCamera);
    $('btnSwitch').addEventListener('click', switchCamera);
    $('btnTorch').addEventListener('click', toggleTorch);

    // Pausar cámara si la pestaña se oculta (ahorra batería y evita bloqueos)
    document.addEventListener('visibilitychange', ()=>{
      if (document.hidden && stream){ visStop=true; stopCamera(); }
      else if (!document.hidden && visStop){ visStop=false; startCamera(); }
    });

    // Liberar recursos al salir
    window.addEventListener('beforeunload', ()=>{ if (stream){ stream.getTracks().forEach(t=>t.stop()); } });

    // Arranque
    startCamera();
  </script>
</body>
</html>
