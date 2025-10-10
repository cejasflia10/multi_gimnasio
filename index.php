<?php
if (session_status() === PHP_SESSION_NONE) session_start();

/* --- Sesión e inactividad --- */
$timeout_minutos = 30;
if (!isset($_SESSION['gimnasio_id'])) { session_unset(); session_destroy(); header('Location: login.php'); exit; }
if (isset($_SESSION['last_activity']) && (time()-$_SESSION['last_activity']) > $timeout_minutos*60){
  session_unset(); session_destroy(); header('Location: login.php?timeout=1'); exit;
}
$_SESSION['last_activity']=time();
if (!isset($_SESSION['session_regenerated_time']) || time()-$_SESSION['session_regenerated_time']>900){
  session_regenerate_id(true); $_SESSION['session_regenerated_time']=time();
}

require_once 'conexion.php';
require_once 'menu_horizontal.php';

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);

/* Datos gym */
$gimnasio   = $conexion->query("SELECT nombre, logo, fecha_vencimiento FROM gimnasios WHERE id={$gimnasio_id}")->fetch_assoc();
$nombre_gym = $gimnasio['nombre'] ?? 'Gimnasio';
$logo       = $gimnasio['logo']   ?? '';
$fecha_venc = $gimnasio['fecha_vencimiento'] ?? '---';

