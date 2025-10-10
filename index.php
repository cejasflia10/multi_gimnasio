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
    WHERE gimnasio_id={$gimnasio_id}
    GROUP BY cliente_id
  ) u ON u.cliente_id=c.id
  WHERE c.gimnasio_id={$gimnasio_id}
")->fetch_assoc();
$activos   = (int)($estado['activos'] ?? 0);
$inactivos = (int)($estado['inactivos'] ?? 0);

/* Cumples */
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
  --bg1:#f5f7fb;--bg2:#eef3f9;--ink:#0f172a;--mut:#475569;
  --brand:#b45309;--brand2:#f59e0b;--ok:#16a34a;--warn:#f59e0b;
  --card:#fff;--stroke:rgba(15,23,42,.08);
  --shadow:0 6px 16px rgba(2,6,23,.08);--radius:18px;
}
body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Inter,sans-serif;background:linear-gradient(180deg,var(--bg1),var(--bg2));color:var(--ink);}
.wrap{max-width:1200px;margin:24px auto;padding:0 16px 40px}
.header{display:grid;grid-template-columns:1fr auto;gap:16px;align-items:center;margin-bottom:16px}
.title{margin:0;font-weight:900;color:var(--brand);}
.sys-exp{margin:4px 0;color:var(--mut);}
.logo-wrap{text-align:right}
#logoGym{max-height:80px;object-fit:contain;background:#fff;padding:6px;border:1px solid var(--stroke);border-radius:12px}
.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:18px;}
@media(max-width:900px){.grid{grid-template-columns:repeat(4,1fr);}}
.card{background:var(--card);border:1px solid var(--stroke);border-radius:18px;box-shadow:var(--shadow);padding:16px;grid-column:span 4;}
.card-header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;}
.card-title{margin:0;color:var(--brand);font-weight:700;}
.card-sub{margin:0;color:#64748b;font-size:.9rem;flex:1 1 100%;text-align:right;}
@media(max-width:520px){.card-header{flex-direction:column;align-items:flex-start}.card-sub{text-align:left;width:100%}}
ul{margin:0;padding-left:18px;}
/* asistencias ordenadas */
#alumnos-body li{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:6px 0;border-bottom:1px dashed rgba(15,23,42,.1);}
#alumnos-body li:last-child{border:none;}
#alumnos-body .n{flex:1 1 auto;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
#alumnos-body .h{flex:0 0 auto;font-variant-numeric:tabular-nums;}
/* reservas compactas */
#reservas-body>div{border:1px solid rgba(15,23,42,.06);border-radius:14px;padding:8px 10px;margin:6px 0;box-shadow:0 3px 8px rgba(0,0,0,.04);}
#reservas-body strong{color:var(--brand);}
@media(max-width:520px){#reservas-body>div{padding:6px 8px;font-size:.95rem}}
</style>
<script>
function sanitizeAjaxContainer(r){if(!r)return;r.querySelectorAll('style').forEach(n=>n.remove());}
function countItems(r){let n=r.querySelectorAll('li').length;if(!n)n=r.querySelectorAll('.asis-item').length;return n;}
function fetchIntoBody(u,id,after){
 const el=document.getElementById(id);if(!el)return;
 fetch(u,{cache:'no-store'}).then(r=>r.text()).then(h=>{
   el.innerHTML=h;sanitizeAjaxContainer(el);
   if(typeof after==='function')after(el);
 }).catch(()=>{el.innerHTML='<div style="color:#b91c1c">Error</div>';});
}
function cargarDatos(){
 const f=document.getElementById('fecha')?.value;
 fetchIntoBody('ajax_ingresos.php','ingresos-body');
 fetchIntoBody('ajax_reservas.php?fecha='+(f?encodeURIComponent(f):''),'reservas-body');
 fetchIntoBody('ajax_alumnos_hoy.php','alumnos-body',(root)=>{
   const sub=document.getElementById('asis-sub');
   const t=countItems(root);
   const hoy=new Date().toLocaleDateString('es-AR',{timeZone:'America/Argentina/San_Luis'});
   const txt=t>0?`${t} ingresos — ${hoy}`:`Sin ingresos — ${hoy}`;
   if(sub){sub.textContent=txt;}
 });
}
window.addEventListener('load',()=>{cargarDatos();setInterval(cargarDatos,10000);});
</script>
</head>
<body>
<div class="wrap">
  <div class="header">
    <div>
      <h1 class="title">🏋️ <?= htmlspecialchars($nombre_gym) ?></h1>
      <p class="sys-exp">Vencimiento sistema: <strong><?= htmlspecialchars($fecha_venc) ?></strong></p>
    </div>
    <div class="logo-wrap"><?php if($logo):?><img id="logoGym" src="<?= htmlspecialchars($logo) ?>"><?php endif;?></div>
  </div>

  <div class="grid">
    <section class="card bloque-monto">
      <div class="card-header"><h3 class="card-title">💰 Ingresos</h3><p class="card-sub">Actualiza cada 10s</p></div>
      <div id="ingresos-body"><div class="skeleton" style="min-height:100px"></div></div>
    </section>

    <section class="card"><div class="card-header"><h3 class="card-title">🎂 Cumpleaños</h3><p class="card-sub">Top 5</p></div><ul>
      <?php while($c=$cumples->fetch_assoc()):?>
      <li><?=htmlspecialchars($c['apellido'].', '.$c['nombre'])?></li>
      <?php endwhile;?>
    </ul></section>

    <section class="card" id="card-venc"><div class="card-header"><h3 class="card-title">🗓 Vencimientos</h3><p class="card-sub">Próximos</p></div><ul>
      <?php while($v=$vencimientos->fetch_assoc()):?>
      <li><?=htmlspecialchars($v['apellido'].', '.$v['nombre'])?> (<?=date('d/m',strtotime($v['fecha_vencimiento']))?>)</li>
      <?php endwhile;?>
    </ul></section>

    <section class="card" style="grid-column:span 8">
      <div class="card-header">
        <h3 class="card-title">📋 Reservas del día</h3>
        <div><form method="GET"><input type="date" id="fecha" name="fecha" value="<?=htmlspecialchars($fecha_filtro)?>"></form></div>
      </div>
      <div id="reservas-body"><div class="skeleton" style="min-height:100px"></div></div>
    </section>

    <section class="card" id="contenedor-alumnos">
      <div class="card-header"><h3 class="card-title">🧑‍🎓 Alumnos de hoy</h3><p class="card-sub" id="asis-sub">Cargando…</p></div>
      <div id="alumnos-body"><div class="skeleton" style="min-height:100px"></div></div>
    </section>
  </div>
</div>
</body>
</html>
