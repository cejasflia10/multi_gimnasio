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

/* Cumples */
$cumples = $conexion->query("
  SELECT nombre, apellido, fecha_nacimiento
  FROM clientes
  WHERE gimnasio_id={$gimnasio_id}
    AND fecha_nacimiento IS NOT NULL
    AND fecha_nacimiento>='1000-01-01'
    AND DATE_FORMAT(fecha_nacimiento,'%m-%d') >= DATE_FORMAT(CURDATE(),'%m-%d')
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
    AND m.fecha_vencimiento>='1000-01-01'
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

/* Nuevos online */
$nuevos = $conexion->query("SELECT id, nombre, apellido FROM clientes WHERE gimnasio_id={$gimnasio_id} AND nuevo_online=1");
$avisos_html = '';
if ($nuevos && $nuevos->num_rows) {
  ob_start(); ?>
  <div class="notice notice-warm">
    <div class="notice-title">📢 Nuevos registros online</div>
    <div class="notice-body">
      <?php while($n=$nuevos->fetch_assoc()): ?>
        <div class="notice-item">
          <?= htmlspecialchars($n['nombre'].' '.$n['apellido']) ?>
          — <a class="link-inline" href="marcar_visto.php?id=<?= (int)$n['id'] ?>">Marcar como visto</a>
        </div>
      <?php endwhile; ?>
    </div>
  </div>
  <?php $avisos_html = ob_get_clean();
}

/* Disciplinas top */
$rows = [];
$q = $conexion->query("
  SELECT nombre_mostrar, total FROM (
    SELECT
      MIN(TRIM(REPLACE(REPLACE(COALESCE(d.nombre,c.disciplina),CONVERT(0xC2A0 USING utf8mb4),' '),CHAR(9),' '))) nombre_mostrar,
      UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(REPLACE(REPLACE(COALESCE(d.nombre,c.disciplina),CONVERT(0xC2A0 USING utf8mb4),' '),CHAR(9),' ')),' ',''),'-',''),'.',''),'_',''),'/','')) clave,
      COUNT(*) total
    FROM clientes c
    LEFT JOIN disciplinas d ON d.id=c.disciplina_id
    WHERE c.gimnasio_id={$gimnasio_id}
      AND COALESCE(d.nombre,c.disciplina) IS NOT NULL
      AND TRIM(REPLACE(REPLACE(COALESCE(d.nombre,c.disciplina),CONVERT(0xC2A0 USING utf8mb4),' '),CHAR(9),' '))<>''
    GROUP BY clave
  ) u
  ORDER BY total DESC
  LIMIT 10
");
if ($q) while($r=$q->fetch_assoc()) $rows[]=['nombre'=>ucwords(strtolower($r['nombre_mostrar'])),'total'=>(int)$r['total']];
if ($rows){
  $agg=[]; foreach($rows as $r){ $k=strtolower(trim($r['nombre'])); $agg[$k]['nombre']=$r['nombre']; $agg[$k]['total']=($agg[$k]['total']??0)+(int)$r['total']; }
  $rows=array_values($agg); usort($rows,fn($a,$b)=>$b['total']<=>$a['total']); $rows=array_slice($rows,0,10);
}

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
  --brand:#b45309; --brand-2:#f59e0b; --brand-3:#fbbf24;
  --card:#fff; --stroke:rgba(15,23,42,.08); --shadow:0 10px 28px rgba(2,6,23,.08);
  --gap:18px;
}
*{ box-sizing:border-box }
html,body{ height:100% }
body{
  margin:0; color:var(--ink);
  font-family:system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial,sans-serif;
  background:
    radial-gradient(900px 600px at -10% -10%, rgba(255,105,0,.08) 0%, transparent 45%),
    radial-gradient(1200px 700px at 110% -10%, rgba(255,170,0,.08) 0%, transparent 55%),
    linear-gradient(180deg,var(--bg1) 0%, var(--bg2) 100%);
}
.wrap{ max-width:1200px; margin:24px auto; padding:0 16px 40px; }