/* KPIs */
$estado = $conexion->query("
  SELECT
    SUM(CASE WHEN u.fv IS NOT NULL AND u.fv >= CURDATE() THEN 1 ELSE 0 END) AS activos,
    SUM(CASE WHEN u.fv IS NULL OR u.fv < CURDATE() THEN 1 ELSE 0 END) AS inactivos
  FROM clientes c
  LEFT JOIN (
    SELECT cliente_id, MAX(fecha_vencimiento) AS fv
    FROM membresias
    WHERE gimnasio_id={$gimnasio_id} AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento>='1000-01-01'
    GROUP BY cliente_id
  ) u ON u.cliente_id=c.id
  WHERE c.gimnasio_id={$gimnasio_id}
")->fetch_assoc();
$activos   = (int)($estado['activos'] ?? 0);
$inactivos = (int)($estado['inactivos'] ?? 0);

/* Cumples (top 5, sin filtro por fecha para mantener tu lógica original) */
$cumples = $conexion->query("
  SELECT nombre, apellido, fecha_nacimiento
  FROM clientes
  WHERE gimnasio_id={$gimnasio_id}
    AND fecha_nacimiento IS NOT NULL
  ORDER BY DATE_FORMAT(fecha_nacimiento,'%m-%d')
  LIMIT 5
");

/* Vencimientos */
$vencimientos = $conexion->query("
  SELECT c.nombre, c.apellido, m.fecha_vencimiento
  FROM membresias m
  JOIN clientes c ON c.id=m.cliente_id
  WHERE m.gimnasio_id={$gimnasio_id}
    AND m.fecha_vencimiento IS NOT NULL
    AND m.fecha_vencimiento >= CURDATE()
  ORDER BY m.fecha_vencimiento ASC
  LIMIT 5
");

/* Pendientes y CC */
$pagos_pendientes = (int)($conexion->query("
  SELECT COUNT(*) t
  FROM pagos_pendientes pp
  JOIN clientes c ON c.id=pp.cliente_id
  WHERE pp.estado='pendiente' AND c.gimnasio_id={$gimnasio_id}
")->fetch_assoc()['t'] ?? 0);

$cuentas_corrientes = (int)($conexion->query("
  SELECT COUNT(*) t FROM (
    SELECT cliente_id FROM cuentas_corrientes
    WHERE gimnasio_id={$gimnasio_id}
    GROUP BY cliente_id HAVING SUM(monto)<0
  ) x
")->fetch_assoc()['t'] ?? 0);

$fecha_filtro = $_GET['fecha'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha_filtro)) $fecha_filtro=date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Panel General - <?= htmlspecialchars($nombre_gym) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
:root{
  --bg1:#f5f7fb; --bg2:#eef3f9; --ink:#0f172a; --mut:#475569;
  --brand:#b45309; --brand-2:#f59e0b; --card:#fff; --stroke:rgba(15,23,42,.08);
  --shadow:0 10px 28px rgba(2,6,23,.08); --gap:18px;
}
*{box-sizing:border-box}
body{margin:0;color:var(--ink);font-family:system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial,sans-serif;background:linear-gradient(180deg,var(--bg1),var(--bg2))}
.wrap{max-width:1200px;margin:24px auto;padding:0 16px 40px}

/* Header */
.header{display:grid;grid-template-columns:1fr auto;gap:16px;align-items:center;margin-bottom:16px}
.title{margin:0;font-weight:900;color:var(--brand)}
.sys-exp{margin:.25rem 0 0;color:var(--mut)}
.logo-wrap{text-align:right}
#logoGym{max-height:64px;max-width:180px;object-fit:contain;background:#fff;padding:6px;border:1px solid var(--stroke);border-radius:12px}

/* Grid */
.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:var(--gap)}
@media (max-width:900px){.grid{grid-template-columns:repeat(4,1fr)}}

/* Cards */
.card{background:var(--card);border:1px solid var(--stroke);border-radius:18px;box-shadow:var(--shadow);padding:16px;grid-column:span 4}
.card-header{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;position:relative;z-index:2}
.card-title{margin:0;color:var(--brand);font-size:1.05rem}
.card-sub{margin:0;color:#64748b;font-size:.9rem}

/* ===========================
   VISUAL PARA CELULARES
   =========================== */

/* Reglas generales (<= 900px) */
@media (max-width: 900px){
  .card, .alert, .notice{ text-align: center !important; }
  .card-header{
    flex-direction: column !important;
    align-items: center !important;
    gap: 6px !important;
    z-index: 3 !important; /* evita que nada lo tape */
  }
  .kpis{ justify-content: center !important; }
  .toolbar{ justify-content: center !important; }
  .alert a, .notice a{ display: inline-block !important; }
}

/* --- INGRESOS ($) --- */
@media (max-width: 560px){
  #ingresos-body .ingresos-wrap{
    display: grid !important;
    grid-template-columns: 1fr !important;
    gap: 12px !important;
  }
  #ingresos-body .ing-card{
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    text-align: center !important;
    gap: 8px !important;
    padding: 14px 12px !important;
    border-radius: 14px !important;
    box-shadow: 0 4px 12px rgba(0,0,0,.08) !important;
  }
  #ingresos-body .ing-title{
    font-size: 1rem !important;
    line-height: 1.3 !important;
    white-space: normal !important;
    word-break: keep-all !important;
    overflow-wrap: anywhere !important;
  }
  #ingresos-body .ing-amount{
    font-size: 1.6rem !important;
    line-height: 1.2 !important;
    font-weight: 900 !important;
  }
}
@media (min-width:561px) and (max-width:900px){
  #ingresos-body .ingresos-wrap{
    display:grid!important;
    grid-template-columns:1fr 1fr!important;
    gap:12px!important;
  }
}

/* --- RESERVAS DEL DÍA (compactas) --- */
@media (max-width: 900px){
  #reservas-body .reserva{
    margin: 8px 0 !important;
    padding: 10px 12px !important;
    border-radius: 14px !important;
    border: 1px solid rgba(15,23,42,.08) !important;
    box-shadow: 0 3px 8px rgba(0,0,0,.04) !important;
    line-height: 1.25 !important;
  }
  #reservas-body .reserva strong{ color:#b45309 !important; }
}
@media (max-width: 520px){
  #reservas-body .reserva{ padding: 6px 8px !important; font-size: .95rem !important; }
}

/* --- ALUMNOS (Asistencias de hoy) --- */
@media (max-width: 768px){
  #alumnos-body .asistencias-hoy{
    margin:0!important; padding:0!important; list-style:none!important;
  }
  #alumnos-body .asistencias-hoy li,
  #alumnos-body .asis-item{
    display:flex!important; justify-content:space-between!important; align-items:center!important;
    gap:10px!important; padding:8px 10px!important; border-bottom:1px dashed rgba(15,23,42,.12)!important;
  }
  #alumnos-body .asistencias-hoy li:last-child,
  #alumnos-body .asis-item:last-child{ border-bottom:none!important; }
  #alumnos-body .n{
    flex:1 1 auto!important; white-space:nowrap!important; overflow:hidden!important; text-overflow:ellipsis!important; word-break:keep-all!important;
  }
  #alumnos-body .h{ flex:0 0 auto!important; font-variant-numeric:tabular-nums!important; }
}

/* --- Vencimientos centrados (móvil) --- */
@media (max-width: 900px){
  #card-venc, #card-venc *{ text-align: center !important; }
}
</style>

