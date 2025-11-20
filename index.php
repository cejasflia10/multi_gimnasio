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

/* ====== Móvil (centrado y columnas completas) ====== */
@media (max-width:900px){
  .card,.alert,.notice{ text-align:center; }
  .card-header{ flex-direction:column; align-items:center; gap:6px; }
  .kpis{ justify-content:center; }
  .toolbar{ display:flex; justify-content:center; }
  .card{ grid-column:1 / -1; }
}

/* ====== ALUMNOS HOY ====== */
#contenedor-alumnos ul.alum-list{ list-style:none; margin:0; padding:0; }
#contenedor-alumnos ul.alum-list li{ padding:8px 6px; border-bottom:1px dashed rgba(15,23,42,.12); }
#contenedor-alumnos ul.alum-list li:last-child{ border-bottom:none; }
#contenedor-alumnos .alum-head{ font-weight:800; color:#b45309; margin-bottom:4px; }
#contenedor-alumnos .alum-name{ white-space:normal; overflow-wrap:anywhere; line-height:1.25; }

/* ====== RESERVAS ====== */
#contenedor-reservas ul.res-list{ list-style:none; margin:0; padding:0; }
#contenedor-reservas ul.res-list li{ padding:8px 6px; border-bottom:1px dashed rgba(15,23,42,.12); }
#contenedor-reservas ul.res-list li:last-child{ border-bottom:none; }
#contenedor-reservas .res-head{ font-weight:800; color:#b45309; margin-bottom:4px; }
#contenedor-reservas .cli{ white-space:normal; overflow-wrap:anywhere; line-height:1.25; }

/* ===== CENTRAR TARJETAS ===== */
#contenedor-ingresos,
#card-venc,
#card-cumples { text-align:center; }

/* ===== MONTO DE INGRESOS: OCULTO POR DEFECTO ===== */
body.ocultar-montos #ingresos-body .monto,
body.ocultar-montos #ingresos-body .amount,
body.ocultar-montos #ingresos-body [class*="monto"]{
  filter: blur(10px);
  pointer-events: none;
  user-select: none;
}

/* Contenido ingresos centrado */
#ingresos-body .box,
#ingresos-body .ing-card{
  display:flex; flex-direction:column; align-items:center; gap:6px;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="ocultar-montos"><!-- ← montos ocultos al cargar -->

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

  <div class="toolbar" style="display:flex; gap:10px; align-items:center">
    <button id="btn-ojo" class="btn-mini" title="Mostrar/Ocultar montos" onclick="toggleMontos()">👁️‍🗨️ Ver montos</button>
  </div>

  <div class="grid">

    <!-- INGRESOS (AJAX) -->
    <section class="card" id="contenedor-ingresos">
      <div class="card-header">
        <h3 class="card-title">💰 Ingresos</h3>
        <p class="card-sub">Actualiza cada 10s</p>
      </div>
      <div id="ingresos-body"><div class="skeleton" style="min-height:120px"></div></div>
    </section>

    <!-- CUMPLES -->
    <section class="card" id="card-cumples">
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
/* Mostrar/Ocultar montos de ingresos */
function toggleMontos(){
  document.body.classList.toggle('ocultar-montos');
  const btn = document.getElementById('btn-ojo');
  const oculto = document.body.classList.contains('ocultar-montos');
  btn.textContent = oculto ? '👁️‍🗨️ Ver montos' : '🙈 Ocultar montos';
}

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

/* helpers nombres/profes (igual que tenías) */
function _cleanName(s){
  return (s||'')
    .replace(/[🕒📅⏰👤🏠🏫🏡🏢🏟️👨‍🏫🧑‍🏫]/g,' ')
    .replace(/\b(Profesor|Profe|Entrenador|Coach|Academia|Escuela|Team|Dojo|Gym|Gimnasio)\b.*$/i,'')
    .replace(/\s+/g,' ')
    .trim();
}
function _lower(s){ return _cleanName(s).toLowerCase(); }