/* Header */
.header{ display:grid; grid-template-columns:1fr auto; gap:16px; align-items:center; margin-bottom:16px; }
.title{
  margin:0; font-weight:900; letter-spacing:.6px;
  background:linear-gradient(90deg,var(--brand),var(--brand-2),var(--brand-3));
  -webkit-background-clip:text; background-clip:text; color:transparent;
}
.sys-exp{ margin:.25rem 0 0; color:var(--mut); }
.logo-wrap{ display:flex; gap:10px; justify-content:flex-end; }
#logoGym{ max-height:170px; max-width:420px; object-fit:contain; background:#fff; padding:8px; border:1px solid var(--stroke); border-radius:16px; box-shadow:var(--shadow); }
.btn-mini{ padding:6px 10px; border:1px solid var(--stroke); background:linear-gradient(180deg,#fff,#f7fafc); border-radius:12px; cursor:pointer; }
@media (max-width:992px){ .header{ grid-template-columns:1fr; } .logo-wrap{ justify-content:flex-start; } #logoGym{ max-height:64px; max-width:180px; padding:6px; } }

/* Grid */
.grid{ display:grid; grid-template-columns:repeat(12,1fr); gap:var(--gap); }
@media (max-width:1100px){ .grid{ grid-template-columns:repeat(8,1fr); } }
@media (max-width:768px){  .grid{ grid-template-columns:repeat(4,1fr); } }

/* Cards */
.card,.notice,.alert,.kpi,.field{ background:var(--card); border:1px solid var(--stroke); border-radius:18px; padding:16px; box-shadow:var(--shadow); }
.card{ grid-column:span 4; }
.card-header{ display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; gap:12px; }
.card-title{ margin:0; color:var(--brand); font-size:1.05rem; }
.card-sub{ margin:0; color:#64748b; font-size:.9rem; }

.kpis{ display:flex; gap:12px; flex-wrap:wrap; margin:10px 0 16px; }
.kpi{ min-width:160px; background:linear-gradient(180deg,#fff,#f8fafc); }
.kpi-label{ color:var(--brand); font-size:.8rem; }
.kpi-value{ font-weight:900; font-size:1.8rem; }

.chart-wrap{ aspect-ratio:16/9; position:relative; width:100%; max-width:820px; margin:0 auto; }
#disciplinasChart{ position:absolute; inset:0; }

/* ====== Móvil ====== */
@media (max-width:900px){
  .card,.alert,.notice{ text-align:center; }
  .card-header{ flex-direction:column; align-items:center; gap:6px; }
  .kpis{ justify-content:center; }
  .toolbar{ display:flex; justify-content:center; }
  .card{ grid-column:1 / -1; }
}

/* ====== ALUMNOS HOY ====== */
#contenedor-alumnos ul.asistencias-hoy{ list-style:none; margin:0; padding:0; }
#contenedor-alumnos ul.asistencias-hoy li{
  display:flex; justify-content:space-between; align-items:baseline;
  gap:10px; padding:8px 6px; border-bottom:1px dashed rgba(15,23,42,.12);
}
#contenedor-alumnos ul.asistencias-hoy li:last-child{ border-bottom:none; }
#contenedor-alumnos .n{ flex:1 1 auto; min-width:0; white-space:normal; word-break:keep-all; overflow-wrap:anywhere; line-height:1.25; }
#contenedor-alumnos .h{ flex:0 0 auto; font-variant-numeric:tabular-nums; white-space:nowrap; }

/* ====== RESERVAS compacto ====== */
@media (max-width:900px){
  #contenedor-reservas .res-card{
    border:1px solid rgba(15,23,42,.08);
    border-radius:16px; box-shadow:0 6px 16px rgba(2,6,23,.06);
    padding:10px 12px; margin:10px 0; background:#fff;
    text-align:left;
  }
  #contenedor-reservas .res-head{
    display:flex; align-items:center; gap:8px;
    font-weight:800; color:#b45309; font-size:1.02rem; line-height:1.2;
    margin-bottom:6px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
  }
  #contenedor-reservas .res-body{
    display:grid; grid-template-columns:1fr 1fr; gap:8px; align-items:start;
  }
  #contenedor-reservas .res-body > div{ display:flex; gap:6px; line-height:1.25; }
  @media (max-width:520px){ #contenedor-reservas .res-body{ grid-template-columns:1fr; } }
}

/* ====== FIX SOLO INGRESOS ====== */
#contenedor-ingresos *{
  white-space: normal !important;
  word-break: keep-all !important;
  overflow-wrap: anywhere !important;
  letter-spacing: normal !important;
  line-height: 1.25 !important;
}
#contenedor-ingresos [style*="position:absolute"],
#contenedor-ingresos .abs, 
#contenedor-ingresos .absolute {
  position: static !important;
  left:auto !important; top:auto !important; right:auto !important; bottom:auto !important;
  transform:none !important;
}
#contenedor-ingresos .ing-row,
#contenedor-ingresos .ing-card,
#contenedor-ingresos .row,
#contenedor-ingresos > div > div {
  display:flex !important; align-items:center !important; justify-content:space-between !important;
  gap:12px !important; padding:12px 14px !important; border-radius:14px !important; background:#fff !important;
}
#contenedor-ingresos .ing-titulo,
#contenedor-ingresos .titulo,
#contenedor-ingresos .left,
#contenedor-ingresos .label,
#contenedor-ingresos .txt,
#contenedor-ingresos .desc {
  flex:1 1 auto !important; min-width:0 !important; color:#b45309 !important; font-weight:800 !important;
}
#contenedor-ingresos .ing-monto,
#contenedor-ingresos .monto,
#contenedor-ingresos .amount,
#contenedor-ingresos .right,
#contenedor-ingresos [class*="monto"],
#contenedor-ingresos [class*="amount"] {
  flex:0 0 auto !important; font-weight:900 !important; font-size:1.5rem !important; white-space:nowrap !important; text-align:right !important;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="wrap">
  <div class="header">
    <div>
      <h1 class="title">🏋️ <?= htmlspecialchars($nombre_gym) ?></h1>
      <p class="sys-exp">🗓 Vencimiento del sistema:
        <strong><?= (is_string($fecha_venc) && $fecha_venc!=='0000-00-00' && strtotime($fecha_venc)) ? date('d/m/Y', strtotime($fecha_venc)) : '---' ?></strong>
      </p>
    </div>
    <div class="logo-wrap">
      <?php if (!empty($logo)): ?>
        <div>
          <img id="logoGym" src="<?= htmlspecialchars($logo) ?>?v=<?= time() ?>" alt="Logo del gimnasio">
          <div style="margin-top:6px; text-align:right">
            <button class="btn-mini" onclick="document.getElementById('formLogo').style.display='block'">🖋 Cambiar logo</button>
            <form id="formLogo" method="POST" action="subir_logo.php" enctype="multipart/form-data" style="display:none; margin-top:6px">
              <input type="file" name="logo" accept="image/*" required onchange="this.form.submit()">
            </form>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="kpis">
    <div class="kpi"><div class="kpi-label">Activos</div><div class="kpi-value"><?= (int)$activos ?></div></div>
    <div class="kpi"><div class="kpi-label">Inactivos</div><div class="kpi-value"><?= (int)$inactivos ?></div></div>
  </div>

  <?= $avisos_html ?>

  <?php if ($cuentas_corrientes > 0): ?>
    <div class="alert" style="margin:10px 0">
      ⚠️ Hay <strong><?= $cuentas_corrientes ?></strong> cliente(s) con saldo negativo.
      <a class="link-inline" href="ver_cuentas_corrientes.php">Ver cuentas corrientes</a>
    </div>
  <?php endif; ?>

  <?php if ($pagos_pendientes > 0): ?>
    <div class="alert" style="margin:10px 0">
      💸 Hay <strong><?= $pagos_pendientes ?></strong> pago(s) pendiente(s) de clientes.
      <a class="link-inline" href="ver_pagos_pendientes.php">Ver pagos</a>
    </div>
  <?php endif; ?>

  <div class="toolbar">
    <span id="icono-ojo" class="icon-btn" title="Mostrar/Ocultar montos" onclick="document.querySelectorAll('.bloque-monto').forEach(b=>b.classList.toggle('hidden'))">👁️‍🗨️</span>
  </div>

  <div class="grid">

    <!-- INGRESOS (AJAX) -->
    <section class="card bloque-monto" id="contenedor-ingresos">
      <div class="card-header">
        <h3 class="card-title">💰 Ingresos</h3>
        <p class="card-sub">Actualiza cada 10s</p>
      </div>
      <div id="ingresos-body"><div class="skeleton" style="min-height:120px"></div></div>
    </section>

    <!-- CUMPLES -->
    <section class="card">
      <div class="card-header">
        <h3 class="card-title">🎂 Próximos Cumpleaños</h3>
        <p class="card-sub">Top 5 próximos</p>
      </div>
      <ul>
        <?php while($c=$cumples->fetch_assoc()): ?>
          <li><?= htmlspecialchars($c['apellido'].', '.$c['nombre']) ?>
            (<?= ($c['fecha_nacimiento'] && strtotime($c['fecha_nacimiento'])) ? date('d/m', strtotime($c['fecha_nacimiento'])) : '--' ?>)
          </li>
        <?php endwhile; ?>
      </ul>
    </section>

    <!-- VENCIMIENTOS -->
    <section class="card" id="card-venc">
      <div class="card-header">
        <h3 class="card-title">🗓 Vencimientos</h3>
        <p class="card-sub">Próximas membresías a vencer</p>
      </div>
      <ul>
        <?php while($v=$vencimientos->fetch_assoc()): ?>
          <li><?= htmlspecialchars($v['apellido'].', '.$v['nombre']) ?>
            (<?= ($v['fecha_vencimiento'] && strtotime($v['fecha_vencimiento'])) ? date('d/m', strtotime($v['fecha_vencimiento'])) : '--' ?>)
          </li>
        <?php endwhile; ?>
      </ul>
    </section>

    <!-- RESERVAS (AJAX) -->
    <section class="card" style="grid-column:span 8">
      <div class="card-header">
        <h3 class="card-title">📋 Reservas del día</h3>
        <div class="field" style="display:flex;align-items:center;gap:8px">
          <label for="fecha" class="mut">Ver día</label>
          <form id="form-fecha" method="GET" oninput="this.submit()">
            <input type="date" id="fecha" name="fecha" value="<?= htmlspecialchars($fecha_filtro) ?>">
          </form>
        </div>
      </div>
      <div id="contenedor-reservas"><div id="reservas-body"><div class="skeleton" style="min-height:110px"></div></div></div>
    </section>

    <!-- ALUMNOS (AJAX) -->
    <section class="card" id="contenedor-alumnos">
      <div class="card-header">
        <h3 class="card-title">🧑‍🎓 Alumnos de hoy</h3>
        <p class="card-sub"><span id="alumnos-count"></span></p>
      </div>
      <div id="alumnos-body"><div class="skeleton" style="min-height:110px"></div></div>
    </section>

    <!-- DISCIPLINAS -->
    <section class="card" style="grid-column:span 8">
      <div class="card-header">
        <h3 class="card-title">📊 Disciplinas más registradas</h3>
        <p class="card-sub">Top 10 normalizadas</p>
      </div>
      <div class="chart-wrap">
        <canvas id="disciplinasChart" role="img" aria-label="Gráfico de barras de disciplinas"></canvas>
      </div>
      <?php if (!count($rows)): ?><small class="mut">No hay datos para mostrar.</small><?php endif; ?>
    </section>

  </div>
