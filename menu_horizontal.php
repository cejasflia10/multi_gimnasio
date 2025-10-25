<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
@require_once __DIR__ . '/permiso.php';
@require_once __DIR__ . '/conexion.php'; // <-- agregado para consultar el flag en BD

if (function_exists('refresh_permissions') && !empty($_SESSION['gimnasio_id'])) {
  refresh_permissions((int)$_SESSION['gimnasio_id']);
}
if (!function_exists('has_perm')) {
  function has_perm(string $feature): bool {
    if (!empty($_SESSION['rol']) && $_SESSION['rol'] === 'admin') return true;
    return function_exists('has_feature') ? has_feature($feature) : true;
  }
}

/* === Helpers de flags de sistema (ajustes_gimnasio) === */
if (!function_exists('gymFlag')) {
  function gymFlag(mysqli $db, int $gid, string $clave, $def='0'){
    $q=$db->prepare("SELECT valor FROM ajustes_gimnasio WHERE gimnasio_id=? AND clave=? LIMIT 1");
    $q->bind_param("is",$gid,$clave); $q->execute();
    $r=$q->get_result()->fetch_assoc(); $q->close();
    return $r['valor'] ?? $def;
  }
}
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
$ROL         = $_SESSION['rol'] ?? '';
$isAdmin     = in_array(strtolower($ROL), ['admin','superadmin','owner','dueño'], true);
$horariosOn  = ($gimnasio_id && isset($conexion) && $conexion instanceof mysqli && gymFlag($conexion,$gimnasio_id,'horarios_gym_activo','0')==='1');

/** Definición única del menú **/
$MENU = [
  'panel_gimnasio' => ['label'=>'🏢 Panel Gimnasio','perm'=>'panel_gimnasio','items'=>[
    ['Dashboard','panel_gimnasios.php'],
    ['Agregar Gimnasio','agregar_gimnasio.php'],
    ['Renovar Plan','renovar_gimnasio.php'],
  ]],
  'clientes' => ['label'=>'👤 Clientes','perm'=>'clientes','items'=>[
    ['Ver Clientes','ver_clientes.php'],
    ['Agregar Cliente','agregar_cliente.php'],
    ['🏷️ QR de Máquinas','maquinas_qr.php'],
    ['📈 Seguimiento de alumnos','profesor_seguimiento.php'],
    // ⬇️ El link "Horarios del Gimnasio" se agrega dinámicamente más abajo
  ]],
  'membresias' => ['label'=>'📅 Membresías','perm'=>'membresias','items'=>[
    ['Ver Membresías','ver_membresias.php'],
    ['Agregar Membresía','nueva_membresia.php'],
    ['Disciplinas','disciplinas.php'],
    ['Planes','planes.php'],
    ['Adicionales','adicionales.php'],
    ['🍽️ Cena (Admin)','admin_cena.php'],
  ]],
  'pagos' => ['label'=>'💳 Pagos','perm'=>'pagos','items'=>[
    ['Pagos Pendientes','ver_pagos_pendientes.php'],
    ['Alias','config_alias.php'],
    ['Pagos del Mes','ver_pagos_mes.php'],
    ['Pagos Cuenta Corriente','ver_cuentas_corrientes.php'],
    ['Gastos','gastos.php'],
  ]],
  'asistencias' => ['label'=>'🧍‍♂️ Asistencias','perm'=>'asistencias','items'=>[
    ['Ver Asistencias','ver_asistencia.php'],
    ['Registrar Asistencia','registrar_asistencia.php',[
      'popup'=>true,'name'=>'asistenciaWin','features'=>'width=1200,height=800,menubar=no,toolbar=no,location=no,status=no,scrollbars=yes,resizable=yes'
    ]],
    ['Escaneo QR','scanner_qr.php'],
    ['Asistencia Profesores','ver_asistencias_profesor.php'],
  ]],
  'ventas' => ['label'=>'🛒 Ventas','perm'=>'ventas','items'=>[
    ['Agregar Productos','agregar_producto.php'],
    ['Ventas Protecciones','ventas_proteccion.php'],
    ['Ventas Suplementos','ventas_suplementos.php'],
    ['Ventas Indumentaria','ventas_indumentaria.php'],
    ['Ver Productos','ver_productos.php'],
    ['Ver Facturas','ver_facturas.php'],
    ['Promociones','promociones_admin.php'],
    ['🛍️ Indumentaria (Admin)','admin_indum.php'],
    ['🧾 Pedidos indumentaria','admin_pedidos_indum.php'],
  ]],
  'profesores' => ['label'=>'👨‍🏫 Profesores','perm'=>'profesores','items'=>[
    ['Agregar Profesor','agregar_profesor.php'],
    ['Panel','login_profesor.php'],
    ['Ver Profesores','ver_profesores.php'],
    ['Turnos Profesores','turnos_profesor.php'],
    ['Precio de Horas','editar_tarifa_profesor.php'],
    ['Reporte de Horas','reporte_horas_profesor.php'],
    ['Enrolar huella','biometria/enrolar_profesores.php'],
  ]],
  'panel_cliente' => ['label'=>'📲 Panel Cliente','perm'=>'panel_cliente','items'=>[
    ['Panel','cliente_acceso.php'],
    ['Panel Configuración','panel_configuracion.php'],
  ]],
  'eventos' => ['label'=>'🎪 Eventos','perm'=>'eventos_panel','items'=>[
    ['Panel de Eventos','panel_eventos.php'],
    ['Acceso a Panel','login_evento.php'],
    ['Eventos Públicos','eventos_publicos.php',['extra_perm'=>'eventos']],
  ]],
];