<script>
/* Limpia estilos embebidos que puedan venir dentro de cada resultado AJAX */
function sanitizeAjaxContainer(root){
  if(!root) return;
  root.querySelectorAll('style, link[rel="stylesheet"]').forEach(n => n.remove());
}

/* Carga AJAX SOLO dentro del cuerpo de cada card (no toca encabezados) */
function fetchIntoBody(url, bodyId){
  const bodyEl = document.getElementById(bodyId);
  if(!bodyEl) return;
  fetch(url, {cache:'no-store'})
    .then(r => r.text())
    .then(html => { bodyEl.innerHTML = html; sanitizeAjaxContainer(bodyEl); })
    .catch(()=>{ bodyEl.innerHTML = '<div style="color:#b91c1c">Error al cargar.</div>'; });
}

function cargarDatos(){
  const f = document.getElementById('fecha')?.value || '';
  fetchIntoBody('ajax_ingresos.php', 'ingresos-body');                                         // ingresos $ día/mes
  fetchIntoBody('ajax_reservas.php' + (f ? ('?fecha='+encodeURIComponent(f)) : ''), 'reservas-body'); // reservas
  fetchIntoBody('ajax_alumnos_hoy.php', 'alumnos-body');                                       // asistencias hoy
}

window.addEventListener('load', () => {
  cargarDatos();
  setInterval(cargarDatos, 10000);
});
</script>
</head>

<body>
<div class="wrap">
  <div class="header">
    <div>
      <h1 class="title">🏋️ <?= htmlspecialchars($nombre_gym) ?></h1>
      <p class="sys-exp">🗓 Vencimiento del sistema:
        <strong><?= htmlspecialchars($fecha_venc) ?></strong>
      </p>
    </div>
    <div class="logo-wrap">
      <?php if ($logo): ?><img id="logoGym" src="<?= htmlspecialchars($logo) ?>" alt="Logo del gimnasio"><?php endif; ?>
    </div>
  </div>

  <div class="grid">

    <!-- INGRESOS ($) -->
    <section class="card">
      <div class="card-header">
        <h3 class="card-title">💰 Ingresos</h3>
        <p class="card-sub">Actualiza cada 10s</p>
      </div>
      <div id="ingresos-body"><div class="skeleton" style="min-height:110px"></div></div>
    </section>

    <!-- CUMPLES -->
    <section class="card">
      <div class="card-header">
        <h3 class="card-title">🎂 Próximos Cumpleaños</h3>
        <p class="card-sub">Top 5 próximos</p>
      </div>
      <ul>
        <?php while($c=$cumples->fetch_assoc()): ?>
          <li><?= htmlspecialchars($c['apellido'].', '.$c['nombre']) ?></li>
        <?php endwhile; ?>
      </ul>
    </section>

    <!-- VENCIMIENTOS -->
    <section class="card" id="card-venc">
      <div class="card-header">
        <h3 class="card-title">🗓 Vencimientos</h3>
        <p class="card-sub">Próximos</p>
      </div>
      <ul>
        <?php while($v=$vencimientos->fetch_assoc()): ?>
          <li><?= htmlspecialchars($v['apellido'].', '.$v['nombre']) ?>
            (<?= ($v['fecha_vencimiento'] && strtotime($v['fecha_vencimiento'])) ? date('d/m', strtotime($v['fecha_vencimiento'])) : '--' ?>)
          </li>
        <?php endwhile; ?>
      </ul>
    </section>

    <!-- RESERVAS -->
    <section class="card" style="grid-column:span 8">
      <div class="card-header">
        <h3 class="card-title">📋 Reservas del día</h3>
        <div>
          <form method="GET" oninput="this.submit()">
            <input type="date" id="fecha" name="fecha" value="<?= htmlspecialchars($fecha_filtro) ?>">
          </form>
        </div>
      </div>
      <div id="reservas-body"><div class="skeleton" style="min-height:110px"></div></div>
    </section>

    <!-- ALUMNOS (ASISTENCIAS HOY) -->
    <section class="card">
      <div class="card-header">
        <h3 class="card-title">🧑‍🎓 Alumnos de hoy</h3>
        <p class="card-sub">Asistencias / ingresos</p>
      </div>
      <div id="alumnos-body"><div class="skeleton" style="min-height:110px"></div></div>
    </section>

  </div>
</div>
</body>
</html>