</div>

<script>
/* Inyecta HTML en el body indicado */
function fetchIntoBody(url, bodyId, afterLoad){
  const el = document.getElementById(bodyId);
  if(!el) return;
  fetch(url, {cache:'no-store'})
    .then(r => r.text())
    .then(html => {
      el.innerHTML = html;
      if (typeof afterLoad === 'function') afterLoad(el);
    })
    .catch(()=>{});
}

/* ===== ALUMNOS: Nombre ..... Hora (y contador) ===== */
function normalizeAlumnos(root){
  const m = (root.textContent||'').match(/(\d+)\s+ingresos?/i);
  if (m) document.getElementById('alumnos-count').textContent = m[1]+' ingresos';

  let ul = root.querySelector('ul');
  if (!ul){
    const items = root.querySelectorAll('li');
    if (items.length){
      ul = document.createElement('ul');
      items.forEach(li => ul.appendChild(li));
      root.innerHTML = '';
      root.appendChild(ul);
    }
  }
  if (!ul) return;
  ul.classList.add('asistencias-hoy');

  ul.querySelectorAll('li').forEach(li=>{
    const txt = (li.textContent||'').replace(/\s+/g,' ').trim();
    const mm = txt.match(/^(.*?)[\s\-–]*\b(\d{2}:\d{2}:\d{2})\b.*$/);
    const nombre = (mm ? mm[1] : txt).trim();
    const hora   = (mm ? mm[2] : '').trim();
    li.innerHTML = `<span class="n">${nombre}</span>${hora?`<span class="h">${hora}</span>`:''}`;
  });
}

