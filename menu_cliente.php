<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<style>
  /* ===== MENÚ CLIENTE (aislado con [data-mc]) ===========================
     - Horizontal en desktop (>= 992px)
     - Hamburguesa + drawer en móvil
     - Sin colisiones: variables --mc-* locales y reset visual
  ======================================================================= */
  [data-mc]{
    /* 🎨 Paleta por defecto (oscuro). Cambiá acá si querés letras negras: 
       --mc-bg:#ffffff; --mc-ink:#0f172a; --mc-muted:#64748b; */
    --mc-bg:#0e1422;
    --mc-ink:#e6edf5;
    --mc-muted:#9aa7bd;

    --mc-surface:#0c111a;
    --mc-border:rgba(255,255,255,.16);

    --mc-brand:#22d3ee;
    --mc-brand2:#06b6d4;
  }

  /* 🧼 Reset anti “texto blanco/transparente” heredado */
  [data-mc], [data-mc] *{
    -webkit-text-fill-color: currentColor !important;
    -webkit-background-clip: initial !important;
    background-clip: initial !important;
    background: none;
    color: inherit;
    box-sizing: border-box;
    font-family: system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
  }

  /* ===== Top bar ======================================================= */
  .mc-top[data-mc]{
    position: sticky; top: 0; z-index: 2000;
    background: var(--mc-bg);
    border-bottom: 1px solid var(--mc-border);
    color: var(--mc-ink);
  }
  .mc-bar[data-mc]{ display:flex; align-items:center; gap:10px; padding:10px 12px; }
  .mc-title[data-mc]{ font-weight:800; letter-spacing:.2px; color: var(--mc-ink); }
  .mc-spacer[data-mc]{ flex:1 }

  .mc-btn[data-mc]{
    appearance:none; cursor:pointer;
    border:1px solid var(--mc-border);
    background:#0f172a; color:#fff;
    padding:8px 12px; border-radius:999px; font-weight:800;
  }
  .mc-link[data-mc]{
    text-decoration:none; color:var(--mc-ink);
    padding:8px 12px; border-radius:12px; border:1px solid var(--mc-border);
    font-weight:600;
  }
  .mc-link[data-mc]:hover{ background:#0f172a; }

  /* ===== Nav inline (solo desktop) ==================================== */
  .mc-inline[data-mc]{ display:none; gap:8px; margin-left:12px; }
  @media (min-width: 992px){
    .mc-inline[data-mc]{ display:flex; }
    .mc-btn[data-mc] { display:none; } /* oculta hamburguesa en PC */
  }

  /* ===== Drawer (móvil) =============================================== */
  .mc-drawer[data-mc]{ position:fixed; inset:0; display:none; z-index:2100; }
  .mc-drawer.show[data-mc]{ display:block; }
  .mc-dim[data-mc]{ position:absolute; inset:0; background:rgba(0,0,0,.48); backdrop-filter: blur(2px); }

  .mc-panel[data-mc]{
    position:absolute; top:0; left:0; height:100%; width:min(86vw,420px);
    background: rgba(14,17,26,.94); color: var(--mc-ink);
    border-right: 1px solid var(--mc-border);
    box-shadow: 0 18px 60px rgba(0,0,0,.55);
    padding:14px; overflow:auto;
    transform: translateX(-102%); transition: transform .26s ease;
  }
  .mc-drawer.show .mc-panel[data-mc]{ transform: translateX(0); }

  .mc-list[data-mc]{ display:grid; gap:8px; margin-top:10px; }
  .mc-item[data-mc]{
    display:flex; align-items:center; gap:10px; padding:12px 14px;
    border-radius:14px; text-decoration:none;
    color:var(--mc-ink); background:var(--mc-surface); border:1px solid var(--mc-border);
  }
  .mc-item[data-mc]:hover{ background:#0f172a; }
  .mc-item.active[data-mc]{ outline:2px solid var(--mc-brand); outline-offset:-2px; }

  /* ===== Tabs inferiores (móvil) – opcional =========================== */
  .mc-tabs[data-mc]{
    position: sticky; bottom:0; z-index: 1900;
    background: var(--mc-bg); border-top:1px solid var(--mc-border);
  }
  .mc-tabs[data-mc] ul{ display:flex; margin:0; padding:6px; list-style:none; gap:6px }
  .mc-tabs[data-mc] a{
    flex:1; text-align:center; text-decoration:none;
    padding:8px 6px; border-radius:12px; color:var(--mc-ink);
    border:1px solid transparent;
  }
  .mc-tabs[data-mc] a.active{ background:#0f172a; border-color:var(--mc-border); }
  .mc-tabs[data-mc] .t-ico{ display:block; font-size:1.15rem; line-height:1; margin-bottom:4px; }

  @media (min-width: 861px){ .mc-tabs[data-mc]{ display:none; } }
</style>

<!-- ===== TOP BAR ======================================================= -->
<div class="mc-top" data-mc>
  <div class="mc-bar" data-mc>
    <button class="mc-btn" data-mc id="mcOpen">☰ Menú</button>
    <div class="mc-title" data-mc>Panel Cliente</div>

    <!-- Menú horizontal (PC) -->
    <nav class="mc-inline" data-mc aria-label="Navegación principal">
      <a class="mc-link" data-mc href="panel_cliente.php">Inicio</a>
      <a class="mc-link" data-mc href="ver_turnos_cliente.php">Turnos</a>
      <a class="mc-link" data-mc href="pago_online.php">Pago Online</a>
      <a class="mc-link" data-mc href="form_progreso.php">Progreso</a>
      <a class="mc-link" data-mc href="tienda_indumentaria.php">Tienda</a>
    </nav>

    <div class="mc-spacer" data-mc></div>
    <a class="mc-link" data-mc href="logout_cliente.php">Salir</a>
  </div>
</div>

<!-- ===== DRAWER (MÓVIL) ============================================== -->
<aside class="mc-drawer" data-mc id="mcDrawer" aria-hidden="true">
  <div class="mc-dim" data-mc id="mcClose" aria-label="Cerrar"></div>
  <div class="mc-panel" data-mc>
    <div style="display:flex; align-items:center; gap:10px">
      <button class="mc-btn" data-mc id="mcClose2">✕</button>
      <strong style="color:var(--mc-ink)">Menú</strong>
    </div>
    <div class="mc-list" data-mc role="menu" aria-label="Menú completo">
      <a class="mc-item" data-mc href="panel_cliente.php">🏠 Inicio</a>
      <a class="mc-item" data-mc href="ver_turnos_cliente.php">🗓️ Ver Turnos</a>
      <a class="mc-item" data-mc href="ver_mis_pagos.php">💳 Mis Pagos</a>
      <a class="mc-item" data-mc href="pago_online.php">⚡ Pago Online</a>
      <a class="mc-item" data-mc href="form_progreso.php">📈 Ver Progreso</a>
      <a class="mc-item" data-mc href="grafico_progreso_cliente.php">📊 Evolución</a>
      <a class="mc-item" data-mc href="tienda_indumentaria.php">🛍️ Indumentaria</a>
      <a class="mc-item" data-mc href="asistente_ia_api.php">🤖 Asistente IA</a>
      <a class="mc-item" data-mc href="cena_fin_anio.php">🍽️ Cena Fin de Año</a>
      <a class="mc-item" data-mc href="cliente_qr_maquinas.php">🧰 QR de Máquinas</a>
      <a class="mc-item" data-mc href="logout_cliente.php">🚪 Salir</a>
    </div>
  </div>
</aside>

<!-- ===== TABS INFERIORES (MÓVIL) – Opcional ========================== -->
<nav class="mc-tabs" data-mc aria-label="Navegación rápida">
  <ul>
    <li><a href="panel_cliente.php"><span class="t-ico">🏠</span><span>Inicio</span></a></li>
    <li><a href="ver_turnos_cliente.php"><span class="t-ico">🗓️</span><span>Turnos</span></a></li>
    <li><a href="pago_online.php"><span class="t-ico">⚡</span><span>Pago</span></a></li>
    <li><a href="form_progreso.php"><span class="t-ico">📈</span><span>Progreso</span></a></li>
  </ul>
</nav>

<script>
  // ===== Toggle drawer
  const d=document, drawer=d.getElementById('mcDrawer');
  d.getElementById('mcOpen')?.addEventListener('click', ()=>{
    drawer.classList.add('show'); drawer.setAttribute('aria-hidden','false');
    document.body.style.overflow='hidden';
  });
  function closeDrawer(){
    drawer.classList.remove('show'); drawer.setAttribute('aria-hidden','true');
    document.body.style.overflow='';
  }
  d.getElementById('mcClose')?.addEventListener('click', closeDrawer);
  d.getElementById('mcClose2')?.addEventListener('click', closeDrawer);
  d.addEventListener('keydown', (e)=>{ if(e.key==='Escape'){ closeDrawer(); } });

  // ===== Marcar activo (drawer + inline + tabs)
  (function markActive(){
    const cur = location.pathname.split('/').pop().toLowerCase();
    d.querySelectorAll('[data-mc] a').forEach(a=>{
      const href=(a.getAttribute('href')||'').split('/').pop().toLowerCase();
      if(href && href===cur){ a.classList.add('active'); }
    });
  })();
</script>
