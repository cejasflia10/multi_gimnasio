<?php
// cena_fin_anio.php — Reserva “Cena de Fin de Año” con MENÚ UNIFICADO incrustado (idéntico a otras pantallas)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

$cliente_id  = (int)($_SESSION['cliente_id']  ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($cliente_id === 0 || $gimnasio_id === 0) { echo "<div style='color:red;text-align:center;'>❌ Acceso denegado</div>"; exit; }

// Helpers
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function db_has_table(mysqli $db, string $t): bool {
  $t = $db->real_escape_string($t);
  $res = $db->query("SHOW TABLES LIKE '{$t}'");
  return ($res && $res->num_rows > 0);
}
function money_ar($n){ return number_format((float)$n, 2, ',', '.'); }

// CSRF
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];

// Evento activo más próximo
$evento = null;
if (db_has_table($conexion,'cenas_eventos')) {
  if ($st = @$conexion->prepare("SELECT id, titulo, fecha, hora, lugar, precio_cubierto, sena_minima, cupo_total, cupo_reservado
                                 FROM cenas_eventos
                                 WHERE gimnasio_id=? AND estado='activo'
                                 ORDER BY fecha ASC, hora ASC
                                 LIMIT 1")) {
    $st->bind_param("i",$gimnasio_id);
    if ($st->execute()) { $evento = $st->get_result()->fetch_assoc(); }
    $st->close();
  }
}
$cupo_disponible = $evento ? max(0,(int)$evento['cupo_total'] - (int)$evento['cupo_reservado']) : 0;
$precio_cubierto = $evento ? (float)$evento['precio_cubierto'] : 0.0;
$sena_minima     = $evento ? (float)$evento['sena_minima']     : 0.0;

// Slots 30' 10:00–24:00
function build_slots(): array {
  $out=[]; $t=strtotime('10:00'); $end=strtotime('24:00');
  while($t<$end){ $out[]=date('H:i',$t); $t+=30*60; }
  return $out;
}
$slots = build_slots();

// Flash GET
$flash_ok  = isset($_GET['ok']) ? "✅ Reserva registrada correctamente." : '';
$flash_err = isset($_GET['err']) ? (string)($_GET['msg'] ?? '⚠️ No se pudo registrar la reserva.') : '';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>🍽️ Cena de Fin de Año</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
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
    .mnu-btn{ display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border-radius:999px; cursor:pointer; background:var(--mnu-accent); color:#111; border:none; font-weight:700; text-decoration:none; }
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

    /* ================== BASE / GLASS ================== */
    *{box-sizing:border-box}
    html,body{height:100%}
    body{ margin:0; background: radial-gradient(1000px 600px at 20% -10%, #1c1f28 0%, #0b0b0b 60%), var(--bg); color:var(--fg); font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; }
    .container{ max-width:1100px; margin:0 auto; padding:16px 16px 48px; }
    .glass{ background: rgba(255,255,255,.05); border:1px solid var(--border); border-radius:20px; backdrop-filter: blur(10px); box-shadow: 0 8px 30px rgba(0,0,0,.35); }

    .grid{ display:grid; gap:16px; grid-template-columns: 1fr; }
    @media (min-width:980px){ .grid{ grid-template-columns: 1fr 1fr; } }
    .card{ padding:18px }
    h2{ text-align:center; margin:10px 0 18px; }
    .muted{ color:var(--muted); }
    .btn{ display:inline-block; padding:12px 16px; border-radius:12px; border:0; cursor:pointer; font-weight:700 }

    /* ====== Inputs / bloques ====== */
    label{display:block;margin:.35rem 0 .35rem;font-weight:700;color:var(--acc)}
    .input, select, textarea{ width:100%; padding:12px; border:1px solid #333; border-radius:12px; background:#0f1115; color:#fff; }
    .chips{display:flex;gap:8px;flex-wrap:wrap;margin:.35rem 0}
    .chip{padding:6px 10px;border:1px solid var(--border);border-radius:999px;cursor:pointer;font-size:12px}
    .tot{margin-top:10px;padding:10px;border-radius:10px;background:#1a1d24;border:1px solid rgba(255,255,255,.08)}
    .mini{font-size:12px}
    .alert-ok{background:var(--ok);border:1px solid var(--okb);color:#b6f0c9;padding:10px;border-radius:10px;margin-bottom:10px}
    .alert-err{background:var(--err);border:1px solid var(--errb);color:#f2bcbc;padding:10px;border-radius:10px;margin-bottom:10px}
  </style>
</head>
<body>

<noscript><div style="background:#3a1010;color:#f2bcbc;padding:10px;text-align:center">⚠️ Activá JavaScript para calcular totales y seleccionar horarios más rápido.</div></noscript>

<!-- ===== Menú Unificado (incrustado) ===== -->
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
      <li><a class="mnu-item" href="cliente_qr_maquinas.php"><span class="mnu-item__icon">🧰</span><span class="mnu-item__text">QR de Máquinas</span></a></li>
      <li><a class="mnu-item" href="cliente_acceso.php?logout=1"><span class="mnu-item__icon">🚪</span><span class="mnu-item__text">Salir</span></a></li>
    </ul>
  </aside>
</header>

<div class="container">
  <h2>🍽️ Cena de Fin de Año</h2>

  <?php if ($flash_ok): ?>
    <div class="alert-ok" role="status" aria-live="polite"><?= h($flash_ok) ?></div>
  <?php endif; ?>
  <?php if ($flash_err): ?>
    <div class="alert-err" role="alert" aria-live="assertive"><?= h($flash_err) ?></div>
  <?php endif; ?>

  <?php if (!$evento): ?>
    <section class="glass card">
      <p class="muted">Aún no hay un evento de cena activo configurado para tu gimnasio.</p>
      <p class="muted">Pedile al administrador que lo cargue en <code>cenas_eventos</code> (estado <code>activo</code>).</p>
    </section>
  <?php else: ?>

  <div class="grid">
    <!-- Columna izquierda: datos + formulario -->
    <section class="glass card">
      <h3 style="margin:0 0 6px"><?= h($evento['titulo']) ?></h3>
      <p class="muted" style="margin:0 0 10px">
        📅 <?= h(date('d/m/Y', strtotime($evento['fecha']))) ?> · ⏰ <?= h(substr($evento['hora'],0,5)) ?><br>
        📍 <?= h($evento['lugar']) ?>
      </p>

      <div class="glass" style="padding:12px; border-radius:12px; margin-bottom:12px">
        <strong>Precio por cubierto:</strong> $<?= money_ar($precio_cubierto) ?><br>
        <strong>Seña mínima (por cubierto):</strong> $<?= money_ar($sena_minima) ?><br>
        <strong>Cupo disponible:</strong> <?= (int)$cupo_disponible ?><br>
        <span class="mini muted">
          🎉 Promo: <strong>3–4</strong> cubiertos → <strong>10% OFF</strong> ·
          🎉 <strong>5+</strong> cubiertos → <strong>15% OFF</strong>
        </span>
      </div>

      <?php if ($cupo_disponible<=0): ?>
        <p style="color:#fca5a5"><strong>Sin cupos disponibles por el momento.</strong></p>
      <?php else: ?>
        <form action="guardar_cena_reserva.php" method="post" enctype="multipart/form-data" autocomplete="off" novalidate>
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          <input type="hidden" name="evento_id" value="<?= (int)$evento['id'] ?>">
          <input type="hidden" id="precio_unit" value="<?= (float)$precio_cubierto ?>">
          <input type="hidden" id="sena_unit" value="<?= (float)$sena_minima ?>">

          <label for="cantidad">Cantidad de cubiertos</label>
          <input class="input" type="number" id="cantidad" name="cantidad" min="1" max="<?= (int)$cupo_disponible ?>" value="1" required placeholder="Ej: 2">

          <label>Seleccioná horarios (podés elegir varios)</label>
          <div class="chips" role="group" aria-label="Rangos rápidos">
            <span class="chip" data-range="12:00-15:00">Almuerzo rápido</span>
            <span class="chip" data-range="16:00-18:00">Merienda</span>
            <span class="chip" data-range="20:00-23:00">Cena típica</span>
            <span class="chip" data-range="10:00-24:00">Todo el día</span>
            <span class="chip" data-clear="1">Limpiar</span>
          </div>
          <select class="input" id="horarios" name="horarios[]" multiple required aria-describedby="horariosHelp">
            <?php foreach ($slots as $h): ?>
              <option value="<?= h($h) ?>"><?= h($h) ?></option>
            <?php endforeach; ?>
          </select>
          <small id="horariosHelp" class="mini muted">Tip: mantené Ctrl/⌘ para seleccionar varios horarios.</small>

          <label for="nombres">Nombres de acompañantes <span class="mini muted">(opcional)</span></label>
          <textarea class="input" id="nombres" name="nombres" rows="3" placeholder="Ej: Juan Pérez, Ana López"></textarea>

          <label for="comentario">Comentario <span class="mini muted">(opcional)</span></label>
          <input class="input" id="comentario" name="comentario" maxlength="200" placeholder="Preferencias, alergias, etc.">

          <label for="pago">Forma de pago</label>
          <select class="input" id="pago" name="pago" required>
            <option value="sena_efectivo">Seña ahora (Efectivo)</option>
            <option value="total_efectivo">Pagar total (Efectivo)</option>
            <option value="sena_transferencia">Seña ahora (Transferencia)</option>
            <option value="total_transferencia">Pagar total (Transferencia)</option>
            <option value="pendiente">Reservar y pagar después</option>
          </select>

          <div id="wrap_comprobante" style="display:none">
            <label for="comprobante">Comprobante (imagen o PDF, máx 6MB)</label>
            <input class="input" type="file" id="comprobante" name="comprobante" accept="image/*,application/pdf">
            <small class="mini muted">Subí el recibo/transferencia. Se guardará en la nube.</small>
          </div>

          <div class="tot" id="tot_view" aria-live="polite">
            <div>Subtotal: <strong id="subtot">$0,00</strong></div>
            <div id="desc_line" style="display:none">Descuento: <strong id="desc">$0,00</strong></div>
            <div>Total: <strong id="total">$0,00</strong></div>
            <div>Seña mínima estimada: <strong id="sena_estimada">$0,00</strong></div>
          </div>

          <div id="warn_cupo" class="alert-err" style="display:none; margin-top:8px">⚠️ La cantidad supera el cupo disponible.</div>

          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
            <button id="btnSubmit" class="btn" style="background:var(--acc);color:#111" type="submit">Reservar</button>
            <a class="btn" style="background:transparent;border:1px solid var(--border);color:#fff;text-decoration:none" href="mis_reservas_cena.php">Ver mis reservas</a>
          </div>
        </form>
      <?php endif; ?>
    </section>

    <!-- Columna derecha: info -->
    <aside class="glass card">
      <p class="muted">
        Tu reserva queda registrada al instante. Si elegís pagar seña o total, el estado figurará como
        <em>“seña”</em> o <em>“pagado”</em>. Con “reservar y pagar después”, queda <em>“pendiente”</em>.
        Si pagás por <strong>transferencia</strong>, podés adjuntar el <strong>comprobante</strong> acá mismo.
      </p>
      <p class="mini muted">
        * Descuentos automáticos: 3–4 cubiertos 10% OFF; 5+ cubiertos 15% OFF.<br>
        * Podés elegir varios horarios entre 10:00 y 24:00.
      </p>
    </aside>
  </div>

  <?php endif; ?>
</div>

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
(function(){
  const $ = (id)=>document.getElementById(id);
  const precio = parseFloat(($('precio_unit')?.value)||'0');
  const sena   = parseFloat(($('sena_unit')?.value)||'0');
  const cupo   = <?= (int)$cupo_disponible ?>;
  const fmt = (n)=> n.toLocaleString('es-AR', {minimumFractionDigits:2, maximumFractionDigits:2});

  function recalc(){
    const qRaw = $('cantidad')?.value || '1';
    const q = Math.max(1, parseInt(qRaw,10) || 1);
    let subtotal = precio * q;
    let desc = 0;
    if (q >= 5) { desc = subtotal * 0.15; }
    else if (q >= 3) { desc = subtotal * 0.10; }
    const total = subtotal - desc;
    $('subtot').textContent = '$'+fmt(subtotal);
    $('desc_line').style.display = desc>0 ? '' : 'none';
    $('desc').textContent = '$'+fmt(desc);
    $('total').textContent = '$'+fmt(total);
    $('sena_estimada').textContent = '$'+fmt(sena*q);

    // Validación cupo
    const over = q > cupo;
    const warn = $('warn_cupo');
    const btn  = $('btnSubmit');
    if (warn) warn.style.display = over ? '' : 'none';
    if (btn)  btn.disabled = over;
  }
  function toggleComprobante(resetFile=true){
    const v = $('pago').value;
    const wrap = $('wrap_comprobante');
    wrap.style.display = (v==='sena_transferencia' || v==='total_transferencia') ? '' : 'none';
    if (resetFile) {
      const file = $('comprobante');
      if (file) file.value = '';
    }
  }

  // Chips de rango rápido
  function parseTime(s){ const [H,M]=s.split(':').map(x=>parseInt(x,10)); return H*60+(M||0); }
  function selectRange(r){
    const [a,b]=r.split('-'); const amin=parseTime(a), bmin=parseTime(b);
    const sel=$('horarios');
    for(let i=0;i<sel.options.length;i++){
      const v=sel.options[i].value; const m=parseTime(v);
      sel.options[i].selected = (m>=amin && m<=bmin);
    }
    sel.dispatchEvent(new Event('change'));
  }
  document.querySelectorAll('.chip').forEach(ch=>{
    ch.addEventListener('click', ()=>{
      const sel=$('horarios');
      if (ch.dataset.clear){ for(let i=0;i<sel.options.length;i++) sel.options[i].selected=false; return; }
      if (ch.dataset.range) selectRange(ch.dataset.range);
    });
  });

  $('cantidad')?.addEventListener('input', recalc);
  $('pago')?.addEventListener('change', ()=>toggleComprobante(true));
  recalc(); toggleComprobante(false);
})();
</script>
</body>
</html>