/* ===== RESERVAS: compactar visual ===== */
function normalizeReservas(root){
  [...root.children].forEach(card=>{
    if (card.nodeType!==1) return;
    card.classList.add('res-card');
    const head = [...card.querySelectorAll('*')].find(e=>/\b\d{2}:\d{2}:\d{2}\b/.test(e.textContent||'')) || card.firstElementChild;
    if (head) head.classList.add('res-head');

    const body = document.createElement('div'); body.className='res-body';
    const alumno = [...card.querySelectorAll('*')].find(e=>/👤|alumno/i.test(e.textContent||''));
    const profe  = [...card.querySelectorAll('*')].find(e=>/profe|entrenador|coach/i.test(e.textContent||''));
    if (alumno){ const d=document.createElement('div'); d.innerHTML=`<span>👤</span><span>${alumno.textContent.trim()}</span>`; body.appendChild(d); }
    if (profe){  const d=document.createElement('div'); d.innerHTML=`<span>🧑‍🏫</span><span>${profe.textContent.trim()}</span>`; body.appendChild(d); }
    if (body.children.length) card.appendChild(body);
  });
}

/* ===== Cargas periódicas ===== */
function cargarDatos(){
  const f = document.getElementById('fecha')?.value;
  fetchIntoBody('ajax_ingresos.php', 'ingresos-body');                                       // Ingresos (solo body)
  if (f) fetchIntoBody('ajax_reservas.php?fecha='+encodeURIComponent(f), 'reservas-body', normalizeReservas);
  fetchIntoBody('ajax_alumnos_hoy.php', 'alumnos-body', normalizeAlumnos);
}

