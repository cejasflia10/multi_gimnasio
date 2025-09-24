<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

/* Intenta cargar permisos; si no existe, no rompe */
@require_once __DIR__ . '/permiso.php';

/* Refresca SIEMPRE los permisos si hay gimnasio en sesión (evita cache viejo) */
if (function_exists('refresh_permissions') && !empty($_SESSION['gimnasio_id'])) {
  refresh_permissions((int)$_SESSION['gimnasio_id']);
}

/* Wrapper de permisos para el menú:
   - Admin ve todo.
   - Si existe has_feature() (de permiso.php), la usamos.
   - Si no existe, devolvemos true para no esconder el menú por error.
*/
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
  <meta charset="UTF-8">
  <title>Menú Horizontal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    :root{
      --bg:#000; --fg:gold; --brand:#a00; --brand-dark:#700; --drop:#111; --border:#700; --line:#333;
    }
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);color:var(--fg);font-family:Arial,Helvetica,sans-serif}

    .menu-toggle{
      display:none;background:var(--brand);color:var(--fg);
      font-size:20px;padding:10px;text-align:center;cursor:pointer
    }

    .menu-horizontal{
      background:var(--brand);display:flex;flex-wrap:wrap;justify-content:flex-start;
      padding:6px 10px;position:relative;z-index:1000
    }
    .menu-horizontal > .dropdown{position:relative}
    .menu-horizontal > .dropdown > a,
    .menu-horizontal > a{
      color:var(--fg);text-decoration:none;font-weight:bold;padding:10px 14px;
      display:inline-block;border-radius:6px
    }

    .dropdown-content{
      display:none;position:absolute;background:var(--drop);min-width:220px;z-index:1100;
      border:1px solid var(--border);border-radius:8px;overflow:hidden
    }
    .dropdown-content a{
      display:block;padding:10px 12px;border-bottom:1px solid var(--line);
      color:var(--fg);text-decoration:none
    }
    .dropdown:hover .dropdown-content{display:block}

    .menu-horizontal a:hover,
    .dropdown-content a:hover{background:var(--brand-dark)}

    /* Indicador visual (opcional) para links que abren ventana */
    .dropdown-content a.newwin::after{
      content:"↗"; font-weight:bold; margin-left:8px; opacity:.85;
    }

    @media (max-width: 768px){
      .menu-toggle{display:block}
      .menu-horizontal{display:none;flex-direction:column;width:100%}
      .menu-horizontal.active{display:flex !important}
      .dropdown{width:100%}
      .dropdown-content{position:static;background:var(--drop);border:none;border-radius:0}
      .menu-horizontal a{display:block;padding:12px}
      .dropdown-content a{padding-left:20px}
    }
  </style>
  <script>
    function toggleMenu(){
      var menu = document.getElementById("menu-principal");
      menu.classList.toggle("active");
    }
    function cerrarApp(){
      if (confirm("¿Seguro que deseas cerrar la aplicación?")) {
        if (window.electronAPI) { window.electronAPI.cerrarVentana(); }
        else { window.close(); }
      }
    }

    // --- POPUP CONTROLADO para <a.newwin> ---
    (function(){
      const opened = new Map(); // reuso por nombre

      document.addEventListener('click', function(e){
        const a = e.target.closest('a.newwin');
        if (!a) return;

        // click de usuario -> no debería bloquearse
        e.preventDefault();

        const href     = a.href;
        const isPopup  = a.dataset.popup === '1';
        const features = (a.dataset.features || '').trim();
        const winName  = (a.dataset.window || '_blank').trim();

        if (!isPopup) {
          window.open(href, '_blank', 'noopener');
          return;
        }

        // parseo básico de width/height
        const parseFeat = (key, def) => {
          const m = new RegExp(key + '=([0-9]+)').exec(features);
          return m ? parseInt(m[1], 10) : def;
        };
        const w = parseFeat('width', 1200);
        const h = parseFeat('height', 800);

        // centro en pantalla disponible
        const left = Math.max(0, Math.floor((window.screen.availWidth  - w) / 2));
        const top  = Math.max(0, Math.floor((window.screen.availHeight - h) / 2));

        // features finales (con left/top)
        const baseFeats = features
          ? features.replace(/\bleft=\d+\b/g,'').replace(/\btop=\d+\b/g,'')
          : 'menubar=no,toolbar=no,location=no,status=no,scrollbars=yes,resizable=yes,width=' + w + ',height=' + h;
        const finalFeats = baseFeats + `,left=${left},top=${top}`;

        // reutilizo ventana si sigue abierta
        let win = opened.get(winName);
        if (win && !win.closed) {
          try { win.focus(); win.location.href = href; } catch {}
        } else {
          win = window.open(href, winName, finalFeats);
          if (win) {
            try { win.opener = null; } catch {}
            opened.set(winName, win);
            try { win.focus(); } catch {}
          } else {
            // fallback si algún bloqueador interviene
            window.open(href, '_blank', 'noopener');
          }
        }
      });
    })();
  </script>