/* === Inyección dinámica del link dentro de CLIENTES (abajo del submenú) === */
if (isset($MENU['clientes'])) {
  if ($horariosOn) {
    // Módulo encendido: lo ven todos
    $MENU['clientes']['items'][] = ['🗓️ Horarios del Gimnasio','horarios_gimnasio.php'];
  } elseif ($isAdmin) {
    // Módulo apagado: solo admin lo ve con un estilo atenuado
    $MENU['clientes']['items'][] = ['🔒 Horarios del Gimnasio','horarios_gimnasio.php',['class'=>'disabled','title'=>'Módulo apagado']];
  }
}

$SALIDA = ['label'=>'❌ Cerrar','items'=>[
  ['Volver al Inicio','index.php'],
  ['Cerrar Sesión','logout.php'],
  ['❌ Cerrar Programa','#',['onclick'=>'cerrarApp()']],
]];

/** Helpers de render **/
function render_link($label,$href,$opts=[]){
  $attrs = [];
  $cls   = [];
  if (!empty($opts['onclick'])) $attrs[] = 'onclick="'.htmlspecialchars($opts['onclick']).'"';
  if (!empty($opts['popup'])) {
    $cls[]='newwin'; $attrs[]='data-popup="1"';
    if (!empty($opts['features'])) $attrs[]='data-features="'.htmlspecialchars($opts['features']).'"';
    if (!empty($opts['name']))     $attrs[]='data-window="'.htmlspecialchars($opts['name']).'"';
  }
  if (!empty($opts['class'])) $cls[]=$opts['class'];
  if (!empty($opts['title'])) $attrs[]='title="'.htmlspecialchars($opts['title']).'"';
  $clsAttr = $cls ? ' class="'.implode(' ',$cls).'"' : '';
  $attrsStr = $attrs ? ' '.implode(' ',$attrs) : '';
  return '<a href="'.htmlspecialchars($href).'"'.$clsAttr.$attrsStr.'>'.htmlspecialchars($label).'</a>';
}
function render_menu_desktop($MENU,$SALIDA){
  ob_start(); ?>
  <nav class="nav-desktop" role="navigation" aria-label="Menú (PC)">
    <?php foreach($MENU as $sec){
      if(!empty($sec['perm']) && !has_perm($sec['perm'])) continue; ?>
      <div class="dd">
        <button class="dd-head" type="button" tabindex="0"><?= htmlspecialchars($sec['label']) ?></button>
        <div class="dd-body">
          <?php foreach($sec['items'] as $it){
            [$label,$href,$opts]=[$it[0],$it[1],$it[2]??[]];
            if(!empty($opts['extra_perm']) && !has_perm($opts['extra_perm'])) continue;
            echo render_link($label,$href,$opts);
          } ?>
        </div>
      </div>
    <?php } ?>
    <div class="dd">
      <button class="dd-head" type="button" tabindex="0"><?= htmlspecialchars($SALIDA['label']) ?></button>
      <div class="dd-body">
        <?php foreach($SALIDA['items'] as $it){ echo render_link($it[0],$it[1],$it[2]??[]); } ?>
      </div>
    </div>
  </nav>
  <?php return ob_get_clean();
}
function render_menu_mobile($MENU,$SALIDA){
  ob_start(); ?>
  <div class="mobile-bar" role="navigation" aria-label="Menú (Celular)">
    <button class="hamb" type="button" aria-controls="drawer" aria-expanded="false" aria-label="Abrir menú">☰</button>
    <div class="brand-mini">Menú</div>
    <a class="logout" href="logout.php" title="Salir">⎋</a>
  </div>
  <aside id="drawer" class="drawer" aria-hidden="true">
    <div class="drawer-inner">
      <div class="drawer-head">
        <span>Menú</span>
        <button class="close" type="button" aria-label="Cerrar">✕</button>
      </div>
      <div class="accordion">
        <?php foreach($MENU as $sec){
          if(!empty($sec['perm']) && !has_perm($sec['perm'])) continue; ?>
          <details class="acc-item">
            <summary><span><?= htmlspecialchars($sec['label']) ?></span></summary>
            <div class="acc-body">
              <?php foreach($sec['items'] as $it){
                [$label,$href,$opts]=[$it[0],$it[1],$it[2]??[]];
                if(!empty($opts['extra_perm']) && !has_perm($opts['extra_perm'])) continue;
                echo render_link($label,$href,$opts);
              } ?>
            </div>
          </details>
        <?php } ?>
        <details class="acc-item">
          <summary><span><?= htmlspecialchars($SALIDA['label']) ?></span></summary>
          <div class="acc-body">
            <?php foreach($SALIDA['items'] as $it){ echo render_link($it[0],$it[1],$it[2]??[]); } ?>
          </div>
        </details>
      </div>
    </div>
  </aside>
  <div id="drawer-backdrop" class="backdrop" hidden></div>
  <?php return ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Menú</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
  :root{
    --brand:#b45309; --brand2:#f59e0b; --fg:#0f172a;
    --bg:#fff; --soft:#f8fafc; --stroke:rgba(2,6,23,.10);
    --drop:#ffffff; --shadow:0 10px 24px rgba(2,6,23,.10);
    --radius:12px;
  }
  *{box-sizing:border-box}
  body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial,sans-serif}

  /* Ocultar por defecto para evitar que se vean ambos antes de aplicar la media-query */
  .nav-desktop{ display:none }
  .mobile-bar, .drawer, #drawer-backdrop{ display:none }

  /* ====== PC (solo PC) ====== */
  @media (min-width: 992px){
    .nav-desktop{
      display:flex; gap:6px; align-items:center;
      position:sticky; top:0; z-index:1000;
      padding:8px 10px; background:linear-gradient(180deg,#fff,#f8fafc);
      border-bottom:1px solid var(--stroke);
    }
    .dd{ position:relative }
    .dd-head{
      background:transparent; border:none; cursor:default;
      color:var(--fg); font-weight:700; padding:8px 12px; border-radius:10px;
    }
    .dd-head:hover{ background:#f1f5f9 }
    .dd-body{
      position:absolute; left:0; top:100%; min-width:240px;
      background:var(--drop); border:1px solid var(--stroke); border-radius:12px;
      box-shadow:var(--shadow); padding:6px; display:none;
    }
    .dd:hover .dd-body{ display:block }
    .dd-body a{ display:block; padding:10px 12px; border-radius:8px; color:var(--fg); text-decoration:none }
    .dd-body a:hover{ background:#f1f5f9 }
    .dd-body a.newwin::after{ content:"↗"; margin-left:8px; opacity:.85 }
    .dd-body a.disabled{ opacity:.6; pointer-events:auto } /* candado admins */
  }

  /* ====== Celular (solo celular) ====== */
  @media (max-width: 991.98px){
    .mobile-bar{ display:flex }
    .mobile-bar{
      position:sticky; top:0; z-index:1001; height:48px;
      align-items:center; justify-content:space-between;
      padding:0 10px; background:linear-gradient(180deg,#fff,#f8fafc);
      border-bottom:1px solid var(--stroke);
    }
    .mobile-bar .hamb{ font-size:20px; border:none; background:#fff; padding:6px 10px; border-radius:10px }
    .mobile-bar .brand-mini{ font-weight:800; color:var(--brand); letter-spacing:.3px }
    .mobile-bar .logout{ color:var(--fg); text-decoration:none; font-size:18px; padding:6px 10px }

    .drawer{ display:block; position:fixed; inset:0 35% 0 0; transform:translateX(-100%);
      background:#fff; border-right:1px solid var(--stroke);
      box-shadow:var(--shadow); transition:.25s transform ease;
      z-index:1002; overflow:auto }
    .drawer.open{ transform:translateX(0) }
    .drawer-inner{ padding:10px }
    .drawer-head{ display:flex; justify-content:space-between; align-items:center; padding:6px 2px 10px; border-bottom:1px solid var(--stroke) }
    .drawer-head .close{ border:none; background:#fff; font-size:20px; padding:6px 10px; border-radius:8px }

    .accordion{ padding:6px 0 }
    .acc-item{ border-bottom:1px solid var(--stroke) }
    .acc-item summary{ list-style:none; cursor:pointer; padding:12px 4px; font-weight:700; color:var(--fg) }
    .acc-item summary::-webkit-details-marker{ display:none }
    .acc-body{ padding:4px 0 10px 8px }
    .acc-body a{ display:block; padding:9px 10px; border-radius:8px; color:var(--fg); text-decoration:none }
    .acc-body a:hover{ background:#f1f5f9 }
    .acc-body a.newwin::after{ content:"↗"; margin-left:8px; opacity:.85 }
    .acc-body a.disabled{ opacity:.6 } /* candado admins */

    #drawer-backdrop{ display:block; position:fixed; inset:0; background:rgba(2,6,23,.35); z-index:1001 }
    #drawer-backdrop[hidden]{ display:none }
  }
</style>

<script>
  // Drawer móvil (solo se ejecuta si existen los nodos)
  document.addEventListener('DOMContentLoaded', function(){
    const drawer   = document.getElementById('drawer');
    const backdrop = document.getElementById('drawer-backdrop');
    const hamb     = document.querySelector('.mobile-bar .hamb');
    const closeBtn = document.querySelector('.drawer .close');
    if(!drawer || !hamb) return;

    function openDrawer(){
      drawer.classList.add('open');
      drawer.setAttribute('aria-hidden','false');
      if(backdrop){ backdrop.hidden = false; }
      hamb.setAttribute('aria-expanded','true');
    }
    function closeDrawer(){
      drawer.classList.remove('open');
      drawer.setAttribute('aria-hidden','true');
      if(backdrop){ backdrop.hidden = true; }
      hamb.setAttribute('aria-expanded','false');
    }
    hamb.addEventListener('click', openDrawer);
    if(closeBtn) closeBtn.addEventListener('click', closeDrawer);
    if(backdrop) backdrop.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') closeDrawer(); });
  });

  function cerrarApp(){
    if (confirm("¿Seguro que deseas cerrar la aplicación?")) {
      if (window.electronAPI) { window.electronAPI.cerrarVentana(); }
      else { window.close(); }
    }
  }

  // Popups controlados para <a.newwin>
  (function(){
    const opened = new Map();
    document.addEventListener('click', function(e){
      const a = e.target.closest('a.newwin');
      if (!a) return;
      e.preventDefault();

      const href     = a.href;
      const isPopup  = a.dataset.popup === '1';
      const features = (a.dataset.features || '').trim();
      const winName  = (a.dataset.window || '_blank').trim();

      if (!isPopup) { window.open(href,'_blank','noopener'); return; }

      const parseFeat = (k, d) => {
        const m = new RegExp(k+'=([0-9]+)').exec(features);
        return m ? parseInt(m[1],10) : d;
      };
      const w = parseFeat('width',1200), h = parseFeat('height',800);
      const left = Math.max(0, Math.floor((screen.availWidth  - w)/2));
      const top  = Math.max(0, Math.floor((screen.availHeight - h)/2));
      const base = features ? features.replace(/\bleft=\d+\b/g,'').replace(/\btop=\d+\b/g,'')
                            : `menubar=no,toolbar=no,location=no,status=no,scrollbars=yes,resizable=yes,width=${w},height=${h}`;
      const finalFeats = `${base},left=${left},top=${top}`;

      let win = opened.get(winName);
      if (win && !win.closed) { try{ win.focus(); win.location.href = href; }catch{} }
      else {
        win = window.open(href, winName, finalFeats);
        if (win) { try{ win.opener=null; }catch{} opened.set(winName,win); try{ win.focus(); }catch{} }
        else { window.open(href,'_blank','noopener'); }
      }
    });
  })();
</script>
</head>
<body>

<!-- PC -->
<?= render_menu_desktop($MENU,$SALIDA) ?>

<!-- Celular -->
<?= render_menu_mobile($MENU,$SALIDA) ?>

</body>
</html>
