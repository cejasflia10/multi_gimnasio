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
  .mp-top, *[data-mp]{ box-sizing:border-box; font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif; }
  .mp-top{ position:sticky; top:0; z-index:50; background:var(--bg); border-bottom:1px solid var(--chip-b); }
  .mp-bar{ display:flex; align-items:center; gap:10px; padding:10px 12px; }
  .mp-title{ font-weight:800; color:var(--ink); letter-spacing:.2px; }
  .mp-spacer{ flex:1 }
  .mp-link{ color:var(--ink); text-decoration:none; font-weight:600; padding:6px 10px; border-radius:8px; }
  .mp-link:hover{ background:var(--chip); }

  /* ======= NUEVO: Tira de links siempre visible (móvil + PC) ======= */
  .mp-nav{
    display:flex;
    gap:8px;
    padding:8px 12px;
    border-top:1px solid var(--chip-b);
    background:var(--bg);
    overflow-x:auto;           /* móvil: permite desplazamiento horizontal */
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
  }
  .mp-nav a{
    white-space:nowrap;
    color:var(--ink);
    text-decoration:none;
    font-weight:600;
    padding:8px 10px;
    border-radius:10px;
    border:1px solid transparent;
    flex:0 0 auto;            /* chips horizontales */
  }
  .mp-nav a:hover{ background:var(--chip); border-color:var(--chip-b); }

  /* Badge de sección en nav (opcional) */
  .mp-chip{
    background:linear-gradient(90deg,var(--brand),var(--brand2));
    color:#111; font-weight:800; padding:8px 12px; border-radius:999px;
    flex:0 0 auto;
  }

  /* Tabs inferiores (móvil) — mantenemos, pero opcional */
  .mp-tabs{ position:sticky; bottom:0; z-index:45; background:var(--bg); border-top:1px solid var(--chip-b); }
  .mp-tabs ul{ display:flex; margin:0; padding:6px; list-style:none; gap:6px }
  .mp-tabs a{
    flex:1; text-align:center; text-decoration:none; padding:8px 6px; border-radius:10px;
    color:var(--ink);
  }
  .mp-tabs a.active{ background:#111827; border:1px solid var(--brand); color:#fff; }

  .mp-tabs .t-ico{ display:block; font-size:1.2rem; line-height:1; margin-bottom:4px; color:inherit !important; opacity:1 !important; }
  .mp-tabs a span{ display:block; white-space:nowrap; color:inherit !important; opacity:1 !important; font-size:.9rem; }
  .mp-tabs a.active .t-ico, .mp-tabs a.active span{ color:#fff !important; opacity:1 !important; }

  /* Ocultar tabs en escritorio */
  @media (min-width: 861px){ .mp-tabs{ display:none; } }

  /* ========= RESET ANTI-GRADIENTE (visibilidad estable) ========= */
  .mp-tabs a, .mp-tabs a *{
    -webkit-text-fill-color: currentColor !important;
    background: none !important;
    -webkit-background-clip: initial !important;
    background-clip: initial !important;
    text-shadow: none !important; filter: none !important; opacity: 1 !important;
  }
  .mp-tabs a:link, .mp-tabs a:visited, .mp-tabs a:hover, .mp-tabs a:active{
    color: var(--ink) !important; -webkit-text-fill-color: currentColor !important;
  }
  .mp-tabs a.active:link, .mp-tabs a.active:visited, .mp-tabs a.active:hover, .mp-tabs a.active:active{
    color: #fff !important; -webkit-text-fill-color: #fff !important;
  }
</style>

<!-- Barra superior -->
<div class="mp-top" data-mp>
  <div class="mp-bar">
    <div class="mp-title">Panel Profesor</div>
    <div class="mp-spacer"></div>
    <a class="mp-link" href="logout_profesor.php">Salir</a>
  </div>

  <!-- ✅ Menú SIEMPRE VISIBLE (móvil y PC) -->
  <nav class="mp-nav" aria-label="Menú principal">
    <span class="mp-chip">☰ Menú</span>
    <a class="mp-link" href="panel_profesor.php">🏠 Inicio</a>
    <a class="mp-link" href="registrar_asistencia.php">🧾 Registro del Profesor</a>
    <a class="mp-link" href="scanner_qr_profesor.php">📷 Escanear Alumnos (QR)</a>
    <a class="mp-link" href="ver_progreso_alumnos.php">📈 Ver Progreso de Alumnos</a>
    <a class="mp-link" href="profesor_seguimiento.php">🗂️ Seguimiento de alumnos</a>
    <a class="mp-link" href="subir_rutina.php">📤 Subir Archivo</a>
    <a class="mp-link" href="registrar_datos_fisicos.php">📏 Datos Físicos</a>
    <a class="mp-link" href="ver_datos_fisicos_profesor.php">📄 Ver Datos</a>
    <a class="mp-link" href="logout_profesor.php">🚪 Cerrar Sesión</a>
  </nav>
</div>

<!-- Tabs inferiores (móvil) — opcional, podés borrar este bloque si no los querés -->
<nav class="mp-tabs" data-mp aria-label="Accesos rápidos">
  <ul>
    <li><a href="panel_profesor.php"><span class="t-ico">🏠</span><span>Inicio</span></a></li>
    <li><a href="registrar_asistencia.php"><span class="t-ico">🧾</span><span>Registro</span></a></li>
    <li><a href="scanner_qr_profesor.php"><span class="t-ico">📷</span><span>Escanear</span></a></li>
    <li><a href="profesor_seguimiento.php"><span class="t-ico">🗂️</span><span>Seguimiento</span></a></li>
    <li><a href="ver_progreso_alumnos.php"><span class="t-ico">📈</span><span>Progreso</span></a></li>
  </ul>
</nav>
