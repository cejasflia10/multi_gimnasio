<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<style>
  :root{
    --bg:#0b1220; --ink:#e5e7eb; --mut:#94a3b8;
    --chip:#111827; --chip-b:#334155;
    --brand:#22d3ee; --brand2:#06b6d4;
  }
  @media (prefers-color-scheme: light){
    :root{ --bg:#ffffff; --ink:#0f172a; --mut:#64748b; --chip:#f1f5f9; --chip-b:#e5e7eb; }
  }

  /* Base */
  .mc-top,*[data-mc]{ box-sizing:border-box; font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif; }
  .mc-top{ position:sticky; top:0; z-index:50; background:var(--bg); border-bottom:1px solid var(--chip-b); }
  .mc-bar{ display:flex; align-items:center; gap:10px; padding:10px 12px; }
  .mc-btn{ appearance:none; border:0; background:linear-gradient(90deg,var(--brand),var(--brand2));
           color:#111; font-weight:800; padding:8px 12px; border-radius:999px; cursor:pointer; }
  .mc-title{ font-weight:800; color:var(--ink); letter-spacing:.2px; }
  .mc-spacer{ flex:1 }
  .mc-link{ color:var(--ink); text-decoration:none; font-weight:600; padding:6px 10px; border-radius:8px; }
  .mc-link:hover{ background:var(--chip); }

  /* Drawer lateral */
  .mc-drawer{ position:fixed; inset:0; display:none; z-index:60; }
  .mc-drawer.show{ display:block; }
  .mc-dim{ position:absolute; inset:0; background:rgba(0,0,0,.45); }
  .mc-panel{ position:absolute; top:0; left:0; height:100%; width:min(86vw,360px);
             background:var(--bg); border-right:1px solid var(--chip-b); padding:14px; overflow:auto; }
  .mc-list{ display:grid; gap:6px; margin-top:8px }
  .mc-item{
    display:flex; align-items:center; gap:10px; padding:12px; border-radius:12px;
    text-decoration:none; color:var(--ink); border:1px solid var(--chip-b); background:var(--chip);
  }
  .mc-item:hover{ border-color:var(--brand); }
  .mc-item:visited, .mc-item *{ color:inherit !important; }

  /* 🔹 Activo en drawer (faltaba esta regla) */
  .mc-item.active{
    background:#0e1526; border-color:var(--brand); color:#fff;
  }
  .mc-item.active *{ color:inherit !important; }

  /* Tabs inferiores (móvil) */
  .mc-tabs{ position:sticky; bottom:0; z-index:45; background:var(--bg); border-top:1px solid var(--chip-b); }
  .mc-tabs ul{ display:flex; margin:0; padding:6px; list-style:none; gap:6px }
  .mc-tabs a{
    flex:1; text-align:center; text-decoration:none; padding:8px 6px; border-radius:10px;
    color:var(--ink);
  }
  .mc-tabs a:visited{ color:var(--ink); }
  .mc-tabs a.active{ background:#111827; border:1px solid var(--brand); color:#fff; }
  .mc-tabs a.active:visited{ color:#fff; }

  .mc-tabs .t-ico{
    display:block; font-size:1.2rem; line-height:1; margin-bottom:4px;
    color:inherit !important; opacity:1 !important;
  }
  .mc-tabs a span{
    display:block; white-space:nowrap;
    color:inherit !important; opacity:1 !important;
  }
  .mc-tabs a.active .t-ico,
  .mc-tabs a.active span{ color:#fff !important; opacity:1 !important; }

  @media (min-width: 861px){ .mc-tabs{ display:none; } }

  /* 🔧 Fix global: anula background-clip:text y text-fill transparent heredados */
  .mc-tabs a,
  .mc-tabs a span,
  .mc-item,
  .mc-item * {
    -webkit-text-fill-color: currentColor !important;
    background: none !important;
    -webkit-background-clip: initial !important;
    background-clip: initial !important;
  }
</style>

<div class="mc-top" data-mc>
  <div class="mc-bar">
    <button class="mc-btn" id="mcOpen">☰ Menú</button>
    <div class="mc-title">Panel Cliente</div>
    <div class="mc-spacer"></div>
    <a class="mc-link" href="logout_cliente.php">Salir</a>
  </div>
</div>

<!-- Drawer lateral -->
<aside class="mc-drawer" id="mcDrawer" aria-hidden="true" data-mc>
  <div class="mc-dim" id="mcClose" aria-label="Cerrar"></div>
  <div class="mc-panel">
    <div style="display:flex; align-items:center; gap:10px">
      <button class="mc-btn" id="mcClose2">✕</button>
      <strong style="color:var(--ink)">Menú</strong>
    </div>
    <div class="mc-list" role="menu" aria-label="Menú completo">
      <a class="mc-item" href="panel_cliente.php">🏠 Inicio</a>
      <a class="mc-item" href="ver_turnos_cliente.php">🗓️ Ver Turnos</a>
      <a class="mc-item" href="ver_mis_pagos.php">💳 Mis Pagos</a>
      <a class="mc-item" href="pago_online.php">⚡ Pago Online</a>
      <a class="mc-item" href="form_progreso.php">📈 Ver Progreso</a>
      <a class="mc-item" href="grafico_progreso_cliente.php">📊 Evolución</a>
      <a class="mc-item" href="tienda_indumentaria.php">🛍️ Indumentaria</a>
      <a class="mc-item" href="asistente_ia_api.php">🤖 Asistente IA</a>
      <a class="mc-item" href="cena_fin_anio.php">🍽️ Cena Fin de Año</a>
      <a class="mc-item" href="cliente_qr_maquinas.php">🧰 QR de Máquinas</a>

      <a class="mc-item" href="logout_cliente.php">🚪 Salir</a>
    </div>
  </div>
</aside>

<!-- Tabs inferiores (móvil) -->
<nav class="mc-tabs" data-mc aria-label="Navegación rápida">
  <ul>
    <li><a href="panel_cliente.php"><span class="t-ico">🏠</span><span>Inicio</span></a></li>
    <li><a href="ver_turnos_cliente.php"><span class="t-ico">🗓️</span><span>Turnos</span></a></li>
    <li><a href="pago_online.php"><span class="t-ico">⚡</span><span>Pago Online</span></a></li>
    <li><a href="ver_progreso.php"><span class="t-ico">📈</span><span>Progreso</span></a></li>
  </ul>
</nav>

<script>
  // Drawer open/close
  const d=document, drawer=d.getElementById('mcDrawer');
  d.getElementById('mcOpen')?.addEventListener('click', ()=>{
    drawer.classList.add('show'); drawer.setAttribute('aria-hidden','false');
  });
  function closeDrawer(){
    drawer.classList.remove('show'); drawer.setAttribute('aria-hidden','true');
  }
  d.getElementById('mcClose')?.addEventListener('click', closeDrawer);
  d.getElementById('mcClose2')?.addEventListener('click', closeDrawer);

  // Marcar activo (drawer + tabs)
  (function markActive(){
    const cur = location.pathname.split('/').pop().toLowerCase();
    d.querySelectorAll('.mc-item, .mc-tabs a').forEach(a=>{
      const href = (a.getAttribute('href')||'').split('/').pop().toLowerCase();
      if (href && href===cur) a.classList.add('active');
    });
  })();
</script>
