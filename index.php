<?php
// --- INICIO: validación de sesión e inactividad ---
if (session_status() === PHP_SESSION_NONE) session_start();

$timeout_minutos = 30;
$timeout_seg = $timeout_minutos * 60;

if (!isset($_SESSION['gimnasio_id'])) {
    if (session_status() !== PHP_SESSION_NONE) {
        session_unset();
        session_destroy();
    }
    header('Location: login.php');
    exit;
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_seg) {
    session_unset();
    session_destroy();
    header('Location: login.php?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['session_regenerated_time'])) {
    session_regenerate_id(true);
    $_SESSION['session_regenerated_time'] = time();
} else {
    if (time() - $_SESSION['session_regenerated_time'] > 15 * 60) {
        session_regenerate_id(true);
        $_SESSION['session_regenerated_time'] = time();
    }
}
// --- FIN: validación de sesión e inactividad ---

require_once 'conexion.php';
require_once 'menu_horizontal.php';

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
$rol         = $_SESSION['rol'] ?? '';

$gimnasio = $conexion->query("SELECT nombre, logo, fecha_vencimiento FROM gimnasios WHERE id = {$gimnasio_id}")->fetch_assoc();
$nombre_gym = $gimnasio['nombre'] ?? 'Gimnasio';
$logo       = $gimnasio['logo']   ?? '';
$fecha_venc = $gimnasio['fecha_vencimiento'] ?? '---';

// ===== KPIs Activos vs Inactivos (última membresía por cliente) =====
$estado = $conexion->query("
  SELECT
    SUM(CASE WHEN u.fv IS NOT NULL AND u.fv >= CURDATE() THEN 1 ELSE 0 END) AS activos,
    SUM(CASE WHEN u.fv IS NULL OR u.fv < CURDATE() THEN 1 ELSE 0 END) AS inactivos
  FROM clientes c
  LEFT JOIN (
    SELECT cliente_id, MAX(fecha_vencimiento) AS fv
    FROM membresias
    WHERE gimnasio_id = {$gimnasio_id}
      AND fecha_vencimiento IS NOT NULL
      AND fecha_vencimiento >= '1000-01-01'
    GROUP BY cliente_id
  ) u ON u.cliente_id = c.id
  WHERE c.gimnasio_id = {$gimnasio_id}
")->fetch_assoc();

$activos   = (int)($estado['activos'] ?? 0);
$inactivos = (int)($estado['inactivos'] ?? 0);

// ===== Cumpleaños (filtro seguro) =====
$cumples = $conexion->query("
  SELECT nombre, apellido, fecha_nacimiento
  FROM clientes
  WHERE gimnasio_id = {$gimnasio_id}
    AND fecha_nacimiento IS NOT NULL
    AND fecha_nacimiento >= '1000-01-01'
    AND DATE_FORMAT(fecha_nacimiento, '%m-%d') >= DATE_FORMAT(CURDATE(), '%m-%d')
  ORDER BY DATE_FORMAT(fecha_nacimiento, '%m-%d')
  LIMIT 5
");

// ===== Vencimientos (filtro seguro) =====
$vencimientos = $conexion->query("
  SELECT c.nombre, c.apellido, m.fecha_vencimiento
  FROM membresias m
  JOIN clientes c ON m.cliente_id = c.id
  WHERE m.gimnasio_id = {$gimnasio_id}
    AND m.fecha_vencimiento IS NOT NULL
    AND m.fecha_vencimiento >= '1000-01-01'
    AND m.fecha_vencimiento >= CURDATE()
  ORDER BY m.fecha_vencimiento ASC
  LIMIT 5
");

$fecha_filtro = $_GET['fecha'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_filtro)) $fecha_filtro = date('Y-m-d');

// ===== PAGOS PENDIENTES =====
$pagos_pendientes = 0;
$consulta = $conexion->query("
  SELECT COUNT(*) AS total
  FROM pagos_pendientes
  JOIN clientes ON pagos_pendientes.cliente_id = clientes.id
  WHERE pagos_pendientes.estado = 'pendiente'
    AND clientes.gimnasio_id = {$gimnasio_id}
");
if ($consulta && $r = $consulta->fetch_assoc()) $pagos_pendientes = (int)$r['total'];

// ===== CUENTAS CORRIENTES =====
$cuentas_corrientes = 0;
$consulta_cc = $conexion->query("
  SELECT COUNT(*) AS total FROM (
    SELECT cliente_id
    FROM cuentas_corrientes
    WHERE gimnasio_id = {$gimnasio_id}
    GROUP BY cliente_id
    HAVING SUM(monto) < 0
  ) AS sub
");
if ($consulta_cc && $r = $consulta_cc->fetch_assoc()) $cuentas_corrientes = (int)$r['total'];

// ===== Avisos de nuevos online =====
$nuevos = $conexion->query("SELECT id, nombre, apellido FROM clientes WHERE gimnasio_id = {$gimnasio_id} AND nuevo_online = 1");
$avisos_html = '';
if ($nuevos && $nuevos->num_rows > 0) {
    ob_start(); ?>
    <div class="notice notice-warm">
      <div class="notice-title">📢 Nuevos registros online</div>
      <div class="notice-body">
        <?php while ($n = $nuevos->fetch_assoc()): ?>
          <div class="notice-item">
            <?= htmlspecialchars($n['nombre'].' '.$n['apellido']) ?>
            — <a class="link-inline" href="marcar_visto.php?id=<?= (int)$n['id'] ?>">Marcar como visto</a>
          </div>
        <?php endwhile; ?>
      </div>
    </div>
    <?php
    $avisos_html = ob_get_clean();
}

// ===== Disciplinas TOP (normalizadas) =====
$disciplinas_top_q = $conexion->query("
  SELECT nombre_mostrar, total
  FROM (
    SELECT
      MIN(
        TRIM(
          REPLACE(
            REPLACE(COALESCE(d.nombre, c.disciplina), CONVERT(0xC2A0 USING utf8mb4), ' '), /* NBSP */
            CHAR(9), ' ' /* TAB */
          )
        )
      ) AS nombre_mostrar,
      UPPER(
        REPLACE(
          REPLACE(
            REPLACE(
              REPLACE(
                REPLACE(
                  TRIM(
                    REPLACE(
                      REPLACE(COALESCE(d.nombre, c.disciplina), CONVERT(0xC2A0 USING utf8mb4), ' '),
                      CHAR(9), ' '
                    )
                  ),
                  ' ', ''), '-', ''), '.', ''), '_', ''), '/'
          , '')
      ) AS clave,
      COUNT(*) AS total
    FROM clientes c
    LEFT JOIN disciplinas d ON d.id = c.disciplina_id
    WHERE c.gimnasio_id = {$gimnasio_id}
      AND COALESCE(d.nombre, c.disciplina) IS NOT NULL
      AND TRIM(REPLACE(REPLACE(COALESCE(d.nombre, c.disciplina), CONVERT(0xC2A0 USING utf8mb4), ' '), CHAR(9), ' ')) <> ''
    GROUP BY clave
  ) u
  ORDER BY total DESC
  LIMIT 10
");

$disciplinas_rows = [];
if ($disciplinas_top_q) {
  while ($row = $disciplinas_top_q->fetch_assoc()) {
    $nombre = ucwords(strtolower($row['nombre_mostrar']));
    $disciplinas_rows[] = ['nombre' => $nombre, 'total' => (int)$row['total']];
  }
}
if ($disciplinas_rows) {
  $agg = [];
  foreach ($disciplinas_rows as $r) {
    $k = strtolower(trim($r['nombre']));
    if (!isset($agg[$k])) $agg[$k] = ['nombre' => $r['nombre'], 'total' => 0];
    $agg[$k]['total'] += (int)$r['total'];
  }
  $disciplinas_rows = array_values($agg);
  usort($disciplinas_rows, function($a,$b){ return $b['total'] <=> $a['total']; });
  $disciplinas_rows = array_slice($disciplinas_rows, 0, 10);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<title>Panel General - <?= htmlspecialchars($nombre_gym) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1" />

<style>
  :root{
    --bg:#0b0f16;
    --card:#0f1725cc; /* glass */
    --stroke:rgba(255,215,0,.16);
    --ink:#eef2ff;
    --mut:#93a3b5;
    --brand:#ffd166;
    --brand-2:#ffb703;
    --ok:#22c55e;
    --warn:#f59e0b;
    --bad:#ef4444;
    --chip:#0f1725;
    --shadow:0 10px 30px rgba(0,0,0,.35);
    --blur:10px;
    --radius:16px;
    --radius-sm:12px;
    --grid-gap:18px;
  }
  @media (prefers-color-scheme: light){
    :root{
      --bg:#f7fafc;
      --card:#ffffffcc;
      --stroke:rgba(17,24,39,.08);
      --ink:#0f172a;
      --mut:#475569;
      --brand:#b45309;
      --brand-2:#a16207;
      --chip:#ffffff;
      --shadow:0 8px 24px rgba(2,6,23,.10);
      --blur:6px;
    }
  }

  *{ box-sizing:border-box }
  html,body{ height:100% }
  body{
    margin:0; padding:0;
    font-family: system-ui, -apple-system, Segoe UI, Roboto, Inter, Arial, sans-serif;
    color:var(--ink); background: radial-gradient(1200px 500px at 10% -10%, #1b2638 0%, transparent 55%), radial-gradient(800px 600px at 110% -10%, #2b3a59 0%, transparent 40%), var(--bg);
    backdrop-filter: blur(0px);
  }

  /* Contenedor principal */
  .wrap{
    max-width:1200px; margin:24px auto; padding:0 16px 40px;
  }

  /* ===== HEADER NUEVO: logo grande a la derecha en desktop ===== */
  .header{
    display:grid;
    grid-template-columns: 1fr auto;
    gap:16px;
    align-items:center;
    margin-bottom:16px;
  }
  .header-left{ display:flex; align-items:center; gap:14px; min-width:0; }
  .title{
    margin:0;
    font-weight:800; letter-spacing:.3px;
    background: linear-gradient(90deg, var(--brand), #fff);
    -webkit-background-clip:text; background-clip:text; color:transparent;
    text-shadow: 0 2px 18px rgba(255, 215, 0, .15);
    line-height:1.1;
  }
  .sys-exp{ margin:.25rem 0 0; font-size:.95rem; color:var(--mut) }

  /* logo chico para mobile (en fila con título) */
  .logo-sm{
    width:auto; max-height:48px; max-width:120px; object-fit:contain;
    border-radius:10px; background:#fff; padding:4px; box-shadow: var(--shadow);
  }

  /* logo grande a la derecha (solo desktop) */
  .header-right{ display:none; justify-content:flex-end; }
  .logo-big{
    width:auto; max-height:150px; max-width:360px; object-fit:contain;
    border-radius:12px; background:#fff; padding:8px; box-shadow: var(--shadow);
  }
  .btn-mini{
    margin-top:6px; padding:6px 10px; border:1px solid var(--stroke);
    background:linear-gradient(180deg, #1e293b, #0b1220);
    color:var(--ink); border-radius:10px; cursor:pointer;
    transition:.25s transform ease;
  }
  .btn-mini:hover{ transform: translateY(-1px) scale(1.02) }

  @media (min-width: 992px){
    /* En desktop ocultamos el logo pequeño y mostramos el grande a la derecha */
    .logo-sm-wrap{ display:none; }
    .header-right{ display:flex; }
  }
  @media (max-width: 991.98px){
    /* En mobile, todo va a la izquierda: logo pequeño + textos */
    .logo-sm-wrap{ display:block; }
  }

  /* Grid */
  .grid{
    display:grid; grid-template-columns: repeat(12, 1fr); gap:var(--grid-gap);
  }
  @media (max-width: 1100px){
    .grid{ grid-template-columns: repeat(8, 1fr); }
  }
  @media (max-width: 768px){
    .grid{ grid-template-columns: repeat(4, 1fr); }
  }

  /* Card */
  .card{
    grid-column: span 4;
    background: var(--card);
    border:1px solid var(--stroke);
    border-radius: var(--radius);
    padding:16px;
    box-shadow: var(--shadow);
    backdrop-filter: blur(var(--blur));
    transition: .25s transform ease, .25s box-shadow ease, .25s border-color ease;
  }
  .card:hover{ transform: translateY(-2px); box-shadow: 0 14px 36px rgba(0,0,0,.4); border-color: rgba(255,215,0,.28); }
  .card-header{
    display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;
  }
  .card-title{ margin:0; font-size:1.05rem; letter-spacing:.2px; color:var(--brand) }
  .card-sub{ margin:0; font-size:.85rem; color:var(--mut) }

  /* KPIs */
  .kpis{
    display:flex; gap:12px; flex-wrap:wrap; margin:10px 0 16px;
  }
  .kpi{
    background: linear-gradient(180deg, #101829, #0b1322);
    border:1px solid var(--stroke);
    border-radius: 14px;
    padding:12px 14px; min-width:160px;
    box-shadow: var(--shadow);
  }
  .kpi-label{ color:#ffdf6b; font-size:.78rem; opacity:.95; letter-spacing:.3px; }
  .kpi-value{ color:#fff; font-weight:900; font-size:1.8rem; line-height:1.1; }

  /* Avisos / Alertas */
  .notice{ border:1px solid var(--stroke); border-radius: var(--radius-sm); padding:12px 14px; background:linear-gradient(180deg, #1f2937e6, #0b1220d9); box-shadow: var(--shadow); }
  .notice-warm{ border-color: rgba(255,215,0,.28); }
  .notice-title{ font-weight:700; color:var(--brand); margin-bottom:6px; }
  .notice-item{ padding:6px 0; color:var(--ink); }
  .link-inline{ color:#ffe08a; text-decoration: underline; }
  .alert{
    border:1px dashed rgba(255,215,0,.35);
    border-radius: var(--radius-sm);
    padding:10px 12px;
    background: linear-gradient(180deg, #1e293bcc, #0b1220cc);
    animation: pulse 2.4s ease-in-out infinite;
  }
  @keyframes pulse{
    0%, 100%{ box-shadow: 0 0 0 rgba(255, 215, 0, 0); }
    50%{ box-shadow: 0 0 24px rgba(255, 215, 0, .15); }
  }

  /* Listas */
  ul{ margin:0; padding-left:16px }
  li{ margin:6px 0; color:var(--ink) }

  /* Toggle montos */
  .toolbar{ display:flex; justify-content:flex-end; align-items:center; gap:10px; margin:6px 0 12px; }
  .icon-btn{
    cursor:pointer; user-select:none; font-size:20px; line-height:1;
    padding:6px 9px; border-radius:10px; border:1px solid var(--stroke);
    background: linear-gradient(180deg, #142033, #0b1220);
    transition: .2s transform ease;
  }
  .icon-btn:hover{ transform: translateY(-1px) }

  /* Chart */
  .chart-wrap{ aspect-ratio: 16/9; position:relative; width:100%; max-width:700px; margin:0 auto; }
  #disciplinasChart{ position:absolute; inset:0; }

  /* Inputs */
  .field{
    display:flex; gap:8px; align-items:center;
    padding:10px; border:1px solid var(--stroke);
    border-radius:12px; background:linear-gradient(180deg, #0d1524, #0b1220);
  }
  input[type="date"]{
    background:transparent; border:none; outline:none; color:var(--ink); font-size:.95rem;
  }

  /* Skeleton / Shimmer */
  .skeleton{
    position:relative; overflow:hidden;
    background: linear-gradient(180deg, #0e1523, #0b1220);
    border-radius: 14px; min-height: 110px; border:1px solid var(--stroke);
  }
  .skeleton::after{
    content:""; position:absolute; inset:0;
    background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,.06) 50%, transparent 100%);
    transform: translateX(-100%); animation: shimmer 1.8s infinite;
  }
  @keyframes shimmer{ 100%{ transform: translateX(100%); } }

  /* Helpers */
  .mut{ color:var(--mut) }
  .ok{ color:var(--ok) }
  .warn{ color:var(--warn) }
  .center{ display:flex; align-items:center; justify-content:center }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
  function toggleMontos(){
    const blocks = document.querySelectorAll('.bloque-monto');
    const icon  = document.getElementById('icono-ojo');
    const hidden = blocks.length && blocks[0].classList.contains('hidden');
    blocks.forEach(b => b.classList.toggle('hidden', !hidden));
    if(icon) icon.textContent = hidden ? '👁️‍🗨️' : '👁️';
  }

  function cargarDatos(){
    const elIng = document.getElementById('contenedor-ingresos');
    const elRes = document.getElementById('contenedor-reservas');
    const elAlu = document.getElementById('contenedor-alumnos');
    if(elIng) fetch('ajax_ingresos.php').then(r=>r.text()).then(html=> elIng.innerHTML=html).catch(()=>{});
    const fecha = document.getElementById('fecha')?.value;
    if(elRes && fecha) fetch('ajax_reservas.php?fecha='+encodeURIComponent(fecha)).then(r=>r.text()).then(html=> elRes.innerHTML=html).catch(()=>{});
    if(elAlu) fetch('ajax_alumnos_hoy.php').then(r=>r.text()).then(html=> elAlu.innerHTML=html).catch(()=>{});
  }

  setInterval(cargarDatos, 10000);
  window.addEventListener('load', cargarDatos);
</script>
</head>
<body>

<div class="wrap">

  <!-- ===== Encabezado con logo grande a la derecha en desktop ===== -->
  <div class="header">

    <!-- Izquierda: logo pequeño (solo mobile) + textos -->
    <div class="header-left">
      <?php if (!empty($logo)): ?>
        <div class="logo-sm-wrap">
          <img src="<?= htmlspecialchars($logo) ?>?v=<?= time() ?>" alt="Logo del gimnasio" class="logo-sm" />
          <?php if ($gimnasio_id > 0): ?>
            <div>
              <button class="btn-mini" onclick="document.getElementById('formLogoSm').style.display='block'">🖋</button>
              <form method="POST" action="subir_logo.php" enctype="multipart/form-data" id="formLogoSm" style="display:none;margin-top:6px">
                <input type="file" name="logo" accept="image/*" required onchange="this.form.submit()">
              </form>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div>
        <h1 class="title">🏋️ <?= htmlspecialchars($nombre_gym) ?></h1>
        <p class="sys-exp">🗓 Vencimiento del sistema:
          <strong class="<?= (is_string($fecha_venc) && $fecha_venc !== '0000-00-00' && strtotime($fecha_venc) && strtotime($fecha_venc) >= time()) ? 'ok' : 'warn' ?>">
            <?= (is_string($fecha_venc) && $fecha_venc !== '0000-00-00' && strtotime($fecha_venc)) ? date('d/m/Y', strtotime($fecha_venc)) : '---' ?>
          </strong>
        </p>
      </div>
    </div>

    <!-- Derecha: logo grande (solo desktop) -->
    <div class="header-right">
      <?php if (!empty($logo)): ?>
        <div>
          <img src="<?= htmlspecialchars($logo) ?>?v=<?= time() ?>" alt="Logo del gimnasio" class="logo-big" id="logoGym" />
          <?php if ($gimnasio_id > 0): ?>
            <div style="text-align:right">
              <button class="btn-mini" onclick="document.getElementById('formLogo').style.display='block'">Cambiar logo</button>
              <form method="POST" action="subir_logo.php" enctype="multipart/form-data" id="formLogo" style="display:none;margin-top:6px">
                <input type="file" name="logo" accept="image/*" required onchange="this.form.submit()">
              </form>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>

  <!-- KPIs -->
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

  <!-- Toolbar -->
  <div class="toolbar">
    <span id="icono-ojo" class="icon-btn" title="Mostrar/Ocultar montos" onclick="toggleMontos()">👁️‍🗨️</span>
  </div>

  <!-- GRID -->
  <div class="grid">

    <!-- Ingresos -->
    <section class="card bloque-monto" id="contenedor-ingresos">
      <div class="card-header">
        <h3 class="card-title">💰 Ingresos</h3>
        <p class="card-sub mut">Actualiza cada 10s</p>
      </div>
      <div class="skeleton" style="min-height:120px"></div>
    </section>

    <!-- Cumpleaños -->
    <section class="card">
      <div class="card-header">
        <h3 class="card-title">🎂 Próximos Cumpleaños</h3>
        <p class="card-sub mut">Top 5 próximos</p>
      </div>
      <ul>
        <?php while($c = $cumples->fetch_assoc()): ?>
          <li>
            <?= htmlspecialchars($c['apellido'] . ', ' . $c['nombre']) ?>
            (<?= ($c['fecha_nacimiento'] && strtotime($c['fecha_nacimiento'])) ? date('d/m', strtotime($c['fecha_nacimiento'])) : '--' ?>)
          </li>
        <?php endwhile; ?>
      </ul>
    </section>

    <!-- Vencimientos -->
    <section class="card">
      <div class="card-header">
        <h3 class="card-title">🗓 Vencimientos</h3>
        <p class="card-sub mut">Próximas membresías a vencer</p>
      </div>
      <ul>
        <?php while($v = $vencimientos->fetch_assoc()): ?>
          <li>
            <?= htmlspecialchars($v['apellido'] . ', ' . $v['nombre']) ?>
            (<?= ($v['fecha_vencimiento'] && strtotime($v['fecha_vencimiento'])) ? date('d/m', strtotime($v['fecha_vencimiento'])) : '--' ?>)
          </li>
        <?php endwhile; ?>
      </ul>
    </section>

    <!-- Reservas -->
    <section class="card" style="grid-column: span 8">
      <div class="card-header">
        <h3 class="card-title">📋 Reservas del día</h3>
        <div class="field">
          <label for="fecha" class="mut">Ver día</label>
          <form method="GET" id="form-fecha" oninput="this.submit()" style="display:flex;align-items:center;gap:8px">
            <input type="date" id="fecha" name="fecha" value="<?= htmlspecialchars($fecha_filtro) ?>">
          </form>
        </div>
      </div>
      <div id="contenedor-reservas">
        <div class="skeleton" style="min-height:110px"></div>
      </div>
    </section>

    <!-- Alumnos hoy -->
    <section class="card" id="contenedor-alumnos">
      <div class="card-header">
        <h3 class="card-title">🧑‍🎓 Alumnos de hoy</h3>
        <p class="card-sub mut">Asistencias/ingresos</p>
      </div>
      <div class="skeleton" style="min-height:110px"></div>
    </section>

    <!-- Gráfico: Disciplinas -->
    <section class="card" style="grid-column: span 8">
      <div class="card-header">
        <h3 class="card-title">📊 Disciplinas más registradas</h3>
        <p class="card-sub mut">Top 10 normalizadas</p>
      </div>
      <div class="chart-wrap">
        <canvas id="disciplinasChart" aria-label="Gráfico de barras de disciplinas" role="img"></canvas>
      </div>
      <?php if (count($disciplinas_rows) === 0): ?>
        <small class="mut">No hay datos para mostrar.</small>
      <?php endif; ?>
    </section>

  </div><!-- /grid -->

</div><!-- /wrap -->

<script>
  // Render del gráfico de disciplinas
  (function(){
    const data = <?= json_encode($disciplinas_rows, JSON_UNESCAPED_UNICODE) ?>;
    if(!Array.isArray(data) || !data.length) return;
    const el = document.getElementById('disciplinasChart');
    if(!el) return;
    const ctx = el.getContext('2d');

    // Degradado vertical en runtime
    const grad = ctx.createLinearGradient(0, 0, 0, el.height);
    grad.addColorStop(0, 'rgba(255, 209, 102, 0.95)'); // brand
    grad.addColorStop(1, 'rgba(255, 183,   3, 0.60)'); // brand-2

    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: data.map(d => d.nombre),
        datasets: [{
          label: 'Registros',
          data: data.map(d => Number(d.total)),
          backgroundColor: grad,
          borderColor: 'rgba(255,215,0,.9)',
          borderWidth: 2,
          borderRadius: 10,
          hoverBorderWidth: 2.5
        }]
      },
      options: {
        responsive:true,
        maintainAspectRatio:false,
        animation:{ duration: 800 },
        plugins:{
          legend:{ labels:{ color:'#ffe9a3' } },
          tooltip:{
            backgroundColor:'rgba(10,14,22,.96)',
            titleColor:'#ffe9a3', bodyColor:'#e6edf7',
            borderColor:'rgba(255,215,0,.35)', borderWidth:1
          }
        },
        scales:{
          x:{ ticks:{ color:'#ffe9a3' }, grid:{ color:'rgba(255,215,0,.10)' } },
          y:{ beginAtZero:true, ticks:{ color:'#ffe9a3', precision:0 }, grid:{ color:'rgba(255,215,0,.08)' } }
        }
      }
    });
  })();
</script>

<style>
  /* util para ocultar montos sin reflow brusco */
  .hidden{ display:none !important; }
</style>

</body>
</html>
