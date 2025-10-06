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
  .mp-top,*[data-mp]{ box-sizing:border-box; font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif; }
  .mp-top{ position:sticky; top:0; z-index:50; background:var(--bg); border-bottom:1px solid var(--chip-b); }
  .mp-bar{ display:flex; align-items:center; gap:10px; padding:10px 12px; }
  .mp-btn{ appearance:none; border:0; background:linear-gradient(90deg,var(--brand),var(--brand2));
           color:#111; font-weight:800; padding:8px 12px; border-radius:999px; cursor:pointer; }
  .mp-title{ font-weight:800; color:var(--ink); letter-spacing:.2px; }
  .mp-spacer{ flex:1 }
  .mp-link{ color:var(--ink); text-decoration:none; font-weight:600; padding:6px 10px; border-radius:8px; }
  .mp-link:hover{ background:var(--chip); }

  /* (Opción A) ocultar chips horizontales */
  .mp-scroll{ display:none !important; }

  /* Drawer lateral */
  .mp-drawer{ position:fixed; inset:0; display:none; z-index:60; }
  .mp-drawer.show{ display:block; }
  .mp-dim{ position:absolute; inset:0; background:rgba(0,0,0,.45); }
  .mp-panel{ position:absolute; top:0; left:0; height:100%; width:min(86vw,360px);
             background:var(--bg); border-right:1px solid var(--chip-b); padding:14px; overflow:auto; }
  .mp-list{ display:grid; gap:6px; margin-top:8px }
  .mp-item{ display:flex; align-items:center; gap:10px; padding:12px; border-radius:12px;
            text-decoration:none; color:var(--ink); border:1px solid var(--chip-b); background:var(--chip); }
  .mp-item:hover{ border-color:var(--brand); }

  /* Bottom tabs (móvil) */
  .mp-tabs{ position:sticky; bottom:0; z-index:45; background:var(--bg); border-top:1px solid var(--chip-b); }
  .mp-tabs ul{ display:flex; margin:0; padding:6px; list-style:none; gap:6px }
  .mp-tabs a{ flex:1; text-align:center; text-decoration:none; color:var(--ink); padding:8px 6px; border-radius:10px }
  .mp-tabs a.active{ background:#111827; border:1px solid var(--brand); color:#fff }
  .mp-tabs .t-ico{ display:block; font-size:1.2rem; line-height:1; margin-bottom:4px }

  /* Ocultar tabs en escritorio */
  @media (min-width: 861px){ .mp-tabs{ display:none; } }
</style>

<div class="mp-top" data-mp>
  <div class="mp-bar">
    <button class="mp-btn" id="mpOpen">☰ Menú</button>
    <div class="mp-title">Panel Profesor</div>
    <div class="mp-spacer"></div>
    <a class="mp-link" href="logout_profesor.php">Salir</a>
  </div>
</div>

<!-- Drawer lateral -->
<aside class="mp-drawer" id="mpDrawer" aria-hidden="true" data-mp>
  <div class="mp-dim" id="mpClose" aria-label="Cerrar"></div>
  <div class="mp-panel">
    <div style="display:flex; align-items:center; gap:10px">
      <button class="mp-btn" id="mpClose2">✕</button>
      <strong style="color:var(--ink)">Menú</strong>
    </div>
    <div class="mp-list" role="menu" aria-label="Menú completo">
      <a class="mp-item" href="panel_profesor.php">🏠 Inicio</a>
      <a class="mp-item" href="registrar_asistencia.php">🧾 Registro del Profesor</a>
      <a class="mp-item" href="scanner_qr_profesor.php">📷 Escanear Alumnos (QR)</a>
      <a class="mp-item" href="ver_progreso_alumnos.php">📈 Ver Progreso de Alumnos</a>
      <a class="mp-item" href="profesor_seguimiento.php">🗂️ Seguimiento de alumnos</a>
      <a class="mp-item" href="subir_rutina.php">📤 Subir Archivo</a>
      <a class="mp-item" href="registrar_graduacion.php">🎓 Graduación</a>
      <a class="mp-item" href="ver_graduaciones.php">📜 Ver Graduaciones</a>
      <a class="mp-item" href="registrar_competencia.php">🥋 Competencia</a>
      <a class="mp-item" href="ver_competencias.php">🏆 Ver Competencias</a>
      <a class="mp-item" href="registrar_datos_fisicos.php">📏 Datos Físicos</a>
      <a class="mp-item" href="ver_datos_fisicos_profesor.php">📄 Ver Datos</a>
      <a class="mp-item" href="ver_competidores.php">👥 Ver Competidores</a>
      <a class="mp-item" href="logout_profesor.php">🚪 Cerrar Sesión</a>
    </div>
  </div>
</aside>

<!-- Tabs inferiores (móvil) -->
<nav class="mp-tabs" data-mp aria-label="Accesos rápidos">
  <ul>
    <li><a href="panel_profesor.php"><span class="t-ico">🏠</span><span>Inicio</span></a></li>
    <li><a href="registrar_asistencia.php"><span class="t-ico">🧾</span><span>Registro</span></a></li>
    <li><a href="scanner_qr_profesor.php"><span class="t-ico">📷</span><span>Escanear</span></a></li>
    <li><a href="profesor_seguimiento.php"><span class="t-ico">🗂️</span><span>Seguimiento</span></a></li>
    <li><a href="ver_progreso_alumnos.php"><span class="t-ico">📈</span><span>Progreso</span></a></li>
  </ul>
</nav>

<script>
  // Abrir/cerrar drawer
  const d=document, drawer=d.getElementById('mpDrawer');
  d.getElementById('mpOpen')?.addEventListener('click', ()=>{
    drawer.classList.add('show'); drawer.setAttribute('aria-hidden','false');
  });
  function closeDrawer(){
    drawer.classList.remove('show'); drawer.setAttribute('aria-hidden','true');
  }
  d.getElementById('mpClose')?.addEventListener('click', closeDrawer);
  d.getElementById('mpClose2')?.addEventListener('click', closeDrawer);

  // Marcar activo según URL (drawer + tabs)
  (function markActive(){
    const cur = location.pathname.split('/').pop().toLowerCase();
    d.querySelectorAll('.mp-item, .mp-tabs a').forEach(a=>{
      const href = (a.getAttribute('href')||'').split('/').pop().toLowerCase();
      if (href && href===cur) a.classList.add('active');
    });
  })();
</script>
