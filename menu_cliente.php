<?php
/* ============================================================================
   menu_cliente.php — Menú unificado reutilizable (PC tabs + móvil drawer)
   Uso:
     require_once __DIR__.'/menu_cliente.php';
     render_menu_cliente('inicio'); // o 'turnos', 'pagos', etc.
   ============================================================================ */

if (!function_exists('render_menu_cliente')) {
  function render_menu_cliente(string $active = 'inicio'){
    // Mapa de items del menú (clave => [icono, texto, href])
    $items = [
      'inicio'   => ['🏠','Inicio','panel_cliente.php'],
      'turnos'   => ['📅','Ver Turnos','ver_turnos_cliente.php'],
      'pagos'    => ['💳','Mis Pagos','ver_mis_pagos.php'],
      'pago_on'  => ['⚡','Pago Online','pago_online.php'],
      'progreso' => ['📈','Ver Progreso','form_progreso.php'],
      'evol'     => ['📊','Evolución','ver_progreso_cliente.php'],
      'tienda'   => ['🛍️','Indumentaria','tienda_indumentaria.php'],
      'ia'       => ['🤖','Asistente IA','asistente_ia.php'],
      'cena'     => ['🍽️','Cena Fin de Año','cena_fin_anio.php'],
      'qrmaq'    => ['🧰','QR de Máquinas','cliente_qr_maquinas.php'],
      'salir'    => ['🚪','Salir','cliente_acceso.php?logout=1'],
    ];

    // Helpers mini
    $h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

    // CSS del menú (aislado y con prefijo mcli- para no chocar estilos)
    ?>
    <style>
      :root{
        --mcli-bg-bar: rgba(15,19,32,.78);
        --mcli-bg-drawer: rgba(10,12,20,.94);
        --mcli-fg: #fff;
        --mcli-accent: #ffd600;
        --mcli-border: rgba(255,255,255,.16);
        --mcli-shadow: 0 10px 30px rgba(0,0,0,.45);
      }
      .mcli-bar{
        position:sticky; top:0; z-index:1000;
        display:flex; align-items:center; gap:12px;
        padding:10px 14px; background:var(--mcli-bg-bar);
        -webkit-backdrop-filter: blur(10px) saturate(1.05);
        backdrop-filter: blur(10px) saturate(1.05);
        border-bottom:1px solid var(--mcli-border);
      }
      .mcli-title{ font-weight:800; color:var(--mcli-accent); }
      .mcli-spacer{ flex:1; }
      .mcli-btn{ display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border-radius:999px; cursor:pointer; background:var(--mcli-accent); color:#111; border:none; font-weight:700; }
      .mcli-btn--ghost{ background:transparent; color:#fff; border:1px solid var(--mcli-border); }

      .mcli-inline{ display:flex; gap:10px; flex-wrap:wrap; padding:10px 14px; background:transparent; border-bottom:1px solid var(--mcli-border); }
      .mcli-tab{ padding:10px 14px; border-radius:14px; border:1px solid var(--mcli-border); color:#fff; text-decoration:none; }
      .mcli-tab:hover{ background:rgba(255,255,255,.06); }
      .mcli-tab.active{ background:rgba(255,214,0,.15); border-color:rgba(255,214,0,.55); color:#fff; }

      @media (max-width:920px){ .mcli-inline{ display:none !important; } }

      .mcli-backdrop{ position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:10005; display:none; }
      .mcli-drawer{
        position:fixed; top:0; bottom:0; left:0; width:86vw; max-width:360px;
        background:var(--mcli-bg-drawer); border-right:1px solid var(--mcli-border);
        box-shadow:var(--mcli-shadow); transform:translateX(-100%); transition:transform .25s ease;
        z-index:10010; padding:14px; display:flex; flex-direction:column; gap:12px;
      }
      .mcli-drawer.open{ transform:translateX(0); }
      .mcli-backdrop.show{ display:block; }
      .mcli-head{ display:flex; align-items:center; gap:10px; margin-bottom:6px; }
      .mcli-close{ width:44px; height:44px; border-radius:50%; display:grid; place-items:center; cursor:pointer; background:var(--mcli-accent); color:#111; font-weight:900; border:none; }
      .mcli-list{ display:flex; flex-direction:column; gap:12px; margin:0; padding:0; list-style:none; }
      .mcli-item{ display:flex; align-items:center; gap:12px; padding:14px; border-radius:14px; border:1px solid var(--mcli-border); color:#fff; text-decoration:none; background:transparent; }
      .mcli-item:hover{ background:rgba(255,255,255,.10); border-color:rgba(255,255,255,.30); }
      .mcli-item.active{ background:rgba(255,214,0,.15); border-color:rgba(255,214,0,.55); }
      .mcli-item__icon{ width:24px; display:inline-grid; place-items:center; color:#fff; }
      .mcli-item__text{ font-size:18px; }

      /* evitar problemas de text-fill transparente en navegadores */
      .mcli-bar *, .mcli-drawer *, .mcli-inline *, .mcli-item, .mcli-item *{
        color:#fff !important; -webkit-text-fill-color:#fff !important;
        text-shadow:none !important; background-clip:initial !important; -webkit-background-clip:initial !important;
      }
    </style>

    <header class="mcli">
      <div class="mcli-bar">
        <button class="mcli-btn mcli-open" type="button">☰ Menú</button>
        <div class="mcli-title">Panel Cliente</div>
        <div class="mcli-spacer"></div>
        <a class="mcli-btn mcli-btn--ghost" href="cliente_acceso.php?logout=1">Salir</a>
      </div>

      <!-- Tabs inline (PC) -->
      <nav class="mcli-inline">
        <?php foreach ($items as $key=>$it): ?>
          <?php if ($key==='salir') continue; // el botón salir ya está arriba ?>
          <a class="mcli-tab <?= $key===$active?'active':'' ?>" href="<?= $h($it[2]) ?>">
            <?= $h($it[0]) ?> <?= $h($it[1]) ?>
          </a>
        <?php endforeach; ?>
      </nav>

      <!-- Drawer (móvil) -->
      <div class="mcli-backdrop" id="mcli-backdrop"></div>
      <aside class="mcli-drawer" id="mcli-drawer">
        <div class="mcli-head">
          <button class="mcli-close" id="mcli-close" type="button">✕</button>
          <div class="mcli-title">Menú</div>
        </div>
        <ul class="mcli-list">
          <?php foreach ($items as $key=>$it): ?>
            <li>
              <a class="mcli-item <?= $key===$active?'active':'' ?>" href="<?= $h($it[2]) ?>">
                <span class="mcli-item__icon"><?= $h($it[0]) ?></span>
                <span class="mcli-item__text"><?= $h($it[1]) ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </aside>
    </header>

    <script>
    // Drawer abrir/cerrar + lock scroll
    (function(){
      const drawer = document.getElementById('mcli-drawer');
      const backdrop = document.getElementById('mcli-backdrop');
      const openBtn = document.querySelector('.mcli-open');
      const closeBtn = document.getElementById('mcli-close');
      const lock = (on)=>{ document.documentElement.style.overflow = document.body.style.overflow = on?'hidden':''; }
      function open(){ drawer.classList.add('open'); backdrop.classList.add('show'); lock(true); }
      function close(){ drawer.classList.remove('open'); backdrop.classList.remove('show'); lock(false); }
      openBtn?.addEventListener('click', open);
      closeBtn?.addEventListener('click', close);
      backdrop?.addEventListener('click', close);
      window.addEventListener('keydown', e=>{ if(e.key==='Escape') close(); });
    })();
    </script>
    <?php
  }
}