function _buildProfExcludes(root){
  const excludes = new Set();
  const full = (root.textContent || '').replace(/\u00A0/g,' ');

  const reProf = /(Profesor|Profe|Entrenador|Coach)\s*:?\s*([A-Za-zÁÉÍÓÚÑñÜü'´` .-]{2,})(?=$|[,\n;|/]| - | – | — )/gi;
  for (let m; (m = reProf.exec(full)); ){
    const nm = _cleanName(m[2]);
    if (nm) excludes.add(_lower(nm));
  }

  const reEmoji = /(👨‍🏫|🧑‍🏫)\s*([A-Za-zÁÉÍÓÚÑñÜü'´` .-]{2,})(?=$|[,\n;|/]| - | – | — )/g;
  for (let m; (m = reEmoji.exec(full)); ){
    const nm = _cleanName(m[2]);
    if (nm) excludes.add(_lower(nm));
  }

  const reCasa = /(🏠)\s*([A-Za-zÁÉÍÓÚÑñÜü'´` .-]{2,})(?=$|[,\n;|/]| - | – | — )/g;
  for (let m; (m = reCasa.exec(full)); ){
    const nm = _cleanName(m[2]);
    if (nm) excludes.add(_lower(nm));
  }

  const reLinea = /\b(Profesor|Profe|Entrenador|Coach)\b[^A-Za-zÁÉÍÓÚÑñÜü]*([A-Za-zÁÉÍÓÚÑñÜü'´` .-]{2,})/gi;
  for (let m; (m = reLinea.exec(full)); ){
    const nm = _cleanName(m[2]);
    if (nm) excludes.add(_lower(nm));
  }

  return excludes;
}

/* ALUMNOS HOY */
function normalizeAlumnos(root){
  const m = (root.textContent||'').match(/(\d+)\s+ingresos?/i);
  if (m) { const span = document.getElementById('alumnos-count'); if (span) span.textContent = m[1]+' ingresos'; }

  const EXC = _buildProfExcludes(root);

  let seedLis = root.querySelectorAll('li');
  if (!seedLis.length){ const ul = root.querySelector('ul'); if (ul) seedLis = ul.querySelectorAll('li'); }
  if (!seedLis.length) return;

  const registros = [];
  seedLis.forEach(li=>{
    let txt = (li.textContent||'').replace(/\u00A0/g,' ').replace(/\s+/g,' ').trim();

    txt = txt.replace(/\b(Profesor|Profe|Entrenador|Coach)\b.*$/i,'')
             .replace(/(🏠|🏫|👨‍🏫|🧑‍🏫).*$/,'')
             .trim();

    const mm = txt.match(/^(.*?)[\s\-–—]*\b(\d{2}):(\d{2})(?::\d{2})?\b.*$/);
    const nombre = _cleanName(mm ? mm[1] : txt);
    const horaHH = mm ? mm[2] : null;

    if (!nombre) return;
    if (EXC.has(_lower(nombre))) return;
    if (/\b(Academia|Escuela|Team|Dojo|Gym|Gimnasio)\b/i.test(nombre)) return;

    const key = horaHH ? (horaHH.padStart(2,'0')+' hs') : '— hs';
    registros.push({ key, nombre });
  });

  const map = new Map();
  for (const r of registros){ if(!map.has(r.key)) map.set(r.key,new Set()); map.get(r.key).add(r.nombre); }

  const sorted = [...map.entries()].sort((a,b)=>{
    if (a[0]==='— hs') return 1; if (b[0]==='— hs') return -1; return a[0].localeCompare(b[0]);
  });

  root.innerHTML = '';
  const ul = document.createElement('ul'); ul.className='alum-list';
  sorted.forEach(([hora, setNombres])=>{
    const li = document.createElement('li');
    li.innerHTML = `<div class="alum-head">${hora}</div>` + [...setNombres].map(n=>`<div class="alum-name">${n}</div>`).join('');
    ul.appendChild(li);
  });
  root.appendChild(ul);
}

/* RESERVAS */
function normalizeReservas(root){
  const cont = document.getElementById('reservas-body') || root;

  const EXC = _buildProfExcludes(root);

  let txt = (root.textContent || '').replace(/\u00A0/g, ' ').trim();
  if (!txt) { cont.innerHTML = '<div class="mut">No hay reservas para este día.</div>'; return; }

  txt = txt
    .replace(/\s*·\s*/g, '\n')
    .replace(/[|/]/g, '\n')
    .replace(/\s*[-–—]\s*/g, '\n')
    .replace(/\b(\d{2}:\d{2})(?::\d{2})?\b/g, '\n$1\n');

  const lines = txt.split(/\r?\n/).map(s=>s.trim()).filter(Boolean);

  const IGNORE_RE = /\b(Lunes|Martes|Mi[eé]rcoles|Jueves|Viernes|S[áa]bado|Domingo|Reserva|Turno|Clase|Horario)\b/i;
  const PROF_WORD = /\b(Profesor|Profe|Entrenador|Coach)\b/i;

  const map = new Map();
  let currentKey = '— hs';

  for (let line of lines){
    const h = line.match(/\b(\d{2}):(\d{2})(?::\d{2})?\b/);
    if (h){
      currentKey = h[1].padStart(2,'0')+' hs';
      if (!map.has(currentKey)) map.set(currentKey, new Set());
      continue;
    }

    if (IGNORE_RE.test(line) || /^👤+$/.test(line) || line.length<2) continue;

    line = line
      .replace(/\b(Profesor|Profe|Entrenador|Coach)\b.*$/i,'')
      .replace(/(🏠|🏫|👨‍🏫|🧑‍🏫).*$/,'')
      .trim();

    const nombre = _cleanName(line);
    if (!nombre) continue;

    if (EXC.has(_lower(nombre))) continue;
    if (/\b(Academia|Escuela|Team|Dojo|Gym|Gimnasio)\b/i.test(nombre)) continue;
    if (PROF_WORD.test(line)) continue;

    if (!map.has(currentKey)) map.set(currentKey, new Set());
    map.get(currentKey).add(nombre);
  }

  if ([...map.values()].every(set => set.size === 0)){
    cont.innerHTML = '<div class="mut">No hay reservas para este día.</div>';
    return;
  }

  const sorted = [...map.entries()].sort((a,b)=>{
    if (a[0]==='— hs') return 1; if (b[0]==='— hs') return -1; return a[0].localeCompare(b[0]);
  });

  const ul = document.createElement('ul'); ul.className='res-list';
  sorted.forEach(([hora, setNombres])=>{
    const li = document.createElement('li');
    li.innerHTML = `<div class="res-head">${hora}</div>` + [...setNombres].map(n=>`<div class="cli">${n}</div>`).join('');
    ul.appendChild(li);
  });

  cont.innerHTML = '';
  cont.appendChild(ul);
}

/* ===== Cargas periódicas ===== */
function cargarDatos(){
  const f = document.getElementById('fecha')?.value;

  // 🔹 CAMBIO: pasamos la fecha también a ajax_ingresos.php
  fetchIntoBody('ajax_ingresos.php' + (f ? '?fecha='+encodeURIComponent(f) : ''), 'ingresos-body');

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