window.addEventListener('load', () => {
  cargarDatos();
  setInterval(cargarDatos, 10000);
});

/* ===== Chart ===== */
(function(){
  const data = <?= json_encode($rows, JSON_UNESCAPED_UNICODE) ?>;
  if(!Array.isArray(data) || !data.length) return;
  const el = document.getElementById('disciplinasChart'); if(!el) return;
  const ctx = el.getContext('2d');
  const h = el.offsetHeight || 300;
  const grad = ctx.createLinearGradient(0,0,0,h);
  grad.addColorStop(0,'rgba(251,191,36,.95)');
  grad.addColorStop(1,'rgba(245,158,11,.65)');
  new Chart(ctx,{
    type:'bar',
    data:{ labels:data.map(d=>d.nombre), datasets:[{ label:'Registros', data:data.map(d=>+d.total), backgroundColor:grad, borderColor:'rgba(180,83,9,.9)', borderWidth:2, borderRadius:10 }]},
    options:{
      responsive:true, maintainAspectRatio:false, animation:{duration:800},
      plugins:{ legend:{labels:{color:'#0f172a'}},
                tooltip:{backgroundColor:'rgba(15,23,42,.96)', titleColor:'#fbbf24', bodyColor:'#e2e8f0', borderColor:'rgba(180,83,9,.35)', borderWidth:1} },
      scales:{ x:{ticks:{color:'#0f172a'}, grid:{color:'rgba(2,6,23,.06)'}},
               y:{beginAtZero:true, ticks:{color:'#0f172a', precision:0}, grid:{color:'rgba(2,6,23,.06)'}} }
    }
  });
})();
</script>
</body>
</html>