</head>
<body>

<div class="menu-toggle" onclick="toggleMenu()">☰ Menú</div>

<nav class="menu-horizontal" id="menu-principal">
  <!-- PANEL GIMNASIO -->
  <?php if (has_perm('panel_gimnasio')): ?>
  <div class="dropdown">
    <a href="#">🏢 Panel Gimnasio</a>
    <div class="dropdown-content">
      <a href="panel_gimnasios.php">Dashboard</a>
      <a href="agregar_gimnasio.php">Agregar Gimnasio</a>
      <a href="renovar_gimnasio.php">Renovar Plan</a>
    </div>
  </div>
  <?php endif; ?>

  <!-- CLIENTES -->
  <?php if (has_perm('clientes')): ?>
  <div class="dropdown">
    <a href="#">👤 Clientes</a>
    <div class="dropdown-content">
      <a href="ver_clientes.php">Ver Clientes</a>
      <a href="agregar_cliente.php">Agregar Cliente</a>
    </div>
  </div>
  <?php endif; ?>

  <!-- MEMBRESÍAS -->
  <?php if (has_perm('membresias')): ?>
  <div class="dropdown">
    <a href="#">📅 Membresías</a>
    <div class="dropdown-content">
      <a href="ver_membresias.php">Ver Membresías</a>
      <a href="nueva_membresia.php">Agregar Membresía</a>
      <a href="disciplinas.php">Disciplinas</a>
      <a href="planes.php">Planes</a>
      <a href="adicionales.php">Adicionales</a>
      <a href="admin_cena.php">🍽️ Cena (Admin)</a>

    </div>
  </div>
  <?php endif; ?>

  <!-- PAGOS -->
  <?php if (has_perm('pagos')): ?>
  <div class="dropdown">
    <a href="#">💳 Pagos</a>
    <div class="dropdown-content">
      <a href="ver_pagos_pendientes.php">Pagos Pendientes</a>
      <a href="config_alias.php">Alias</a>
      <a href="ver_pagos_mes.php">Pagos del Mes</a>
      <a href="ver_cuentas_corrientes.php">Pagos Cuenta Corriente</a>
      <a href="gastos.php">Gastos</a>
    </div>
  </div>
  <?php endif; ?>

  <!-- ASISTENCIAS -->
  <?php if (has_perm('asistencias')): ?>
  <div class="dropdown">
    <a href="#">🧍‍♂️ Asistencias</a>
    <div class="dropdown-content">
      <a href="ver_asistencia.php">Ver Asistencias</a>

      <!-- Popup controlado 1200x800, centrado, reutiliza 'asistenciaWin' -->
      <a href="registrar_asistencia.php"
         class="newwin"
         data-popup="1"
         data-features="width=1200,height=800,menubar=no,toolbar=no,location=no,status=no,scrollbars=yes,resizable=yes"
         data-window="asistenciaWin"
         rel="noopener">Registrar Asistencia</a>

      <a href="scanner_qr.php">Escaneo QR</a>
      <a href="ver_asistencias_profesor.php">Asistencia Profesores</a>
    </div>
  </div>
  <?php endif; ?>

  <!-- VENTAS -->
  <?php if (has_perm('ventas')): ?>
  <div class="dropdown">
    <a href="#">🛒 Ventas</a>
    <div class="dropdown-content">
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

  <!-- PROFESORES -->
  <?php if (has_perm('profesores')): ?>
  <div class="dropdown">
    <a href="#">👨‍🏫 Profesores</a>
    <div class="dropdown-content">
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

  <!-- PANEL CLIENTE -->
  <?php if (has_perm('panel_cliente')): ?>
  <div class="dropdown">
    <a href="#">📲 Panel Cliente</a>
    <div class="dropdown-content">
      <a href="cliente_acceso.php">Panel</a>
      <a href="panel_configuracion.php">Panel Configuración</a>
    </div>
  </div>
  <?php endif; ?>

  <!-- EVENTOS -->
  <?php if (has_perm('eventos_panel')): ?>
  <div class="dropdown">
    <a href="#">🎪 Eventos</a>
    <div class="dropdown-content">
      <a href="panel_eventos.php">Panel de Eventos</a>
      <a href="login_evento.php">Acceso a Panel</a>
      <?php if (has_perm('eventos')): ?>
        <a href="eventos_publicos.php">Eventos Públicos</a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- CERRAR (siempre visible) -->
  <div class="dropdown">
    <a href="#">❌ Cerrar</a>
    <div class="dropdown-content">
      <a href="index.php">Volver al Inicio</a>
      <a href="logout.php">Cerrar Sesión</a>
      <a href="#" onclick="cerrarApp()">❌ Cerrar Programa</a>
    </div>
  </div>
</nav>

</body>
</html>
