<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

/* Permisos opcionales */
@require_once __DIR__ . '/permiso.php';
if (function_exists('refresh_permissions') && !empty($_SESSION['gimnasio_id'])) {
  refresh_permissions((int)$_SESSION['gimnasio_id']);
}
if (!function_exists('has_perm')) {
  function has_perm(string $feature): bool {
    if (!empty($_SESSION['rol']) && $_SESSION['rol'] === 'admin') return true;
    return function_exists('has_feature') ? has_feature($feature) : true;
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Menú</title>
<style>
  /* ====== Paleta y resets locales para evitar “ensaimada” ====== */
  :root{
    --red:#b91c1c;        /* barra */
    --red-dark:#991b1b;   /* hover */
    --gold:#ffd166;       /* acentos */
    --ink:#111827;        /* texto oscuro */
    --ink-2:#374151;
    --paper:#ffffff;
    --shadow:0 12px 28px rgba(0,0,0,.18);
    --line:#e5e7eb;
    --radius:14px;
  }
  /* Reset tipográfico SOLO para este menú */
  .nav-scope, .nav-scope *{
    font-family: system-ui, -apple-system, Segoe UI, Roboto, Inter, Arial, sans-serif !important;
    letter-spacing: normal !important;
    line-height: 1.25 !important;
    text-transform: none !important;
    word-break: normal !important;
    overflow-wrap: anywhere !important; /* evita desbordes raros */
    box-sizing: border-box;
  }

  /* ====== Topbar común (ambos modos comparten) ====== */
  .topbar{
    position:sticky; top:0; left:0; right:0; z-index: 4000;
    background: var(--red);
    color:#fff;
    display:flex; align-items:center; justify-content:space-between;
    padding:10px 12px;
    box-shadow: var(--shadow);
  }
  .tb-left,.tb-right{display:flex; gap:8px; align-items:center}
  .tb-title{font-weight:800; letter-spacing:.2px}
  .tb-btn{
    appearance:none; border:0; cursor:pointer;
    background: rgba(255,255,255,.12); color:#fff;
    padding:8px 10px; border-radius:10px; font-size:16px;
    transition: .2s transform ease, .2s background ease;
  }
  .tb-btn:hover{ transform: translateY(-1px); background: rgba(255,255,255,.18) }
  .tb-logo{
    display:inline-flex; align-items:center; gap:8px;
    color:#fff; text-decoration:none;
    padding:6px 10px; border-radius:10px; background:transparent;
  }
  .tb-logo strong{ color:var(--gold) }

  /* ====== DESKTOP NAV (>=992px) ====== */
  .nav-desktop{ display:none; background:var(--red); color:#fff; padding:0 8px; }
  .nav-desktop .bar{
    max-width:1200px; margin:0 auto; display:flex; gap:4px; align-items:center;
  }
  .nav-desktop .item, .nav-desktop .drop > .item{
    display:inline-block; padding:12px 14px; color:#fff; text-decoration:none; font-weight:700; border-radius:10px;
  }
  .nav-desktop .item:hover{ background: var(--red-dark); }
  .drop{ position:relative }
  .drop .menu{
    position:absolute; top:100%; left:0; min-width:240px;
    background:var(--paper); color:var(--ink);
    border:1px solid var(--line); border-radius:12px;
    box-shadow: var(--shadow);
    padding:6px 0; display:none; z-index: 4200;
  }
  .drop:hover .menu{ display:block; }
  .menu a{
    display:block; padding:10px 12px; color:var(--ink); text-decoration:none; font-weight:600;
  }
  .menu a:hover{ background:#f9fafb }
  .menu hr{ border:0; border-top:1px solid var(--line); margin:6px 0 }

  /* ====== MOBILE (drawer) (<992px) ====== */
  .nav-mobile{ display:block }
  .drawer{
    position: fixed; inset:0 0 0 auto; width:min(86vw,380px);
    background: var(--paper); color: var(--ink);
    transform: translateX(102%); transition: .28s transform ease;
    z-index: 4500; box-shadow: var(--shadow); padding:14px 12px 18px;
    overflow:auto;
  }
  .drawer.open{ transform: translateX(0); }
  .mask{
    position: fixed; inset:0; background: rgba(0,0,0,.35);
    opacity:0; pointer-events:none; transition:.25s opacity ease; z-index:4400;
  }
  .mask.show{ opacity:1; pointer-events:auto; }

  .d-section{
    border:1px solid var(--line); border-radius:12px; margin:8px 0; overflow:hidden; background:#fff;
  }
  .d-head{
    display:flex; align-items:center; justify-content:space-between;
    padding:12px; font-weight:800; color:var(--ink-2); cursor:pointer; background:#fff;
  }
  .d-body{ display:none; border-top:1px dashed var(--line); padding:8px; }
  .d-body a{
    display:block; padding:10px; border-radius:10px; color:var(--ink); text-decoration:none; font-weight:600;
  }
  .d-body a:hover{ background:#f3f4f6; }

  /* ====== Breakpoint ====== */
  @media (min-width: 992px){
    .nav-mobile{ display:none !important; }
    .nav-desktop{ display:block !important; position:sticky; top:48px; z-index:3500; }
    /* En desktop ocultamos botón hamburguesa, dejamos logo+titulo */
    .topbar .tb-left .tb-btn{ display:none; }
  }
</style>
</head>
<body class="nav-scope">

<!-- ========= TOPBAR (común) ========= -->
<div class="topbar">
  <div class="tb-left">
    <button class="tb-btn" id="btnOpen" aria-label="Abrir menú">☰</button>
    <a href="index.php" class="tb-logo">
      <span>🏋️</span> <span class="tb-title"><strong>Menú</strong></span>
    </a>
  </div>
  <div class="tb-right">
    <!-- lugar para reloj/usuario si querés -->
    <button class="tb-btn" onclick="location.reload()" title="Refrescar">↻</button>
  </div>
</div>

<!-- ========= NAV DESKTOP ========= -->
<nav class="nav-desktop">
  <div class="bar">

    <?php if (has_perm('panel_gimnasio')): ?>
    <div class="drop">
      <a class="item" href="#">🏢 Panel Gimnasio</a>
      <div class="menu">
        <a href="panel_gimnasios.php">Dashboard</a>
        <a href="agregar_gimnasio.php">Agregar Gimnasio</a>
        <a href="renovar_gimnasio.php">Renovar Plan</a>
      </div>
    </div>
    <?php endif; ?>

    <?php if (has_perm('clientes')): ?>
    <div class="drop">
      <a class="item" href="#">👤 Clientes</a>
      <div class="menu">
        <a href="ver_clientes.php">Ver Clientes</a>
        <a href="agregar_cliente.php">Agregar Cliente</a>
        <a href="maquinas_qr.php">🏷️ QR de Máquinas</a>
        <a href="profesor_seguimiento.php">📈 Seguimiento de alumnos</a>
      </div>
    </div>
    <?php endif; ?>

    <?php if (has_perm('membresias')): ?>
    <div class="drop">
      <a class="item" href="#">📅 Membresías</a>
      <div class="menu">
        <a href="ver_membresias.php">Ver Membresías</a>
        <a href="nueva_membresia.php">Agregar Membresía</a>
        <a href="disciplinas.php">Disciplinas</a>
        <a href="planes.php">Planes</a>
        <a href="adicionales.php">Adicionales</a>
        <hr>
        <a href="admin_cena.php">🍽️ Cena (Admin)</a>
      </div>
    </div>
    <?php endif; ?>

    <?php if (has_perm('pagos')): ?>
    <div class="drop">
      <a class="item" href="#">💳 Pagos</a>
      <div class="menu">
        <a href="ver_pagos_pendientes.php">Pagos Pendientes</a>
        <a href="config_alias.php">Alias</a>
        <a href="ver_pagos_mes.php">Pagos del Mes</a>
        <a href="ver_cuentas_corrientes.php">Pagos Cuenta Corriente</a>
        <a href="gastos.php">Gastos</a>
      </div>
    </div>
    <?php endif; ?>

    <?php if (has_perm('asistencias')): ?>
    <div class="drop">
      <a class="item" href="#">🧍‍♂️ Asistencias</a>
      <div class="menu">
        <a href="ver_asistencia.php">Ver Asistencias</a>
        <a href="registrar_asistencia.php" target="_blank" rel="noopener">Registrar Asistencia</a>
        <a href="scanner_qr.php">Escaneo QR</a>
        <a href="ver_asistencias_profesor.php">Asistencia Profesores</a>
      </div>
    </div>
    <?php endif; ?>

    <?php if (has_perm('ventas')): ?>
    <div class="drop">
      <a class="item" href="#">🛒 Ventas</a>
      <div class="menu">
        <a href="agregar_producto.php">Agregar Productos</a>
        <a href="ventas_proteccion.php">Ventas Protecciones</a>
        <a href="ventas_suplementos.php">Ventas Suplementos</a>
        <a href="ventas_indumentaria.php">Ventas Indumentaria</a>
        <a href="ver_productos.php">Ver Productos</a>
        <a href="ver_facturas.php">Ver Facturas</a>
        <a href="promociones_admin.php">Promociones</a>
        <a href="admin_indum.php">🛍️ Indumentaria (Admin)</a>
        <a href="admin_pedidos_indum.php">🧾 Pedidos indumentaria</a>
      </div>
    </div>
    <?php endif; ?>

    <?php if (has_perm('profesores')): ?>
    <div class="drop">
      <a class="item" href="#">👨‍🏫 Profesores</a>
      <div class="menu">
        <a href="agregar_profesor.php">Agregar Profesor</a>
        <a href="login_profesor.php">Panel</a>
        <a href="ver_profesores.php">Ver Profesores</a>
        <a href="turnos_profesor.php">Turnos Profesores</a>
        <a href="editar_tarifa_profesor.php">Precio de Horas</a>
        <a href="reporte_horas_profesor.php">Reporte de Horas</a>
        <a href="biometria/enrolar_profesores.php">Enrolar huella</a>
      </div>
    </div>
    <?php endif; ?>

    <?php if (has_perm('panel_cliente')): ?>
    <div class="drop">
      <a class="item" href="#">📲 Panel Cliente</a>
      <div class="menu">
        <a href="cliente_acceso.php">Panel</a>
        <a href="panel_configuracion.php">Panel Configuración</a>
      </div>
    </div>
    <?php endif; ?>

    <?php if (has_perm('eventos_panel')): ?>
    <div class="drop">
      <a class="item" href="#">🎪 Eventos</a>
      <div class="menu">
        <a href="panel_eventos.php">Panel de Eventos</a>
        <a href="login_evento.php">Acceso a Panel</a>
        <?php if (has_perm('eventos')): ?>
          <a href="eventos_publicos.php">Eventos Públicos</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="drop">
      <a class="item" href="#">❌ Cerrar</a>
      <div class="menu">
        <a href="index.php">Volver al Inicio</a>
        <a href="logout.php">Cerrar Sesión</a>
        <a href="#" onclick="if(confirm('¿Cerrar la aplicación?')){ window.close(); }">❌ Cerrar Programa</a>
      </div>
    </div>

  </div>
</nav>

<!-- ========= NAV MOBILE (DRAWER) ========= -->
<div class="nav-mobile">
  <div class="mask" id="mask"></div>
  <aside class="drawer" id="drawer" aria-hidden="true">
    <!-- Secciones en acordeón -->
    <?php if (has_perm('panel_gimnasio')): ?>
    <section class="d-section">
      <header class="d-head" data-acc>🏢 Panel Gimnasio</header>
      <div class="d-body">
        <a href="panel_gimnasios.php">Dashboard</a>
        <a href="agregar_gimnasio.php">Agregar Gimnasio</a>
        <a href="renovar_gimnasio.php">Renovar Plan</a>
      </div>
    </section>
    <?php endif; ?>

    <?php if (has_perm('clientes')): ?>
    <section class="d-section">
      <header class="d-head" data-acc>👤 Clientes</header>
      <div class="d-body">
        <a href="ver_clientes.php">Ver Clientes</a>
        <a href="agregar_cliente.php">Agregar Cliente</a>
        <a href="maquinas_qr.php">🏷️ QR de Máquinas</a>
        <a href="profesor_seguimiento.php">📈 Seguimiento de alumnos</a>
      </div>
    </section>
    <?php endif; ?>

    <?php if (has_perm('membresias')): ?>
    <section class="d-section">
      <header class="d-head" data-acc>📅 Membresías</header>
      <div class="d-body">
        <a href="ver_membresias.php">Ver Membresías</a>
        <a href="nueva_membresia.php">Agregar Membresía</a>
        <a href="disciplinas.php">Disciplinas</a>
        <a href="planes.php">Planes</a>
        <a href="adicionales.php">Adicionales</a>
        <a href="admin_cena.php">🍽️ Cena (Admin)</a>
      </div>
    </section>
    <?php endif; ?>

    <?php if (has_perm('pagos')): ?>
    <section class="d-section">
      <header class="d-head" data-acc>💳 Pagos</header>
      <div class="d-body">
        <a href="ver_pagos_pendientes.php">Pagos Pendientes</a>
        <a href="config_alias.php">Alias</a>
        <a href="ver_pagos_mes.php">Pagos del Mes</a>
        <a href="ver_cuentas_corrientes.php">Pagos Cuenta Corriente</a>
        <a href="gastos.php">Gastos</a>
      </div>
    </section>
    <?php endif; ?>

    <?php if (has_perm('asistencias')): ?>
    <section class="d-section">
      <header class="d-head" data-acc>🧍‍♂️ Asistencias</header>
      <div class="d-body">
        <a href="ver_asistencia.php">Ver Asistencias</a>
        <a href="registrar_asistencia.php" target="_blank" rel="noopener">Registrar Asistencia</a>
        <a href="scanner_qr.php">Escaneo QR</a>
        <a href="ver_asistencias_profesor.php">Asistencia Profesores</a>
      </div>
    </section>
    <?php endif; ?>

    <?php if (has_perm('ventas')): ?>
    <section class="d-section">
      <header class="d-head" data-acc>🛒 Ventas</header>
      <div class="d-body">
        <a href="agregar_producto.php">Agregar Productos</a>
        <a href="ventas_proteccion.php">Ventas Protecciones</a>
        <a href="ventas_suplementos.php">Ventas Suplementos</a>
        <a href="ventas_indumentaria.php">Ventas Indumentaria</a>
        <a href="ver_productos.php">Ver Productos</a>
        <a href="ver_facturas.php">Ver Facturas</a>
        <a href="promociones_admin.php">Promociones</a>
        <a href="admin_indum.php">🛍️ Indumentaria (Admin)</a>
        <a href="admin_pedidos_indum.php">🧾 Pedidos indumentaria</a>
      </div>
    </section>
    <?php endif; ?>

    <?php if (has_perm('profesores')): ?>
    <section class="d-section">
      <header class="d-head" data-acc>👨‍🏫 Profesores</header>
      <div class="d-body">
        <a href="agregar_profesor.php">Agregar Profesor</a>
        <a href="login_profesor.php">Panel</a>
        <a href="ver_profesores.php">Ver Profesores</a>
        <a href="turnos_profesor.php">Turnos Profesores</a>
        <a href="editar_tarifa_profesor.php">Precio de Horas</a>
        <a href="reporte_horas_profesor.php">Reporte de Horas</a>
        <a href="biometria/enrolar_profesores.php">Enrolar huella</a>
      </div>
    </section>
    <?php endif; ?>

    <?php if (has_perm('panel_cliente')): ?>
    <section class="d-section">
      <header class="d-head" data-acc>📲 Panel Cliente</header>
      <div class="d-body">
        <a href="cliente_acceso.php">Panel</a>
        <a href="panel_configuracion.php">Panel Configuración</a>
      </div>
    </section>
    <?php endif; ?>

    <?php if (has_perm('eventos_panel')): ?>
    <section class="d-section">
      <header class="d-head" data-acc>🎪 Eventos</header>
      <div class="d-body">
        <a href="panel_eventos.php">Panel de Eventos</a>
        <a href="login_evento.php">Acceso a Panel</a>
        <?php if (has_perm('eventos')): ?>
          <a href="eventos_publicos.php">Eventos Públicos</a>
        <?php endif; ?>
      </div>
    </section>
    <?php endif; ?>

    <section class="d-section">
      <header class="d-head" data-acc>❌ Cerrar</header>
      <div class="d-body">
        <a href="index.php">Volver al Inicio</a>
        <a href="logout.php">Cerrar Sesión</a>
        <a href="#" onclick="if(confirm('¿Cerrar la aplicación?')){ window.close(); }">❌ Cerrar Programa</a>
      </div>
    </section>
  </aside>
</div>

<script>
  // Drawer
  const drawer = document.getElementById('drawer');
  const mask   = document.getElementById('mask');
  const btnOpen= document.getElementById('btnOpen');

  function openDrawer(){ drawer.classList.add('open'); mask.classList.add('show'); drawer.setAttribute('aria-hidden','false'); }
  function closeDrawer(){ drawer.classList.remove('open'); mask.classList.remove('show'); drawer.setAttribute('aria-hidden','true'); }

  btnOpen?.addEventListener('click', openDrawer);
  mask?.addEventListener('click', closeDrawer);
  document.addEventListener('keydown', e => { if(e.key==='Escape') closeDrawer(); });

  // Acordeones móviles
  document.querySelectorAll('[data-acc]').forEach(h=>{
    h.addEventListener('click', ()=>{
      const body = h.nextElementSibling;
      const open = body && body.style.display === 'block';
      // cerrar otros
      document.querySelectorAll('.d-body').forEach(b=> b.style.display='none');
      if (body) body.style.display = open ? 'none' : 'block';
    });
  });
</script>

</body>
</html>
